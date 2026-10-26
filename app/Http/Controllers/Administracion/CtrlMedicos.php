<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\BuscarMedicoRequest;
use App\Http\Requests\Administracion\CambiarEstadoRequest;
use App\Http\Requests\Administracion\StoreMedicoRequest;
use App\Http\Requests\Administracion\UpdateMedicoRequest;
use App\Models\Atiende;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlMedicos: CU06 – Gestionar médicos.
 */
class CtrlMedicos extends Controller
{
    /**
     * CU06 – Gestionar médicos (paso 1): lista los médicos con sus especialidades.
     * Con ?editar={id} carga el médico en el formulario integrado (B1).
     */
    public function index(Request $request): View
    {
        return view('medicos', [
            'medicos'        => Medico::listar(),
            'especialidades' => Especialidad::listar(),
            'editado'        => $request->filled('editar') ? Medico::obtener((int) $request->query('editar')) : null,
            'criterio'       => null,
        ]);
    }

    /**
     * CU06 – Gestionar médicos (pasos 2 a 5): registra al médico con sus especialidades;
     * la base de datos genera el código M-0001.
     */
    public function store(StoreMedicoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $medico = DB::transaction(function () use ($datos) {
            $medico = Medico::crear(Arr::except($datos, 'especialidades'));
            Atiende::asignar($medico, $datos['especialidades']);
            return $medico;
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Médicos', $medico->id,
            "Registro del médico {$medico->codigo} ({$medico->nombres} {$medico->apellidos})");

        return redirect()->route('medicos.index')->with('exito', "Médico {$medico->codigo} registrado.");
    }

    /**
     * CU06 – Gestionar médicos (pasos 2 a 4): actualiza los datos y sincroniza las especialidades (B5).
     */
    public function update(UpdateMedicoRequest $request, Medico $medico): RedirectResponse
    {
        $datos = $request->validated();

        DB::transaction(function () use ($medico, $datos) {
            $medico->actualizar(Arr::except($datos, 'especialidades'));
            Atiende::asignar($medico, $datos['especialidades']);
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'MODIFICAR', 'Médicos', $medico->id,
            "Modificación del médico {$medico->codigo}");

        return redirect()->route('medicos.index')->with('exito', "Médico {$medico->codigo} actualizado.");
    }

    /**
     * CU06 – Gestionar médicos (paso 6): un médico inactivo no recibe citas nuevas y conserva
     * su historial (RN04, RN11); también se puede reactivar (B6).
     */
    public function cambiarEstado(CambiarEstadoRequest $request, Medico $medico): RedirectResponse
    {
        $activar = $request->validated('accion') === 'activar';
        $medico->cambiarEstado($activar);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, $activar ? 'ACTIVAR' : 'DESACTIVAR', 'Médicos', $medico->id,
            ($activar ? 'Activación' : 'Desactivación') . " del médico {$medico->codigo}");

        return redirect()->route('medicos.index')->with('exito', "Médico {$medico->codigo} " . ($activar ? 'activado.' : 'desactivado.'));
    }

    /**
     * CU06 – Gestionar médicos (paso 1): busca por nombre, CI o especialidad.
     */
    public function buscar(BuscarMedicoRequest $request): View
    {
        $criterio = $request->validated('criterio');

        return view('medicos', [
            'medicos'        => Medico::buscar($criterio),
            'especialidades' => Especialidad::listar(),
            'editado'        => null,
            'criterio'       => $criterio,
        ]);
    }
}
