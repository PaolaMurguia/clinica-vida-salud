<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Entidad ANTECEDENTE: antecedentes médicos declarados por el paciente o registrados por un médico.
 * No se eliminan (preservación histórica, decisión A5).
 */
class Antecedente extends Model
{
    protected $table = 'antecedente';
    public $timestamps = false;

    public const TIPOS = ['Alergia', 'Enfermedad cronica', 'Medicamento', 'Cirugia', 'Familiar', 'Otro'];
    public const ORIGEN_PACIENTE = 'Paciente';
    public const ORIGEN_MEDICO = 'Médico';

    protected $fillable = ['historia_clinica_id', 'tipo', 'descripcion', 'origen', 'usuario_id'];

    protected function casts(): array
    {
        return ['fecha_registro' => 'datetime'];
    }

    public function historia(): BelongsTo
    {
        return $this->belongsTo(Historia::class, 'historia_clinica_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** CU11: antecedentes de la historia, del más antiguo al más reciente. */
    public static function listar(int $historiaId): Collection
    {
        return self::where('historia_clinica_id', $historiaId)
            ->orderBy('fecha_registro')->orderBy('id')
            ->get();
    }

    /** CU11: registra un antecedente (el trigger actualiza la fecha de la historia). */
    public static function crear(array $datos): self
    {
        return self::create($datos);
    }
}
