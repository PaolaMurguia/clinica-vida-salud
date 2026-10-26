<?php

namespace App\Http\Requests\Administracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU05, CU06, CU07 – cambiarEstado(): activar o desactivar el registro (B2, B6).
 */
class CambiarEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(['activar', 'desactivar'])],
        ];
    }
}
