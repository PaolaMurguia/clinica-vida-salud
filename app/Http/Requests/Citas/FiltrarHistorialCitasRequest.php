<?php

namespace App\Http\Requests\Citas;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU19 – Consultar historial de citas (mostrar y filtrar): paciente, estado y rango de fechas.
 * El paciente solo consulta su propio historial (RN18); un paciente inactivo se consulta igual (RN11).
 */
class FiltrarHistorialCitasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'integer', Rule::exists('paciente', 'id')],
            'estado'      => ['nullable', Rule::in(Cita::ESTADOS)],
            'desde'       => ['nullable', 'date_format:Y-m-d'],
            'hasta'       => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Seleccione primero un paciente.',
            'paciente_id.exists'   => 'El paciente no existe.',
            'estado.in'            => 'El estado elegido no es válido.',
        ];
    }

    /** RN18: sin paciente_id, el paciente consulta el suyo. */
    protected function prepareForValidation(): void
    {
        $propio = $this->user()?->paciente_id;
        if ($propio !== null && ! $this->filled('paciente_id')) {
            $this->merge(['paciente_id' => $propio]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $propio = $this->user()?->paciente_id;
                if ($propio !== null && (int) $this->input('paciente_id') !== $propio) {
                    $validator->errors()->add('paciente_id', 'Solo puede consultar su propio historial de citas (RN18).');
                }
                // Excepción 4: rango invertido
                if ($this->filled('desde') && $this->filled('hasta') && ! $validator->errors()->hasAny(['desde', 'hasta'])
                    && $this->input('hasta') < $this->input('desde')) {
                    $validator->errors()->add('hasta', 'Rango de fechas inválido: la fecha final es anterior a la inicial.');
                }
            },
        ];
    }
}
