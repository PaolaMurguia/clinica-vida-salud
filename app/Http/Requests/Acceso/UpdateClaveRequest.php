<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * CU26 – Cambiar contraseña: campos obligatorios, confirmación, política RNF05
 * y contraseña nueva distinta de la actual.
 */
class UpdateClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'clave_actual' => ['required', 'string', 'max:255'],
            'clave_nueva'  => ['required', 'string', 'max:255', 'confirmed', 'different:clave_actual', Password::defaults()],
        ];
    }
}
