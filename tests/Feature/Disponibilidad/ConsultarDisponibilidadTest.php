<?php

namespace Tests\Feature\Disponibilidad;

use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CU13 – Consultar disponibilidad. Carriles: elegir especialidad → elegir médico → fecha con horario
 * (estaBloqueada, obtenerDelDia, generarFranjas, listarOcupadas) → fecha bloqueada con motivo →
 * médico que no atiende ese día con la siguiente fecha disponible. Sin fn_franjas_disponibles.
 */
class ConsultarDisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Carbon $lunes;          // «proxima» de la población: lunes de la semana siguiente
    private Medico $perez;          // Medicina general: lun 08-12, mié 14-18, vie 08-12 (30 min)
    private Especialidad $general;
    private Paciente $diego;        // 25 años, sin citas con Pérez

    protected function setUp(): void
    {
        parent::setUp();
        $this->lunes = now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
        $this->perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $this->general = Especialidad::where('nombre', 'Medicina general')->first();
        $this->diego = Paciente::buscarPorCI('8901234');
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function franjas(Carbon $fecha, ?Medico $medico = null, ?Especialidad $especialidad = null, ?Paciente $paciente = null)
    {
        return $this->get('/disponibilidad/franjas?' . http_build_query([
            'paciente_id'     => ($paciente ?? $this->diego)->id,
            'especialidad_id' => ($especialidad ?? $this->general)->id,
            'medico_id'       => ($medico ?? $this->perez)->id,
            'fecha'           => $fecha->toDateString(),
        ]));
    }

    // ---------- Carril 1: elegir especialidad ----------

    public function test_el_paciente_ve_solo_las_especialidades_de_su_edad(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))   // María, 36 años
            ->get('/disponibilidad')->assertOk()
            ->assertSee('Medicina general')->assertSee('Ginecología')->assertSee('Cardiología')
            ->assertDontSee('Pediatría')->assertDontSee('Geriatría');
    }

    public function test_la_recepcionista_consulta_para_el_paciente_seleccionado(): void
    {
        $this->get('/disponibilidad')->assertOk()->assertSee('Seleccione primero un paciente');

        $rosa = Paciente::buscarPorCI('3456789');   // 11 años
        $this->get("/pacientes/{$rosa->id}")->assertSee('Disponibilidad');
        $this->get("/disponibilidad?paciente_id={$rosa->id}")->assertOk()
            ->assertSee('Pediatría')->assertDontSee('Medicina general')->assertDontSee('Geriatría');
    }

    public function test_paciente_sin_especialidad_para_su_edad(): void
    {
        Especialidad::whereNotIn('nombre', ['Pediatría', 'Geriatría'])->update(['estado' => false]);

        $this->get("/disponibilidad?paciente_id={$this->diego->id}")->assertOk()
            ->assertSee('No hay especialidades disponibles para la edad del paciente.');
    }

    public function test_un_paciente_no_consulta_en_nombre_de_otro(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))
            ->get("/disponibilidad?paciente_id={$this->diego->id}")->assertOk()
            ->assertSee('López Vargas, María')->assertDontSee('Paz Mercado');
    }

    // ---------- Carril 2: elegir médico ----------

    public function test_lista_los_medicos_activos_de_la_especialidad(): void
    {
        $this->get("/disponibilidad/medicos?paciente_id={$this->diego->id}&especialidad_id={$this->general->id}")->assertOk()
            ->assertSee('Pérez Rojas, Juan')->assertSee('Vaca Chávez, Jorge')->assertDontSee('Mendoza Vaca');

        $this->perez->cambiarEstado(false);
        $this->get("/disponibilidad/medicos?paciente_id={$this->diego->id}&especialidad_id={$this->general->id}")
            ->assertDontSee('Pérez Rojas, Juan');
    }

    public function test_especialidad_que_no_corresponde_a_la_edad(): void
    {
        $pediatria = Especialidad::where('nombre', 'Pediatría')->first();
        $this->from('/disponibilidad')->get("/disponibilidad/medicos?paciente_id={$this->diego->id}&especialidad_id={$pediatria->id}")
            ->assertSessionHasErrors(['especialidad_id' => 'La especialidad no está disponible para la edad del paciente.']);
    }

    // ---------- Carril 3: fecha con horario ----------

    public function test_franjas_libres_y_ocupadas_sin_datos_de_otros_pacientes(): void
    {
        // CM-0009: María con Pérez el lunes a las 08:00
        $respuesta = $this->franjas($this->lunes)->assertOk()
            ->assertSee('7 libre(s)')->assertSee('1 ocupada(s)')
            ->assertSee('08:30–09:00')->assertSee('11:30–12:00')->assertSee('Ocupada');

        $respuesta->assertDontSee('López')->assertDontSee('María')->assertDontSee('CM-0009')->assertDontSee('Revisión de análisis');
    }

    public function test_las_citas_canceladas_no_ocupan_la_franja(): void
    {
        Cita::where('codigo', 'CM-0009')->update(['estado' => 'Cancelada', 'motivo_cancelacion' => 'Prueba']);
        $this->franjas($this->lunes)->assertSee('8 libre(s)')->assertSee('0 ocupada(s)');
    }

    public function test_descarta_las_franjas_pasadas_del_dia(): void
    {
        Carbon::setTestNow($this->lunes->copy()->setTime(10, 5));

        $this->franjas($this->lunes)->assertOk()
            ->assertSee('10:30–11:00')->assertDontSee('08:30–09:00')->assertDontSee('10:00–10:30')->assertSee('3 libre(s)');

        Carbon::setTestNow();
    }

    public function test_fecha_pasada(): void
    {
        $this->from('/disponibilidad')->get('/disponibilidad/franjas?' . http_build_query([
            'paciente_id' => $this->diego->id, 'especialidad_id' => $this->general->id, 'medico_id' => $this->perez->id,
            'fecha' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors(['fecha' => 'No se puede consultar una fecha pasada.']);
    }

    public function test_medico_que_no_atiende_la_especialidad(): void
    {
        $mendoza = Medico::where('nro_registro_prof', 'RP-1002')->first();   // Pediatría
        $this->from('/disponibilidad')->franjas($this->lunes, $mendoza)
            ->assertSessionHasErrors(['medico_id' => 'El médico no está activo o no atiende esa especialidad.']);
    }

    // ---------- Carril 4: fecha bloqueada con motivo ----------

    public function test_fecha_bloqueada_del_medico_muestra_el_motivo_y_la_siguiente_fecha(): void
    {
        Bloqueo::whereNull('medico_id')->delete();   // sin feriados de la clínica en el rango
        $rojas = Medico::where('nro_registro_prof', 'RP-1003')->first();       // Odontología: lun 14-18, mié 08-12
        $odontologia = Especialidad::where('nombre', 'Odontología')->first();
        $bloqueado = $this->lunes->copy()->addDays(14);                           // «Capacitación del médico» en la población

        $this->franjas($bloqueado, $rojas, $odontologia)->assertOk()
            ->assertSee('Fecha bloqueada: Capacitación del médico.')
            ->assertSee('Siguiente fecha disponible: ' . $bloqueado->copy()->addDays(2)->translatedFormat('l d/m/Y'));
    }

    public function test_fecha_bloqueada_para_toda_la_clinica(): void
    {
        Bloqueo::whereNull('medico_id')->delete();
        $feriado = $this->lunes->copy()->addWeek();
        Bloqueo::create(['medico_id' => null, 'fecha' => $feriado->toDateString(), 'motivo' => 'Feriado de prueba']);

        $this->franjas($feriado)->assertOk()->assertSee('Fecha bloqueada: Feriado de prueba.')->assertDontSee('libre(s)');
    }

    // ---------- Carril 5: no atiende ese día → siguiente fecha disponible ----------

    public function test_medico_que_no_atiende_ese_dia_sugiere_la_siguiente_fecha(): void
    {
        $martes = $this->lunes->copy()->addDay();

        $this->franjas($martes)->assertOk()
            ->assertSee('El médico no atiende ese día.')
            ->assertSee('Siguiente fecha disponible: ' . $martes->copy()->addDay()->translatedFormat('l d/m/Y'));
    }

    public function test_sin_franjas_libres_sugiere_la_siguiente_fecha(): void
    {
        Bloqueo::whereNull('medico_id')->delete();
        $viernes = $this->lunes->copy()->addDays(4);
        $recep = Usuario::buscarPorLogin('recep1');
        foreach (range(0, 7) as $i) {   // ocupa las 8 franjas de 08:00 a 12:00
            $inicio = Carbon::parse('08:00')->addMinutes(30 * $i);
            Cita::create([
                'paciente_id' => $this->diego->id, 'medico_id' => $this->perez->id, 'especialidad_id' => $this->general->id,
                'usuario_registro_id' => $recep->id, 'fecha' => $viernes->toDateString(),
                'hora_inicio' => $inicio->format('H:i'), 'hora_fin' => $inicio->copy()->addMinutes(30)->format('H:i'),
            ]);
        }

        $this->franjas($viernes)->assertOk()
            ->assertSee('0 libre(s)')->assertSee('8 ocupada(s)')->assertSee('El médico no tiene franjas libres ese día.')
            ->assertSee('Siguiente fecha disponible: ' . $this->lunes->copy()->addWeek()->translatedFormat('l d/m/Y'));
    }

    // ---------- Acceso ----------

    public function test_solo_recepcionista_y_paciente_consultan(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('admin'))->get('/disponibilidad')->assertForbidden();
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'))->get('/disponibilidad')->assertForbidden();
    }
}
