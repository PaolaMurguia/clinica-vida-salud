<?php

use App\Http\Controllers\Acceso\CtrlClave;
use App\Http\Controllers\Acceso\CtrlSesion;
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
