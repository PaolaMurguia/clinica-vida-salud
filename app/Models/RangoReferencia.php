<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entidad RANGO_REFERENCIA: valores normales de un parámetro según la edad (RN16).
 */
class RangoReferencia extends Model
{
    protected $table = 'rango_referencia';
    public $timestamps = false;

    protected $fillable = ['parametro_id', 'edad_minima', 'edad_maxima', 'valor_minimo', 'valor_maximo'];

    protected function casts(): array
    {
        return [
            'edad_minima'  => 'integer',
            'edad_maxima'  => 'integer',
            'valor_minimo' => 'decimal:2',
            'valor_maximo' => 'decimal:2',
        ];
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(ParametroLaboratorio::class, 'parametro_id');
    }

    /** CU22: rango del parámetro que corresponde a la edad (RN16; los rangos no se superponen, ex_rango_no_solapado). */
    public static function obtenerPorEdad(int $parametroId, int $edad): ?self
    {
        return self::where('parametro_id', $parametroId)
            ->where('edad_minima', '<=', $edad)
            ->where('edad_maxima', '>=', $edad)
            ->first();
    }
}
