<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entidad REGISTRO_ATENCION: diagnóstico, tratamiento y signos vitales de una cita atendida.
 */
class RegistroAtencion extends Model
{
    protected $table = 'registro_atencion';
    public $timestamps = false;

    protected $fillable = [
        'cita_id', 'diagnostico', 'tratamiento', 'indicaciones', 'peso_kg', 'talla_cm',
        'presion_arterial', 'temperatura_c', 'dictado_voz',
    ];

    protected function casts(): array
    {
        return [
            'peso_kg'        => 'decimal:2',
            'talla_cm'       => 'decimal:1',
            'temperatura_c'  => 'decimal:1',
            'dictado_voz'    => 'boolean',
            'fecha_registro' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }
}
