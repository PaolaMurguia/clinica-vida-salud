<?php

namespace App\Http\Requests\Administracion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CU06 – Gestionar médicos (buscar): criterio por nombre, CI o especialidad.
 */
class BuscarMedicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'criterio' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['criterio' => trim((string) $this->input('criterio'))]);
    }
}
