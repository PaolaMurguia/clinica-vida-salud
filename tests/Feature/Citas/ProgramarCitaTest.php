<?php

namespace Tests\Feature\Citas;

use App\Models\Bitacora;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HistorialCita;
use App\Models\Medico;
use App\Models\Notificacion;
use App\Models\Paciente;
use App\Models\Usuario;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * CU14 – Programar cita. Flujo principal (recepción y portal), las 8 excepciones del documento,
 * la concurrencia (D2), las notificaciones (D3, D4, D5) y el traductor de errores de la BD (D1, RNF08).
 */
class ProgramarCitaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Carbon $lunes;          // «proxima» de la población: lunes de la semana siguiente
    private Medico $perez;          // Medicina general: lun 08-12 (30 min); tiene cuenta dr.perez
    private Especialidad $general;
    private Paciente $diego;        // 25 años, sin cuenta de usuario
    private Paciente $maria;        // 36 años, cuenta 1234567; ya tiene cita con Pérez el lunes a las 08:00

    protected function setUp(): void
    {
        parent::setUp();
        $this->lunes = now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
        $this->perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $this->general = Especialidad::where('nombre', 'Medicina general')->first();
        $this->diego = Paciente::buscarPorCI('8901234');
        $this->maria = Paciente::buscarPorCI('1234567');
        $this->actingAs(Usuario::buscarPorLogin('recep1'));
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'paciente_id'     => $this->diego->id,
            'especialidad_id' => $this->general->id,
            'medico_id'       => $this->perez->id,
            'fecha'           => $this->lunes->toDateString(),
            'hora_inicio'     => '09:00',
            'modalidad'       => 'Presencial',
            'motivo'          => 'Control general',
        ];
    }

    private function citasDe(Paciente $paciente)
    {
        return Cita::where('paciente_id', $paciente->id)->whereDate('fecha', $this->lunes->toDateString());
    }

    // ---------- Flujo principal ----------

    public function test_la_recepcionista_programa_una_cita_y_ve_el_comprobante(): void
    {
        $respuesta = $this->post('/citas', $this->datos());

        $cita = $this->citasDe($this->diego)->first();
        $this->assertNotNull($cita);
        $respuesta->assertRedirect(route('citas.create', ['cita' => $cita->id]));
        $this->assertMatchesRegularExpression('/^CM-\d{4,}$/', $cita->codigo);
        $this->assertSame('Programada', $cita->estado);
        $this->assertSame('Recepcion', $cita->canal);
        $this->assertSame('09:30:00', $cita->hora_fin);   // la duración la fija el horario (RN08)
        $this->assertSame(Usuario::buscarPorLogin('recep1')->id, $cita->usuario_registro_id);

        // paso 7: estado inicial en el historial
        $this->assertSame(1, HistorialCita::where('cita_id', $cita->id)->whereNull('estado_anterior')->where('estado_nuevo', 'Programada')->count());

        // paso 8: aviso al médico; Diego no tiene cuenta y no recibe notificaciones
        $this->assertTrue(Notificacion::where('cita_id', $cita->id)->where('tipo', 'Agenda')
            ->where('usuario_id', Usuario::buscarPorLogin('dr.perez')->id)->where('canal', 'Sistema')->exists());
        $this->assertSame(1, Notificacion::where('cita_id', $cita->id)->count());

        // paso 9: bitácora y comprobante
        $this->assertTrue(Bitacora::where('accion', 'CREAR')->where('modulo', 'Citas')->where('registro_id', $cita->id)->exists());
        $this->get(route('citas.create', ['cita' => $cita->id]))->assertOk()
            ->assertSee($cita->codigo)->assertSee('Comprobante de cita')->assertSee('Paz Mercado')->assertSee('09:00–09:30');
    }

    public function test_el_paciente_programa_desde_el_portal_y_recibe_confirmacion_y_recordatorio(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));
        // una semana más tarde, para que el recordatorio (24 horas antes) siempre quede en el futuro
        $this->lunes->addWeek();
        $datos = $this->datos(['hora_inicio' => '10:00']);
        unset($datos['paciente_id']);   // el portal no envía el paciente: siempre es el propio (RN18)

        $this->post('/citas', $datos)->assertRedirect();

        $cita = $this->citasDe($this->maria)->where('hora_inicio', '10:00')->first();
        $this->assertSame('Web', $cita->canal);

        $usuarioId = Usuario::buscarPorLogin('1234567')->id;
        $confirmaciones = Notificacion::where('cita_id', $cita->id)->where('usuario_id', $usuarioId)->where('tipo', 'Confirmacion')->get();
        $this->assertEqualsCanonicalizing(['Sistema', 'Correo'], $confirmaciones->pluck('canal')->all());
        // el correo inmediato se envía al confirmar la transacción
        $this->assertSame('Enviado', $confirmaciones->firstWhere('canal', 'Correo')->estado);

        // RN21: recordatorio 24 horas antes, pendiente
        $recordatorios = Notificacion::where('cita_id', $cita->id)->where('tipo', 'Recordatorio')->get();
        $this->assertCount(2, $recordatorios);
        $this->assertTrue($recordatorios->every(fn ($n) => $n->estado === 'Pendiente'
            && $n->fecha_programada->equalTo($this->lunes->copy()->setTime(10, 0)->subDay())));
    }

    public function test_muestra_el_resumen_de_la_franja_elegida(): void
    {
        $this->get(route('citas.create', $this->datos()))->assertOk()
            ->assertSee('Programar cita')->assertSee('Paz Mercado')->assertSee('Medicina general')->assertSee('09:00–09:30');
    }

    public function test_las_franjas_libres_de_la_disponibilidad_llevan_a_programar(): void
    {
        $this->get('/disponibilidad/franjas?' . http_build_query([
            'paciente_id' => $this->diego->id, 'especialidad_id' => $this->general->id,
            'medico_id' => $this->perez->id, 'fecha' => $this->lunes->toDateString(),
        ]))->assertOk()->assertSee(route('citas.create', [
            'paciente_id' => $this->diego->id, 'especialidad_id' => $this->general->id, 'medico_id' => $this->perez->id,
            'fecha' => $this->lunes->toDateString(), 'hora_inicio' => '09:00',
        ]));
    }

    // ---------- Excepciones ----------

    public function test_franja_ya_ocupada(): void
    {
        $this->post('/citas', $this->datos(['hora_inicio' => '08:00']))   // la tiene María
            ->assertSessionHasErrors(['hora_inicio' => 'La franja ya fue ocupada por otra cita. Elija otra franja libre (RN07).']);
        $this->assertSame(0, $this->citasDe($this->diego)->count());
    }

    public function test_la_franja_ocupada_mientras_tanto_vuelve_a_la_disponibilidad(): void
    {
        // D2: otro usuario inserta la misma franja después de la validación; la restricción EXCLUDE lo detecta
        Cita::creating(function (Cita $cita) {
            DB::table('cita')->insert([
                'paciente_id' => Paciente::buscarPorCI('6789012')->id, 'medico_id' => $cita->medico_id,
                'especialidad_id' => $cita->especialidad_id, 'usuario_registro_id' => Usuario::buscarPorLogin('recep2')->id,
                'fecha' => $cita->fecha, 'hora_inicio' => $cita->hora_inicio, 'hora_fin' => $cita->hora_fin,
            ]);
        });

        $this->post('/citas', $this->datos())
            ->assertRedirect(route('disponibilidad.franjas', [
                'paciente_id' => $this->diego->id, 'especialidad_id' => $this->general->id,
                'medico_id' => $this->perez->id, 'fecha' => $this->lunes->toDateString(),
            ]))
            ->assertSessionHas('error', 'La franja ya fue ocupada por otro usuario. Elija otra franja libre.');
        $this->assertSame(0, $this->citasDe($this->diego)->count());
    }

    public function test_fecha_u_hora_pasada(): void
    {
        $this->post('/citas', $this->datos(['fecha' => $this->lunes->copy()->subWeeks(2)->toDateString()]))
            ->assertSessionHasErrors(['fecha' => 'No se pueden programar citas en fechas u horas pasadas (RN06).']);
    }

    public function test_medico_o_paciente_inactivo(): void
    {
        $this->perez->cambiarEstado(false);
        $this->post('/citas', $this->datos())->assertSessionHasErrors('medico_id');

        $this->perez->cambiarEstado(true);
        $this->diego->desactivar();
        $this->post('/citas', $this->datos())->assertSessionHasErrors('paciente_id');
        $this->assertSame(0, $this->citasDe($this->diego)->count());
    }

    public function test_especialidad_que_no_corresponde_a_la_edad(): void
    {
        $pediatria = Especialidad::where('nombre', 'Pediatría')->first();
        $mendoza = Medico::where('nro_registro_prof', 'RP-1002')->first();   // pediatría: mar 09-13

        $this->post('/citas', $this->datos([
            'especialidad_id' => $pediatria->id, 'medico_id' => $mendoza->id, 'fecha' => $this->lunes->copy()->addDay()->toDateString(),
        ]))->assertSessionHasErrors(['especialidad_id' => 'La especialidad no está disponible o no corresponde a la edad del paciente en esa fecha (25 años) (RN15).']);
    }

    public function test_medico_que_no_atiende_la_especialidad(): void
    {
        $odontologia = Especialidad::where('nombre', 'Odontología')->first();

        $this->post('/citas', $this->datos(['especialidad_id' => $odontologia->id]))
            ->assertSessionHasErrors(['especialidad_id' => 'El médico no atiende la especialidad seleccionada.']);
    }

    public function test_franja_fuera_del_horario_o_con_duracion_invalida(): void
    {
        $mensaje = 'La franja no pertenece al horario del médico o no respeta la duración configurada (RN05, RN08).';
        $this->post('/citas', $this->datos(['hora_inicio' => '13:00']))->assertSessionHasErrors(['hora_inicio' => $mensaje]);
        $this->post('/citas', $this->datos(['hora_inicio' => '09:15']))->assertSessionHasErrors(['hora_inicio' => $mensaje]);
        $this->post('/citas', $this->datos(['fecha' => $this->lunes->copy()->addDay()->toDateString()]))   // martes: no atiende
            ->assertSessionHasErrors(['hora_inicio' => $mensaje]);
    }

    public function test_fecha_bloqueada(): void
    {
        Bloqueo::create(['medico_id' => null, 'fecha' => $this->lunes->toDateString(), 'motivo' => 'Feriado de prueba']);

        $this->post('/citas', $this->datos())->assertSessionHasErrors(['fecha' => 'La fecha está bloqueada: Feriado de prueba.']);
    }

    public function test_el_paciente_no_programa_para_otra_persona(): void
    {
        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'));

        $this->post('/citas', $this->datos())   // paciente_id = Diego
            ->assertSessionHasErrors(['paciente_id' => 'Solo puede programar citas para usted (RN18).']);
        $this->assertSame(0, $this->citasDe($this->diego)->count());
    }

    public function test_el_paciente_no_ve_el_comprobante_de_otro(): void
    {
        $ajena = Cita::where('paciente_id', '<>', $this->maria->id)->first();

        $this->flushSession()->actingAs(Usuario::buscarPorLogin('1234567'))
            ->get(route('citas.create', ['cita' => $ajena->id]))->assertForbidden();
    }

    public function test_datos_incompletos_o_invalidos(): void
    {
        $this->post('/citas', $this->datos(['modalidad' => '']))->assertSessionHasErrors('modalidad');
        $this->post('/citas', $this->datos(['modalidad' => 'Domicilio']))->assertSessionHasErrors('modalidad');
        $this->post('/citas', $this->datos(['hora_inicio' => '9h']))->assertSessionHasErrors('hora_inicio');
        $this->post('/citas', $this->datos(['motivo' => str_repeat('x', 201)]))->assertSessionHasErrors('motivo');
        $this->assertSame(0, $this->citasDe($this->diego)->count());
    }

    public function test_el_medico_y_el_administrador_no_programan_citas(): void
    {
        foreach (['dr.perez', 'admin'] as $login) {
            $this->flushSession()->actingAs(Usuario::buscarPorLogin($login))
                ->post('/citas', $this->datos())->assertForbidden();
        }
    }

    // ---------- Notificaciones (D3, D4, D5) ----------

    public function test_la_bandeja_muestra_los_avisos_y_los_marca_como_leidos(): void
    {
        $this->post('/citas', $this->datos());
        $perez = Usuario::buscarPorLogin('dr.perez');

        $this->flushSession()->actingAs($perez)->get('/menu')->assertSee('Notificaciones');
        $this->assertSame(1, Notificacion::where('usuario_id', $perez->id)->where('canal', 'Sistema')->where('estado', 'Pendiente')
            ->where('fecha_programada', '<=', now())->count());

        $this->get('/notificaciones')->assertOk()->assertSee('Nueva cita en su agenda')->assertSee('Paz Mercado');
        $this->assertSame(0, Notificacion::where('usuario_id', $perez->id)->where('canal', 'Sistema')->where('estado', 'Pendiente')
            ->where('fecha_programada', '<=', now())->count());
    }

    public function test_el_comando_envia_los_correos_vencidos_y_registra_los_fallos(): void
    {
        $maria = Usuario::buscarPorLogin('1234567');
        $vencida = Notificacion::create([
            'usuario_id' => $maria->id, 'canal' => 'Correo', 'tipo' => 'Recordatorio', 'mensaje' => 'Recuerde su cita',
            'fecha_programada' => now()->subMinute(),
        ]);
        $futura = Notificacion::create([
            'usuario_id' => $maria->id, 'canal' => 'Correo', 'tipo' => 'Recordatorio', 'mensaje' => 'Recuerde su otra cita',
            'fecha_programada' => now()->addDay(),
        ]);

        $this->artisan('notificaciones:enviar')->assertSuccessful();

        $this->assertSame('Enviado', $vencida->refresh()->estado);
        $this->assertNotNull($vencida->fecha_envio);
        $this->assertSame('Pendiente', $futura->refresh()->estado);

        // D3: un fallo del correo se registra y no interrumpe
        $vencida->update(['estado' => 'Pendiente', 'intentos' => 0]);
        Mail::shouldReceive('raw')->andThrow(new Exception('Servidor de correo caído'));
        $this->assertSame(0, Notificacion::enviarPendientes());
        $this->assertSame('Fallido', $vencida->refresh()->estado);
        $this->assertSame(1, $vencida->intentos);
    }

    // ---------- Traductor de errores de la base de datos (D1, RNF08) ----------

    public function test_los_errores_de_los_triggers_se_muestran_sin_detalles_internos(): void
    {
        // Ruta de prueba que salta las validaciones e inserta directamente una cita pasada: la rechaza trg_validar_cita
        Route::middleware('web')->post('/_prueba/cita-directa', fn () => Cita::create([
            'paciente_id' => $this->diego->id, 'medico_id' => $this->perez->id, 'especialidad_id' => $this->general->id,
            'usuario_registro_id' => auth()->id(), 'fecha' => $this->lunes->copy()->subWeeks(2)->toDateString(),
            'hora_inicio' => '09:00', 'hora_fin' => '09:30',
        ]));

        $this->from('/menu')->post('/_prueba/cita-directa')
            ->assertRedirect('/menu')
            ->assertSessionHas('error', 'No se pueden registrar citas en fechas u horas pasadas (RN06).');
        $this->assertStringNotContainsString('SQLSTATE', session('error'));
    }
}
