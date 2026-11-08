<?php

namespace App\Http\Requests\Administracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CU24 – Gestionar feriados y bloqueos (mostrar): filtros por médico (o toda la clínica) y por rango de fechas.
 */
class FiltrarBloqueosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medico_id' => ['nullable', 'regex:/^(clinica|\d+)$/'],
            'desde'     => ['nullable', 'date_format:Y-m-d'],
            'hasta'     => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return ['medico_id.regex' => 'El filtro de médico no es válido.'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('desde') && $this->filled('hasta') && ! $validator->errors()->hasAny(['desde', 'hasta'])
                    && $this->input('hasta') < $this->input('desde')) {
                    $validator->errors()->add('hasta', 'Rango de fechas inválido: la fecha final es anterior a la inicial.');
                }
            },
        ];
    }
}
