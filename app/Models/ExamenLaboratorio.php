<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Entidad EXAMEN_LABORATORIO: análisis solicitado por un médico (RN20). El código EX-00001 lo genera la base de datos.
 */
class ExamenLaboratorio extends Model
{
    protected $table = 'examen_laboratorio';
    public $timestamps = false;

    protected $fillable = [
        'paciente_id', 'medico_id', 'cita_id', 'tipo_examen', 'fecha_muestra', 'observacion',
        'estado', 'usuario_registro_id', 'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_muestra'   => 'date',
            'fecha_registro'  => 'datetime',
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

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_registro_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoLaboratorio::class, 'examen_id');
    }

    /**
     * CU20, CU22: exámenes del paciente, del más reciente al más antiguo. Con $soloConResultados, solo los
     * Registrados o Revisados (lo que ve el paciente, D8).
     */
    public static function listarPorPaciente(int $pacienteId, bool $soloConResultados = false): Collection
    {
        return self::with(['medico', 'resultados.parametro'])
            ->where('paciente_id', $pacienteId)
            ->when($soloConResultados, fn ($q) => $q->whereIn('estado', ['Registrado', 'Revisado']))
            ->orderByDesc('fecha_solicitud')
            ->get();
    }
}
