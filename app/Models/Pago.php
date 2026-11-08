<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /** CU25: pago de la cita, si ya existe (un solo pago por cita). */
    public static function obtenerPorCita(int $citaId): ?self
    {
        return self::where('cita_id', $citaId)->first();
    }

    /**
     * CU25: registra el pago (Pagado o Pendiente). La base de datos asigna el comprobante PG-00001, aplica la
     * cobertura del seguro vigente (trg_aplicar_cobertura, RN19) y calcula el monto del paciente.
     */
    public static function crear(array $datos): self
    {
        return self::create($datos)->refresh();
    }

    /** CU25: completa un pago pendiente con el método de pago (ck_pago_completo). */
    public function completar(string $metodoPago): void
    {
        $this->update(['metodo_pago' => $metodoPago, 'estado' => 'Pagado', 'fecha_pago' => now()]);
    }

    /** CU25: pagos pendientes, los más antiguos primero, para su seguimiento. */
    public static function listarPendientes(): Collection
    {
        return self::with(['cita.paciente', 'cita.especialidad'])
            ->where('estado', 'Pendiente')
            ->orderBy('fecha_registro')
            ->get();
    }
}
