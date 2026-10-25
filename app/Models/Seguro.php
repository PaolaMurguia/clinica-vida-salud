<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entidad SEGURO: seguro médico del paciente (tabla seguro_paciente, RF19). Un solo seguro activo.
 */
class Seguro extends Model
{
    protected $table = 'seguro_paciente';
    public $timestamps = false;

    protected $fillable = [
        'paciente_id', 'aseguradora', 'nro_poliza', 'tipo_cobertura', 'porcentaje_cobertura',
        'fecha_inicio', 'fecha_fin', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_cobertura' => 'decimal:2',
            'fecha_inicio'         => 'date',
            'fecha_fin'            => 'date',
            'estado'               => 'boolean',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'seguro_id');
    }

    /** CU11, CU12: seguro activo del paciente, si existe. */
    public static function obtenerActivo(int $pacienteId): ?self
    {
        return self::where('paciente_id', $pacienteId)->where('estado', true)->first();
    }

    /** CU12: da de baja el seguro (queda en el historial del paciente). */
    public function darDeBaja(): void
    {
        $this->update(['estado' => false]);
    }

    /** CU12: registra el nuevo seguro activo. */
    public static function crear(array $datos): self
    {
        return self::create($datos);
    }
}
