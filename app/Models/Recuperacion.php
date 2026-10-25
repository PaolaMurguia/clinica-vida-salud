<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Entidad RECUPERACION: enlaces de recuperación de contraseña (tabla recuperacion_clave, RN22).
 * Solo se guarda el hash SHA-256 del enlace.
 */
class Recuperacion extends Model
{
    protected $table = 'recuperacion_clave';
    public $timestamps = false;

    public const MINUTOS_VIGENCIA = 60;
    public const MAX_SOLICITUDES_HORA = 3;

    protected $fillable = ['usuario_id', 'token_hash', 'fecha_solicitud', 'fecha_expira', 'fecha_uso', 'ip'];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_expira'    => 'datetime',
            'fecha_uso'       => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** CU27: solicitudes del usuario en la última hora. */
    public static function contarRecientes(int $usuarioId): int
    {
        return self::where('usuario_id', $usuarioId)
            ->where('fecha_solicitud', '>', now()->subHour())
            ->count();
    }

    /**
     * CU27: genera un enlace aleatorio, guarda solo su hash con vencimiento a los 60 minutos
     * y devuelve el token en claro para enviarlo por correo.
     */
    public static function crear(int $usuarioId, ?string $ip = null): string
    {
        $token = Str::random(64);
        $ahora = now();

        self::create([
            'usuario_id'      => $usuarioId,
            'token_hash'      => hash('sha256', $token),
            'fecha_solicitud' => $ahora,
            'fecha_expira'    => $ahora->copy()->addMinutes(self::MINUTOS_VIGENCIA),
            'ip'              => $ip,
        ]);

        return $token;
    }

    /** CU27: devuelve la solicitud si el enlace existe, no se usó y no venció; si no, null. */
    public static function validar(string $token): ?self
    {
        return self::with('usuario')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('fecha_uso')
            ->where('fecha_expira', '>', now())
            ->first();
    }

    /** CU27: el enlace queda usado y no se puede volver a usar. */
    public function marcarUsado(): void
    {
        $this->update(['fecha_uso' => now()]);
    }
}
