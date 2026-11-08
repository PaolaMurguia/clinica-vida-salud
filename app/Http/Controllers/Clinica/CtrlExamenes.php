<?php

namespace App\Http\Controllers\Clinica;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinica\RegistrarResultadosRequest;
use App\Http\Requests\Clinica\StoreExamenRequest;
use App\Models\Cita;
use App\Models\ExamenLaboratorio;
use App\Models\Notificacion;
use App\Models\ParametroLaboratorio;
use App\Models\Paciente;
use App\Models\RangoReferencia;
use App\Models\ResultadoLaboratorio;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlExamenes: CU22 – Gestionar resultados de laboratorio (Recepcionista, Médico y Paciente).
 * Solo el médico solicita y revisa; la recepcionista registra los resultados (RN20).
 */
class CtrlExamenes extends Controller
{
    /**
     * CU22 – Gestionar resultados de laboratorio (buscar): la recepcionista busca por código o paciente
     * (sin criterio, los pendientes de registrar); el médico ve sus solicitudes; el paciente, sus resultados.
     */
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $criterio = trim((string) $request->query('q', ''));

        $examenes = match (true) {
            $usuario->paciente_id !== null => ExamenLaboratorio::listarPorPaciente($usuario->paciente_id, true),
            $usuario->medico_id !== null   => ExamenLaboratorio::with(['paciente', 'medico'])->where('medico_id', $usuario->medico_id)
                ->orderByRaw("CASE estado WHEN 'Registrado' THEN 0 WHEN 'Solicitado' THEN 1 ELSE 2 END")
                ->orderByDesc('fecha_solicitud')->get(),
            default                        => ExamenLaboratorio::buscar($criterio, $criterio === '' ? 'Solicitado' : null),
        };

        return view('examenes', ['modo' => 'lista', 'examenes' => $examenes, 'criterio' => $criterio]);
    }

    /**
     * CU22 – Gestionar resultados de laboratorio (pasos 1 y 2, solicitar): el médico solicita el análisis desde la
     * historia clínica; se genera el código EX-00001 en estado Solicitado y se registra en la bitácora.
     */
    public function store(StoreExamenRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $paciente = Paciente::obtener((int) $datos['paciente_id']);
        $examen = ExamenLaboratorio::crear($datos + ['medico_id' => $request->user()->medico_id]);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'SOLICITAR', 'Laboratorio', $examen->id,
            "Solicitud del examen {$examen->codigo} ({$examen->tipo_examen}) para el paciente {$paciente->codigo}");

        return redirect()->route('historiaClinica.show', $paciente)->with('exito', "Examen {$examen->codigo} solicitado.");
    }

    /**
     * CU22 – Gestionar resultados de laboratorio (pasos 3 a 7, registrarResultados): guarda la fecha de la muestra
     * y cada valor con el rango de referencia de la edad del paciente (RN16) y su interpretación; el examen pasa a
     * Registrado y se notifica al médico solicitante y al paciente (RN20, RF21).
     */
    public function registrarResultados(RegistrarResultadosRequest $request, ExamenLaboratorio $examen): RedirectResponse
    {
        $examen = ExamenLaboratorio::obtener($examen->id);
        $fecha = Carbon::createFromFormat('Y-m-d', $request->validated('fecha_muestra'))->startOfDay();
        $edad = (int) $examen->paciente->fecha_nacimiento->diffInYears($fecha);
        $valores = $request->validated('valores');

        DB::transaction(function () use ($examen, $fecha, $edad, $valores, $request) {
            if ($examen->estado === 'Solicitado') {
                $examen->registrarMuestra($fecha, $request->user()->id);
            }
            foreach ($valores as $nombre => $valor) {
                $parametro = ParametroLaboratorio::obtenerPorNombre((string) $nombre);
                ResultadoLaboratorio::registrar($examen->id, $parametro->id, (float) $valor,
                    RangoReferencia::obtenerPorEdad($parametro->id, $edad));
            }

            // paso 7: avisos al médico solicitante y al paciente (D3)
            $mensaje = "Los resultados del examen {$examen->codigo} ({$examen->tipo_examen}) de {$examen->paciente->nombres} {$examen->paciente->apellidos} están disponibles.";
            foreach ([$examen->medico->usuario, $examen->paciente->usuario] as $destinatario) {
                if ($destinatario) {
                    Notificacion::programar($destinatario, 'Resultado', $mensaje, $examen->cita_id ? Cita::obtener($examen->cita_id) : null);
                }
            }
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'REGISTRAR_RESULTADOS', 'Laboratorio', $examen->id,
            'Resultados del examen ' . $examen->codigo . ': ' . implode(', ', array_keys($valores)));

        return redirect()->route('examenes.show', $examen)->with('exito', 'Resultados registrados.');
    }

    /**
     * CU22 – Gestionar resultados de laboratorio (pasos 8 y 9, verResultados): resultados con el rango aplicado y su
     * interpretación. El paciente ve solo los suyos con resultados; el médico, los de pacientes que atiende (RN18).
     */
    public function show(Request $request, ExamenLaboratorio $examen): View
    {
        $examen = ExamenLaboratorio::obtener($examen->id);
        $usuario = $request->user();
        if ($usuario->paciente_id !== null) {
            abort_if($examen->paciente_id !== $usuario->paciente_id || $examen->estado === 'Solicitado', 403);
        } elseif ($usuario->medico_id !== null) {
            abort_unless($examen->medico_id === $usuario->medico_id || Cita::where('paciente_id', $examen->paciente_id)
                ->where('medico_id', $usuario->medico_id)->where('estado', '<>', 'Cancelada')->exists(), 403);
        }

        $resultados = ResultadoLaboratorio::listarPorExamen($examen->id);

        return view('examenes', [
            'modo'       => 'detalle',
            'examen'     => $examen,
            'resultados' => $resultados,
            // formulario de la recepción: parámetros activos aún no registrados en el examen
            'parametros' => $usuario->rol->nombre === 'Recepcionista' && $examen->estado !== 'Revisado'
                ? ParametroLaboratorio::listar()->reject(fn ($parametro) => $resultados->contains('parametro_id', $parametro->id))->values()
                : collect(),
        ]);
    }

    /**
     * CU22 – Gestionar resultados de laboratorio (paso 8, marcarRevisado): el médico solicitante revisa los
     * resultados; un examen Revisado ya no se modifica.
     */
    public function marcarRevisado(Request $request, ExamenLaboratorio $examen): RedirectResponse
    {
        if ($examen->medico_id !== $request->user()->medico_id) {
            return back()->with('error', 'Solo el médico que solicitó el examen lo marca como revisado.');
        }
        if ($examen->estado !== 'Registrado') {
            return back()->with('error', $examen->estado === 'Revisado'
                ? 'El examen ya está Revisado.' : 'El examen aún no tiene resultados registrados.');
        }

        $examen->marcarRevisado();
        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'REVISAR', 'Laboratorio', $examen->id, "Examen {$examen->codigo} revisado");

        return redirect()->route('examenes.show', $examen)->with('exito', "Examen {$examen->codigo} marcado como revisado.");
    }
}
