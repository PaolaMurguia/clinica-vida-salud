<?php

use App\Http\Middleware\SinCache;
use App\Http\Middleware\VerificarClaveTemporal;
use App\Http\Middleware\VerificarRol;
use App\Models\Rol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol'            => VerificarRol::class,
            'clave.temporal' => VerificarClaveTemporal::class,
            'sin.cache'      => SinCache::class,
        ]);

        // RNF03: sin sesión, toda página interna lleva al inicio de sesión
        $middleware->redirectGuestsTo(fn () => route('login'));

        // CU01: con una sesión abierta, el inicio de sesión lleva al menú o al portal del paciente
        $middleware->redirectUsersTo(fn (Request $request) => route(
            $request->user()?->rol?->nombre === Rol::PACIENTE ? 'portal' : 'menu'
        ));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
