<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * CU27 – Recuperar contraseña (restablecimiento): enlace, contraseña nueva con la política RNF05 y confirmación.
 */
class RestablecerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:100'],
            'clave' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ];
    }
}
