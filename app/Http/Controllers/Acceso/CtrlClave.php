<?php

namespace App\Http\Controllers\Acceso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Acceso\UpdateClaveRequest;
use App\Models\Rol;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Control CtrlClave: CU26 – Cambiar contraseña.
 */
class CtrlClave extends Controller
{
    /**
     * CU26 – Cambiar contraseña: verifica la actual, guarda el nuevo hash, quita la marca
     * de contraseña temporal e invalida las demás sesiones abiertas del usuario.
     */
    public function update(UpdateClaveRequest $request): RedirectResponse
    {
        $usuario = $request->user();
        $datos = $request->validated();   // pasos 3 y 5: obligatorios, confirmación, RNF05 y distinta de la actual

        // Paso 4: contraseña actual contra el hash almacenado
        if (! $usuario->verificarClave($datos['clave_actual'])) {
            $minutosBloqueo = $usuario->registrarIntentoFallido();
            ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'CAMBIO_CLAVE_FALLIDO', 'Acceso', $usuario->id,
                'Contraseña actual incorrecta');

            // B9: cinco fallos consecutivos bloquean la cuenta y cierran la sesión
            if ($minutosBloqueo) {
                ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'BLOQUEO_CUENTA', 'Acceso', $usuario->id,
                    "Cuenta bloqueada $minutosBloqueo minutos por intentos fallidos al cambiar la contraseña");
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', "Demasiados intentos fallidos. La cuenta quedó bloqueada por $minutosBloqueo minutos.");
            }

            return back()->withErrors(['clave_actual' => 'La contraseña actual es incorrecta.']);
        }

        // Paso 6: nuevo hash sin marca de temporal; la contraseña actual fue correcta, se reinician los intentos
        $usuario->actualizarClave($datos['clave_nueva']);
        $usuario->reiniciarIntentos();

        // Paso 6: guarda el nuevo hash en esta sesión; AuthenticateSession cierra las demás sesiones abiertas
        Auth::login($usuario);
        $request->session()->regenerate();

        // Paso 7: bitácora y confirmación
        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'CAMBIO_CLAVE', 'Acceso', $usuario->id, 'Cambio de contraseña');

        return redirect()->route($usuario->rol->nombre === Rol::PACIENTE ? 'portal' : 'menu')
            ->with('exito', 'Contraseña actualizada correctamente.');
    }
}
