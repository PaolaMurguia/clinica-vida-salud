<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Entidad HORARIO: horario de atención de un médico (tabla horario_atencion, RF05).
 * dia_semana: 1 = lunes ... 7 = domingo (ISO).
 */
class Horario extends Model
{
    protected $table = 'horario_atencion';
    public $timestamps = false;

    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected $fillable = ['medico_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'duracion_cita_min', 'estado'];

    protected function casts(): array
    {
        return [
            'dia_semana'        => 'integer',
            'duracion_cita_min' => 'integer',
            'estado'            => 'boolean',
        ];
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    /** CU07: horarios del médico (activos e inactivos) ordenados por día y hora. */
    public static function listar(int $medicoId): Collection
    {
        return self::where('medico_id', $medicoId)
            ->orderBy('dia_semana')->orderBy('hora_inicio')
            ->get();
    }

    /** CU07: indica si el rango se superpone con otro horario activo del mismo médico y día. */
    public static function seSuperpone(int $medicoId, int $dia, string $horaInicio, string $horaFin, ?int $exceptoId = null): bool
    {
        return self::where('medico_id', $medicoId)
            ->where('dia_semana', $dia)
            ->where('estado', true)
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($exceptoId, fn ($q) => $q->where('id', '<>', $exceptoId))
            ->exists();
    }

    /** CU07: registra el horario. */
    public static function crear(array $datos): self
    {
        return self::create($datos);
    }

    /**
     * CU07, CU13: divide el horario en franjas de la duración configurada.
     * Devuelve [['inicio' => '08:00', 'fin' => '08:30'], ...].
     */
    public function generarFranjas(): array
    {
        $franjas = [];
        $inicio = Carbon::createFromFormat('H:i', substr($this->hora_inicio, 0, 5));
        $fin = Carbon::createFromFormat('H:i', substr($this->hora_fin, 0, 5));

        while ($inicio->copy()->addMinutes($this->duracion_cita_min)->lte($fin)) {
            $siguiente = $inicio->copy()->addMinutes($this->duracion_cita_min);
            $franjas[] = ['inicio' => $inicio->format('H:i'), 'fin' => $siguiente->format('H:i')];
            $inicio = $siguiente;
        }

        return $franjas;
    }

    /** CU07: activa o desactiva el horario (no se edita: se desactiva y se agrega otro, B3). */
    public function cambiarEstado(bool $activo): void
    {
        $this->update(['estado' => $activo]);
    }

    /** CU13: horarios activos del médico para el día de la semana de la fecha. */
    public static function obtenerDelDia(int $medicoId, Carbon $fecha): Collection
    {
        return self::where('medico_id', $medicoId)
            ->where('dia_semana', $fecha->isoWeekday())
            ->where('estado', true)
            ->orderBy('hora_inicio')
            ->get();
    }
}
