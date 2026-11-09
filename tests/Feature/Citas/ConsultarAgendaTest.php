<?php

namespace Tests\Feature\Citas;

use App\Models\Cita;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CU18 – Consultar agenda médica (y los botones de CU17 en V.agenda). Día y semana, filtros, bloqueos,
 * RN18 y las 5 excepciones del documento.
 */
class ConsultarAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Carbon $lunes;    // «proxima»: María con Pérez a las 08:00
    private Carbon $pasada;   // «pasada»: Pérez atendió a María 09:00 y Pedro faltó 09:30
    private Medico $perez;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lunes = now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
        $this->pasada = now()->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay();
        $this->perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $this->actingAs(Usuario::buscarPorLogin('dr.perez'));
    }

    private function agenda(array $filtros)
    {
        return $this->get('/agenda/filtrar?' . http_build_query($filtros));
    }

    public function test_el_medico_ve_su_agenda_del_dia(): void
    {
        $this->get('/menu')->assertSee('Agenda');
        $this->get('/agenda')->assertOk()->assertSee('Pérez Rojas, Juan')->assertDontSee('Seleccione…');

        $cita = Cita::where('medico_id', $this->perez->id)->whereDate('fecha', $this->lunes)->first();
        $this->agenda(['desde' => $this->lunes->toDateString()])->assertOk()
            ->assertSee($cita->codigo)->assertSee('López Vargas, María')->assertSee('Medicina general')
            ->assertSee('Revisión de análisis')->assertSee('Programada')->assertSee('Presencial')->assertSee('08:00–08:30');
    }

    public function test_vista_semanal_ordenada_por_fecha_y_hora(): void
    {
        $this->agenda(['desde' => $this->pasada->copy()->addDays(2)->toDateString(), 'vista' => 'semana'])->assertOk()
            ->assertSeeInOrder(['09:00–09:30', 'López Vargas', '09:30–10:00', 'Antelo Saucedo'])
            ->assertSee('Atendida')->assertSee('Inasistencia');
    }

    public function test_la_recepcion_y_el_administrador_eligen_el_medico(): void
    {
        foreach (['recep1', 'admin'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login));
            $this->get('/agenda')->assertOk()->assertSee('Elija un médico para ver su agenda.');
            $this->agenda(['medico_id' => $this->perez->id, 'desde' => $this->lunes->toDateString()])->assertOk()->assertSee('López Vargas, María');
        }
    }

    public function test_filtra_por_estado(): void
    {
        $this->agenda(['desde' => $this->pasada->toDateString(), 'estado' => 'Inasistencia'])->assertOk()
            ->assertSee('Antelo Saucedo')->assertDontSee('López Vargas');
    }

    public function test_las_citas_de_un_medico_por_fecha(): void
    {
        // Prueba mínima 8 del enunciado: solo las citas del médico en la fecha elegida
        $esperadas = Cita::where('medico_id', $this->perez->id)->whereDate('fecha', $this->pasada)->pluck('codigo');
        $otras = Cita::where(fn ($q) => $q->where('medico_id', '<>', $this->perez->id)->orWhereDate('fecha', '<>', $this->pasada))->pluck('codigo');

        $respuesta = $this->agenda(['desde' => $this->pasada->toDateString()])->assertOk();
        $esperadas->each(fn ($codigo) => $respuesta->assertSee($codigo));
        $otras->each(fn ($codigo) => $respuesta->assertDontSee($codigo));
    }

    // ---------- Excepciones ----------

    public function test_sin_citas_en_el_rango(): void
    {
        $this->agenda(['desde' => $this->lunes->copy()->addDay()->toDateString()])->assertOk()
            ->assertSee('El médico no tiene citas en ese rango.');
    }

    public function test_rango_invalido(): void
    {
        $this->agenda(['desde' => $this->lunes->toDateString(), 'hasta' => $this->pasada->toDateString()])
            ->assertSessionHasErrors(['hasta' => 'Rango de fechas inválido: la fecha final es anterior a la inicial.']);
        $this->agenda(['desde' => '2026-13-01'])->assertSessionHasErrors('desde');
    }

    public function test_el_medico_no_ve_la_agenda_de_otro(): void
    {
        $otro = Medico::where('nro_registro_prof', 'RP-1002')->first();

        $this->agenda(['medico_id' => $otro->id])->assertSessionHasErrors(['medico_id' => 'Solo puede consultar su propia agenda (RN18).']);
    }

    public function test_medico_inactivo_muestra_su_agenda_historica(): void
    {
        $this->perez->cambiarEstado(false);
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'));

        $this->agenda(['medico_id' => $this->perez->id, 'desde' => $this->pasada->toDateString()])->assertOk()
            ->assertSee('no recibe nuevas citas (RN04)')->assertSee('Antelo Saucedo');
    }

    public function test_fecha_bloqueada_con_su_motivo(): void
    {
        $rojas = Medico::where('nro_registro_prof', 'RP-1003')->first();   // bloqueado «proxima» + 14
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'));

        $this->agenda(['medico_id' => $rojas->id, 'desde' => $this->lunes->copy()->addDays(14)->toDateString()])->assertOk()
            ->assertSee('fecha bloqueada para el médico')->assertSee('Capacitación del médico');
    }

    public function test_el_paciente_no_accede_a_la_agenda(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))->get('/agenda')->assertForbidden();
    }

    // ---------- Botones de CU17 en V.agenda ----------

    public function test_acciones_de_asistencia_segun_el_rol_y_la_hora(): void
    {
        $cita = Cita::where('medico_id', $this->perez->id)->whereDate('fecha', $this->lunes)->first();
        $filtros = ['medico_id' => $this->perez->id, 'desde' => $this->lunes->toDateString()];

        // antes de la hora: la recepción solo confirma; el médico aún no puede marcarla atendida
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'));
        $this->agenda($filtros)->assertSee('Confirmar')->assertDontSee('Inasistencia</button>', false);
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'));
        $this->agenda($filtros)->assertDontSee('Atendida</button>', false);

        // después de la hora de inicio
        Carbon::setTestNow($cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio)->addMinutes(5));
        $this->agenda($filtros)->assertSee('Atendida</button>', false);
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'));
        $this->agenda($filtros)->assertSee('Inasistencia</button>', false)->assertDontSee('Atendida</button>', false);
    }

    public function test_el_medico_sin_permiso_no_ve_el_boton_de_atendida(): void
    {
        $mendoza = Medico::where('nro_registro_prof', 'RP-1002')->first();
        $cita = Cita::where('medico_id', $mendoza->id)->whereIn('estado', Cita::VIGENTES)->first();
        Carbon::setTestNow($cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio)->addMinutes(5));

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dra.mendoza'));
        $this->agenda(['desde' => $cita->fecha->toDateString()])->assertSee($cita->codigo)->assertDontSee('Atendida</button>', false);
    }
}
