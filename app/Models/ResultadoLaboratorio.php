<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entidad RESULTADO_LABORATORIO: valor de un parámetro; el trigger fija el rango y la interpretación (RN16).
 */
class ResultadoLaboratorio extends Model
{
    protected $table = 'resultado_laboratorio';
    public $timestamps = false;

    protected $fillable = ['examen_id', 'parametro_id', 'valor', 'valor_minimo_ref', 'valor_maximo_ref', 'interpretacion'];

    protected function casts(): array
    {
        return [
            'valor'            => 'decimal:2',
            'valor_minimo_ref' => 'decimal:2',
            'valor_maximo_ref' => 'decimal:2',
        ];
    }

    public function examen(): BelongsTo
    {
        return $this->belongsTo(ExamenLaboratorio::class, 'examen_id');
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(ParametroLaboratorio::class, 'parametro_id');
    }
}
