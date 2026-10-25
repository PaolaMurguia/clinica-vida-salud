<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Entidad BITACORA: accesos y acciones críticas (RF15). Solo se inserta y se consulta.
 */
class Bitacora extends Model
{
    protected $table = 'bitacora';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 'tipo', 'accion', 'modulo', 'registro_id', 'descripcion', 'ip', 'navegador',
    ];

    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Registra un acceso o una acción; recorta los textos al tamaño de las columnas. */
    public static function registrar(array $datos): self
    {
        $datos['descripcion'] = isset($datos['descripcion']) ? Str::limit($datos['descripcion'], 297) : null;
        $datos['navegador'] = isset($datos['navegador']) ? Str::limit($datos['navegador'], 197) : null;

        return self::create($datos);
    }

    /** CU04: registros más recientes, paginados. */
    public static function listar(int $porPagina = 20): LengthAwarePaginator
    {
        return self::with('usuario')->orderByDesc('fecha_hora')->orderByDesc('id')->paginate($porPagina);
    }

    /**
     * CU04: consulta filtrada por usuario, rango de fechas y módulo.
     * Devuelve la consulta para paginarla o exportarla a CSV.
     */
    public static function buscar(array $filtros): Builder
    {
        return self::with('usuario')
            ->when($filtros['usuario_id'] ?? null, fn ($q, $id) => $q->where('usuario_id', $id))
            ->when($filtros['modulo'] ?? null, fn ($q, $modulo) => $q->where('modulo', $modulo))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->where('fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->where('fecha_hora', '<', Carbon::parse($hasta)->addDay()))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id');
    }
}
