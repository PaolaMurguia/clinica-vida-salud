<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Entidad USUARIO: cuentas de acceso (CU01, CU03, CU04, CU10, CU11, CU26, CU27).
 */
class Usuario extends Authenticatable
{
    protected $table = 'usuario';
    public $timestamps = false;

    /** Minutos de bloqueo por nivel (RNF04): 1 = 5 min, 2 = 15 min, 3 = 60 min. */
    public const MINUTOS_BLOQUEO = [1 => 5, 2 => 15, 3 => 60];

    /** Intentos fallidos consecutivos que provocan un bloqueo. */
    public const MAX_INTENTOS = 5;

    /** La tabla no tiene columna remember_token. */
    protected $rememberTokenName = '';

    protected $fillable = [
        'nombre_usuario', 'correo', 'password_hash', 'debe_cambiar_clave', 'rol_id',
        'medico_id', 'paciente_id', 'puede_marcar_atendida', 'estado',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'debe_cambiar_clave'    => 'boolean',
            'puede_marcar_atendida' => 'boolean',
            'estado'                => 'boolean',
            'bloqueado_hasta'       => 'datetime',
            'ultimo_acceso'         => 'datetime',
            'fecha_creacion'        => 'datetime',
        ];
    }

    // ----- Autenticación de Laravel sobre la columna password_hash -----

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // ----- Relaciones -----

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function recuperaciones(): HasMany
    {
        return $this->hasMany(Recuperacion::class, 'usuario_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'usuario_id');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'usuario_id');
    }

    // ----- Métodos del diagrama de clases -----

    /** CU01, CU27: busca por nombre de usuario o correo, sin distinguir mayúsculas. */
    public static function buscarPorLogin(string $login): ?self
    {
        $login = mb_strtolower(trim($login));

        return self::with('rol')
            ->whereRaw('lower(nombre_usuario) = ?', [$login])
            ->orWhereRaw('lower(correo) = ?', [$login])
            ->first();
    }

    /** CU01: tras un ingreso correcto se reinician el contador y el nivel de bloqueo (B9). */
    public function reiniciarIntentos(): void
    {
        // columnas de seguridad fuera de $fillable: solo se modifican desde el propio modelo
        $this->forceFill(['intentos_fallidos' => 0, 'nivel_bloqueo' => 0, 'bloqueado_hasta' => null])->save();
    }

    /**
     * CU01, CU26: suma un intento fallido; al quinto consecutivo bloquea la cuenta
     * 5, 15 y luego 60 minutos, y repite 60 minutos ante nuevos fallos (RNF04, B9).
     * Devuelve los minutos de bloqueo aplicados, o null si todavía no se bloquea.
     */
    public function registrarIntentoFallido(): ?int
    {
        $this->intentos_fallidos++;

        if ($this->intentos_fallidos < self::MAX_INTENTOS) {
            $this->save();
            return null;
        }

        $this->nivel_bloqueo = min($this->nivel_bloqueo + 1, 3);
        $minutos = self::MINUTOS_BLOQUEO[$this->nivel_bloqueo];
        $this->bloqueado_hasta = now()->addMinutes($minutos);
        $this->intentos_fallidos = 0;
        $this->save();

        return $minutos;
    }

    /** CU03: cuentas con su rol y médico vinculado. */
    public static function listar(): Collection
    {
        return self::with(['rol', 'medico'])->orderBy('nombre_usuario')->get();
    }

    /**
     * CU03, CU10: crea la cuenta guardando solo el hash de la contraseña (RNF01) y recupera
     * los valores por defecto de la base de datos (estado, debe_cambiar_clave, fechas).
     */
    public static function crear(array $datos): self
    {
        $datos['password_hash'] = Hash::make($datos['clave']);
        unset($datos['clave']);

        return self::create($datos)->refresh();
    }

    /** CU03: actualiza los datos de la cuenta (no la contraseña). */
    public function actualizar(array $datos): void
    {
        $this->update($datos);
    }

    /** CU03: contraseña temporal que el usuario debe cambiar al ingresar; desbloquea la cuenta (RN23). */
    public function marcarTemporal(string $claveTemporal): void
    {
        $this->update([
            'password_hash'      => Hash::make($claveTemporal),
            'debe_cambiar_clave' => true,
        ]);
        $this->reiniciarIntentos();
    }

    /** CU03: aplica la acción elegida: activar, desactivar o desbloquear (B2). */
    public function actualizarEstado(string $accion): void
    {
        match ($accion) {
            'activar'     => $this->update(['estado' => true]),
            'desactivar'  => $this->update(['estado' => false]),
            'desbloquear' => $this->reiniciarIntentos(),
        };
    }

    /** CU04: obtiene un usuario por su identificador. */
    public static function obtener(int $id): self
    {
        return self::with('rol')->findOrFail($id);
    }

    /** CU11: el correo del perfil del paciente también es el de su cuenta. */
    public function actualizarCorreo(string $correo): void
    {
        $this->update(['correo' => $correo]);
    }

    /** CU26: compara la contraseña con el hash almacenado (password_verify). */
    public function verificarClave(string $clave): bool
    {
        return Hash::check($clave, $this->password_hash);
    }

    /** CU26, CU27: guarda el nuevo hash y quita la marca de contraseña temporal. */
    public function actualizarClave(string $nuevaClave): void
    {
        $this->update([
            'password_hash'      => Hash::make($nuevaClave),
            'debe_cambiar_clave' => false,
        ]);
    }
}
