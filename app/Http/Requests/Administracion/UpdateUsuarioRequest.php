<?php

namespace App\Http\Requests\Administracion;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU03 – Gestionar usuarios y roles (editar): mismas reglas que el registro, sin contraseña.
 * No se puede cambiar el rol del único administrador activo, y una cuenta de paciente conserva su rol.
 */
class UpdateUsuarioRequest extends StoreUsuarioRequest
{
    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $reglasRol = $usuario->rol->nombre === Rol::PACIENTE
            ? ['required', 'integer', Rule::in([$usuario->rol_id])]   // las cuentas de paciente se crean en CU10
            : ['required', 'integer', Rule::exists('rol', 'id')->whereNot('nombre', Rol::PACIENTE)];

        return [
            'nombre_usuario'        => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'correo'                => ['required', 'string', 'email', 'max:120'],
            'rol_id'                => $reglasRol,
            'medico_id'             => $this->reglasMedico(),
            'puede_marcar_atendida' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            // CU03, excepción 4: el único administrador activo no puede cambiar de rol
            function (Validator $validator) {
                $usuario = $this->route('usuario');
                $rolAdmin = Rol::obtener(Rol::ADMINISTRADOR)->id;
                $dejaDeSerAdmin = $usuario->rol_id === $rolAdmin && (int) $this->input('rol_id') !== $rolAdmin;
                $adminsActivos = Usuario::where('rol_id', $rolAdmin)->where('estado', true)->count();

                if ($dejaDeSerAdmin && $usuario->estado && $adminsActivos <= 1) {
                    $validator->errors()->add('rol_id', 'No se puede cambiar el rol del único administrador activo.');
                }
            },
        ];
    }
}
