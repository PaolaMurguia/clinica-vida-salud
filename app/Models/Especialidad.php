<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Entidad ESPECIALIDAD: costo de consulta y rango de edad atendido (RF04, RN15).
 */
class Especialidad extends Model
{
    protected $table = 'especialidad';
    public $timestamps = false;

    // nombre_normalizado lo completa el trigger trg_normalizar_especialidad
    protected $fillable = ['nombre', 'descripcion', 'costo_consulta', 'edad_minima', 'edad_maxima', 'estado'];

    protected function casts(): array
    {
        return [
            'costo_consulta' => 'decimal:2',
            'edad_minima'    => 'integer',
            'edad_maxima'    => 'integer',
            'estado'         => 'boolean',
        ];
    }

    public function medicos(): BelongsToMany
    {
        return $this->belongsToMany(Medico::class, 'medico_especialidad', 'especialidad_id', 'medico_id')
            ->using(Atiende::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'especialidad_id');
    }

    /** CU05, CU06: especialidades ordenadas por nombre; opcionalmente solo las activas. */
    public static function listar(bool $soloActivas = false): Collection
    {
        return self::when($soloActivas, fn ($q) => $q->where('estado', true))->orderBy('nombre')->get();
    }

    /** CU05: compara con la misma normalización del trigger (minúsculas y espacios simples). */
    public static function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return self::whereRaw("nombre_normalizado = lower(btrim(regexp_replace(?, '\\s+', ' ', 'g')))", [$nombre])
            ->when($exceptoId, fn ($q) => $q->where('id', '<>', $exceptoId))
            ->exists();
    }

    /** CU05: registra la especialidad. */
    public static function crear(array $datos): self
    {
        return self::create($datos);
    }

    /** CU05: actualiza los datos de la especialidad. */
    public function actualizar(array $datos): void
    {
        $this->update($datos);
    }

    /** CU05: activa o desactiva la especialidad. */
    public function cambiarEstado(bool $activa): void
    {
        $this->update(['estado' => $activa]);
    }

    /** CU13: especialidades activas cuyo rango de edad incluye la edad indicada (RN15). */
    public static function listarPorEdad(int $edad): Collection
    {
        return self::where('estado', true)
            ->where(fn ($q) => $q->whereNull('edad_minima')->orWhere('edad_minima', '<=', $edad))
            ->where(fn ($q) => $q->whereNull('edad_maxima')->orWhere('edad_maxima', '>=', $edad))
            ->orderBy('nombre')
            ->get();
    }
}
