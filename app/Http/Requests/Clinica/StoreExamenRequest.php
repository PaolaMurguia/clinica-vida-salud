<?php

namespace App\Http\Requests\Clinica;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU22 – Gestionar resultados de laboratorio (solicitar): solo un médico que atiende o atendió al paciente
 * solicita análisis (RN20, RN18); tipo de examen obligatorio y observación opcional.
 */
class StoreExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicoId = $this->user()?->medico_id;

        return $medicoId !== null && Cita::where('paciente_id', (int) $this->input('paciente_id'))->where('medico_id', $medicoId)
            ->where('estado', '<>', 'Cancelada')->exists();
    }

    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'integer', Rule::exists('paciente', 'id')],
            'tipo_examen' => ['required', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:200'],
            'cita_id'     => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_examen.required' => 'Indique el tipo de examen.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo_examen' => trim((string) $this->input('tipo_examen')),
            'observacion' => trim((string) $this->input('observacion')) ?: null,
        ]);
    }

    /** La cita indicada debe ser de este paciente y de este médico. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('cita_id') || $validator->errors()->isNotEmpty()) {
                    return;
                }
                $valida = Cita::whereKey((int) $this->input('cita_id'))->where('paciente_id', (int) $this->input('paciente_id'))
                    ->where('medico_id', $this->user()->medico_id)->exists();
                if (! $valida) {
                    $validator->errors()->add('cita_id', 'La cita no corresponde a este paciente y médico.');
                }
            },
        ];
    }
}
