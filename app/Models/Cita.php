<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Entidad CITA. El código CM-0001 lo genera la base de datos; los triggers validan
 * horario, edad, superposición y transiciones de estado.
 */
class Cita extends Model
{
    protected $table = 'cita';
    public $timestamps = false;

    protected $fillable = [
        'paciente_id', 'medico_id', 'especialidad_id', 'usuario_registro_id', 'fecha', 'hora_inicio',
        'hora_fin', 'modalidad', 'canal', 'motivo', 'estado', 'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'          => 'date',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_registro_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialCita::class, 'cita_id');
    }

    public function pago(): HasOne
    {
        return $this->hasOne(Pago::class, 'cita_id');
    }

    public function registroAtencion(): HasOne
    {
        return $this->hasOne(RegistroAtencion::class, 'cita_id');
    }

    public function examenes(): HasMany
    {
        return $this->hasMany(ExamenLaboratorio::class, 'cita_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'cita_id');
    }

    /**
     * CU13: horas ocupadas por citas no canceladas del médico en la fecha (RN09).
     * Solo devuelve las horas, sin datos de los pacientes.
     */
    public static function listarOcupadas(int $medicoId, Carbon $fecha): Collection
    {
        return self::where('medico_id', $medicoId)
            ->whereDate('fecha', $fecha->toDateString())
            ->where('estado', '<>', 'Cancelada')
            ->orderBy('hora_inicio')
            ->get(['hora_inicio', 'hora_fin']);
    }

    /** CU15, CU16, CU17, CU20, CU25: obtiene la cita con sus datos principales. */
    public static function obtener(int $id): self
    {
        return self::with(['paciente', 'medico', 'especialidad'])->findOrFail($id);
    }

    /**
     * CU14, CU15: indica si otra cita no cancelada del médico se superpone con el rango (RN07, RN09).
     * $exceptoId excluye la propia cita al reprogramar.
     */
    public static function existeConflicto(int $medicoId, Carbon $fecha, string $horaInicio, string $horaFin, ?int $exceptoId = null): bool
    {
        return self::where('medico_id', $medicoId)
            ->whereDate('fecha', $fecha->toDateString())
            ->where('estado', '<>', 'Cancelada')
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($exceptoId, fn ($q) => $q->where('id', '<>', $exceptoId))
            ->exists();
    }

    /** CU14: registra la cita en estado Programada y recupera el código CM-0001 que asignó la base de datos. */
    public static function crear(array $datos): self
    {
        return self::create($datos + ['estado' => 'Programada'])->refresh();
    }
}
