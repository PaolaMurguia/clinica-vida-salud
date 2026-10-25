<?php

namespace App\Http\Requests\Acceso;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CU27 – Recuperar contraseña (solicitud): correo o nombre de usuario obligatorio.
 */
class SolicitarEnlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['login' => trim((string) $this->input('login'))]);
    }
}
