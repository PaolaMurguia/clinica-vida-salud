<?php

namespace App\Http\Requests\Administracion;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU03 – Gestionar usuarios y roles (cambiar estado): activar, desactivar o desbloquear (B2).
 * No se desactiva al único administrador activo ni la propia cuenta en uso.
 */
class CambiarEstadoUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(['activar', 'desactivar', 'desbloquear'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('accion') !== 'desactivar') {
                    return;
                }
                $usuario = $this->route('usuario');

                // CU03, excepción 5
                if ($usuario->id === $this->user()->id) {
                    $validator->errors()->add('accion', 'No puede desactivar su propia cuenta mientras tiene la sesión abierta.');
                    return;
                }

                // CU03, excepción 4
                $rolAdmin = Rol::obtener(Rol::ADMINISTRADOR)->id;
                $adminsActivos = Usuario::where('rol_id', $rolAdmin)->where('estado', true)->count();
                if ($usuario->rol_id === $rolAdmin && $usuario->estado && $adminsActivos <= 1) {
                    $validator->errors()->add('accion', 'No se puede desactivar al único administrador activo.');
                }
            },
        ];
    }
}
