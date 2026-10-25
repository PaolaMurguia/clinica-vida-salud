<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entidad PAGO: cobro de la consulta. monto_paciente es una columna generada y la
 * cobertura la aplica el trigger trg_aplicar_cobertura (RN19).
 */
class Pago extends Model
{
    protected $table = 'pago';
    public $timestamps = false;

    protected $fillable = [
        'cita_id', 'seguro_id', 'usuario_id', 'monto_consulta', 'porcentaje_cobertura',
        'metodo_pago', 'estado', 'fecha_pago',
    ];

    protected function casts(): array
    {
        return [
            'monto_consulta'       => 'decimal:2',
            'porcentaje_cobertura' => 'decimal:2',
            'monto_paciente'       => 'decimal:2',
            'fecha_pago'           => 'datetime',
            'fecha_registro'       => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function seguro(): BelongsTo
    {
        return $this->belongsTo(Seguro::class, 'seguro_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
