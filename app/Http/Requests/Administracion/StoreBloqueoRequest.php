<?php

namespace App\Http\Requests\Administracion;

use App\Models\Bloqueo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU24 – Gestionar feriados y bloqueos (agregar): fecha no pasada, ámbito (toda la clínica o un médico),
 * motivo de hasta 120 caracteres y sin bloqueo duplicado para la misma fecha y ámbito (uq_bloqueo).
 */
class StoreBloqueoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'fecha'     => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ambito'    => ['required', 'in:clinica,medico'],
            'medico_id' => ['nullable', 'required_if:ambito,medico', 'integer', Rule::exists('medico', 'id')],
            'motivo'    => ['required', 'string', 'max:120'],
            'confirmar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required'        => 'Indique la fecha.',
            'fecha.after_or_equal'  => 'No se puede bloquear una fecha pasada.',
            'medico_id.required_if' => 'Elija el médico.',
            'medico_id.exists'      => 'El médico no existe.',
            'motivo.required'       => 'Indique el motivo.',
            'motivo.max'            => 'El motivo no puede superar los 120 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
        if ($this->input('ambito') === 'clinica') {
            $this->merge(['medico_id' => null]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $medicoId = $this->input('medico_id') !== null ? (int) $this->input('medico_id') : null;
                if (Bloqueo::existe($medicoId, Carbon::createFromFormat('Y-m-d', $this->input('fecha')))) {
                    $validator->errors()->add('fecha', 'Ya existe un bloqueo para esa fecha y ese ámbito.');
                }
            },
        ];
    }
}
