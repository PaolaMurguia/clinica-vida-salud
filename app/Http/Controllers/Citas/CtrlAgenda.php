<?php

namespace App\Http\Controllers\Citas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Citas\FiltrarAgendaRequest;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Medico;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Control CtrlAgenda: CU18 – Consultar agenda médica (Recepcionista, Médico y Administrador).
 * Solo lectura; desde cada cita se registra la asistencia (CU17) o se abre la historia clínica (CU20).
 */
class CtrlAgenda extends Controller
{
    /**
     * CU18 – Consultar agenda médica (pasos 1 y 2, mostrar): citas de hoy. El médico ve su propia agenda;
     * la recepción y el administrador eligen el médico.
     */
    public function index(FiltrarAgendaRequest $request): View
    {
        return $this->filtrar($request);
    }

    /**
     * CU18 – Consultar agenda médica (pasos 2 a 4, filtrar): citas del médico en el día o la semana,
     * ordenadas por fecha y hora, con filtro de estado y las fechas bloqueadas con su motivo.
     */
    public function filtrar(FiltrarAgendaRequest $request): View
    {
        $filtros = $request->validated();
        $desde = Carbon::parse($filtros['desde'] ?? today())->startOfDay();
        $vista = $filtros['vista'] ?? 'dia';
        if ($vista === 'semana') {
            $desde->startOfWeek(Carbon::MONDAY);
        }
        $hasta = match (true) {
            ! empty($filtros['hasta']) => Carbon::parse($filtros['hasta'])->startOfDay(),
            $vista === 'semana'        => $desde->copy()->addDays(6),
            default                    => $desde->copy(),
        };

        $medico = ! empty($filtros['medico_id']) ? Medico::obtener((int) $filtros['medico_id']) : null;

        return view('agenda', [
            'medicos'  => $request->user()->medico_id === null ? Medico::listar() : collect(),
            'medico'   => $medico,
            'desde'    => $desde,
            'hasta'    => $hasta,
            'vista'    => $vista,
            'estado'   => $filtros['estado'] ?? null,
            'citas'    => $medico ? Cita::listarAgenda($medico->id, $desde, $hasta, $filtros['estado'] ?? null) : collect(),
            'bloqueos' => $medico ? Bloqueo::listarPorRango($medico->id, $desde, $hasta) : collect(),
        ]);
    }
}
