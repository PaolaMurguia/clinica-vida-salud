<?php

namespace Tests\Feature;

use App\Models\Antecedente;
use App\Models\Atiende;
use App\Models\Bitacora;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\ExamenLaboratorio;
use App\Models\Historia;
use App\Models\HistorialCita;
use App\Models\Horario;
use App\Models\Medico;
use App\Models\Notificacion;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\ParametroLaboratorio;
use App\Models\RangoReferencia;
use App\Models\Recuperacion;
use App\Models\RegistroAtencion;
use App\Models\ResultadoLaboratorio;
use App\Models\Rol;
use App\Models\Seguro;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Paso 2: esquema, población y modelos Eloquent sobre clinica_vida_salud_test.
 */
class ModelosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Modelo => registros que crea 04_poblacion.sql. */
    private const POBLACION = [
        Rol::class => 4, Especialidad::class => 6, Medico::class => 6, Atiende::class => 7,
        Horario::class => 13, Bloqueo::class => 3, Paciente::class => 10, Usuario::class => 8,
        Recuperacion::class => 2, Seguro::class => 3, Cita::class => 14, HistorialCita::class => 23,
        Pago::class => 6, Historia::class => 5, Antecedente::class => 8, RegistroAtencion::class => 6,
        ParametroLaboratorio::class => 4, RangoReferencia::class => 10, ExamenLaboratorio::class => 5,
        ResultadoLaboratorio::class => 8, Bitacora::class => 6,
    ];

    public function test_existen_las_22_tablas_y_los_triggers(): void
    {
        $tablas = DB::scalar("SELECT count(*) FROM information_schema.tables
                              WHERE table_schema = 'public' AND table_type = 'BASE TABLE' AND table_name <> 'migrations'");
        $triggers = DB::scalar('SELECT count(*) FROM pg_trigger WHERE NOT tgisinternal');

        $this->assertSame(22, $tablas);
        $this->assertSame(7, $triggers);
    }

    public function test_cada_modelo_lee_su_tabla_sin_timestamps(): void
    {
        foreach (self::POBLACION as $modelo => $cantidad) {
            $this->assertFalse((new $modelo)->timestamps, "$modelo debe tener \$timestamps = false");
            $this->assertSame($cantidad, $modelo::count(), "Registros de $modelo");
        }
        // las notificaciones dependen del día en que se ejecuta la población
        $this->assertGreaterThan(0, Notificacion::count());
        $this->assertFalse((new Notificacion)->timestamps);
    }

    public function test_las_relaciones_cargan(): void
    {
        $cita = Cita::with(['paciente', 'medico.especialidades', 'especialidad', 'usuarioRegistro', 'historial', 'pago', 'registroAtencion'])
            ->where('codigo', 'CM-0001')->first();

        $this->assertSame('1234567', $cita->paciente->ci);
        $this->assertSame('Pérez Rojas', $cita->medico->apellidos);
        $this->assertSame('Medicina general', $cita->especialidad->nombre);
        $this->assertCount(2, $cita->historial);
        $this->assertNotNull($cita->pago);
        $this->assertNotNull($cita->registroAtencion);

        $examen = ExamenLaboratorio::with('resultados.parametro.rangos')->where('codigo', 'EX-00001')->first();
        $this->assertCount(2, $examen->resultados);
        $this->assertSame('Bajo', $examen->resultados->firstWhere('parametro.nombre', 'Hemoglobina')->interpretacion);

        $paciente = Paciente::with(['usuario.rol', 'historia.antecedentes', 'seguros'])->where('ci', '4567890')->first();
        $this->assertSame(Rol::PACIENTE, $paciente->usuario->rol->nombre);
        $this->assertCount(2, $paciente->historia->antecedentes);
    }

    public function test_usuarios_de_pacientes_usan_su_ci(): void
    {
        $this->assertSame(['1234567', '4567890'],
            Usuario::whereNotNull('paciente_id')->orderBy('nombre_usuario')->pluck('nombre_usuario')->all());
    }

    public function test_usuario_busca_por_login_y_verifica_clave(): void
    {
        $this->assertSame('admin', Usuario::buscarPorLogin('  ADMIN ')->nombre_usuario);
        $this->assertSame('recep1', Usuario::buscarPorLogin('Recep1@VidaSalud.com')->nombre_usuario);
        $this->assertNull(Usuario::buscarPorLogin('nadie'));

        $admin = Usuario::buscarPorLogin('admin');
        $this->assertTrue($admin->verificarClave('Clinica#2026'));
        $this->assertFalse($admin->verificarClave('otra'));

        $admin->actualizarClave('Nueva#Clave1');
        $this->assertTrue($admin->fresh()->verificarClave('Nueva#Clave1'));
        $this->assertFalse($admin->fresh()->debe_cambiar_clave);
    }

    public function test_bloqueo_progresivo_5_15_60_y_reinicio(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');

        foreach ([5, 15, 60, 60] as $minutosEsperados) {
            for ($i = 1; $i < Usuario::MAX_INTENTOS; $i++) {
                $this->assertNull($usuario->registrarIntentoFallido());
            }
            $this->assertSame($minutosEsperados, $usuario->registrarIntentoFallido());
            $this->assertTrue($usuario->fresh()->bloqueado_hasta->isFuture());
        }
        $this->assertSame(3, $usuario->fresh()->nivel_bloqueo);

        $usuario->reiniciarIntentos();
        $usuario->refresh();
        $this->assertSame([0, 0, null], [$usuario->intentos_fallidos, $usuario->nivel_bloqueo, $usuario->bloqueado_hasta]);
    }

    public function test_usuario_temporal_y_estados(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');
        $usuario->marcarTemporal('Temporal#2026');
        $this->assertTrue($usuario->fresh()->debe_cambiar_clave);

        $usuario->actualizarEstado('desactivar');
        $this->assertFalse($usuario->fresh()->estado);
        $usuario->actualizarEstado('activar');
        $this->assertTrue($usuario->fresh()->estado);
    }

    public function test_rol_obtener(): void
    {
        $this->assertSame(Rol::PACIENTE, Rol::obtener(Rol::PACIENTE)->nombre);
        $this->assertSame(Rol::ADMINISTRADOR, Rol::obtener(1)->nombre);
        $this->assertCount(4, Rol::obtener());
    }

    public function test_especialidad_nombre_normalizado_y_edad(): void
    {
        $this->assertTrue(Especialidad::existeNombre('  MEDICINA    General '));
        $this->assertFalse(Especialidad::existeNombre('Neurología'));

        $paraNino = Especialidad::listarPorEdad(10)->pluck('nombre');
        $this->assertContains('Pediatría', $paraNino);
        $this->assertNotContains('Medicina general', $paraNino);
        $this->assertNotContains('Geriatría', $paraNino);
        $this->assertContains('Odontología', $paraNino);

        $this->assertContains('Geriatría', Especialidad::listarPorEdad(70)->pluck('nombre'));
    }

    public function test_medico_crear_buscar_y_asignar_especialidades(): void
    {
        $medico = Medico::crear([
            'ci' => '5009001', 'nro_registro_prof' => 'RP-2001', 'nombres' => 'Óscar', 'apellidos' => 'Núñez',
        ]);
        $this->assertSame('M-0007', $medico->codigo);
        $this->assertTrue(Medico::existeCI('5009001'));
        $this->assertFalse(Medico::existeCI('5009001', $medico->id));

        $ids = Especialidad::whereIn('nombre', ['Cardiología', 'Odontología'])->pluck('id')->all();
        Atiende::asignar($medico, $ids);
        $this->assertCount(2, $medico->especialidades()->get());
        Atiende::asignar($medico, [$ids[0]]);
        $this->assertCount(1, $medico->especialidades()->get());

        $this->assertSame('RP-1001', Medico::buscar('perez')->first()->nro_registro_prof);
        $this->assertTrue(Medico::buscar('pediatria')->contains('nro_registro_prof', 'RP-1002'));

        $general = Especialidad::where('nombre', 'Medicina general')->first();
        $this->assertEqualsCanonicalizing(['RP-1001', 'RP-1005'],
            Medico::listarPorEspecialidad($general->id)->pluck('nro_registro_prof')->all());
    }

    public function test_horario_franjas_y_superposicion(): void
    {
        $perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $lunes = Horario::where('medico_id', $perez->id)->where('dia_semana', 1)->first();

        $franjas = $lunes->generarFranjas();
        $this->assertCount(8, $franjas);
        $this->assertSame(['inicio' => '08:00', 'fin' => '08:30'], $franjas[0]);
        $this->assertSame(['inicio' => '11:30', 'fin' => '12:00'], $franjas[7]);

        $this->assertTrue(Horario::seSuperpone($perez->id, 1, '11:00', '13:00'));
        $this->assertFalse(Horario::seSuperpone($perez->id, 1, '12:00', '14:00'));
        $this->assertCount(3, Horario::listar($perez->id));
        $this->assertCount(1, Horario::obtenerDelDia($perez->id, Carbon::parse('next monday')));
    }

    public function test_paciente_busqueda_sin_tildes_y_por_ci(): void
    {
        $this->assertSame('1234567', Paciente::buscar('LOPEZ')->first()->ci);
        $this->assertSame('7654321', Paciente::buscar('gomez aÑez')->first()->ci);
        $this->assertCount(1, Paciente::buscar('765432'));
        $this->assertNotNull(Paciente::buscarPorCI('1234567')->usuario);

        $nuevo = Paciente::crear([
            'ci' => '9998887', 'nombres' => 'Ana', 'apellidos' => 'Rivas', 'fecha_nacimiento' => '2000-01-01', 'telefono' => '70000000',
        ]);
        $this->assertSame('P-0011', $nuevo->codigo);
        $nuevo->desactivar();
        $this->assertFalse($nuevo->fresh()->estado);
    }

    public function test_historia_se_crea_si_no_existe_y_antecedentes(): void
    {
        $paciente = Paciente::where('ci', '9012345')->first();
        $this->assertNull($paciente->historia);

        $historia = Historia::obtener($paciente->id);
        $this->assertSame($historia->id, Historia::obtener($paciente->id)->id);

        $usuario = Usuario::buscarPorLogin('admin');
        Antecedente::crear([
            'historia_clinica_id' => $historia->id, 'tipo' => 'Alergia', 'descripcion' => 'Polen',
            'origen' => Antecedente::ORIGEN_PACIENTE, 'usuario_id' => $usuario->id,
        ]);
        $this->assertCount(1, Antecedente::listar($historia->id));
    }

    public function test_seguro_activo_y_baja(): void
    {
        $maria = Paciente::where('ci', '1234567')->first();
        $seguro = Seguro::obtenerActivo($maria->id);
        $this->assertSame('AA-2026-0154', $seguro->nro_poliza);

        $seguro->darDeBaja();
        $this->assertNull(Seguro::obtenerActivo($maria->id));
    }

    public function test_bloqueo_y_citas_ocupadas_sin_datos_de_pacientes(): void
    {
        $perez = Medico::where('nro_registro_prof', 'RP-1001')->first();
        $todosSantos = Carbon::create(now()->year, 11, 2);
        $this->assertSame('Día de Todos Santos', Bloqueo::estaBloqueada($perez->id, $todosSantos)->motivo);
        $this->assertNull(Bloqueo::estaBloqueada($perez->id, Carbon::create(now()->year, 11, 3)));

        $cita = Cita::where('codigo', 'CM-0009')->first();
        $ocupadas = Cita::listarOcupadas($perez->id, $cita->fecha);
        $this->assertCount(1, $ocupadas);
        $this->assertSame(['hora_inicio', 'hora_fin'], array_keys($ocupadas->first()->getAttributes()));
    }

    public function test_recuperacion_enlace_de_un_solo_uso(): void
    {
        $usuario = Usuario::buscarPorLogin('recep1');
        $this->assertSame(0, Recuperacion::contarRecientes($usuario->id));

        $token = Recuperacion::crear($usuario->id, '127.0.0.1');
        $this->assertSame(64, strlen($token));
        $this->assertSame(1, Recuperacion::contarRecientes($usuario->id));
        $this->assertDatabaseMissing('recuperacion_clave', ['token_hash' => $token]);

        $solicitud = Recuperacion::validar($token);
        $this->assertSame($usuario->id, $solicitud->usuario->id);
        $solicitud->marcarUsado();
        $this->assertNull(Recuperacion::validar($token));
        $this->assertNull(Recuperacion::validar('alterado'));
    }

    public function test_bitacora_registrar_y_buscar(): void
    {
        $admin = Usuario::buscarPorLogin('admin');
        Bitacora::registrar([
            'usuario_id' => $admin->id, 'tipo' => 'Accion', 'accion' => 'PRUEBA', 'modulo' => 'Pruebas',
            'descripcion' => str_repeat('x', 400),
        ]);

        $this->assertSame(7, Bitacora::listar()->total());
        $resultado = Bitacora::buscar(['modulo' => 'Pruebas', 'desde' => today()->toDateString(), 'hasta' => today()->toDateString()])->get();
        $this->assertCount(1, $resultado);
        $this->assertLessThanOrEqual(300, mb_strlen($resultado->first()->descripcion));
        $this->assertSame('admin', Usuario::obtener($admin->id)->nombre_usuario);
    }
}
