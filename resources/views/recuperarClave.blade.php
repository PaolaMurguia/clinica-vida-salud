{{--
    UI_RecuperarClave (CU27 – Recuperar contraseña)
    - solicitar()   → CtrlRecuperacion@solicitarEnlace (sin $token)
    - restablecer() → CtrlRecuperacion@restablecer (con $token válido)
    La ruta del formulario valida el enlace con Recuperacion::validar() y entrega $enlaceValido.
--}}
@extends('plantilla')

@section('titulo', 'Recuperar contraseña')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Recuperar contraseña</h1>

                @if (! isset($token))
                    {{-- Pasos 2 y 3: correo o nombre de usuario --}}
                    <p class="text-muted">Ingrese su correo o nombre de usuario y le enviaremos un enlace para crear una contraseña nueva.</p>
                    <form method="POST" action="{{ route('recuperacion.enlace') }}" data-validar novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="login" class="form-label">Correo o nombre de usuario</label>
                            <input type="text" id="login" name="login" value="{{ old('login') }}" maxlength="120"
                                   class="form-control @error('login') is-invalid @enderror" required autofocus>
                            <div class="invalid-feedback">Ingrese su correo o nombre de usuario.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
                    </form>
                @elseif ($enlaceValido)
                    {{-- Paso 7: contraseña nueva y confirmación --}}
                    <form method="POST" action="{{ route('recuperacion.restablecer') }}" data-validar novalidate>
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="mb-3">
                            <label for="clave" class="form-label">Contraseña nueva</label>
                            <input type="password" id="clave" name="clave" maxlength="255" autocomplete="new-password"
                                   class="form-control @error('clave') is-invalid @enderror" required aria-describedby="politicaClave">
                            <div class="invalid-feedback">Ingrese la contraseña nueva.</div>
                            <div id="politicaClave" class="form-text">
                                Al menos 8 caracteres, con mayúsculas, minúsculas, números y un símbolo.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="clave_confirmation" class="form-label">Confirmación</label>
                            <input type="password" id="clave_confirmation" name="clave_confirmation" maxlength="255"
                                   autocomplete="new-password" class="form-control" data-coincide="clave" required>
                            <div class="invalid-feedback">La confirmación no coincide.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Restablecer contraseña</button>
                    </form>
                @else
                    {{-- Excepción: enlace vencido, ya usado o alterado --}}
                    <div class="alert alert-warning mb-3">
                        El enlace venció, ya se usó o no es válido. Puede solicitar uno nuevo.
                    </div>
                    <a href="{{ route('recuperacion.solicitar') }}" class="btn btn-primary w-100">Solicitar otro enlace</a>
                @endif

                <div class="mt-3 small text-center">
                    <a href="{{ route('login') }}">Volver a iniciar sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
