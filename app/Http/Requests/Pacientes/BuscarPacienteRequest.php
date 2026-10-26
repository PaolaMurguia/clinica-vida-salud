<?php

namespace App\Http\Requests\Pacientes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CU09 – Buscar paciente: el criterio debe tener al menos tres caracteres.
 * Sin criterio se muestra solo el buscador.
 */
class BuscarPacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'criterio' => ['sometimes', 'required', 'string', 'min:3', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'criterio.min' => 'Escriba al menos 3 caracteres para buscar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('criterio')) {
            $this->merge(['criterio' => trim((string) $this->input('criterio'))]);
        }
    }
}
