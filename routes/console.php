<?php

use App\Models\Notificacion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// D4 (aprobado por la autora): envía las notificaciones vencidas (recordatorios 24 horas antes, RN21).
// Render gratuito no tiene tareas programadas: también se procesan al abrir la bandeja.
Artisan::command('notificaciones:enviar', function () {
    $enviadas = Notificacion::enviarPendientes();
    $this->info("Notificaciones enviadas: $enviadas");
})->purpose('Envía las notificaciones por correo pendientes y vencidas');
