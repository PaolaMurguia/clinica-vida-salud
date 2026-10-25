{{-- UI_Login (CU01 – Iniciar sesión): operación ingresar() → CtrlSesion@autenticar --}}
@extends('plantilla')

@section('titulo', 'Iniciar sesión')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h4 mb-3 text-center">Iniciar sesión</h1>

                <form method="POST" action="{{ route('login.autenticar') }}" data-validar novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="login" class="form-label">Usuario o correo</label>
                        <input type="text" id="login" name="login" value="{{ old('login') }}" maxlength="120"
                               class="form-control @error('login') is-invalid @enderror" autocomplete="username" required autofocus>
                        <div class="invalid-feedback">Ingrese su usuario o correo.</div>
                    </div>
                    <div class="mb-3">
                        <label for="clave" class="form-label">Contraseña</label>
                        <input type="password" id="clave" name="clave" maxlength="255"
                               class="form-control @error('clave') is-invalid @enderror" autocomplete="current-password" required>
                        <div class="invalid-feedback">Ingrese su contraseña.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>

                <div class="d-flex justify-content-between mt-3 small">
                    @if (Route::has('recuperacion.solicitar'))
                        <a href="{{ route('recuperacion.solicitar') }}">¿Olvidó su contraseña?</a>
                    @endif
                    @if (Route::has('registro'))
                        <a href="{{ route('registro') }}">Crear cuenta de paciente</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
