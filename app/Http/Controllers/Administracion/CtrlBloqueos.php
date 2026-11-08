<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\FiltrarBloqueosRequest;
use App\Http\Requests\Administracion\StoreBloqueoRequest;
use App\Models\Bloqueo;
use App\Models\Cita;
use App\Models\Medico;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Control CtrlBloqueos: CU24 – Gestionar feriados y bloqueos (Administrador).
 * Los bloqueos solo se registran y se eliminan; no se editan (D10).
 */
class CtrlBloqueos extends Controller
{
    /**
     * CU24 – Gestionar feriados y bloqueos (paso 1, mostrar): lista de bloqueos (fecha, ámbito y motivo)
     * con filtros por médico y por rango de fechas, y el formulario «Nuevo bloqueo».
     */
    public function index(FiltrarBloqueosRequest $request): View
    {
        $filtros = $request->validated();

        return view('bloqueos', [
            'bloqueos' => Bloqueo::listar($filtros),
            'medicos'  => Medico::listar(),
            'filtros'  => $filtros,
        ]);
    }

    /**
     * CU24 – Gestionar feriados y bloqueos (pasos 2 a 6 y 8, agregar): si hay citas no canceladas en la fecha,
     * las muestra y pide confirmar (no se cancelan solas); luego guarda el bloqueo y lo registra en la bitácora.
     */
    public function store(StoreBloqueoRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();
        $medicoId = $datos['medico_id'] ?? null;

        // Paso 5: citas existentes en la fecha y el ámbito
        $citas = Cita::listarPorFecha($fecha, $medicoId);
        if ($citas->isNotEmpty() && empty($datos['confirmar'])) {
            return back()->withInput()->with('citasEnFecha', $citas->map(fn (Cita $cita) => [
                'codigo'   => $cita->codigo,
                'hora'     => substr($cita->hora_inicio, 0, 5),
                'paciente' => "{$cita->paciente->apellidos}, {$cita->paciente->nombres}",
                'medico'   => "{$cita->medico->apellidos}, {$cita->medico->nombres}",
                'estado'   => $cita->estado,
            ])->all());
        }

        $bloqueo = Bloqueo::crear(['medico_id' => $medicoId, 'fecha' => $fecha->toDateString(), 'motivo' => $datos['motivo']]);
        $ambito = $medicoId ? 'del médico ' . Medico::obtener($medicoId)->codigo : 'de toda la clínica';

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Bloqueos', $bloqueo->id,
            "Bloqueo $ambito el {$fecha->format('d/m/Y')}: {$bloqueo->motivo}" . ($citas->isNotEmpty() ? " ({$citas->count()} cita(s) existentes)" : ''));

        $respuesta = redirect()->route('bloqueos.index')->with('exito', "Fecha {$fecha->format('d/m/Y')} bloqueada $ambito.");

        return $citas->isNotEmpty()
            ? $respuesta->with('aviso', "Hay {$citas->count()} cita(s) en esa fecha: la recepción debe reprogramarlas o cancelarlas (CU15, CU16).")
            : $respuesta;
    }

    /**
     * CU24 – Gestionar feriados y bloqueos (pasos 7 y 8, eliminar): libera la fecha, que vuelve a la
     * disponibilidad, y lo registra en la bitácora.
     */
    public function destroy(Bloqueo $bloqueo): RedirectResponse
    {
        $descripcion = 'Bloqueo ' . ($bloqueo->medico_id ? 'del médico ' . $bloqueo->medico->codigo : 'de toda la clínica')
            . " el {$bloqueo->fecha->format('d/m/Y')}: {$bloqueo->motivo}";
        $bloqueo->eliminar();

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'ELIMINAR', 'Bloqueos', $bloqueo->id, $descripcion);

        return redirect()->route('bloqueos.index')->with('exito', 'Bloqueo eliminado: la fecha vuelve a estar disponible.');
    }
}
