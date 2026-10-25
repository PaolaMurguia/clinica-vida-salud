<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CU01 – Iniciar sesión: valida en el servidor que las credenciales no estén vacías.
 */
class AutenticarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:120'],
            'clave' => ['required', 'string', 'max:255'],
        ];
    }

    /** Sanea el usuario o correo antes de validarlo (CU01, paso 5). */
    protected function prepareForValidation(): void
    {
        $this->merge(['login' => trim((string) $this->input('login'))]);
    }
}
