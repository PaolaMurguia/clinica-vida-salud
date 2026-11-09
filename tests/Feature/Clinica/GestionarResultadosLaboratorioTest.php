<?php

namespace Tests\Feature\Clinica;

use App\Models\Bitacora;
use App\Models\ExamenLaboratorio;
use App\Models\Notificacion;
use App\Models\Paciente;
use App\Models\ParametroLaboratorio;
use App\Models\ResultadoLaboratorio;
use App\Models\Usuario;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CU22 – Gestionar resultados de laboratorio. Solicitud del médico, registro de la recepción con el rango
 * por edad (RN16), notificaciones (RN20, RF21), revisión y las 9 excepciones del documento.
 */
class GestionarResultadosLaboratorioTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Paciente $luis;              // 74 años, cuenta 4567890; atendido por Justiniano
    private ExamenLaboratorio $solicitado; // de Luis, solicitado por Justiniano, sin resultados

    protected function setUp(): void
    {
        parent::setUp();
        $this->luis = Paciente::buscarPorCI('4567890');
        $this->solicitado = ExamenLaboratorio::where('paciente_id', $this->luis->id)->where('estado', 'Solicitado')->first();
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function registrar(array $valores, ?string $fecha = null, ?ExamenLaboratorio $examen = null)
    {
        $examen ??= $this->solicitado;

        return $this->from("/examenes/{$examen->id}")->post("/examenes/{$examen->id}/resultados", [
            'fecha_muestra' => $fecha ?? today()->toDateString(), 'valores' => $valores,
        ]);
    }

    public function test_el_medico_solicita_un_analisis_desde_la_historia_clinica(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dra.justiniano'));
        $this->get("/historia-clinica/{$this->luis->id}")->assertSee('Solicitar análisis');

        $this->post('/examenes', ['paciente_id' => $this->luis->id, 'tipo_examen' => 'Perfil lipídico', 'observacion' => 'En ayunas'])
            ->assertRedirect(route('historiaClinica.show', $this->luis))->assertSessionHas('exito');

        $examen = ExamenLaboratorio::where('tipo_examen', 'Perfil lipídico')->first();
        $this->assertMatchesRegularExpression('/^EX-\d{5,}$/', $examen->codigo);
        $this->assertSame(['Solicitado', 'En ayunas'], [$examen->estado, $examen->observacion]);
        $this->assertSame(Usuario::buscarPorLogin('dra.justiniano')->medico_id, $examen->medico_id);
        $this->assertTrue(Bitacora::where('accion', 'SOLICITAR')->where('registro_id', $examen->id)->exists());
    }

    public function test_la_recepcionista_busca_por_codigo_o_paciente(): void
    {
        $this->get('/examenes')->assertOk()->assertSee($this->solicitado->codigo);
        $this->get('/examenes?q=' . $this->solicitado->codigo)->assertOk()->assertSee('Fernández Cuéllar');
        $this->get('/examenes?q=4567890')->assertOk()->assertSee($this->solicitado->codigo);
    }

    public function test_registra_los_resultados_interpretados_segun_la_edad_y_notifica(): void
    {
        $this->get("/examenes/{$this->solicitado->id}")->assertOk()->assertSee('Registrar resultados')->assertSee('Glucosa en ayunas');

        $this->registrar(['Glucosa en ayunas' => '128', 'Hemoglobina' => '13,2', 'Leucocitos' => ''])
            ->assertRedirect(route('examenes.show', $this->solicitado))->assertSessionHas('exito');

        $examen = $this->solicitado->refresh();
        $this->assertSame('Registrado', $examen->estado);
        $this->assertSame(today()->toDateString(), $examen->fecha_muestra->toDateString());
        $this->assertSame(Usuario::buscarPorLogin('recep1')->id, $examen->usuario_registro_id);

        // RN16: 74 años → glucosa 70–100 (Alto) y hemoglobina 11,5–16,5 (Normal); el rango aplicado queda guardado
        $resultados = ResultadoLaboratorio::listarPorExamen($examen->id)->keyBy(fn ($r) => $r->parametro->nombre);
        $this->assertCount(2, $resultados);
        $this->assertSame(['128.00', '70.00', '100.00', 'Alto'], [
            $resultados['Glucosa en ayunas']->valor, $resultados['Glucosa en ayunas']->valor_minimo_ref,
            $resultados['Glucosa en ayunas']->valor_maximo_ref, $resultados['Glucosa en ayunas']->interpretacion,
        ]);
        $this->assertSame('Normal', $resultados['Hemoglobina']->interpretacion);

        // notificación al médico solicitante y al paciente (RN20, RF21)
        $avisados = Notificacion::where('tipo', 'Resultado')->pluck('usuario_id');
        $this->assertTrue($avisados->contains(Usuario::buscarPorLogin('dra.justiniano')->id));
        $this->assertTrue($avisados->contains(Usuario::buscarPorLogin('4567890')->id));
        $this->assertTrue(Bitacora::where('accion', 'REGISTRAR_RESULTADOS')->where('registro_id', $examen->id)->exists());
    }

    public function test_el_medico_revisa_y_el_paciente_consulta_sus_resultados(): void
    {
        $this->registrar(['Glucosa en ayunas' => '95']);

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dra.justiniano'));
        $this->get('/examenes')->assertOk()->assertSee($this->solicitado->codigo);
        $this->get("/examenes/{$this->solicitado->id}")->assertOk()->assertSee('Marcar como revisado')->assertSee('70.00 – 100.00');
        $this->patch("/examenes/{$this->solicitado->id}/revisado")->assertSessionHas('exito');
        $this->assertSame('Revisado', $this->solicitado->refresh()->estado);

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('4567890'));
        $this->get('/portal')->assertSee('Mis resultados');
        $this->get('/examenes')->assertOk()->assertSee($this->solicitado->codigo);
        $this->get("/examenes/{$this->solicitado->id}")->assertOk()->assertSee('Glucosa en ayunas')->assertSee('Normal')
            ->assertDontSee('Registrar resultados');
    }

    // ---------- Excepciones ----------

    public function test_solo_un_medico_solicita_analisis(): void
    {
        foreach (['recep1', '4567890'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login));
            $this->post('/examenes', ['paciente_id' => $this->luis->id, 'tipo_examen' => 'Hemograma'])->assertForbidden();
        }
        // un médico que no atiende al paciente tampoco
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'));
        $this->post('/examenes', ['paciente_id' => $this->luis->id, 'tipo_examen' => 'Hemograma'])->assertForbidden();
    }

    public function test_examen_revisado_no_se_modifica(): void
    {
        $revisado = ExamenLaboratorio::where('estado', 'Revisado')->first();

        $this->registrar(['Leucocitos' => '7'], $revisado->fecha_muestra->toDateString(), $revisado)
            ->assertSessionHasErrors(['examen' => 'El examen ya está Revisado: no se modifica.']);
        $this->get("/examenes/{$revisado->id}")->assertDontSee('Registrar resultados');
        $this->get('/examenes/999999')->assertNotFound();
    }

    public function test_fecha_de_muestra_invalida(): void
    {
        $this->registrar(['Hemoglobina' => '13'], today()->addDay()->toDateString())
            ->assertSessionHasErrors(['fecha_muestra' => 'La fecha de la muestra no puede ser futura.']);
        $this->registrar(['Hemoglobina' => '13'], $this->solicitado->fecha_solicitud->copy()->subDay()->toDateString())
            ->assertSessionHasErrors('fecha_muestra');
        $this->registrar(['Hemoglobina' => '13'], '')->assertSessionHasErrors('fecha_muestra');
    }

    public function test_parametro_inexistente_o_inactivo(): void
    {
        $this->registrar(['Ferritina' => '50'])->assertSessionHasErrors(['valores' => 'El parámetro Ferritina no existe o está inactivo.']);

        ParametroLaboratorio::where('nombre', 'Leucocitos')->update(['estado' => false]);
        $this->registrar(['Leucocitos' => '7'])->assertSessionHasErrors(['valores' => 'El parámetro Leucocitos no existe o está inactivo.']);
        $this->assertSame('Solicitado', $this->solicitado->refresh()->estado);
    }

    public function test_sin_rango_de_referencia_para_la_edad(): void
    {
        ParametroLaboratorio::create(['nombre' => 'Ferritina', 'unidad' => 'ng/mL']);   // sin rangos

        $this->registrar(['Ferritina' => '50'])
            ->assertSessionHasErrors(['valores' => 'No existe rango de referencia de Ferritina para 74 años: no se registra el valor.']);
    }

    public function test_parametro_repetido_en_el_examen(): void
    {
        $this->registrar(['Hemoglobina' => '13']);
        $this->registrar(['Hemoglobina' => '14'])->assertSessionHasErrors(['valores' => 'El parámetro Hemoglobina ya está registrado en este examen.']);

        // con el examen ya Registrado se pueden agregar otros parámetros (misma fecha de muestra)
        $this->registrar(['Leucocitos' => '7'])->assertSessionHasNoErrors();
        $this->assertSame(2, ResultadoLaboratorio::where('examen_id', $this->solicitado->id)->count());
    }

    public function test_valor_no_numerico(): void
    {
        $this->registrar(['Hemoglobina' => 'alto'])->assertSessionHasErrors(['valores' => 'El valor de Hemoglobina debe ser numérico.']);
        $this->registrar([])->assertSessionHasErrors('valores');
    }

    public function test_un_paciente_o_medico_no_ve_examenes_ajenos(): void
    {
        $this->registrar(['Hemoglobina' => '13']);

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));
        $this->get("/examenes/{$this->solicitado->id}")->assertForbidden();

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'));   // no atiende a Luis
        $this->get("/examenes/{$this->solicitado->id}")->assertForbidden();
    }

    public function test_el_paciente_no_ve_un_examen_aun_solicitado(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('4567890'));
        $this->get("/examenes/{$this->solicitado->id}")->assertForbidden();
        $this->get('/examenes')->assertDontSee($this->solicitado->codigo);
    }

    public function test_falla_el_correo_y_el_resultado_se_conserva(): void
    {
        Mail::shouldReceive('raw')->andThrow(new Exception('Servidor de correo caído'));

        $this->registrar(['Hemoglobina' => '13'])->assertSessionHas('exito');

        $this->assertSame('Registrado', $this->solicitado->refresh()->estado);
        $this->assertTrue(Notificacion::where('tipo', 'Resultado')->where('canal', 'Correo')->where('estado', 'Fallido')->exists());
    }

    public function test_solo_el_medico_solicitante_marca_revisado(): void
    {
        $this->registrar(['Hemoglobina' => '13']);

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'));
        $this->patch("/examenes/{$this->solicitado->id}/revisado")
            ->assertSessionHas('error', 'Solo el médico que solicitó el examen lo marca como revisado.');
        $this->assertSame('Registrado', $this->solicitado->refresh()->estado);
    }
}
