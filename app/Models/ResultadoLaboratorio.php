<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /**
     * CU22: guarda el valor con el rango aplicado y su interpretación (Bajo, Normal o Alto). trg_interpretar_resultado
     * vuelve a calcular ambos con la edad del paciente en la fecha de la muestra (RN16) y uq_resultado impide repetir.
     */
    public static function registrar(int $examenId, int $parametroId, float $valor, RangoReferencia $rango): self
    {
        return self::create([
            'examen_id'        => $examenId,
            'parametro_id'     => $parametroId,
            'valor'            => $valor,
            'valor_minimo_ref' => $rango->valor_minimo,
            'valor_maximo_ref' => $rango->valor_maximo,
            'interpretacion'   => match (true) {
                $valor < (float) $rango->valor_minimo => 'Bajo',
                $valor > (float) $rango->valor_maximo => 'Alto',
                default                               => 'Normal',
            },
        ])->refresh();
    }

    /** CU22: resultados del examen con su parámetro, ordenados por nombre del parámetro. */
    public static function listarPorExamen(int $examenId): Collection
    {
        return self::with('parametro')->where('examen_id', $examenId)->get()
            ->sortBy(fn (self $resultado) => $resultado->parametro->nombre)->values();
    }
}
