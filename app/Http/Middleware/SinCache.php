<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CU02: las páginas internas no se guardan en caché, así el botón «Atrás» no las muestra tras cerrar sesión.
 */
class SinCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $respuesta->headers->set('Pragma', 'no-cache');
        $respuesta->headers->set('Expires', '0');

        return $respuesta;
    }
}
