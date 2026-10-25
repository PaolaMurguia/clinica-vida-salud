<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Entidad BLOQUEO: fechas sin atención (tabla bloqueo_agenda). medico_id nulo = toda la clínica.
 */
class Bloqueo extends Model
{
    protected $table = 'bloqueo_agenda';
    public $timestamps = false;

    protected $fillable = ['medico_id', 'fecha', 'motivo'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    /**
     * CU13: devuelve el bloqueo de la fecha (de la clínica o del médico), con su motivo,
     * o null si la fecha está libre.
     */
    public static function estaBloqueada(int $medicoId, Carbon $fecha): ?self
    {
        return self::whereDate('fecha', $fecha->toDateString())
            ->where(fn ($q) => $q->whereNull('medico_id')->orWhere('medico_id', $medicoId))
            ->first();
    }
}
