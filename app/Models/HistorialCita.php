<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entidad HISTORIAL_CITA: cambios de estado de cada cita (tabla historial_estado_cita, RNF14).
 */
class HistorialCita extends Model
{
    protected $table = 'historial_estado_cita';
    public $timestamps = false;

    protected $fillable = ['cita_id', 'usuario_id', 'estado_anterior', 'estado_nuevo', 'observacion'];

    protected function casts(): array
    {
        return ['fecha_cambio' => 'datetime'];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
