<?php

namespace App\Http\Controllers\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\StoreAntecedenteRequest;
use App\Http\Requests\Pacientes\UpdatePerfilRequest;
use App\Models\Antecedente;
use App\Models\Historia;
use App\Models\Paciente;
use App\Models\Seguro;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Control CtrlPerfil: CU11 – Gestionar perfil del paciente. El paciente solo ve y modifica
 * su propia información (RN18).
 */
class CtrlPerfil extends Controller
{
    /**
     * CU11 – Gestionar perfil del paciente (pasos 1 y 2): datos personales, contacto, antecedentes,
     * contacto de emergencia, seguro y preferencias. La historia se crea si no existe (B7).
     */
    public function show(Request $request): View
    {
        $paciente = Paciente::obtener($request->user()->paciente_id);
        $historia = Historia::obtener($paciente->id);

        return view('perfil', [
            'paciente'     => $paciente,
            'historia'     => $historia,
            'antecedentes' => Antecedente::listar($historia->id),
            'seguro'       => Seguro::obtenerActivo($paciente->id),
        ]);
    }

    /**
     * CU11 – Gestionar perfil del paciente (pasos 3, 5 y 7): guarda teléfono, correo, dirección,
     * contacto de emergencia y preferencias; el correo de la cuenta se mantiene igual al del perfil.
     */
    public function update(UpdatePerfilRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $usuario = $request->user();
        $paciente = Paciente::obtener($usuario->paciente_id);

        DB::transaction(function () use ($paciente, $usuario, $datos) {
            $paciente->actualizar($datos);
            if ($usuario->correo !== $datos['correo']) {
                $usuario->actualizarCorreo($datos['correo']);
            }
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'MODIFICAR', 'Perfil', $paciente->id,
            "Actualización del perfil del paciente {$paciente->codigo}");

        return redirect()->route('perfil.show')->with('exito', 'Su perfil fue actualizado.');
    }

    /**
     * CU11 – Gestionar perfil del paciente (pasos 4, 5 y 7): registra el tipo de sangre (B7) o un
     * antecedente indicando que fue declarado por el paciente. Los antecedentes no se borran (A5).
     */
    public function storeAntecedente(StoreAntecedenteRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $usuario = $request->user();
        $historia = Historia::obtener($usuario->paciente_id);

        DB::transaction(function () use ($historia, $usuario, $datos) {
            if (! empty($datos['tipo_sangre'])) {
                $historia->update(['tipo_sangre' => $datos['tipo_sangre'], 'fecha_actualizacion' => now()]);
            }
            if (! empty($datos['tipo'])) {
                Antecedente::crear([
                    'historia_clinica_id' => $historia->id,
                    'tipo'                => $datos['tipo'],
                    'descripcion'         => $datos['descripcion'],
                    'origen'              => Antecedente::ORIGEN_PACIENTE,
                    'usuario_id'          => $usuario->id,
                ]);
            }
        });

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'DECLARAR_ANTECEDENTE', 'Perfil', $historia->id,
            'Antecedentes declarados por el paciente');

        return redirect()->route('perfil.show')->with('exito', 'Antecedentes registrados como declarados por usted.');
    }
}
