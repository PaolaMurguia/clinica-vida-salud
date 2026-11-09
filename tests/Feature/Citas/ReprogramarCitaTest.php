<?php

namespace Tests\Feature\Citas;

use App\Models\Bitacora;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\HistorialCita;
use App\Models\Notificacion;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CU15 – Reprogramar cita. Flujo principal (misma cita, nueva franja, historial, recordatorio nuevo y
 * avisos), concurrencia (D2) y las excepciones del documento.
 */
class ReprogramarCitaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Carbon $lunes;   // «proxima» de la población
    private Cita $deMaria;   // lunes 08:00 con Pérez (medicina general: lun 08-12, 30 min), Programada

    protected function setUp(): void
    {
        parent::setUp();
        $this->lunes = now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
        $this->deMaria = Cita::where('paciente_id', Paciente::buscarPorCI('1234567')->id)->where('estado', 'Programada')->first();
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function reprogramar(Cita $cita, Carbon $fecha, string $hora)
    {
        return $this->put("/citas/{$cita->id}", ['fecha' => $fecha->toDateString(), 'hora_inicio' => $hora]);
    }

    public function test_la_recepcionista_reprograma_y_la_cita_conserva_sus_datos(): void
    {
        $nueva = $this->lunes->copy()->addWeek();   // así el recordatorio de 24 horas siempre queda en el futuro
        $codigo = $this->deMaria->codigo;

        $this->get("/historial-citas/{$this->deMaria->id}")->assertSee(route('citas.edit', $this->deMaria));
        $this->get("/citas/{$this->deMaria->id}/reprogramar?fecha={$nueva->toDateString()}")->assertOk()
            ->assertSee('Reprogramar cita ' . $codigo)->assertSee('10:00–10:30');

        $this->reprogramar($this->deMaria, $nueva, '10:00')
            ->assertRedirect(route('citas.create', ['cita' => $this->deMaria->id]))->assertSessionHas('exito');

        $cita = $this->deMaria->refresh();
        $this->assertSame($codigo, $cita->codigo);
        $this->assertSame('Programada', $cita->estado);
        $this->assertSame($nueva->toDateString(), $cita->fecha->toDateString());
        $this->assertSame(['10:00:00', '10:30:00'], [$cita->hora_inicio, $cita->hora_fin]);

        // la franja anterior queda libre
        $this->assertFalse(Cita::existeConflicto($cita->medico_id, $this->lunes, '08:00', '08:30'));

        $cambio = HistorialCita::where('cita_id', $cita->id)->latest('id')->first();
        $this->assertSame(['Programada', 'Programada'], [$cambio->estado_anterior, $cambio->estado_nuevo]);
        $this->assertStringContainsString('al ' . $nueva->format('d/m/Y') . ' 10:00', $cambio->observacion);
        $this->assertSame(Usuario::buscarPorLogin('recep1')->id, $cambio->usuario_id);

        // recordatorios: los anteriores anulados y uno nuevo 24 horas antes (RN21)
        $recordatorios = Notificacion::where('cita_id', $cita->id)->where('tipo', 'Recordatorio')->get();
        $this->assertTrue($recordatorios->where('estado', 'Anulado')->isNotEmpty());
        $this->assertTrue($recordatorios->where('estado', 'Pendiente')->every(
            fn ($n) => $n->fecha_programada->equalTo($nueva->copy()->setTime(10, 0)->subDay())));
        $this->assertCount(2, $recordatorios->where('estado', 'Pendiente'));

        // aviso al paciente y al médico (RF21) y bitácora
        $avisos = Notificacion::where('cita_id', $cita->id)->where('tipo', 'Reprogramacion')->pluck('usuario_id');
        $this->assertTrue($avisos->contains(Usuario::buscarPorLogin('1234567')->id));
        $this->assertTrue($avisos->contains(Usuario::buscarPorLogin('dr.perez')->id));
        $this->assertTrue(Bitacora::where('accion', 'REPROGRAMAR')->where('registro_id', $cita->id)->exists());

        $this->get("/historial-citas/{$cita->id}")->assertSee('Reprogramación (Programada)');
    }

    public function test_el_paciente_reprograma_su_propia_cita(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->reprogramar($this->deMaria, $this->lunes, '11:00')->assertSessionHas('exito');
        $this->assertSame('11:00:00', $this->deMaria->refresh()->hora_inicio);
    }

    public function test_muestra_la_franja_actual_y_las_ocupadas(): void
    {
        $this->get("/citas/{$this->deMaria->id}/reprogramar?fecha={$this->lunes->toDateString()}")->assertOk()
            ->assertSee('08:00–08:30 (actual)')->assertSee('09:00–09:30');
    }

    // ---------- Excepciones ----------

    public function test_cita_atendida_cancelada_o_con_inasistencia_no_se_reprograma(): void
    {
        foreach (['Atendida', 'Cancelada', 'Inasistencia'] as $estado) {
            $cita = Cita::where('estado', $estado)->first();
            $this->reprogramar($cita, $this->lunes, '09:00')
                ->assertSessionHasErrors(['cita' => "La cita está en estado $estado: no se reprograma; registre una nueva cita (CU14)."]);
        }
        $this->get('/citas/' . Cita::where('estado', 'Atendida')->value('id') . '/reprogramar')->assertSee('no se reprograma');
    }

    public function test_paciente_fuera_del_plazo_de_dos_horas(): void
    {
        Carbon::setTestNow($this->lunes->copy()->setTime(7, 0));
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->get("/citas/{$this->deMaria->id}/reprogramar")->assertSee('comuníquese con la recepción (RN17)');
        $this->reprogramar($this->deMaria, $this->lunes, '11:00')
            ->assertSessionHasErrors(['cita' => 'Faltan menos de 2 horas para la cita: para reprogramarla comuníquese con la recepción (RN17).']);
    }

    public function test_nueva_franja_ocupada(): void
    {
        Cita::crear([
            'paciente_id' => Paciente::buscarPorCI('8901234')->id, 'medico_id' => $this->deMaria->medico_id,
            'especialidad_id' => $this->deMaria->especialidad_id, 'usuario_registro_id' => Usuario::buscarPorLogin('recep1')->id,
            'fecha' => $this->lunes->toDateString(), 'hora_inicio' => '09:00', 'hora_fin' => '09:30',
        ]);

        $this->reprogramar($this->deMaria, $this->lunes, '09:00')->assertSessionHasErrors('hora_inicio');
        $this->assertSame('08:00:00', $this->deMaria->refresh()->hora_inicio);
    }

    public function test_la_franja_ocupada_mientras_tanto_vuelve_a_las_franjas(): void
    {
        // D2: otro usuario ocupa la franja después de la validación; la restricción EXCLUDE lo detecta
        Cita::updating(function (Cita $cita) {
            DB::table('cita')->insert([
                'paciente_id' => Paciente::buscarPorCI('8901234')->id, 'medico_id' => $cita->medico_id,
                'especialidad_id' => $cita->especialidad_id, 'usuario_registro_id' => Usuario::buscarPorLogin('recep2')->id,
                'fecha' => $cita->fecha, 'hora_inicio' => $cita->hora_inicio, 'hora_fin' => $cita->hora_fin,
            ]);
        });

        $this->reprogramar($this->deMaria, $this->lunes, '09:00')
            ->assertRedirect(route('citas.edit', ['cita' => $this->deMaria->id, 'fecha' => $this->lunes->toDateString()]))
            ->assertSessionHas('error');
        $this->assertSame('08:00:00', $this->deMaria->refresh()->hora_inicio);
    }

    public function test_nueva_fecha_pasada_bloqueada_o_fuera_del_horario(): void
    {
        $this->reprogramar($this->deMaria, $this->lunes->copy()->subWeeks(2), '09:00')
            ->assertSessionHasErrors(['fecha' => 'No se pueden programar citas en fechas u horas pasadas (RN06).']);

        $this->reprogramar($this->deMaria, $this->lunes->copy()->addDay(), '09:00')   // martes: Pérez no atiende
            ->assertSessionHasErrors(['hora_inicio' => 'La franja no pertenece al horario del médico o no respeta la duración configurada (RN05, RN08).']);

        $otroLunes = $this->lunes->copy()->addWeek();
        Bloqueo::create(['medico_id' => $this->deMaria->medico_id, 'fecha' => $otroLunes->toDateString(), 'motivo' => 'Congreso']);
        $this->reprogramar($this->deMaria, $otroLunes, '09:00')->assertSessionHasErrors(['fecha' => 'La fecha está bloqueada: Congreso.']);
    }

    public function test_medico_inactivo_o_que_ya_no_atiende_la_especialidad(): void
    {
        DB::table('medico_especialidad')->where('medico_id', $this->deMaria->medico_id)
            ->where('especialidad_id', $this->deMaria->especialidad_id)->delete();
        $this->reprogramar($this->deMaria, $this->lunes, '09:00')
            ->assertSessionHasErrors(['especialidad_id' => 'El médico no atiende la especialidad seleccionada.']);

        $this->deMaria->medico->cambiarEstado(false);
        $this->reprogramar($this->deMaria, $this->lunes, '09:00')->assertSessionHasErrors('medico_id');
        $this->get("/citas/{$this->deMaria->id}/reprogramar")->assertSee('El médico está inactivo');
    }

    public function test_misma_fecha_y_hora_no_hay_cambios(): void
    {
        $this->reprogramar($this->deMaria, $this->lunes, '08:00')
            ->assertSessionHasErrors(['hora_inicio' => 'La nueva fecha y hora son iguales a las actuales: no hay cambios.']);
    }

    public function test_sin_franja_elegida(): void
    {
        $this->put("/citas/{$this->deMaria->id}", ['fecha' => $this->lunes->toDateString()])
            ->assertSessionHasErrors(['hora_inicio' => 'Elija una nueva franja libre.']);
    }

    public function test_el_paciente_no_reprograma_una_cita_ajena(): void
    {
        $ajena = Cita::where('paciente_id', Paciente::buscarPorCI('7654321')->id)->where('estado', 'Programada')->first();
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->get("/citas/{$ajena->id}/reprogramar")->assertForbidden();
        $this->reprogramar($ajena, $this->lunes->copy()->addWeek(), '08:00')
            ->assertSessionHasErrors(['cita' => 'Solo puede reprogramar sus propias citas (RN18).']);
    }
}
