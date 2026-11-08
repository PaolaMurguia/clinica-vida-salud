<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Entidad REGISTRO_ATENCION: diagnóstico, tratamiento y signos vitales de una cita atendida.
 */
class RegistroAtencion extends Model
{
    protected $table = 'registro_atencion';
    public $timestamps = false;

    protected $fillable = [
        'cita_id', 'diagnostico', 'tratamiento', 'indicaciones', 'peso_kg', 'talla_cm',
        'presion_arterial', 'temperatura_c', 'dictado_voz',
    ];

    protected function casts(): array
    {
        return [
            'peso_kg'        => 'decimal:2',
            'talla_cm'       => 'decimal:1',
            'temperatura_c'  => 'decimal:1',
            'dictado_voz'    => 'boolean',
            'fecha_registro' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    /** CU20: registro de atención de la cita, si existe (uno por cita). */
    public static function obtenerPorCita(int $citaId): ?self
    {
        return self::where('cita_id', $citaId)->first();
    }

    /** CU20: atenciones del paciente, de la más reciente a la más antigua, con su cita, médico y especialidad. */
    public static function listarPorPaciente(int $pacienteId): Collection
    {
        return self::with(['cita.medico', 'cita.especialidad'])
            ->whereHas('cita', fn ($q) => $q->where('paciente_id', $pacienteId))
            ->get()
            ->sortByDesc(fn (self $registro) => $registro->cita->fecha->toDateString() . ' ' . $registro->cita->hora_inicio)
            ->values();
    }

    /** CU20, CU21: registra la atención de una cita atendida (UNIQUE cita_id). */
    public static function crear(array $datos): self
    {
        return self::create($datos);
    }

    /** CU20: el médico modifica su propio registro; cada cambio queda en la bitácora (D7). */
    public function actualizar(array $datos): void
    {
        $this->update($datos);
    }
}
