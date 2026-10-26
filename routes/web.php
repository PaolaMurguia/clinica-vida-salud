<?php

use App\Http\Controllers\Acceso\CtrlClave;
use App\Http\Controllers\Acceso\CtrlRecuperacion;
use App\Http\Controllers\Acceso\CtrlSesion;
use App\Http\Controllers\Administracion\CtrlBitacora;
use App\Http\Controllers\Administracion\CtrlUsuarios;
use App\Models\Recuperacion;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web del ciclo 1, agrupadas por módulo
|--------------------------------------------------------------------------
| Middlewares (bootstrap/app.php):
|  - guest          sin sesión; con sesión abierta lleva al menú o al portal (CU01)
|  - auth           con sesión; sin ella lleva al inicio de sesión (RNF03)
|  - sin.cache      páginas internas sin caché (CU02)
|  - clave.temporal con contraseña temporal solo se puede cambiarla (RN23)
|  - rol:...        roles autorizados (RN12)
*/

// «/» lleva al inicio de sesión; con sesión abierta, guest redirige al menú o al portal
Route::redirect('/', '/login');

// =====================================================================
// Módulo Acceso: CU01, CU02, CU26, CU27
// =====================================================================

// --- Sin sesión ---
Route::middleware('guest')->group(function () {
    // CU01 – Iniciar sesión: UI_Login (V.login) → CtrlSesion@autenticar
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [CtrlSesion::class, 'autenticar'])->name('login.autenticar');

    // CU27 – Recuperar contraseña: UI_RecuperarClave (V.recuperarClave)
    // solicitar() → CtrlRecuperacion@solicitarEnlace
    Route::view('/recuperar-clave', 'recuperarClave')->name('recuperacion.solicitar');
    Route::post('/recuperar-clave', [CtrlRecuperacion::class, 'solicitarEnlace'])->name('recuperacion.enlace');
    // Paso 6: al abrir el enlace se valida con Recuperacion::validar(), sin métodos nuevos en el controlador
    Route::get('/recuperar-clave/{token}', function (string $token) {
        $solicitud = Recuperacion::validar($token);

        return view('recuperarClave', [
            'token'        => $token,
            'enlaceValido' => $solicitud !== null && $solicitud->usuario->estado,
        ]);
    })->name('recuperacion.form');
    // restablecer() → CtrlRecuperacion@restablecer (vuelve a validar el enlace)
    Route::post('/restablecer-clave', [CtrlRecuperacion::class, 'restablecer'])->name('recuperacion.restablecer');
});

// --- Con sesión (también con contraseña temporal) ---
// auth.session cierra las sesiones cuyo hash de contraseña ya no coincide (CU26, CU27)
Route::middleware(['auth', 'auth.session', 'sin.cache'])->group(function () {
    // CU02 – Cerrar sesión: UI_Menu.salir() → CtrlSesion@cerrarSesion
    Route::post('/logout', [CtrlSesion::class, 'cerrarSesion'])->name('logout');

    // CU26 – Cambiar contraseña: UI_CambiarClave (V.cambiarClave) → CtrlClave@update
    Route::view('/cambiar-clave', 'cambiarClave')->name('clave.edit');
    Route::put('/cambiar-clave', [CtrlClave::class, 'update'])->name('clave.update');
});

// --- Con sesión y contraseña definitiva ---
Route::middleware(['auth', 'auth.session', 'sin.cache', 'clave.temporal'])->group(function () {
    // CU01, paso 9: menú principal (B1) para el personal y portal para el paciente
    Route::view('/menu', 'menu')->middleware('rol:Administrador,Recepcionista,Médico')->name('menu');
    Route::view('/portal', 'portal')->middleware('rol:Paciente')->name('portal');
});

// =====================================================================
// Módulo Administración: CU03, CU04, CU05, CU06, CU07 (solo Administrador)
// =====================================================================
Route::middleware(['auth', 'auth.session', 'sin.cache', 'clave.temporal', 'rol:Administrador'])->group(function () {
    // CU03 – Gestionar usuarios y roles: UI_Usuarios (V.usuarios) → CtrlUsuarios
    Route::get('/usuarios', [CtrlUsuarios::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [CtrlUsuarios::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{usuario}', [CtrlUsuarios::class, 'update'])->name('usuarios.update');
    Route::post('/usuarios/{usuario}/restablecer-clave', [CtrlUsuarios::class, 'restablecerClave'])->name('usuarios.restablecerClave');
    Route::patch('/usuarios/{usuario}/estado', [CtrlUsuarios::class, 'cambiarEstado'])->name('usuarios.cambiarEstado');

    // CU04 – Consultar bitácora: UI_Bitacora (V.bitacora) → CtrlBitacora (solo lectura)
    Route::get('/bitacora', [CtrlBitacora::class, 'index'])->name('bitacora.index');
    Route::get('/bitacora/filtrar', [CtrlBitacora::class, 'filtrar'])->name('bitacora.filtrar');
    Route::get('/bitacora/exportar', [CtrlBitacora::class, 'exportar'])->name('bitacora.exportar');
});
