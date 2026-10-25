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
}
