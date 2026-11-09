<?php

namespace App\Http\Requests\Citas;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CU16 – Cancelar cita (cancelar): motivo obligatorio, cita propia del paciente (RN18), estado Programada
 * o Confirmada (RN10) y, para el paciente, al menos dos horas de anticipación (RN17).
 */
class CancelarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta; RN18 se valida abajo
    }

    public function rules(): array
    {
        return [
            'motivo_cancelacion' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_cancelacion.required' => 'Indique el motivo de la cancelación.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo_cancelacion' => trim((string) $this->input('motivo_cancelacion'))]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Cita $cita */
                $cita = $this->route('cita');
                $propio = $this->user()->paciente_id;

                if ($propio !== null && $cita->paciente_id !== $propio) {
                    $validator->errors()->add('cita', 'Solo puede cancelar sus propias citas (RN18).');
                    return;
                }
                if ($cita->estado === 'Cancelada') {
                    $validator->errors()->add('cita', 'La cita ya está cancelada.');
                    return;
                }
                if (! in_array($cita->estado, Cita::VIGENTES, true)) {
                    $validator->errors()->add('cita', "La cita está en estado {$cita->estado}: no se cancela como si nunca hubiera ocurrido (RN10).");
                    return;
                }
                $inicio = $cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio);
                if ($propio !== null && now()->addHours(Cita::PLAZO_PACIENTE_HORAS)->gt($inicio)) {
                    $validator->errors()->add('cita', 'Faltan menos de ' . Cita::PLAZO_PACIENTE_HORAS
                        . ' horas para la cita: para cancelarla comuníquese con la recepción (RN17).');
                }
            },
        ];
    }
}
