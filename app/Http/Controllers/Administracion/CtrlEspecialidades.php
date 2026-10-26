<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\CambiarEstadoRequest;
use App\Http\Requests\Administracion\StoreEspecialidadRequest;
use App\Http\Requests\Administracion\UpdateEspecialidadRequest;
use App\Models\Especialidad;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Control CtrlEspecialidades: CU05 – Gestionar especialidades.
 */
class CtrlEspecialidades extends Controller
{
    /**
     * CU05 – Gestionar especialidades (paso 1): lista las especialidades.
     * Con ?editar={id} carga la especialidad en el formulario integrado (B1).
     */
    public function index(Request $request): View
    {
        return view('especialidades', [
            'especialidades' => Especialidad::listar(),
            'editada'        => $request->filled('editar') ? Especialidad::findOrFail((int) $request->query('editar')) : null,
        ]);
    }

    /**
     * CU05 – Gestionar especialidades (pasos 2 a 5): registra la especialidad; el trigger normaliza el nombre.
     */
    public function store(StoreEspecialidadRequest $request): RedirectResponse
    {
        $especialidad = Especialidad::crear($request->validated());

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Especialidades', $especialidad->id,
            "Registro de la especialidad {$especialidad->nombre}");

        return redirect()->route('especialidades.index')->with('exito', "Especialidad {$especialidad->nombre} registrada.");
    }

    /**
     * CU05 – Gestionar especialidades (pasos 2 a 5): actualiza nombre, descripción, costo y rango de edad.
     */
    public function update(UpdateEspecialidadRequest $request, Especialidad $especialidad): RedirectResponse
    {
        $especialidad->actualizar($request->validated());

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'MODIFICAR', 'Especialidades', $especialidad->id,
            "Modificación de la especialidad {$especialidad->nombre}");

        return redirect()->route('especialidades.index')->with('exito', "Especialidad {$especialidad->nombre} actualizada.");
    }

    /**
     * CU05 – Gestionar especialidades (paso 6): activa o desactiva la especialidad.
     */
    public function cambiarEstado(CambiarEstadoRequest $request, Especialidad $especialidad): RedirectResponse
    {
        $activar = $request->validated('accion') === 'activar';
        $especialidad->cambiarEstado($activar);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, $activar ? 'ACTIVAR' : 'DESACTIVAR', 'Especialidades', $especialidad->id,
            ($activar ? 'Activación' : 'Desactivación') . " de la especialidad {$especialidad->nombre}");

        return redirect()->route('especialidades.index')
            ->with('exito', "Especialidad {$especialidad->nombre} " . ($activar ? 'activada.' : 'desactivada.'));
    }
}
