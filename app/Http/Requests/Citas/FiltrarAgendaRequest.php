<?php

namespace App\Http\Requests\Citas;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU18 – Consultar agenda médica (mostrar y filtrar): médico, rango de fechas (día o semana) y estado.
 * El médico solo ve su propia agenda (RN18); la recepción y el administrador eligen el médico.
 */
class FiltrarAgendaRequest extends FormRequest
{
    /** Rango máximo de la consulta, en días. */
    public const MAX_DIAS = 31;

    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'medico_id' => ['nullable', 'integer', Rule::exists('medico', 'id')],
            'desde'     => ['nullable', 'date_format:Y-m-d'],
            'hasta'     => ['nullable', 'date_format:Y-m-d'],
            'vista'     => ['nullable', 'in:dia,semana'],
            'estado'    => ['nullable', Rule::in(Cita::ESTADOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'medico_id.exists' => 'El médico no existe.',
            'estado.in'        => 'El estado elegido no es válido.',
        ];
    }

    /** RN18: el médico consulta su propia agenda si no indica otra. */
    protected function prepareForValidation(): void
    {
        $propio = $this->user()?->medico_id;
        if ($propio !== null && ! $this->filled('medico_id')) {
            $this->merge(['medico_id' => $propio]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $propio = $this->user()->medico_id;
                if ($propio !== null && (int) $this->input('medico_id') !== $propio) {
                    $validator->errors()->add('medico_id', 'Solo puede consultar su propia agenda (RN18).');
                }
                if ($this->filled('desde') && $this->filled('hasta') && ! $validator->errors()->hasAny(['desde', 'hasta'])) {
                    if ($this->input('hasta') < $this->input('desde')) {
                        $validator->errors()->add('hasta', 'Rango de fechas inválido: la fecha final es anterior a la inicial.');
                    } elseif (now()->parse($this->input('desde'))->diffInDays(now()->parse($this->input('hasta'))) >= self::MAX_DIAS) {
                        $validator->errors()->add('hasta', 'El rango no puede superar ' . self::MAX_DIAS . ' días.');
                    }
                }
            },
        ];
    }
}
