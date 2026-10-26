<?php

namespace App\Http\Middleware;

use App\Services\ServicioBitacora;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * CU03 (decisión aprobada): si el administrador desactiva una cuenta con una sesión abierta,
 * esa sesión se cierra en la siguiente petición.
 */
class VerificarCuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->estado) {
            ServicioBitacora::registrar(ServicioBitacora::ACCESO, 'SESION_CERRADA', 'Acceso', $usuario->id,
                'Sesión cerrada: la cuenta fue desactivada', $usuario->id);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Su cuenta fue desactivada. Comuníquese con la clínica.');
        }

        return $next($request);
    }
}
