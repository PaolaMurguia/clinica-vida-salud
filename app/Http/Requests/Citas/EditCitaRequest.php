<?php

namespace App\Http\Requests\Citas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CU15 – Reprogramar cita (mostrar): fecha opcional para consultar las franjas libres del mismo médico.
 */
class EditCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta; RN18 lo verifica el controlador
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.after_or_equal' => 'No se puede reprogramar a una fecha pasada (RN06).',
        ];
    }
}
