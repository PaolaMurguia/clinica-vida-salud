<?php

namespace App\Http\Requests\Disponibilidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU13 – Consultar disponibilidad (elegir especialidad): paciente para el que se consulta.
 * El paciente consulta para sí mismo (RN18); la recepcionista indica el paciente seleccionado (CU09).
 */
class EspecialidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'paciente_id' => ['nullable', 'integer', Rule::exists('paciente', 'id')->where('estado', true)],
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.exists' => 'El paciente no existe o está inactivo.',
        ];
    }

    /** RN18: un paciente nunca consulta en nombre de otro; se usa siempre su propia ficha. */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->paciente_id !== null) {
            $this->merge(['paciente_id' => $this->user()->paciente_id]);
        }
    }
}
