<?php

namespace Tests\Feature\Citas;

use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\HistorialCita;
use App\Models\Medico;
use App\Models\Notificacion;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CU17 – Registrar asistencia. Acciones por rol (confirmar, registrar inasistencia, atender), transiciones
 * válidas (RF10), la hora de inicio (D6), el permiso del médico y las 6 excepciones del documento.
 */
class RegistrarAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Cita $deMaria;   // con Pérez (dr.perez, con permiso), Programada, con recordatorios pendientes
    private Carbon $inicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deMaria = Cita::where('paciente_id', Paciente::buscarPorCI('1234567')->id)->where('estado', 'Programada')->first();
        $this->inicio = $this->deMaria->fecha->copy()->setTimeFromTimeString($this->deMaria->hora_inicio);
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function accion(Cita $cita, string $accion)
    {
        return $this->from('/menu')->patch("/citas/{$cita->id}/estado", ['accion' => $accion]);
    }

    public function test_la_recepcionista_confirma_la_asistencia(): void
    {
        $this->accion($this->deMaria, 'confirmar')->assertRedirect('/menu')->assertSessionHas('exito');

        $this->assertSame('Confirmada', $this->deMaria->refresh()->estado);
        $cambio = HistorialCita::where('cita_id', $this->deMaria->id)->latest('id')->first();
        $this->assertSame(['Programada', 'Confirmada', Usuario::buscarPorLogin('recep1')->id],
            [$cambio->estado_anterior, $cambio->estado_nuevo, $cambio->usuario_id]);
        $this->assertTrue(Bitacora::where('accion', 'CAMBIAR_ESTADO')->where('registro_id', $this->deMaria->id)->exists());
        // confirmar no anula los recordatorios
        $this->assertTrue(Notificacion::where('cita_id', $this->deMaria->id)->where('tipo', 'Recordatorio')->where('estado', 'Pendiente')->exists());
    }

    public function test_la_recepcionista_registra_la_inasistencia_despues_de_la_hora(): void
    {
        Carbon::setTestNow($this->inicio->copy()->addMinutes(15));

        $this->accion($this->deMaria, 'registrarInasistencia')->assertSessionHas('exito');
        $this->assertSame('Inasistencia', $this->deMaria->refresh()->estado);
        // RN21: se anulan los recordatorios pendientes
        $this->assertSame(0, Notificacion::where('cita_id', $this->deMaria->id)->where('tipo', 'Recordatorio')->where('estado', 'Pendiente')->count());
    }

    public function test_el_medico_con_permiso_marca_su_cita_como_atendida(): void
    {
        Carbon::setTestNow($this->inicio->copy()->addMinutes(5));
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'));

        $this->accion($this->deMaria, 'atender')->assertSessionHas('exito');
        $this->assertSame('Atendida', $this->deMaria->refresh()->estado);
        $this->assertSame(0, Notificacion::where('cita_id', $this->deMaria->id)->where('tipo', 'Recordatorio')->where('estado', 'Pendiente')->count());
    }

    // ---------- Excepciones ----------

    public function test_transicion_no_permitida(): void
    {
        $cancelada = Cita::where('estado', 'Cancelada')->first();
        $this->accion($cancelada, 'confirmar')
            ->assertSessionHasErrors(['accion' => 'Transición no permitida: la cita está Cancelada y no puede pasar a Confirmada (RF10).']);

        $confirmada = Cita::where('estado', 'Confirmada')->first();
        $this->accion($confirmada, 'confirmar')->assertSessionHasErrors('accion');
        $this->assertSame('Confirmada', $confirmada->refresh()->estado);
    }

    public function test_medico_sin_el_permiso(): void
    {
        $deMendoza = Cita::where('medico_id', Medico::where('nro_registro_prof', 'RP-1002')->value('id'))->whereIn('estado', Cita::VIGENTES)->first();
        $estado = $deMendoza->estado;
        Carbon::setTestNow($deMendoza->fecha->copy()->setTimeFromTimeString($deMendoza->hora_inicio)->addMinutes(5));
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dra.mendoza'));   // puede_marcar_atendida = false

        $this->accion($deMendoza, 'atender')
            ->assertSessionHasErrors(['accion' => 'No tiene el permiso de marcar citas como atendidas; solicítelo al administrador.']);
        $this->assertSame($estado, $deMendoza->refresh()->estado);
    }

    public function test_el_medico_no_cambia_citas_de_otro_medico_ni_confirma(): void
    {
        $ajena = Cita::where('medico_id', '<>', $this->deMaria->medico_id)->where('estado', 'Programada')->first();
        Carbon::setTestNow($ajena->fecha->copy()->setTimeFromTimeString($ajena->hora_inicio)->addMinutes(5));
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'));

        $this->accion($ajena, 'atender')->assertSessionHasErrors(['accion' => 'No puede cambiar el estado de una cita de otro médico (RN18).']);
        $this->accion($this->deMaria, 'confirmar')->assertSessionHasErrors('accion');
        $this->assertSame('Programada', $ajena->refresh()->estado);
    }

    public function test_la_recepcionista_no_marca_atendida(): void
    {
        Carbon::setTestNow($this->inicio->copy()->addMinutes(5));

        $this->accion($this->deMaria, 'atender')
            ->assertSessionHasErrors(['accion' => 'Solo el médico con el permiso marca la cita como atendida.']);
        $this->assertSame('Programada', $this->deMaria->refresh()->estado);
    }

    public function test_atendida_o_inasistencia_antes_de_la_hora_de_inicio(): void
    {
        Carbon::setTestNow($this->inicio->copy()->subMinutes(10));

        $this->accion($this->deMaria, 'registrarInasistencia')
            ->assertSessionHasErrors(['accion' => "No se puede registrar Inasistencia antes de la hora de inicio de la cita ({$this->inicio->format('d/m/Y H:i')})."]);

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('dr.perez'));
        $this->accion($this->deMaria, 'atender')->assertSessionHasErrors('accion');
        $this->assertSame('Programada', $this->deMaria->refresh()->estado);
    }

    public function test_dos_usuarios_cambian_la_misma_cita_a_la_vez(): void
    {
        // Otro usuario confirma la cita justo después de que esta petición la cargó (enlace de la ruta)
        $id = $this->deMaria->id;
        $hecho = false;
        Cita::retrieved(function (Cita $cita) use ($id, &$hecho) {
            if (! $hecho && $cita->id === $id) {
                $hecho = true;
                DB::table('cita')->where('id', $id)->update(['estado' => 'Confirmada']);
            }
        });

        $this->accion($this->deMaria, 'confirmar')
            ->assertSessionHas('error', 'Transición no permitida: la cita ya cambió de estado (otro usuario la actualizó primero).');
        $this->assertSame(0, HistorialCita::where('cita_id', $id)->where('estado_nuevo', 'Confirmada')->count());
    }

    public function test_paciente_y_administrador_no_registran_asistencia(): void
    {
        foreach (['1234567', 'admin'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login));
            $this->accion($this->deMaria, 'confirmar')->assertForbidden();
        }
    }

    public function test_accion_invalida(): void
    {
        $this->accion($this->deMaria, 'eliminar')->assertSessionHasErrors(['accion' => 'La acción elegida no es válida.']);
    }
}
