<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RNF03, RN12: solo los roles indicados acceden a la ruta. Uso: ->middleware('rol:Administrador,Recepcionista').
 */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->user()?->rol?->nombre, $roles, true)) {
            abort(403, 'No tiene permiso para acceder a esta opción.');
        }

        return $next($request);
    }
}
