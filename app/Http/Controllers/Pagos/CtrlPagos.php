<?php

namespace App\Http\Controllers\Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pagos\CreateRequest;
use App\Http\Requests\Pagos\StoreRequest;
use App\Http\Requests\Pagos\UpdateRequest;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Seguro;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlPagos: CU25 – Registrar pago de consulta (Recepcionista).
 */
class CtrlPagos extends Controller
{
    /**
     * CU25 – Registrar pago de consulta (pasos 1 a 3, mostrar): datos de la cita, costo de la consulta y
     * cobertura del seguro vigente (RN19). Sin cita elegida, lista los pagos pendientes y el buscador por código.
     */
    public function create(CreateRequest $request): View|RedirectResponse
    {
        $datos = $request->validated();
        $citaId = ! empty($datos['codigo'])
            ? Cita::where('codigo', $datos['codigo'])->value('id')
            : ($datos['cita'] ?? null);

        if (! $citaId) {
            return view('pagos', ['modo' => 'lista', 'pendientes' => Pago::listarPendientes()]);
        }

        $cita = Cita::obtener((int) $citaId);

        // Excepción 2: la cita ya tiene un pago; se muestra el existente
        $existente = Pago::obtenerPorCita($cita->id);
        if ($existente) {
            return redirect()->route('pagos.show', $existente)->with('aviso', 'La cita ya tiene un pago registrado.');
        }

        $costo = Especialidad::obtenerCosto($cita->especialidad_id);
        $vigente = Seguro::obtenerVigente($cita->paciente_id, $cita->fecha);
        $porcentaje = $vigente ? (float) $vigente->porcentaje_cobertura : 0.0;
        $aPagar = round((float) $costo * (100 - $porcentaje) / 100, 2);

        return view('pagos', [
            'modo'       => 'cobro',
            'cita'       => $cita,
            'costo'      => (float) $costo,
            'vigente'    => $vigente,
            // Excepción 3: el seguro existe pero no está vigente en la fecha de la cita
            'noVigente'  => $vigente ? null : Seguro::obtenerActivo($cita->paciente_id),
            'porcentaje' => $porcentaje,
            'cubierto'   => round((float) $costo - $aPagar, 2),
            'aPagar'     => $aPagar,
        ]);
    }

    /**
     * CU25 – Registrar pago de consulta (pasos 4 a 9, cobrar y dejarPendiente): guarda el pago con el
     * comprobante correlativo, el estado y los montos (la cobertura la aplica la base de datos) y lo registra
     * en la bitácora.
     */
    public function store(StoreRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $cita = Cita::obtener((int) $datos['cita_id']);
        $cobrar = $datos['accion'] === 'cobrar';
        $vigente = Seguro::obtenerVigente($cita->paciente_id, $cita->fecha);

        $pago = DB::transaction(fn () => Pago::crear([
            'cita_id'        => $cita->id,
            'seguro_id'      => $vigente?->id,
            'usuario_id'     => $request->user()->id,
            'monto_consulta' => Especialidad::obtenerCosto($cita->especialidad_id),
            'metodo_pago'    => $cobrar ? $datos['metodo_pago'] : null,
            'estado'         => $cobrar ? 'Pagado' : 'Pendiente',
            'fecha_pago'     => $cobrar ? now() : null,
        ]));

        ServicioBitacora::registrar(ServicioBitacora::ACCION, $cobrar ? 'COBRAR' : 'PAGO_PENDIENTE', 'Pagos', $pago->id,
            "Pago {$pago->nro_comprobante} de la cita {$cita->codigo}: Bs {$pago->monto_paciente} ({$pago->estado})");

        return redirect()->route('pagos.show', $pago)
            ->with('exito', $cobrar ? "Pago {$pago->nro_comprobante} registrado." : "Pago {$pago->nro_comprobante} registrado como pendiente.");
    }

    /**
     * CU25 – Registrar pago de consulta (paso 8, completar): completa un pago pendiente con el método de pago.
     */
    public function update(UpdateRequest $request, Pago $pago): RedirectResponse
    {
        $pago->completar($request->validated('metodo_pago'));

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'COMPLETAR_PAGO', 'Pagos', $pago->id,
            "Pago {$pago->nro_comprobante} completado ({$pago->metodo_pago})");

        return redirect()->route('pagos.show', $pago)->with('exito', "Pago {$pago->nro_comprobante} completado.");
    }

    /**
     * CU25 – Registrar pago de consulta (paso 7): comprobante para pantalla e impresión.
     */
    public function show(Pago $pago): View
    {
        return view('pagos', [
            'modo' => 'comprobante',
            'pago' => $pago->load(['cita.paciente', 'cita.medico', 'cita.especialidad', 'seguro', 'usuario']),
        ]);
    }
}
