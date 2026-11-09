<?php

namespace App\Http\Requests\Citas;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CU17 – Registrar asistencia: la acción según el rol (la recepcionista confirma o registra la
 * inasistencia; el médico con el permiso marca la cita atendida), la transición válida (RF10) y que
 * Atendida e Inasistencia no se registren antes de la hora de inicio (D6).
 */
class CambiarEstadoCitaRequest extends FormRequest
{
    /** Acción de V.agenda → estado nuevo de la cita (B2: una sola ruta con el parámetro acción). */
    public const ACCIONES = [
        'confirmar'             => 'Confirmada',
        'atender'               => 'Atendida',
        'registrarInasistencia' => 'Inasistencia',
    ];

    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', 'in:' . implode(',', array_keys(self::ACCIONES))],
        ];
    }

    public function messages(): array
    {
        return [
            'accion.required' => 'Elija la acción.',
            'accion.in'       => 'La acción elegida no es válida.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                /** @var Cita $cita */
                $cita = $this->route('cita');
                $usuario = $this->user();
                $accion = $this->input('accion');
                $nuevo = self::ACCIONES[$accion];

                if ($usuario->medico_id !== null) {
                    if ($accion !== 'atender') {
                        $validator->errors()->add('accion', 'El médico solo marca sus citas como atendidas; la recepción confirma la asistencia o registra la inasistencia.');
                        return;
                    }
                    if ($cita->medico_id !== $usuario->medico_id) {
                        $validator->errors()->add('accion', 'No puede cambiar el estado de una cita de otro médico (RN18).');
                        return;
                    }
                    if (! $usuario->puedeMarcarAtendida()) {
                        $validator->errors()->add('accion', 'No tiene el permiso de marcar citas como atendidas; solicítelo al administrador.');
                        return;
                    }
                } elseif ($accion === 'atender') {
                    $validator->errors()->add('accion', 'Solo el médico con el permiso marca la cita como atendida.');
                    return;
                }

                if (! in_array($nuevo, Cita::TRANSICIONES[$cita->estado] ?? [], true)) {
                    $validator->errors()->add('accion', "Transición no permitida: la cita está {$cita->estado} y no puede pasar a $nuevo (RF10).");
                    return;
                }

                $inicio = $cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio);
                if (in_array($nuevo, ['Atendida', 'Inasistencia'], true) && now()->lt($inicio)) {
                    $validator->errors()->add('accion', "No se puede registrar $nuevo antes de la hora de inicio de la cita ({$inicio->format('d/m/Y H:i')}).");
                }
            },
        ];
    }
}
