<?php

namespace App\Http\Controllers\Acceso;

use App\Http\Controllers\Controller;
use App\Http\Requests\Acceso\RestablecerRequest;
use App\Http\Requests\Acceso\SolicitarEnlaceRequest;
use App\Models\Recuperacion;
use App\Models\Usuario;
use App\Services\ServicioBitacora;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Control CtrlRecuperacion: CU27 – Recuperar contraseña.
 * La validación del enlace al abrirlo la hace la ruta del formulario con Recuperacion::validar()
 * (ver docs/diferencias_con_documento.md).
 */
class CtrlRecuperacion extends Controller
{
    /** Respuesta única, exista o no la cuenta (RN22). */
    private const MENSAJE_SOLICITUD = 'Si la cuenta existe, recibirá en su correo un enlace para restablecer la contraseña.';

    /**
     * CU27 – Recuperar contraseña (pasos 2 a 5): genera un enlace de un solo uso, guarda solo
     * su hash con vencimiento a los 60 minutos y lo envía por correo. Responde siempre lo mismo.
     */
    public function solicitarEnlace(SolicitarEnlaceRequest $request): RedirectResponse
    {
        $usuario = Usuario::buscarPorLogin($request->validated('login'));

        // Cuenta inexistente o inactiva: mismo mensaje y no se envía correo
        if (! $usuario || ! $usuario->estado) {
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'SOLICITUD_RECUPERACION_IGNORADA', 'Acceso', $usuario?->id,
                'Cuenta inexistente o inactiva', $usuario?->id);
            return back()->with('exito', self::MENSAJE_SOLICITUD);
        }

        // Más de 3 solicitudes en una hora: no se genera un enlace nuevo (RN22)
        if (Recuperacion::contarRecientes($usuario->id) >= Recuperacion::MAX_SOLICITUDES_HORA) {
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'LIMITE_RECUPERACION', 'Acceso', $usuario->id,
                'Límite de 3 solicitudes por hora alcanzado', $usuario->id);
            return back()->with('exito', self::MENSAJE_SOLICITUD);
        }

        try {
            // el trigger trg_limitar_recuperaciones vuelve a controlar el límite en la base de datos
            $token = DB::transaction(fn () => Recuperacion::crear($usuario->id, $request->ip()));
        } catch (QueryException $e) {
            report($e);
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'LIMITE_RECUPERACION', 'Acceso', $usuario->id,
                'Solicitud rechazada por la base de datos', $usuario->id);
            return back()->with('exito', self::MENSAJE_SOLICITUD);
        }

        $enlace = route('recuperacion.form', $token);
        try {
            Mail::raw(
                "Recibimos una solicitud para restablecer su contraseña en " . config('app.name') . ".\n\n"
                . "Abra este enlace para crear una contraseña nueva (vence en " . Recuperacion::MINUTOS_VIGENCIA . " minutos y se usa una sola vez):\n"
                . "$enlace\n\nSi usted no lo solicitó, ignore este mensaje.",
                fn ($mensaje) => $mensaje->to($usuario->correo)->subject('Recuperación de contraseña - ' . config('app.name'))
            );
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'SOLICITUD_RECUPERACION', 'Acceso', $usuario->id,
                'Enlace de recuperación enviado', $usuario->id);
        } catch (Throwable $e) {
            // Falla del correo: queda registrada y el usuario puede volver a solicitar
            report($e);
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'ENVIO_CORREO_FALLIDO', 'Acceso', $usuario->id,
                'No se pudo enviar el enlace de recuperación', $usuario->id);
        }

        return back()->with('exito', self::MENSAJE_SOLICITUD);
    }

    /**
     * CU27 – Recuperar contraseña (pasos 6 a 9): vuelve a validar el enlace, guarda el nuevo hash,
     * marca el enlace como usado, desbloquea la cuenta e invalida las sesiones abiertas.
     */
    public function restablecer(RestablecerRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        // Paso 6 (de nuevo, por seguridad): el enlace pudo vencer o usarse desde que se abrió
        $solicitud = Recuperacion::validar($datos['token']);
        if (! $solicitud || ! $solicitud->usuario->estado) {
            return redirect()->route('recuperacion.form', $datos['token']);
        }

        // Paso 8: nuevo hash, enlace usado y cuenta desbloqueada en una sola transacción.
        // Las sesiones abiertas se cierran solas: su hash de contraseña ya no coincide (auth.session).
        $usuario = $solicitud->usuario;
        DB::transaction(function () use ($usuario, $solicitud, $datos) {
            $usuario->actualizarClave($datos['clave']);
            $usuario->reiniciarIntentos();
            $solicitud->marcarUsado();
        });
        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'RESTABLECER_CLAVE', 'Acceso', $usuario->id,
            'Contraseña restablecida con enlace de recuperación', $usuario->id);

        // Paso 9: inicio de sesión
        return redirect()->route('login')->with('exito', 'Su contraseña fue restablecida. Ya puede iniciar sesión.');
    }
}
