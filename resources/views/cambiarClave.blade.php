{{-- UI_CambiarClave (CU26 – Cambiar contraseña): operación cambiar() → CtrlClave@update --}}
@extends('plantilla')

@section('titulo', 'Cambiar contraseña')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Cambiar contraseña</h1>

                @if (auth()->user()->debe_cambiar_clave && ! session('aviso'))
                    <div class="alert alert-warning">Su contraseña es temporal. Debe cambiarla antes de continuar.</div>
                @endif

                <form method="POST" action="{{ route('clave.update') }}" data-validar novalidate>
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="clave_actual" class="form-label">Contraseña actual</label>
                        <input type="password" id="clave_actual" name="clave_actual" maxlength="255" autocomplete="current-password"
                               class="form-control @error('clave_actual') is-invalid @enderror" required>
                        <div class="invalid-feedback">Ingrese su contraseña actual.</div>
                    </div>
                    <div class="mb-3">
                        <label for="clave_nueva" class="form-label">Contraseña nueva</label>
                        <input type="password" id="clave_nueva" name="clave_nueva" maxlength="255" autocomplete="new-password"
                               class="form-control @error('clave_nueva') is-invalid @enderror" required aria-describedby="politicaClave">
                        <div class="invalid-feedback">Ingrese la contraseña nueva.</div>
                        <div id="politicaClave" class="form-text">
                            Al menos 8 caracteres, con mayúsculas, minúsculas, números y un símbolo.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="clave_nueva_confirmation" class="form-label">Confirmación</label>
                        <input type="password" id="clave_nueva_confirmation" name="clave_nueva_confirmation" maxlength="255"
                               autocomplete="new-password" class="form-control" data-coincide="clave_nueva" required>
                        <div class="invalid-feedback">La confirmación no coincide.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Cambiar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
