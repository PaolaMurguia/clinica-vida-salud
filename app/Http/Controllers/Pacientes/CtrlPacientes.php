<?php

namespace App\Http\Controllers\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\StorePacienteRequest;
use App\Http\Requests\Pacientes\UpdatePacienteRequest;
use App\Models\Paciente;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Control CtrlPacientes: CU08 – Gestionar pacientes (Recepcionista y Administrador, A1).
 */
class CtrlPacientes extends Controller
{
    /**
     * CU08 – Gestionar pacientes (paso 1): lista los pacientes.
     * Con ?editar={id} carga el paciente en el formulario integrado (B1).
     */
    public function index(Request $request): View
    {
        return view('pacientes', [
            'pacientes' => Paciente::listar(),
            'editado'   => $request->filled('editar') ? Paciente::obtener((int) $request->query('editar')) : null,
        ]);
    }

    /**
     * CU08 – Gestionar pacientes (pasos 2 a 5 y 7): registra al paciente; la base de datos genera el código P-0001.
     */
    public function store(StorePacienteRequest $request): RedirectResponse
    {
        $paciente = Paciente::crear($request->validated());

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Pacientes', $paciente->id,
            "Registro del paciente {$paciente->codigo} (CI {$paciente->ci})");

        return redirect()->route('pacientes.index')->with('exito', "Paciente {$paciente->codigo} registrado.");
    }

    /**
     * CU08 – Gestionar pacientes (pasos 2 a 4 y 7): actualiza los datos del paciente.
     */
    public function update(UpdatePacienteRequest $request, Paciente $paciente): RedirectResponse
    {
        $paciente->actualizar($request->validated());

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'MODIFICAR', 'Pacientes', $paciente->id,
            "Modificación del paciente {$paciente->codigo}");

        return redirect()->route('pacientes.index')->with('exito', "Paciente {$paciente->codigo} actualizado.");
    }

    /**
     * CU08 – Gestionar pacientes (pasos 6 y 7): desactiva al paciente sin eliminar su historial (RN11).
     */
    public function destroy(Paciente $paciente): RedirectResponse
    {
        $paciente->desactivar();

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'DESACTIVAR', 'Pacientes', $paciente->id,
            "Desactivación del paciente {$paciente->codigo}");

        return redirect()->route('pacientes.index')->with('exito', "Paciente {$paciente->codigo} desactivado; su historial se conserva.");
    }
}
