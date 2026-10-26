<?php

namespace App\Http\Requests\Pacientes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU08 – Gestionar pacientes (cambiarEstado, aprobado por la autora): reactiva a un paciente
 * desactivado (B6). La desactivación sigue siendo destroy() del documento.
 */
class CambiarEstadoPacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(['activar'])],
        ];
    }

    public function messages(): array
    {
        return [
            'accion.in' => 'Acción no válida: para desactivar use «Desactivar».',
        ];
    }
}
