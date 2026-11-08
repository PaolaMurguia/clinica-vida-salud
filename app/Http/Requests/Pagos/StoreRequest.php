<?php

namespace App\Http\Requests\Pagos;

use App\Models\Cita;
use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU25 – Registrar pago de consulta (cobrar y dejarPendiente): método de pago obligatorio al cobrar,
 * cita no cancelada y un solo pago por cita.
 */
class StoreRequest extends FormRequest
{
    public const METODOS = ['Efectivo', 'QR'];

    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'cita_id'     => ['required', 'integer', Rule::exists('cita', 'id')],
            'accion'      => ['required', 'in:cobrar,pendiente'],
            'metodo_pago' => ['nullable', 'required_if:accion,cobrar', Rule::in(self::METODOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'cita_id.exists'          => 'La cita no existe.',
            'metodo_pago.required_if' => 'Elija el método de pago (efectivo o QR) para cobrar.',
            'metodo_pago.in'          => 'El método de pago debe ser efectivo o QR.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $cita = Cita::find((int) $this->input('cita_id'));
                if ($cita->estado === 'Cancelada') {
                    $validator->errors()->add('cita_id', 'La cita está cancelada: no se cobra.');
                } elseif (Pago::obtenerPorCita($cita->id)) {
                    $validator->errors()->add('cita_id', 'La cita ya tiene un pago registrado.');
                }
            },
        ];
    }
}
