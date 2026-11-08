<?php

namespace Tests\Feature\Citas;

use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\HistorialCita;
use App\Models\Notificacion;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CU16 – Cancelar cita. Flujo principal (la cita se conserva y la franja queda libre), historial,
 * recordatorios anulados, avisos y las 5 excepciones del documento.
 */
class CancelarCitaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Cita $deCarlos;   // futura, Programada, paciente sin cuenta
    private Cita $deMaria;    // futura, Programada, con Pérez; María (cuenta 1234567) tiene recordatorios pendientes

    protected function setUp(): void
    {
        parent::setUp();
        $this->deCarlos = Cita::where('paciente_id', Paciente::buscarPorCI('7654321')->id)->where('estado', 'Programada')->first();
        $this->deMaria = Cita::where('paciente_id', Paciente::buscarPorCI('1234567')->id)->where('estado', 'Programada')->first();
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function cancelar(Cita $cita, string $motivo = 'El paciente no puede asistir')
    {
        return $this->patch("/citas/{$cita->id}/cancelar", ['motivo_cancelacion' => $motivo]);
    }

    public function test_la_recepcionista_cancela_y_la_cita_se_conserva_con_su_historial(): void
    {
        $this->get("/historial-citas/{$this->deCarlos->id}")->assertSee(route('citas.show', $this->deCarlos));
        $this->get("/citas/{$this->deCarlos->id}")->assertOk()->assertSee('Motivo de la cancelación');

        $this->cancelar($this->deCarlos)
            ->assertRedirect(route('historialCitas.show', $this->deCarlos))->assertSessionHas('exito');

        $cita = $this->deCarlos->refresh();
        $this->assertSame('Cancelada', $cita->estado);
        $this->assertSame('El paciente no puede asistir', $cita->motivo_cancelacion);

        $cambio = HistorialCita::where('cita_id', $cita->id)->where('estado_nuevo', 'Cancelada')->first();
        $this->assertSame('Programada', $cambio->estado_anterior);
        $this->assertSame('El paciente no puede asistir', $cambio->observacion);
        $this->assertSame(Usuario::buscarPorLogin('recep1')->id, $cambio->usuario_id);

        // RN09: la franja queda libre de inmediato
        $this->assertFalse(Cita::existeConflicto($cita->medico_id, $cita->fecha, $cita->hora_inicio, $cita->hora_fin));
        $this->assertTrue(Bitacora::where('accion', 'CANCELAR')->where('registro_id', $cita->id)->exists());

        // la cita sigue visible en el historial (RN10)
        $this->get("/historial-citas?paciente_id={$cita->paciente_id}")->assertSee($cita->codigo);
    }

    public function test_el_paciente_cancela_su_cita_y_se_anulan_los_recordatorios(): void
    {
        $maria = Usuario::buscarPorLogin('1234567');
        $this->assertTrue(Notificacion::where('cita_id', $this->deMaria->id)->where('tipo', 'Recordatorio')->where('estado', 'Pendiente')->exists());

        $this->flushSession()->actingAs($maria);
        $this->cancelar($this->deMaria, 'Viaje imprevisto')->assertSessionHas('exito');

        $this->assertSame('Cancelada', $this->deMaria->refresh()->estado);
        $this->assertSame(0, Notificacion::where('cita_id', $this->deMaria->id)->where('tipo', 'Recordatorio')->where('estado', 'Pendiente')->count());
        $this->assertTrue(Notificacion::where('cita_id', $this->deMaria->id)->where('tipo', 'Recordatorio')->where('estado', 'Anulado')->exists());

        // aviso de cancelación al paciente y al médico (RF21)
        $avisos = Notificacion::where('cita_id', $this->deMaria->id)->where('tipo', 'Cancelacion')->get();
        $this->assertTrue($avisos->contains('usuario_id', $maria->id));
        $this->assertTrue($avisos->contains('usuario_id', Usuario::buscarPorLogin('dr.perez')->id));
        $this->assertStringContainsString('Viaje imprevisto', $avisos->first()->mensaje);
    }

    // ---------- Excepciones ----------

    public function test_motivo_vacio(): void
    {
        $this->cancelar($this->deCarlos, '')->assertSessionHasErrors(['motivo_cancelacion' => 'Indique el motivo de la cancelación.']);
        $this->cancelar($this->deCarlos, '   ')->assertSessionHasErrors('motivo_cancelacion');
        $this->assertSame('Programada', $this->deCarlos->refresh()->estado);
    }

    public function test_la_cita_ya_esta_cancelada(): void
    {
        $cancelada = Cita::where('estado', 'Cancelada')->first();

        $this->cancelar($cancelada)->assertSessionHasErrors(['cita' => 'La cita ya está cancelada.']);
        $this->get("/citas/{$cancelada->id}")->assertOk()->assertSee('La cita ya está cancelada.')->assertDontSee('Cancelar la cita');
    }

    public function test_cita_atendida_o_con_inasistencia_no_se_cancela(): void
    {
        foreach (['Atendida', 'Inasistencia'] as $estado) {
            $cita = Cita::where('estado', $estado)->first();
            $this->cancelar($cita)->assertSessionHasErrors(['cita' => "La cita está en estado $estado: no se cancela como si nunca hubiera ocurrido (RN10)."]);
            $this->assertSame($estado, $cita->refresh()->estado);
        }
    }

    public function test_paciente_fuera_del_plazo_de_dos_horas(): void
    {
        // una hora antes de la cita de María
        Carbon::setTestNow($this->deMaria->fecha->copy()->setTimeFromTimeString($this->deMaria->hora_inicio)->subHour());
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->get("/citas/{$this->deMaria->id}")->assertOk()->assertSee('comuníquese con la recepción (RN17)');
        $this->cancelar($this->deMaria)->assertSessionHasErrors(['cita' => 'Faltan menos de 2 horas para la cita: para cancelarla comuníquese con la recepción (RN17).']);
        $this->assertSame('Programada', $this->deMaria->refresh()->estado);

        // la recepción sí puede cancelarla
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('recep1'));
        $this->cancelar($this->deMaria)->assertSessionHas('exito');
        Carbon::setTestNow();
    }

    public function test_el_paciente_no_cancela_una_cita_ajena(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->get("/citas/{$this->deCarlos->id}")->assertForbidden();
        $this->cancelar($this->deCarlos)->assertSessionHasErrors(['cita' => 'Solo puede cancelar sus propias citas (RN18).']);
        $this->assertSame('Programada', $this->deCarlos->refresh()->estado);
    }

    public function test_el_medico_y_el_administrador_no_cancelan(): void
    {
        foreach (['dr.perez', 'admin'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login));
            $this->cancelar($this->deCarlos)->assertForbidden();
        }
    }
}
