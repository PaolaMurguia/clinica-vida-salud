<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entidad NOTIFICACION: recordatorios y avisos por correo o dentro del sistema (RN21).
 */
class Notificacion extends Model
{
    protected $table = 'notificacion';
    public $timestamps = false;

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
}
