<?php

use App\Http\Middleware\SinCache;
use App\Http\Middleware\VerificarClaveTemporal;
use App\Http\Middleware\VerificarCuentaActiva;
use App\Http\Middleware\VerificarRol;
use App\Models\Rol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Despliegue en Render: el TLS lo termina el proxy de Render, que envía X-Forwarded-*.
        // Se confía en él para que Laravel reconozca https y la IP real del usuario (bitácora).
        $middleware->trustProxies(at: '*');

        // CU03: una cuenta desactivada pierde su sesión en la siguiente petición
        $middleware->web(append: [VerificarCuentaActiva::class]);

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
        // D1, RNF08 (aprobado por la autora): los triggers y las restricciones son la red de seguridad
        // de las validaciones. Sus errores se traducen a mensajes claros, sin SQL ni detalles internos;
        // el detalle técnico queda en el log (report).
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $estado = $e->errorInfo[0] ?? (string) $e->getCode();
            $detalle = $e->errorInfo[2] ?? $e->getMessage();

            // Restricciones por nombre (EXCLUDE, UNIQUE y CHECK)
            $restricciones = [
                'ex_cita_no_solapada'         => 'La franja ya fue ocupada por otra cita. Elija otra franja libre.',
                'ex_horario_no_solapado'      => 'El horario se superpone con otro horario activo del médico.',
                'ex_rango_no_solapado'        => 'El rango de edad se superpone con otro rango del mismo parámetro.',
                'pago_cita_id_key'            => 'La cita ya tiene un pago registrado.',
                'registro_atencion_cita_id_key' => 'La cita ya tiene un registro de atención.',
                'uq_resultado'                => 'El parámetro ya está registrado en este examen.',
                'uq_bloqueo'                  => 'Ya existe un bloqueo para esa fecha y ese ámbito.',
                'ux_recordatorio'             => 'El recordatorio de la cita ya estaba programado.',
                'ck_pago_completo'            => 'Para registrar el pago como pagado indique el método de pago.',
                'ck_examen_registrado'        => 'Indique la fecha de la muestra antes de registrar los resultados.',
                'ck_cita_horas'               => 'La hora de fin debe ser posterior a la de inicio.',
            ];

            $mensaje = match (true) {
                // RAISE EXCEPTION de los triggers: su texto ya está redactado para el usuario
                $estado === 'P0001' && preg_match('/ERROR:\s+(.+?)(?:\n|$)/u', $detalle, $m) === 1 => trim($m[1]),
                in_array($estado, ['23P01', '23505', '23514'], true) => collect($restricciones)
                    ->first(fn ($texto, $nombre) => str_contains($detalle, "\"$nombre\""),
                        match ($estado) {
                            '23P01' => 'El registro se superpone con otro existente.',
                            '23505' => 'El registro ya existe.',
                            default => 'Algún dato está fuera de los valores permitidos.',
                        }),
                $estado === '23503' => 'El registro relacionado no existe o está en uso.',
                default => null,
            };

            return $mensaje === null ? null : back()->withInput()->with('error', $mensaje);
        });
    })->create();
