{{-- UI_Registro (CU10 – Registrarse como paciente): operación registrarse() → CtrlRegistro@registrar --}}
@extends('plantilla')

@section('titulo', 'Crear cuenta de paciente')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Crear cuenta de paciente</h1>
                <p class="text-muted">Si ya se atendió en la clínica, use los mismos datos de su ficha: la cuenta se vinculará a ella.
                    Su nombre de usuario será su CI.</p>

                <form method="POST" action="{{ route('registro.registrar') }}" data-validar novalidate>
                    @csrf
                    <div class="row g-2">
                        <div class="col-sm-4">
                            <label for="ci" class="form-label">CI</label>
                            <input type="text" id="ci" name="ci" maxlength="20" required value="{{ old('ci') }}"
                                   class="form-control @error('ci') is-invalid @enderror" autofocus>
                            <div class="invalid-feedback">Ingrese su CI.</div>
                        </div>
                        <div class="col-sm-4">
                            <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required max="{{ today()->toDateString() }}"
                                   value="{{ old('fecha_nacimiento') }}" class="form-control @error('fecha_nacimiento') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese su fecha de nacimiento.</div>
                        </div>
                        <div class="col-sm-4">
                            <label for="sexo" class="form-label">Sexo</label>
                            <select id="sexo" name="sexo" required class="form-select @error('sexo') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                <option value="F" @selected(old('sexo') === 'F')>Femenino</option>
                                <option value="M" @selected(old('sexo') === 'M')>Masculino</option>
                            </select>
                            <div class="invalid-feedback">Seleccione el sexo.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="nombres" class="form-label">Nombres</label>
                            <input type="text" id="nombres" name="nombres" maxlength="80" required value="{{ old('nombres') }}"
                                   class="form-control @error('nombres') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese sus nombres.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="apellidos" class="form-label">Apellidos</label>
                            <input type="text" id="apellidos" name="apellidos" maxlength="80" required value="{{ old('apellidos') }}"
                                   class="form-control @error('apellidos') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese sus apellidos.</div>
                        </div>
                        <div class="col-sm-5">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" maxlength="20" required value="{{ old('telefono') }}"
                                   class="form-control @error('telefono') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese su teléfono.</div>
                        </div>
                        <div class="col-sm-7">
                            <label for="correo" class="form-label">Correo</label>
                            <input type="email" id="correo" name="correo" maxlength="120" required value="{{ old('correo') }}"
                                   class="form-control @error('correo') is-invalid @enderror" autocomplete="email">
                            <div class="invalid-feedback">Ingrese un correo válido.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="clave" class="form-label">Contraseña</label>
                            <input type="password" id="clave" name="clave" maxlength="255" required autocomplete="new-password"
                                   class="form-control @error('clave') is-invalid @enderror">
                            <div class="form-text">Al menos 8 caracteres, con mayúsculas, minúsculas, números y un símbolo.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="clave_confirmation" class="form-label">Confirmación</label>
                            <input type="password" id="clave_confirmation" name="clave_confirmation" maxlength="255" required
                                   autocomplete="new-password" class="form-control" data-coincide="clave">
                            <div class="invalid-feedback">La confirmación no coincide.</div>
                        </div>
                    </div>

                    <fieldset class="border rounded p-2 mt-3">
                        <legend class="float-none w-auto px-1 fs-6 mb-0">Contacto de emergencia</legend>
                        <div class="form-text mb-1">Obligatorio si es menor de 18 años: madre, padre o tutor.</div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label for="contacto_emerg_nombre" class="form-label">Nombre</label>
                                <input type="text" id="contacto_emerg_nombre" name="contacto_emerg_nombre" maxlength="100"
                                       value="{{ old('contacto_emerg_nombre') }}" class="form-control @error('contacto_emerg_nombre') is-invalid @enderror">
                            </div>
                            <div class="col-sm-3">
                                <label for="contacto_emerg_parent" class="form-label">Parentesco</label>
                                <input type="text" id="contacto_emerg_parent" name="contacto_emerg_parent" maxlength="40" list="parentescos"
                                       value="{{ old('contacto_emerg_parent') }}" class="form-control @error('contacto_emerg_parent') is-invalid @enderror">
                                <datalist id="parentescos"><option value="Madre"><option value="Padre"><option value="Tutor"></datalist>
                            </div>
                            <div class="col-sm-3">
                                <label for="contacto_emerg_tel" class="form-label">Teléfono</label>
                                <input type="text" id="contacto_emerg_tel" name="contacto_emerg_tel" maxlength="20"
                                       value="{{ old('contacto_emerg_tel') }}" class="form-control @error('contacto_emerg_tel') is-invalid @enderror">
                            </div>
                        </div>
                    </fieldset>

                    <div class="form-check mt-3">
                        <input type="checkbox" id="acepta_privacidad" name="acepta_privacidad" value="1" required
                               class="form-check-input @error('acepta_privacidad') is-invalid @enderror" @checked(old('acepta_privacidad'))>
                        <label for="acepta_privacidad" class="form-check-label">
                            Acepto la política de privacidad: mis datos se usan solo para mi atención en la clínica.
                        </label>
                        <div class="invalid-feedback">Debe aceptar la política de privacidad.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3">Crear cuenta</button>
                </form>
                <p class="text-center small mt-3 mb-0">¿Ya tiene cuenta? <a href="{{ route('login') }}">Inicie sesión</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
