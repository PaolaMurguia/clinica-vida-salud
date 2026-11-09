<?php

namespace Tests\Feature\Citas;

use App\Models\Cita;
use App\Models\Paciente;
use App\Models\RegistroAtencion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CU19 – Consultar historial de citas. Lista cronológica (recientes primero), filtros, detalle con el
 * historial de estados y las 4 excepciones del documento. Sin diagnósticos (RN18).
 */
class ConsultarHistorialCitasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Paciente $carlos;   // una cita pasada Cancelada («El paciente viajó») y una futura Programada
    private Paciente $maria;    // cuenta 1234567; una cita pasada Atendida (con diagnóstico) y una futura

    protected function setUp(): void
    {
        parent::setUp();
        $this->carlos = Paciente::buscarPorCI('7654321');
        $this->maria = Paciente::buscarPorCI('1234567');
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function codigos(Paciente $paciente): array
    {
        return Cita::where('paciente_id', $paciente->id)->orderByDesc('fecha')->pluck('codigo')->all();
    }

    public function test_la_recepcionista_ve_las_citas_del_paciente_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        [$futura, $pasada] = $this->codigos($this->carlos);

        $this->get("/historial-citas?paciente_id={$this->carlos->id}")->assertOk()
            ->assertSee('Historial de citas')->assertSeeInOrder([$futura, $pasada])
            ->assertSee('Cancelada')->assertSee('Programada')->assertSee('Dolor de muela');
    }

    public function test_desde_el_paciente_seleccionado_se_abre_su_historial(): void
    {
        $this->get("/pacientes/{$this->carlos->id}")->assertOk()
            ->assertSee(route('historialCitas.index', ['paciente_id' => $this->carlos->id]));
    }

    public function test_el_administrador_tambien_consulta_el_historial(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('admin'))
            ->get("/historial-citas?paciente_id={$this->carlos->id}")->assertOk()->assertSee($this->codigos($this->carlos)[0]);
    }

    public function test_el_paciente_ve_mis_citas_sin_diagnosticos(): void
    {
        $diagnostico = RegistroAtencion::whereIn('cita_id', Cita::where('paciente_id', $this->maria->id)->pluck('id'))->value('diagnostico');
        $this->assertNotNull($diagnostico);

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));
        $this->get('/portal')->assertSee('Mis citas');
        $this->get('/historial-citas')->assertOk()->assertSee('Mis citas')
            ->assertSee($this->codigos($this->maria)[0])->assertSee($this->codigos($this->maria)[1])
            ->assertDontSee($diagnostico);
    }

    public function test_filtra_por_estado_y_rango_de_fechas(): void
    {
        [$futura, $pasada] = $this->codigos($this->carlos);

        $this->get("/historial-citas?paciente_id={$this->carlos->id}&estado=Cancelada")->assertOk()
            ->assertSee($pasada)->assertDontSee($futura);

        $desde = today()->toDateString();
        $this->get("/historial-citas?paciente_id={$this->carlos->id}&desde=$desde")->assertOk()
            ->assertSee($futura)->assertDontSee($pasada);

        $this->get("/historial-citas?paciente_id={$this->carlos->id}&estado=Atendida")->assertOk()
            ->assertSee('No hay citas con los filtros elegidos.');
    }

    public function test_detalle_con_historial_de_estados_y_motivo_de_cancelacion(): void
    {
        $cancelada = Cita::where('paciente_id', $this->carlos->id)->where('estado', 'Cancelada')->first();

        $this->get("/historial-citas/{$cancelada->id}")->assertOk()
            ->assertSee($cancelada->codigo)->assertSee('Historial de estados')
            ->assertSee('Programada → Cancelada')->assertSee('recep1')
            ->assertSee('Motivo de cancelación')->assertSee('El paciente viajó');
    }

    // ---------- Excepciones ----------

    public function test_paciente_sin_citas(): void
    {
        $nuevo = Paciente::crear([
            'ci' => '5550001', 'nombres' => 'Ana', 'apellidos' => 'Sin Citas', 'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'F', 'telefono' => '70000001',
        ]);

        $this->get("/historial-citas?paciente_id={$nuevo->id}")->assertOk()->assertSee('El paciente no tiene citas registradas.');
    }

    public function test_paciente_inactivo_se_consulta_igual(): void
    {
        $this->carlos->desactivar();

        $this->get("/historial-citas?paciente_id={$this->carlos->id}")->assertOk()
            ->assertSee('Paciente inactivo')->assertSee($this->codigos($this->carlos)[0]);
    }

    public function test_el_paciente_no_ve_el_historial_ni_las_citas_de_otro(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->get("/historial-citas?paciente_id={$this->carlos->id}")
            ->assertSessionHasErrors(['paciente_id' => 'Solo puede consultar su propio historial de citas (RN18).']);

        $ajena = Cita::where('paciente_id', $this->carlos->id)->first();
        $this->get("/historial-citas/{$ajena->id}")->assertForbidden();
    }

    public function test_filtro_invalido(): void
    {
        $this->get("/historial-citas?paciente_id={$this->carlos->id}&desde=2026-05-10&hasta=2026-05-01")
            ->assertSessionHasErrors(['hasta' => 'Rango de fechas inválido: la fecha final es anterior a la inicial.']);
        $this->get("/historial-citas?paciente_id={$this->carlos->id}&estado=Perdida")->assertSessionHasErrors('estado');
        $this->get('/historial-citas')->assertSessionHasErrors(['paciente_id' => 'Seleccione primero un paciente.']);
    }

    public function test_el_medico_no_accede_al_historial_de_citas(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'))
            ->get("/historial-citas?paciente_id={$this->carlos->id}")->assertForbidden();
    }
}
