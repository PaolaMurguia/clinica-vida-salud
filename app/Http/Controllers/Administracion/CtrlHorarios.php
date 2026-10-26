<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\CambiarEstadoRequest;
use App\Http\Requests\Administracion\StoreHorarioRequest;
use App\Models\Horario;
use App\Models\Medico;
use App\Services\ServicioBitacora;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Control CtrlHorarios: CU07 – Definir horarios de atención. En el ciclo 1 no se editan horarios:
 * se desactiva el anterior y se agrega otro (B3).
 */
class CtrlHorarios extends Controller
{
    /**
     * CU07 – Definir horarios de atención (paso 1): con ?medico_id={id} muestra los horarios
     * actuales del médico y sus franjas.
     */
    public function index(Request $request): View
    {
        $medico = $request->filled('medico_id') ? Medico::obtener((int) $request->query('medico_id')) : null;

        return view('horarios', [
            'medicos'  => Medico::listar(),
            'medico'   => $medico,
            'horarios' => $medico ? Horario::listar($medico->id) : collect(),
        ]);
    }

    /**
     * CU07 – Definir horarios de atención (pasos 2 a 4): registra el horario y muestra las franjas que generará.
     */
    public function store(StoreHorarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $horario = Horario::crear($datos);
        } catch (QueryException $e) {
            // las restricciones CHECK y EXCLUDE de la base de datos respaldan las validaciones (RNF06)
            report($e);
            return back()->withInput()->with('error', 'No se pudo registrar el horario: se superpone con otro o no forma franjas completas.');
        }

        $franjas = $horario->generarFranjas();
        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Horarios', $horario->id,
            'Horario ' . Horario::DIAS[$horario->dia_semana] . " {$datos['hora_inicio']}-{$datos['hora_fin']} del médico {$horario->medico->codigo}");

        return redirect()->route('horarios.index', ['medico_id' => $horario->medico_id])
            ->with('exito', 'Horario registrado: genera ' . count($franjas) . " franjas de {$horario->duracion_cita_min} minutos.")
            ->with('franjas', $franjas);
    }

    /**
     * CU07 – Definir horarios de atención (paso 5): desactiva el horario; también lo reactiva (B6)
     * si no se superpone con otro horario activo.
     */
    public function cambiarEstado(CambiarEstadoRequest $request, Horario $horario): RedirectResponse
    {
        $activar = $request->validated('accion') === 'activar';
        $volver = redirect()->route('horarios.index', ['medico_id' => $horario->medico_id]);

        if ($activar && Horario::seSuperpone($horario->medico_id, $horario->dia_semana, $horario->hora_inicio, $horario->hora_fin, $horario->id)) {
            return $volver->with('error', 'No se puede activar: se superpone con otro horario activo del médico ese día.');
        }

        $horario->cambiarEstado($activar);
        ServicioBitacora::registrar(ServicioBitacora::ACCION, $activar ? 'ACTIVAR' : 'DESACTIVAR', 'Horarios', $horario->id,
            ($activar ? 'Activación' : 'Desactivación') . ' del horario ' . Horario::DIAS[$horario->dia_semana]
            . ' ' . substr($horario->hora_inicio, 0, 5) . '-' . substr($horario->hora_fin, 0, 5) . " del médico {$horario->medico->codigo}");

        return $volver->with('exito', 'Horario ' . ($activar ? 'activado.' : 'desactivado.'));
    }
}
