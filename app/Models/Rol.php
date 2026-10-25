<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Entidad ROL: Administrador, Recepcionista, Médico y Paciente.
 */
class Rol extends Model
{
    protected $table = 'rol';
    public $timestamps = false;

    public const ADMINISTRADOR = 'Administrador';
    public const RECEPCIONISTA = 'Recepcionista';
    public const MEDICO = 'Médico';
    public const PACIENTE = 'Paciente';

    protected $fillable = ['nombre', 'descripcion'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }

    /**
     * CU01, CU03, CU10: obtiene un rol por su id o por su nombre; sin argumento devuelve todos.
     */
    public static function obtener(int|string|null $clave = null): self|Collection|null
    {
        return match (true) {
            $clave === null => self::orderBy('id')->get(),
            is_int($clave)  => self::find($clave),
            default         => self::where('nombre', $clave)->first(),
        };
    }
}
