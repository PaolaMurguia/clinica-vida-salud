<?php

namespace App\Http\Requests\Administracion;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * CU03 – Gestionar usuarios y roles (nuevo usuario): usuario y correo únicos, política RNF05
 * y médico vinculado activo y sin cuenta.
 */
class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // el rol Administrador lo exige la ruta
    }

    public function rules(): array
    {
        return [
            'nombre_usuario'        => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'correo'                => ['required', 'string', 'email', 'max:120'],
            'clave'                 => ['required', 'string', 'max:255', Password::defaults()],
            'rol_id'                => ['required', 'integer', Rule::exists('rol', 'id')->whereNot('nombre', Rol::PACIENTE)],
            'medico_id'             => $this->reglasMedico(),
            'puede_marcar_atendida' => ['boolean'],
        ];
    }

    /** Médico vinculado: obligatorio con el rol Médico, activo y sin otra cuenta (CU03, excepción 3). */
    protected function reglasMedico(): array
    {
        $rolMedico = Rol::obtener(Rol::MEDICO)?->id;

        return [
            'nullable',
            Rule::requiredIf(fn () => (int) $this->input('rol_id') === $rolMedico),
            'integer',
            Rule::exists('medico', 'id')->where('estado', true),
            Rule::unique('usuario', 'medico_id')->ignore($this->route('usuario')?->id),
        ];
    }

    /** Usuario y correo únicos sin distinguir mayúsculas, con la misma búsqueda del inicio de sesión. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $exceptoId = $this->route('usuario')?->id;
                foreach (['nombre_usuario' => 'El nombre de usuario ya está registrado.', 'correo' => 'El correo ya está registrado.'] as $campo => $mensaje) {
                    $existente = $this->filled($campo) ? Usuario::buscarPorLogin($this->input($campo)) : null;
                    if ($existente && $existente->id !== $exceptoId) {
                        $validator->errors()->add($campo, $mensaje);
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre_usuario'        => trim((string) $this->input('nombre_usuario')),
            'correo'                => mb_strtolower(trim((string) $this->input('correo'))),
            'puede_marcar_atendida' => $this->boolean('puede_marcar_atendida'),
        ]);
    }
}
