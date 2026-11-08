<?php

namespace App\Http\Requests\Pagos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU25 – Registrar pago de consulta (completar, paso 8): un pago pendiente se completa con el método de pago.
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // los roles los exige la ruta
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', Rule::in(StoreRequest::METODOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago.required' => 'Elija el método de pago (efectivo o QR).',
            'metodo_pago.in'       => 'El método de pago debe ser efectivo o QR.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('pago')->estado !== 'Pendiente') {
                    $validator->errors()->add('metodo_pago', 'El pago ya está registrado como pagado.');
                }
            },
        ];
    }
}
