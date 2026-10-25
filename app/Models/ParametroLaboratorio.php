<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entidad PARAMETRO_LABORATORIO: parámetros que se miden en los análisis.
 */
class ParametroLaboratorio extends Model
{
    protected $table = 'parametro_laboratorio';
    public $timestamps = false;

    protected $fillable = ['nombre', 'unidad', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function rangos(): HasMany
    {
        return $this->hasMany(RangoReferencia::class, 'parametro_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoLaboratorio::class, 'parametro_id');
    }
}
