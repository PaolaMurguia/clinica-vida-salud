<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entidad HISTORIA: historia clínica del paciente (tabla historia_clinica).
 */
class Historia extends Model
{
    protected $table = 'historia_clinica';
    public $timestamps = false;

    public const TIPOS_SANGRE = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    protected $fillable = ['paciente_id', 'tipo_sangre', 'observaciones', 'fecha_actualizacion'];

    protected function casts(): array
    {
        return [
            'fecha_creacion'      => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function antecedentes(): HasMany
    {
        return $this->hasMany(Antecedente::class, 'historia_clinica_id');
    }

    /** CU11: obtiene la historia del paciente; si no existe, la crea (B7). */
    public static function obtener(int $pacienteId): self
    {
        return self::firstOrCreate(['paciente_id' => $pacienteId])->refresh();
    }
}
