<?php

namespace App\Http\Controllers\Acceso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Acceso\AutenticarRequest;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Control CtrlSesion: CU01 – Iniciar sesión y CU02 – Cerrar sesión.
 */
class CtrlSesion extends Controller
{
    /** Mensaje genérico: no indica cuál de los datos falló (CU01, excepción 1). */
    private const MENSAJE_CREDENCIALES = 'Usuario o contraseña incorrectos.';

    /**
     * CU01 – Iniciar sesión: autentica al usuario y lo lleva al menú de su rol,
     * al portal si es paciente, o a «Cambiar contraseña» si la contraseña es temporal.
     */
    public function autenticar(AutenticarRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        // en cada rechazo se devuelve el usuario ingresado, nunca la contraseña

        // Paso 5: búsqueda parametrizada por usuario o correo
        $usuario = Usuario::buscarPorLogin($datos['login']);

        if (! $usuario) {
            ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'LOGIN_FALLIDO', 'Acceso', null,
                'Usuario inexistente: ' . $datos['login']);
            return back()->withInput($request->only('login'))->with('error',self::MENSAJE_CREDENCIALES);
        }

        // Paso 6: cuenta activa
        if (! $usuario->estado) {
            ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'LOGIN_DENEGADO', 'Acceso', $usuario->id,
                'Cuenta inactiva', $usuario->id);
            return back()->withInput($request->only('login'))->with('error','Acceso denegado: la cuenta está inactiva. Comuníquese con la clínica.');
        }

        // Paso 6: bloqueo vigente; se informa el tiempo restante sin verificar la contraseña
        if ($usuario->bloqueado_hasta?->isFuture()) {
            $minutos = (int) ceil(now()->diffInSeconds($usuario->bloqueado_hasta) / 60);
            ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'LOGIN_BLOQUEADO', 'Acceso', $usuario->id,
                'Intento con la cuenta bloqueada', $usuario->id);
            return back()->withInput($request->only('login'))->with('error',"La cuenta está bloqueada temporalmente. Intente nuevamente en $minutos minuto(s).");
        }

        // Paso 6: contraseña contra el hash almacenado; cinco fallos seguidos bloquean la cuenta (RNF04)
        if (! $usuario->verificarClave($datos['clave'])) {
            $minutosBloqueo = $usuario->registrarIntentoFallido();
            ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'LOGIN_FALLIDO', 'Acceso', $usuario->id,
                'Contraseña incorrecta', $usuario->id);

            if ($minutosBloqueo) {
                ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'BLOQUEO_CUENTA', 'Acceso', $usuario->id,
                    "Cuenta bloqueada $minutosBloqueo minutos por intentos fallidos", $usuario->id);
                return back()->withInput($request->only('login'))->with('error',"Demasiados intentos fallidos. La cuenta quedó bloqueada por $minutosBloqueo minutos.");
            }

            return back()->withInput($request->only('login'))->with('error',self::MENSAJE_CREDENCIALES);
        }

        // Paso 7: reinicia los intentos, regenera la sesión y guarda el usuario con su rol
        $usuario->reiniciarIntentos();
        $usuario->forceFill(['ultimo_acceso' => now()])->save();
        $rol = Rol::obtener($usuario->rol_id);
        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->put('rol', $rol->nombre);

        // Paso 8: acceso en la bitácora
        ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'LOGIN', 'Acceso', $usuario->id, 'Inicio de sesión correcto');

        // Paso 9: contraseña temporal → CU26; paciente → portal; demás roles → menú
        if ($usuario->debe_cambiar_clave) {
            return redirect()->route('clave.edit')
                ->with('aviso', 'Su contraseña es temporal. Debe cambiarla antes de continuar.');
        }

        return redirect()->route($rol->nombre === Rol::PACIENTE ? 'portal' : 'menu');
    }

    /**
     * CU02 – Cerrar sesión: registra el cierre, invalida la sesión, regenera el token CSRF
     * y muestra el inicio de sesión. Con la sesión ya expirada, el middleware auth lleva
     * directamente al inicio de sesión.
     */
    public function cerrarSesion(Request $request): RedirectResponse
    {
        // Paso 2: cierre en la bitácora, antes de perder el usuario de la sesión
        ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'LOGOUT', 'Acceso', $request->user()->id, 'Cierre de sesión');

        // Paso 3: invalida la sesión y regenera el token CSRF
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Paso 4: página de inicio de sesión
        return redirect()->route('login')->with('exito', 'Sesión cerrada correctamente.');
    }
}
