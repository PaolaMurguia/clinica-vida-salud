<?php

namespace App\Http\Controllers\Citas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Citas\FiltrarHistorialCitasRequest;
use App\Models\Cita;
use App\Models\HistorialCita;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Control CtrlHistorialCitas: CU19 – Consultar historial de citas (Recepcionista, Administrador y Paciente).
 * Solo lectura; no muestra diagnósticos ni datos clínicos (RN18).
 */
class CtrlHistorialCitas extends Controller
{
    /**
     * CU19 – Consultar historial de citas (pasos 1 a 3, mostrar y filtrar): citas del paciente de la más
     * reciente a la más antigua, con filtros de estado y rango de fechas.
     */
    public function index(FiltrarHistorialCitasRequest $request): View
    {
        $filtros = $request->validated();
        $paciente = Paciente::obtener((int) $filtros['paciente_id']);

        return view('historialCitas', [
            'paciente' => $paciente,
            'citas'    => Cita::listarPorPaciente($paciente->id, $filtros),
            'filtros'  => $filtros,
            'cita'     => null,
        ]);
    }

    /**
     * CU19 – Consultar historial de citas (paso 4, verDetalle): historial de estados de la cita (quién hizo
     * cada cambio y cuándo) y el motivo de cancelación; ofrece reprogramar o cancelar según el rol y RN17.
     */
    public function show(Request $request, Cita $cita): View
    {
        $cita = Cita::obtener($cita->id);
        // RN18: el paciente solo ve sus propias citas
        abort_if($request->user()->paciente_id !== null && $cita->paciente_id !== $request->user()->paciente_id, 403);

        return view('historialCitas', [
            'paciente'  => Paciente::obtener($cita->paciente_id),
            'cita'      => $cita,
            'historial' => HistorialCita::listarPorCita($cita->id),
        ]);
    }
}
