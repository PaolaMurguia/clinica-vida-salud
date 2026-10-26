<?php

namespace App\Http\Requests\Administracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU04 – Consultar bitácora (filtrar y exportar): usuario, rango de fechas (máximo 90 días, B11) y módulo.
 */
class FiltrarBitacoraRequest extends FormRequest
{
    /** Rango máximo del filtro de fechas, en días (B11). */
    public const MAX_DIAS = 90;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario_id' => ['nullable', 'integer', Rule::exists('usuario', 'id')],
            'desde'      => ['nullable', 'date_format:Y-m-d', 'required_with:hasta'],
            'hasta'      => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'modulo'     => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'Rango de fechas inválido: la fecha inicial es posterior a la final.',
        ];
    }

    /** CU04, excepción 3: un rango mayor a 90 días pide acotar el período (sin «hasta» se cuenta hasta hoy). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('desde') || $validator->errors()->hasAny(['desde', 'hasta'])) {
                    return;
                }
                $desde = Carbon::parse($this->input('desde'));
                $hasta = Carbon::parse($this->input('hasta', today()->toDateString()));
                if ($desde->diffInDays($hasta) > self::MAX_DIAS) {
                    $validator->errors()->add('desde', 'El rango es mayor a ' . self::MAX_DIAS . ' días: acote el período.');
                }
            },
        ];
    }
}
