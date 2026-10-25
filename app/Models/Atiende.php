<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Entidad ATIENDE: relación médico-especialidad (tabla medico_especialidad).
 */
class Atiende extends Pivot
{
    protected $table = 'medico_especialidad';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = ['medico_id', 'especialidad_id'];

    /** CU06: sincroniza las especialidades del médico, agregando y quitando (B5). */
    public static function asignar(Medico $medico, array $especialidadIds): void
    {
        $medico->especialidades()->sync($especialidadIds);
    }
}
