<?php

namespace App\Http\Controllers\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\BuscarPacienteRequest;
use App\Http\Requests\Pacientes\CambiarEstadoPacienteRequest;
use App\Http\Requests\Pacientes\StorePacienteRequest;
use App\Http\Requests\Pacientes\UpdatePacienteRequest;
use App\Models\Historia;
use App\Models\Paciente;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlPacientes: CU08 – Gestionar pacientes y CU09 – Buscar paciente
 * (Recepcionista y Administrador, A1).
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
        $datos = $request->validated();

        $paciente = DB::transaction(function () use ($datos) {
            $paciente = Paciente::crear(Arr::except($datos, 'tipo_sangre'));
            // tipo de sangre en la historia clínica, que se crea si no existe (B7)
            if (! empty($datos['tipo_sangre'])) {
                Historia::obtener($paciente->id)->update(['tipo_sangre' => $datos['tipo_sangre'], 'fecha_actualizacion' => now()]);
            }
            return $paciente;
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Pacientes', $paciente->id,
            "Registro del paciente {$paciente->codigo} (CI {$paciente->ci})");

        return redirect()->route('pacientes.index')->with('exito', "Paciente {$paciente->codigo} registrado.");
    }

    /**
     * CU08 – Gestionar pacientes (pasos 2 a 4 y 7): actualiza los datos del paciente; la recepción
     * también corrige aquí el tipo de sangre declarado por el paciente (CU11).
     */
    public function update(UpdatePacienteRequest $request, Paciente $paciente): RedirectResponse
    {
        $datos = $request->validated();
        $historia = Historia::obtener($paciente->id);
        $tipoSangre = $datos['tipo_sangre'] ?? null;
        $sangreAnterior = $historia->tipo_sangre;
        $cambiaSangre = $sangreAnterior !== $tipoSangre;

        DB::transaction(function () use ($paciente, $historia, $datos, $tipoSangre, $cambiaSangre) {
            $paciente->actualizar(Arr::except($datos, 'tipo_sangre'));
            if ($cambiaSangre) {
                $historia->update(['tipo_sangre' => $tipoSangre, 'fecha_actualizacion' => now()]);
            }
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'MODIFICAR', 'Pacientes', $paciente->id,
            "Modificación del paciente {$paciente->codigo}"
            . ($cambiaSangre ? '; tipo de sangre: ' . ($sangreAnterior ?? 'sin registrar') . ' → ' . ($tipoSangre ?? 'sin registrar') : ''));

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

    /**
     * CU08 – Gestionar pacientes (cambiarEstado, aprobado por la autora): reactiva a un paciente
     * desactivado (B2, B6) y lo registra en la bitácora.
     */
    public function cambiarEstado(CambiarEstadoPacienteRequest $request, Paciente $paciente): RedirectResponse
    {
        $request->validated();   // accion = activar
        $paciente->actualizar(['estado' => true]);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'ACTIVAR', 'Pacientes', $paciente->id,
            "Reactivación del paciente {$paciente->codigo}");

        return redirect()->route('pacientes.index')->with('exito', "Paciente {$paciente->codigo} reactivado.");
    }

    /**
     * CU09 – Buscar paciente (pasos 1 a 3): búsqueda parametrizada por CI, nombre o apellido,
     * sin distinguir mayúsculas ni tildes; hasta 50 resultados.
     */
    public function buscar(BuscarPacienteRequest $request): View
    {
        $criterio = $request->validated('criterio');
        $resultados = $criterio !== null ? Paciente::buscar($criterio) : null;

        return view('buscarPaciente', [
            'criterio'     => $criterio,
            // más de 50 coincidencias: se pide precisar la búsqueda (CU09, excepción 3)
            'demasiados'   => $resultados !== null && $resultados->count() > Paciente::MAX_RESULTADOS,
            'resultados'   => $resultados?->take(Paciente::MAX_RESULTADOS),
            'seleccionado' => null,
        ]);
    }

    /**
     * CU09 – Buscar paciente (paso 4): el paciente queda seleccionado para registrar citas,
     * seguro, historial o resultados.
     */
    public function show(Paciente $paciente): View
    {
        return view('buscarPaciente', [
            'criterio'     => null,
            'demasiados'   => false,
            'resultados'   => null,
            'seleccionado' => Paciente::obtener($paciente->id),
        ]);
    }
}
