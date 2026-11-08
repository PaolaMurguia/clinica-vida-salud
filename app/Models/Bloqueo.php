<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * CU24: bloqueos con su médico, de la fecha más reciente a la más antigua, con filtros opcionales
     * por médico (medico_id o 'clinica' para los de toda la clínica) y por rango de fechas.
     */
    public static function listar(array $filtros = []): Collection
    {
        $medico = $filtros['medico_id'] ?? null;

        return self::with('medico')
            ->when($medico === 'clinica', fn ($q) => $q->whereNull('medico_id'))
            ->when($medico !== null && $medico !== 'clinica', fn ($q) => $q->where('medico_id', (int) $medico))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->get();
    }

    /** CU24: indica si ya hay un bloqueo para la fecha y el mismo ámbito (uq_bloqueo; medico_id nulo = clínica). */
    public static function existe(?int $medicoId, Carbon $fecha): bool
    {
        return self::whereDate('fecha', $fecha->toDateString())
            ->when($medicoId === null, fn ($q) => $q->whereNull('medico_id'), fn ($q) => $q->where('medico_id', $medicoId))
            ->exists();
    }

    /** CU24: registra el feriado (toda la clínica) o el bloqueo del médico. */
    public static function crear(array $datos): self
    {
        return self::create($datos);
    }

    /** CU24: elimina el bloqueo; la fecha vuelve a la disponibilidad (D10: no se edita). */
    public function eliminar(): void
    {
        $this->delete();
    }

    /** CU18: bloqueos del médico y de toda la clínica en el rango de fechas, con su motivo. */
    public static function listarPorRango(int $medicoId, Carbon $desde, Carbon $hasta): Collection
    {
        return self::whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->where(fn ($q) => $q->whereNull('medico_id')->orWhere('medico_id', $medicoId))
            ->orderBy('fecha')
            ->get();
    }
}
