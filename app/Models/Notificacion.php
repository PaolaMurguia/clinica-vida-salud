<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Entidad NOTIFICACION: recordatorios y avisos por correo o dentro del sistema (RN21, D3).
 */
class Notificacion extends Model
{
    protected $table = 'notificacion';
    public $timestamps = false;

    /** Intentos de envío por correo antes de dejar la notificación como Fallido definitivo. */
    public const MAX_INTENTOS = 3;

    /** Notificaciones por correo que se procesan en cada envío. */
    public const LOTE = 50;

    public const ASUNTOS = [
        'Recordatorio'   => 'Recordatorio de cita',
        'Confirmacion'   => 'Cita programada',
        'Reprogramacion' => 'Cita reprogramada',
        'Cancelacion'    => 'Cita cancelada',
        'Resultado'      => 'Resultados de laboratorio',
        'Agenda'         => 'Nueva cita en su agenda',
    ];

    protected $fillable = [
        'usuario_id', 'cita_id', 'canal', 'tipo', 'mensaje', 'fecha_programada', 'fecha_envio', 'estado', 'intentos',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'datetime',
            'fecha_envio'      => 'datetime',
            'intentos'         => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    /**
     * CU14, CU15, CU16, CU22: programa una notificación para el usuario destinatario (D3).
     * Canal Sistema (bandeja) para todos; el paciente la recibe según sus preferencias
     * (notif_alerta = bandeja, notif_correo = correo). Sin fecha, se envía de inmediato.
     * Devuelve las notificaciones creadas (ninguna si el paciente desactivó ambos canales).
     */
    public static function programar(Usuario $usuario, string $tipo, string $mensaje, ?Cita $cita = null, ?Carbon $fecha = null): Collection
    {
        $fecha = ($fecha === null || $fecha->isPast()) ? now() : $fecha;
        $paciente = $usuario->paciente;

        $canales = $paciente === null
            ? ['Sistema']
            : array_keys(array_filter(['Sistema' => $paciente->notif_alerta, 'Correo' => $paciente->notif_correo]));

        $creadas = collect($canales)->map(fn (string $canal) => self::create([
            'usuario_id'       => $usuario->id,
            'cita_id'          => $cita?->id,
            'canal'            => $canal,
            'tipo'             => $tipo,
            'mensaje'          => mb_substr($mensaje, 0, 300),
            'fecha_programada' => $fecha,
            'estado'           => 'Pendiente',
        ]));

        // Avisos inmediatos por correo: se envían al confirmar la transacción; si fallan, quedan Fallido (D3)
        if (in_array('Correo', $canales, true) && ! $fecha->isFuture()) {
            DB::afterCommit(fn () => self::enviarPendientes());
        }

        return $creadas;
    }

    /** CU15, CU16, CU17: anula los recordatorios pendientes de la cita (RN21). */
    public static function anularPendientes(Cita $cita): int
    {
        return self::where('cita_id', $cita->id)
            ->where('tipo', 'Recordatorio')
            ->whereIn('estado', ['Pendiente', 'Fallido'])
            ->update(['estado' => 'Anulado']);
    }

    /**
     * D3, D4 (aprobado por la autora): envía las notificaciones vencidas. Canal Correo: se envía con el
     * mailer configurado; un fallo suma un intento y deja la notificación Fallido sin interrumpir nada
     * (hasta MAX_INTENTOS). Canal Sistema: con $usuarioId, las del usuario pasan a Enviado al abrir su bandeja.
     * Lo usan el comando notificaciones:enviar, la bandeja y los avisos inmediatos. Devuelve cuántas se enviaron.
     */
    public static function enviarPendientes(?int $usuarioId = null): int
    {
        $enviadas = 0;

        $correos = self::with('usuario')
            ->where('canal', 'Correo')
            ->whereIn('estado', ['Pendiente', 'Fallido'])
            ->where('intentos', '<', self::MAX_INTENTOS)
            ->where('fecha_programada', '<=', now())
            ->orderBy('fecha_programada')
            ->limit(self::LOTE)
            ->get();

        foreach ($correos as $notificacion) {
            try {
                Mail::raw($notificacion->mensaje, fn ($correo) => $correo
                    ->to($notificacion->usuario->correo)
                    ->subject(config('app.name') . ' – ' . self::ASUNTOS[$notificacion->tipo]));
                $notificacion->update(['estado' => 'Enviado', 'fecha_envio' => now(), 'intentos' => $notificacion->intentos + 1]);
                $enviadas++;
            } catch (Throwable $e) {
                report($e);
                $notificacion->update(['estado' => 'Fallido', 'intentos' => $notificacion->intentos + 1]);
            }
        }

        if ($usuarioId !== null) {
            $enviadas += self::where('usuario_id', $usuarioId)
                ->where('canal', 'Sistema')
                ->where('estado', 'Pendiente')
                ->where('fecha_programada', '<=', now())
                ->update(['estado' => 'Enviado', 'fecha_envio' => now(), 'intentos' => DB::raw('intentos + 1')]);
        }

        return $enviadas;
    }
}
