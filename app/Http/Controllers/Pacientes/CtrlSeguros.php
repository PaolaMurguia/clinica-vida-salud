<?php

namespace App\Http\Controllers\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\StoreSeguroRequest;
use App\Models\Paciente;
use App\Models\Seguro;
use App\Services\ServicioBitacora;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlSeguros: CU12 – Registrar seguro del paciente (Recepcionista).
 */
class CtrlSeguros extends Controller
{
    /**
     * CU12 – Registrar seguro del paciente (pasos 1 y 2): muestra el seguro activo del paciente
     * y los seguros anteriores.
     */
    public function index(Paciente $paciente): View
    {
        $paciente = Paciente::obtener($paciente->id);

        return view('seguro', [
            'paciente'   => $paciente,
            'activo'     => Seguro::obtenerActivo($paciente->id),
            'anteriores' => $paciente->seguros()->where('estado', false)->orderByDesc('fecha_fin')->get(),
        ]);
    }

    /**
     * CU12 – Registrar seguro del paciente (pasos 3 a 6): si hay un seguro activo lo da de baja
     * y guarda el nuevo, que queda como único seguro activo.
     */
    public function store(StoreSeguroRequest $request, Paciente $paciente): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $seguro = DB::transaction(function () use ($paciente, $datos) {
                Seguro::obtenerActivo($paciente->id)?->darDeBaja();
                return Seguro::crear($datos + ['paciente_id' => $paciente->id, 'estado' => true]);
            });
        } catch (QueryException $e) {
            // uq_seguro_poliza y ux_seguro_activo respaldan la validación (RNF06)
            report($e);
            return back()->withInput()->with('error', 'No se pudo registrar el seguro: la póliza ya existe en esa aseguradora.');
        }

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Seguros', $seguro->id,
            "Seguro {$seguro->aseguradora} póliza {$seguro->nro_poliza} del paciente {$paciente->codigo}");

        $respuesta = redirect()->route('seguros.index', $paciente)->with('exito', 'Seguro registrado como seguro activo del paciente.');

        // CU12, excepción 4: un seguro vencido se guarda, pero se informa
        return Carbon::parse($datos['fecha_fin'])->isBefore(today())
            ? $respuesta->with('aviso', 'El seguro registrado ya está vencido: no se aplicará a las consultas (RN19).')
            : $respuesta;
    }

    /**
     * CU12 – Registrar seguro del paciente (darDeBaja): da de baja el seguro activo; queda en el historial.
     */
    public function destroy(Paciente $paciente, Seguro $seguro): RedirectResponse
    {
        $seguro->darDeBaja();

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'DAR_DE_BAJA', 'Seguros', $seguro->id,
            "Baja del seguro {$seguro->aseguradora} póliza {$seguro->nro_poliza} del paciente {$paciente->codigo}");

        return redirect()->route('seguros.index', $paciente)->with('exito', 'Seguro dado de baja.');
    }
}
