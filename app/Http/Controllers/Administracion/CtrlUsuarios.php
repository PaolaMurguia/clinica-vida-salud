<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administracion\CambiarEstadoUsuarioRequest;
use App\Http\Requests\Administracion\StoreUsuarioRequest;
use App\Http\Requests\Administracion\UpdateUsuarioRequest;
use App\Models\Medico;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Control CtrlUsuarios: CU03 – Gestionar usuarios y roles.
 */
class CtrlUsuarios extends Controller
{
    /**
     * CU03 – Gestionar usuarios y roles (paso 1): lista las cuentas con su rol y estado.
     * Con ?editar={id} carga la cuenta en el formulario integrado (B1).
     */
    public function index(Request $request): View
    {
        return view('usuarios', [
            'usuarios' => Usuario::listar(),
            'roles'    => Rol::obtener()->where('nombre', '<>', Rol::PACIENTE),
            'medicos'  => Medico::listar()->where('estado', true),
            'editado'  => $request->filled('editar') ? Usuario::obtener((int) $request->query('editar')) : null,
        ]);
    }

    /**
     * CU03 – Gestionar usuarios y roles (pasos 2 a 5 y 7): registra la cuenta con la contraseña
     * cifrada; la contraseña inicial queda como temporal (RN23, B12).
     */
    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $esMedico = (int) $datos['rol_id'] === Rol::obtener(Rol::MEDICO)->id;
        $medico = $esMedico ? Medico::obtener((int) $datos['medico_id']) : null;

        $usuario = Usuario::crear([
            'nombre_usuario'        => $datos['nombre_usuario'],
            'correo'                => $datos['correo'],
            'clave'                 => $datos['clave'],
            'rol_id'                => $datos['rol_id'],
            'medico_id'             => $medico?->id,
            'puede_marcar_atendida' => $esMedico && $datos['puede_marcar_atendida'],
            'debe_cambiar_clave'    => true,
        ]);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CREAR', 'Usuarios', $usuario->id,
            "Registro del usuario {$usuario->nombre_usuario}" . ($medico ? " vinculado al médico {$medico->codigo}" : ''));

        return redirect()->route('usuarios.index')->with('exito', "Usuario {$usuario->nombre_usuario} registrado. Deberá cambiar su contraseña al ingresar.");
    }

    /**
     * CU03 – Gestionar usuarios y roles (pasos 2 a 4 y 7): actualiza los datos y el rol de la cuenta.
     */
    public function update(UpdateUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $datos = $request->validated();
        $esMedico = (int) $datos['rol_id'] === Rol::obtener(Rol::MEDICO)->id;
        $medico = $esMedico ? Medico::obtener((int) $datos['medico_id']) : null;

        $usuario->actualizar([
            'nombre_usuario'        => $datos['nombre_usuario'],
            'correo'                => $datos['correo'],
            'rol_id'                => $datos['rol_id'],
            'medico_id'             => $medico?->id,
            'puede_marcar_atendida' => $esMedico && $datos['puede_marcar_atendida'],
        ]);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'MODIFICAR', 'Usuarios', $usuario->id,
            "Modificación del usuario {$usuario->nombre_usuario}");

        return redirect()->route('usuarios.index')->with('exito', "Usuario {$usuario->nombre_usuario} actualizado.");
    }

    /**
     * CU03 – Gestionar usuarios y roles (paso 6): genera una contraseña temporal, marca la cuenta
     * como «debe cambiar contraseña», la desbloquea y muestra la contraseña una sola vez.
     */
    public function restablecerClave(Usuario $usuario): RedirectResponse
    {
        // contraseña temporal aleatoria que cumple la política RNF05
        do {
            $temporal = Str::password(12);
        } while (! preg_match('/[a-z]/', $temporal) || ! preg_match('/[A-Z]/', $temporal)
            || ! preg_match('/\d/', $temporal) || ! preg_match('/[^A-Za-z0-9]/', $temporal));

        $usuario->marcarTemporal($temporal);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'RESTABLECER_CLAVE', 'Usuarios', $usuario->id,
            "Contraseña temporal para {$usuario->nombre_usuario}");

        // flash: se muestra una sola vez y no queda guardada en claro
        return redirect()->route('usuarios.index')
            ->with('claveTemporal', ['usuario' => $usuario->nombre_usuario, 'clave' => $temporal]);
    }

    /**
     * CU03 – Gestionar usuarios y roles (paso 6): activa, desactiva o desbloquea la cuenta (B2).
     */
    public function cambiarEstado(CambiarEstadoUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $accion = $request->validated('accion');
        $usuario->actualizarEstado($accion);

        ServicioBitacora::registrar(ServicioBitacora::ACCION, strtoupper($accion), 'Usuarios', $usuario->id,
            ucfirst($accion) . " la cuenta {$usuario->nombre_usuario}");

        $textos = ['activar' => 'activada', 'desactivar' => 'desactivada', 'desbloquear' => 'desbloqueada'];

        return redirect()->route('usuarios.index')->with('exito', "Cuenta {$usuario->nombre_usuario} {$textos[$accion]}.");
    }
}
