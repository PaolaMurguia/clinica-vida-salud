<?php

namespace App\Services;

use App\Models\Bitacora;
use Throwable;

/**
 * Servicio centralizado de auditoría (B8, RF15). Completa el usuario, la IP y el navegador
 * de la petición y registra el evento con M.Bitacora::registrar().
 */
class ServicioBitacora
{
    public const ACCESO = 'Acceso';
    public const ACCION = 'Accion';

    /**
     * Registra un acceso o una acción. Si falla, no interrumpe la operación del usuario:
     * el error queda en el log de Laravel (RNF08).
     */
    public static function registrar(
        string $tipo,
        string $accion,
        ?string $modulo = null,
        ?int $registroId = null,
        ?string $descripcion = null,
        ?int $usuarioId = null,
    ): void {
        try {
            Bitacora::registrar([
                'usuario_id'  => $usuarioId ?? auth()->id(),
                'tipo'        => $tipo,
                'accion'      => $accion,
                'modulo'      => $modulo,
                'registro_id' => $registroId,
                'descripcion' => $descripcion,
                'ip'          => request()->ip(),
                'navegador'   => request()->userAgent(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
