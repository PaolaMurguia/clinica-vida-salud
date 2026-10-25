<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RN23, CU26: con una contraseña temporal el usuario no puede usar otras opciones hasta cambiarla.
 * Se aplica a todas las rutas internas excepto «Cambiar contraseña» y «Salir».
 */
class VerificarClaveTemporal
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_clave) {
            return redirect()->route('clave.edit')
                ->with('aviso', 'Su contraseña es temporal. Debe cambiarla antes de continuar.');
        }

        return $next($request);
    }
}
