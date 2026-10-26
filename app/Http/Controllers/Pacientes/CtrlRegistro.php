<?php

namespace App\Http\Controllers\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pacientes\RegistrarRequest;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioBitacora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Control CtrlRegistro: CU10 – Registrarse como paciente.
 */
class CtrlRegistro extends Controller
{
    /**
     * CU10 – Registrarse como paciente: crea la ficha (o vincula la existente si coinciden la fecha
     * de nacimiento y los apellidos) y la cuenta con rol Paciente en una sola transacción; el nombre
     * de usuario es el CI (A7). Después inicia la sesión y muestra el portal.
     */
    public function registrar(RegistrarRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $sinClaves = $request->except(['clave', 'clave_confirmation']);

        // Paso 5: ¿ya existe la ficha?
        $ficha = Paciente::buscarPorCI($datos['ci']);

        // Excepción 1: el CI ya tiene una cuenta
        if ($ficha?->usuario || Usuario::buscarPorLogin($datos['ci'])) {
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'REGISTRO_RECHAZADO', 'Pacientes', $ficha?->id,
                "Autorregistro rechazado: el CI {$datos['ci']} ya tiene una cuenta");
            return back()->withInput($sinClaves)
                ->with('error', 'El CI ya tiene una cuenta. Si olvidó su contraseña, use «¿Olvidó su contraseña?» en el inicio de sesión.');
        }

        // Excepción 2: el CI existe pero los datos no coinciden (o la ficha está inactiva)
        $normalizar = fn (string $texto) => Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($texto))));
        $coincide = $ficha
            && $ficha->estado
            && $ficha->fecha_nacimiento->toDateString() === $datos['fecha_nacimiento']
            && $normalizar($ficha->apellidos) === $normalizar($datos['apellidos']);
        if ($ficha && ! $coincide) {
            ServicioBitacora::registrar(ServicioBitacora::ACCION, 'REGISTRO_RECHAZADO', 'Pacientes', $ficha->id,
                "Autorregistro rechazado: los datos no coinciden con la ficha {$ficha->codigo}");
            return back()->withInput($sinClaves)
                ->with('error', 'Los datos no coinciden con la ficha registrada en la clínica. Acuda a recepción para crear su cuenta.');
        }

        // Pasos 5 y 6: ficha nueva o vinculada y cuenta en la misma transacción
        $usuario = DB::transaction(function () use ($ficha, $datos) {
            if ($ficha) {
                $ficha->actualizar(['acepta_privacidad' => true] + ($ficha->correo ? [] : ['correo' => $datos['correo']]));
                $paciente = $ficha;
            } else {
                $paciente = Paciente::crear(Arr::except($datos, ['clave', 'acepta_privacidad']) + ['acepta_privacidad' => true]);
            }

            return Usuario::crear([
                'nombre_usuario' => $paciente->ci,
                'correo'         => $datos['correo'],
                'clave'          => $datos['clave'],
                'rol_id'         => Rol::obtener(Rol::PACIENTE)->id,
                'paciente_id'    => $paciente->id,
            ]);
        });

        // Paso 7: bitácora, sesión y portal
        ServicioBitacora::registrar(ServicioBitacora::ACCION, 'REGISTRO_PACIENTE', 'Pacientes', $usuario->paciente_id,
            ($ficha ? "Cuenta vinculada a la ficha {$ficha->codigo}" : 'Autorregistro de paciente') . " (CI {$usuario->nombre_usuario})",
            $usuario->id);
        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->put('rol', Rol::PACIENTE);

        return redirect()->route('portal')->with('exito', 'Su cuenta fue creada. Su nombre de usuario es su CI.');
    }
}
