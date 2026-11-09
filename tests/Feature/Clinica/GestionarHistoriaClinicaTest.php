<?php

namespace Tests\Feature\Clinica;

use App\Models\Antecedente;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Historia;
use App\Models\Paciente;
use App\Models\RegistroAtencion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CU20 – Gestionar historia clínica. Vista del médico y del paciente, registro único de atención por cita
 * atendida, modificación, antecedentes y las 6 excepciones del documento (RN18).
 */
class GestionarHistoriaClinicaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Paciente $maria;      // O+, alergia a la penicilina; atendida por Pérez (con registro)
    private Cita $atendidaSinRegistro;
    private Cita $atendidaConRegistro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maria = Paciente::buscarPorCI('1234567');
        $this->atendidaConRegistro = Cita::where('paciente_id', $this->maria->id)->where('estado', 'Atendida')->first();
        // la cita futura de María con Pérez pasa a Atendida (transición válida) para registrar su atención
        $this->atendidaSinRegistro = Cita::where('paciente_id', $this->maria->id)->where('estado', 'Programada')->first();
        DB::table('cita')->where('id', $this->atendidaSinRegistro->id)->update(['estado' => 'Atendida']);
        $this->actingAs(Usuario::buscarPorLogin('dr.perez'));
    }

    private function registrar(array $cambios = [])
    {
        return $this->post("/historia-clinica/{$this->maria->id}/atenciones", $cambios + [
            'cita_id' => $this->atendidaSinRegistro->id, 'diagnostico' => 'Rinofaringitis aguda',
            'tratamiento' => 'Paracetamol 500 mg', 'indicaciones' => 'Reposo y líquidos',
            'peso_kg' => '62.5', 'talla_cm' => '165', 'presion_arterial' => '115/75', 'temperatura_c' => '37.8',
        ]);
    }

    public function test_el_medico_ve_la_historia_completa_del_paciente_que_atiende(): void
    {
        $diagnostico = RegistroAtencion::obtenerPorCita($this->atendidaConRegistro->id)->diagnostico;

        $this->get("/historia-clinica/{$this->maria->id}")->assertOk()
            ->assertSee('Historia clínica')->assertSee('O+')->assertSee('Penicilina')
            ->assertSee($diagnostico)->assertSee('Registrar atención')->assertSee($this->atendidaSinRegistro->codigo);
    }

    public function test_crea_la_historia_si_no_existe(): void
    {
        $pedro = Paciente::buscarPorCI('6789012');   // faltó a su cita con Pérez; sin historia clínica
        $this->assertNull(Historia::where('paciente_id', $pedro->id)->first());

        $this->get("/historia-clinica/{$pedro->id}")->assertOk()->assertSee('no registrado')->assertSee('Sin atenciones registradas.');
        $this->assertNotNull(Historia::where('paciente_id', $pedro->id)->first());
    }

    public function test_registra_la_atencion_de_una_cita_atendida(): void
    {
        $antes = Historia::obtener($this->maria->id)->fecha_actualizacion;
        $this->travel(1)->minutes();

        $this->registrar()->assertRedirect(route('historiaClinica.show', $this->maria))->assertSessionHas('exito');

        $registro = RegistroAtencion::obtenerPorCita($this->atendidaSinRegistro->id);
        $this->assertSame('Rinofaringitis aguda', $registro->diagnostico);
        $this->assertSame(['62.50', '165.0', '115/75', '37.8'], [$registro->peso_kg, $registro->talla_cm, $registro->presion_arterial, $registro->temperatura_c]);
        $this->assertFalse($registro->dictado_voz);
        $this->assertTrue(Historia::obtener($this->maria->id)->fecha_actualizacion->gt($antes));
        $this->assertTrue(Bitacora::where('modulo', 'Historia clínica')->where('accion', 'CREAR')->where('registro_id', $registro->id)->exists());
    }

    public function test_el_medico_modifica_su_registro_y_queda_en_la_bitacora(): void
    {
        $registro = RegistroAtencion::obtenerPorCita($this->atendidaConRegistro->id);

        $this->get("/historia-clinica/{$this->maria->id}?editar={$registro->id}")->assertOk()->assertSee('Modificar la atención');
        $this->put("/atenciones/{$registro->id}", ['diagnostico' => 'Diagnóstico corregido', 'temperatura_c' => '36.5'])
            ->assertRedirect(route('historiaClinica.show', $this->maria->id));

        $this->assertSame('Diagnóstico corregido', $registro->refresh()->diagnostico);
        $this->assertTrue(Bitacora::where('modulo', 'Historia clínica')->where('accion', 'MODIFICAR')->where('registro_id', $registro->id)->exists());
    }

    public function test_el_medico_agrega_antecedentes(): void
    {
        $this->post("/historia-clinica/{$this->maria->id}/antecedentes", ['tipo' => 'Cirugia', 'descripcion' => 'Apendicectomía (2010)'])
            ->assertSessionHas('exito');

        $antecedente = Antecedente::where('descripcion', 'Apendicectomía (2010)')->first();
        $this->assertSame(['Cirugia', 'Médico'], [$antecedente->tipo, $antecedente->origen]);
        $this->post("/historia-clinica/{$this->maria->id}/antecedentes", ['tipo' => 'Cirugia', 'descripcion' => ''])->assertSessionHasErrors('descripcion');
    }

    public function test_el_paciente_consulta_su_historia_sin_poder_modificarla(): void
    {
        $diagnostico = RegistroAtencion::obtenerPorCita($this->atendidaConRegistro->id)->diagnostico;
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->get('/portal')->assertSee('Mi historia clínica');
        $this->get('/mi-historia-clinica')->assertOk()->assertSee($diagnostico)->assertSee('Penicilina')
            ->assertDontSee('Registrar atención')->assertDontSee('Agregar</button>', false);
        $this->registrar()->assertForbidden();
    }

    // ---------- Excepciones ----------

    public function test_diagnostico_vacio_o_signos_vitales_fuera_de_rango(): void
    {
        $this->registrar(['diagnostico' => '  '])->assertSessionHasErrors(['diagnostico' => 'El diagnóstico es obligatorio.']);
        $this->registrar(['peso_kg' => '500'])->assertSessionHasErrors('peso_kg');
        $this->registrar(['talla_cm' => '5'])->assertSessionHasErrors('talla_cm');
        $this->registrar(['temperatura_c' => '50'])->assertSessionHasErrors('temperatura_c');
        $this->registrar(['presion_arterial' => '12080'])->assertSessionHasErrors('presion_arterial');
        $this->registrar(['presion_arterial' => '300/80'])->assertSessionHasErrors(['presion_arterial' => 'La presión arterial está fuera de los rangos permitidos.']);
        $this->registrar(['presion_arterial' => '80/120'])->assertSessionHasErrors('presion_arterial');
        $this->assertNull(RegistroAtencion::obtenerPorCita($this->atendidaSinRegistro->id));
    }

    public function test_la_cita_no_esta_atendida(): void
    {
        $programada = Cita::crear([
            'paciente_id' => $this->maria->id, 'medico_id' => $this->atendidaSinRegistro->medico_id,
            'especialidad_id' => $this->atendidaSinRegistro->especialidad_id, 'usuario_registro_id' => Usuario::buscarPorLogin('recep1')->id,
            'fecha' => $this->atendidaSinRegistro->fecha->toDateString(), 'hora_inicio' => '11:00', 'hora_fin' => '11:30',
        ]);

        $this->registrar(['cita_id' => $programada->id])
            ->assertSessionHasErrors(['cita_id' => 'La cita está en estado Programada: solo se registra la atención de una cita Atendida.']);
    }

    public function test_la_cita_ya_tiene_registro(): void
    {
        $this->registrar(['cita_id' => $this->atendidaConRegistro->id])
            ->assertSessionHasErrors(['cita_id' => 'La cita ya tiene un registro de atención: modifique el existente.']);
        $this->assertSame(1, RegistroAtencion::where('cita_id', $this->atendidaConRegistro->id)->count());
    }

    public function test_medico_que_no_atiende_ni_atendio_al_paciente(): void
    {
        $rosa = Paciente::buscarPorCI('3456789');   // atendida por Mendoza

        $this->get("/historia-clinica/{$rosa->id}")->assertForbidden();
        $this->post("/historia-clinica/{$rosa->id}/antecedentes", ['tipo' => 'Alergia', 'descripcion' => 'Polen'])->assertForbidden();

        // otro médico no modifica el registro de Pérez
        $registro = RegistroAtencion::obtenerPorCita($this->atendidaConRegistro->id);
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dra.mendoza'));
        $this->put("/atenciones/{$registro->id}", ['diagnostico' => 'Otro'])->assertForbidden();
    }

    public function test_la_recepcion_no_ve_diagnosticos(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'));
        $this->get("/historia-clinica/{$this->maria->id}")->assertForbidden();
    }

    public function test_el_paciente_no_ve_la_historia_de_otro(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));
        $this->get('/historia-clinica/' . Paciente::buscarPorCI('3456789')->id)->assertForbidden();
    }

    public function test_desde_la_agenda_el_medico_abre_la_historia(): void
    {
        $this->get("/agenda/filtrar?desde={$this->atendidaSinRegistro->fecha->toDateString()}")
            ->assertSee(route('historiaClinica.show', ['paciente' => $this->maria->id, 'cita' => $this->atendidaSinRegistro->id]));
    }
}
