<?php

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Entidad PACIENTE (RF02). El código P-0001 lo genera la base de datos.
 */
class Paciente extends Model
{
    protected $table = 'paciente';
    public $timestamps = false;

    /** Límite de resultados de la búsqueda (CU09). */
    public const MAX_RESULTADOS = 50;

    protected $fillable = [
        'ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'correo', 'direccion',
        'contacto_emerg_nombre', 'contacto_emerg_parent', 'contacto_emerg_tel',
        'notif_correo', 'notif_alerta', 'acepta_privacidad', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento'  => 'date',
            'notif_correo'      => 'boolean',
            'notif_alerta'      => 'boolean',
            'acepta_privacidad' => 'boolean',
            'estado'            => 'boolean',
            'fecha_registro'    => 'datetime',
        ];
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class, 'paciente_id');
    }

    public function seguros(): HasMany
    {
        return $this->hasMany(Seguro::class, 'paciente_id');
    }

    public function historia(): HasOne
    {
        return $this->hasOne(Historia::class, 'paciente_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'paciente_id');
    }

    public function examenes(): HasMany
    {
        return $this->hasMany(ExamenLaboratorio::class, 'paciente_id');
    }

    /** CU08: pacientes ordenados por apellido, paginados. */
    public static function listar(int $porPagina = 15): LengthAwarePaginator
    {
        return self::orderBy('apellidos')->orderBy('nombres')->paginate($porPagina);
    }

    /** CU08: indica si el CI ya está registrado en otro paciente. */
    public static function existeCI(string $ci, ?int $exceptoId = null): bool
    {
        return self::where('ci', trim($ci))
            ->when($exceptoId, fn ($q) => $q->where('id', '<>', $exceptoId))
            ->exists();
    }

    /** CU08, CU10: registra al paciente y recupera el código que asignó la base de datos. */
    public static function crear(array $datos): self
    {
        return self::create($datos)->refresh();
    }

    /** CU08, CU11: actualiza los datos del paciente. */
    public function actualizar(array $datos): void
    {
        $this->update($datos);
    }

    /** CU08: se desactiva sin eliminar su historial (RN11). */
    public function desactivar(): void
    {
        $this->update(['estado' => false]);
    }

    /**
     * CU09: busca por CI, nombre o apellido sin distinguir mayúsculas ni tildes.
     * Trae un resultado más del límite para saber si hay que pedir que se precise la búsqueda.
     */
    public static function buscar(string $criterio): Collection
    {
        $patron = '%' . trim($criterio) . '%';

        return self::where(function ($q) use ($patron) {
                $q->where('ci', 'ILIKE', $patron)
                  ->orWhereRaw("unaccent(nombres || ' ' || apellidos) ILIKE unaccent(?)", [$patron])
                  ->orWhereRaw("unaccent(apellidos || ' ' || nombres) ILIKE unaccent(?)", [$patron]);
            })
            ->orderBy('apellidos')->orderBy('nombres')
            ->limit(self::MAX_RESULTADOS + 1)
            ->get();
    }

    /** CU09, CU11, CU12, CU13: obtiene un paciente por su identificador. */
    public static function obtener(int $id): self
    {
        return self::findOrFail($id);
    }

    /** CU10: busca la ficha por CI (para crearla o vincularla a la cuenta nueva). */
    public static function buscarPorCI(string $ci): ?self
    {
        return self::with('usuario')->where('ci', trim($ci))->first();
    }
}
