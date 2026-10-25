<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Entidad MEDICO (RF03). El código M-0001 lo genera la base de datos.
 */
class Medico extends Model
{
    protected $table = 'medico';
    public $timestamps = false;

    protected $fillable = ['ci', 'nro_registro_prof', 'nombres', 'apellidos', 'telefono', 'correo', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidad::class, 'medico_especialidad', 'medico_id', 'especialidad_id')
            ->using(Atiende::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'medico_id');
    }

    public function bloqueos(): HasMany
    {
        return $this->hasMany(Bloqueo::class, 'medico_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'medico_id');
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class, 'medico_id');
    }

    /** CU03, CU07: obtiene un médico por su identificador. */
    public static function obtener(int $id): self
    {
        return self::with('especialidades')->findOrFail($id);
    }

    /** CU06: médicos con sus especialidades, ordenados por apellido. */
    public static function listar(): Collection
    {
        return self::with('especialidades')->orderBy('apellidos')->orderBy('nombres')->get();
    }

    /** CU06: indica si el CI ya está registrado en otro médico. */
    public static function existeCI(string $ci, ?int $exceptoId = null): bool
    {
        return self::where('ci', trim($ci))
            ->when($exceptoId, fn ($q) => $q->where('id', '<>', $exceptoId))
            ->exists();
    }

    /** CU06: registra al médico y recupera el código que asignó la base de datos. */
    public static function crear(array $datos): self
    {
        return self::create($datos)->refresh();
    }

    /** CU06: actualiza los datos del médico. */
    public function actualizar(array $datos): void
    {
        $this->update($datos);
    }

    /** CU06: un médico inactivo no recibe citas nuevas y conserva su historial (RN04, RN11). */
    public function cambiarEstado(bool $activo): void
    {
        $this->update(['estado' => $activo]);
    }

    /** CU06: busca por nombre, apellido, CI o especialidad sin distinguir mayúsculas ni tildes. */
    public static function buscar(string $criterio): Collection
    {
        $patron = '%' . trim($criterio) . '%';

        return self::with('especialidades')
            ->where(function ($q) use ($patron) {
                $q->whereRaw("unaccent(nombres || ' ' || apellidos) ILIKE unaccent(?)", [$patron])
                  ->orWhere('ci', 'ILIKE', $patron)
                  ->orWhereHas('especialidades', fn ($e) => $e->whereRaw('unaccent(nombre) ILIKE unaccent(?)', [$patron]));
            })
            ->orderBy('apellidos')
            ->get();
    }

    /** CU13: médicos activos que atienden la especialidad. */
    public static function listarPorEspecialidad(int $especialidadId): Collection
    {
        return self::where('estado', true)
            ->whereHas('especialidades', fn ($q) => $q->where('especialidad.id', $especialidadId))
            ->orderBy('apellidos')
            ->get();
    }
}
