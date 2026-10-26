{{--
    UI_Perfil (CU11 – Gestionar perfil del paciente)
    mostrar() → CtrlPerfil@show · guardar() → update · declararAntecedente() → storeAntecedente
--}}
@extends('plantilla')

@section('titulo', 'Mi perfil')

@section('contenido')
@use('App\Models\Historia')
@use('App\Http\Requests\Pacientes\StoreAntecedenteRequest')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Mi perfil</h1>
    {{-- A4 / paso 6: el perfil enlaza a «Cambiar contraseña» (CU26) --}}
    <a href="{{ route('clave.edit') }}" class="btn btn-outline-secondary">Cambiar contraseña</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        {{-- Datos personales: solo lectura (los cambia la recepción) --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5">Datos personales</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Código</dt><dd class="col-sm-8">{{ $paciente->codigo }}</dd>
                    <dt class="col-sm-4">CI</dt><dd class="col-sm-8">{{ $paciente->ci }}</dd>
                    <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8">{{ $paciente->nombres }} {{ $paciente->apellidos }}</dd>
                    <dt class="col-sm-4">Nacimiento</dt><dd class="col-sm-8">{{ $paciente->fecha_nacimiento->format('d/m/Y') }} ({{ $paciente->fecha_nacimiento->age }} años)</dd>
                </dl>
                <p class="form-text mb-0">Para corregir el CI, los nombres o la fecha de nacimiento, acuda a recepción.</p>
            </div>
        </div>

        {{-- guardar(): contacto, contacto de emergencia y preferencias --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5">Contacto y preferencias</h2>
                <form method="POST" action="{{ route('perfil.update') }}" data-validar novalidate>
                    @csrf @method('PUT')
                    <div class="row g-2">
                        <div class="col-sm-5">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" maxlength="20" required value="{{ old('telefono', $paciente->telefono) }}"
                                   class="form-control @error('telefono') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese su teléfono.</div>
                        </div>
                        <div class="col-sm-7">
                            <label for="correo" class="form-label">Correo</label>
                            <input type="email" id="correo" name="correo" maxlength="120" required
                                   value="{{ old('correo', $paciente->correo ?? auth()->user()->correo) }}"
                                   class="form-control @error('correo') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese un correo válido.</div>
                        </div>
                        <div class="col-12">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" id="direccion" name="direccion" maxlength="150" value="{{ old('direccion', $paciente->direccion) }}"
                                   class="form-control @error('direccion') is-invalid @enderror">
                        </div>
                    </div>
                    <fieldset class="border rounded p-2 mt-3">
                        <legend class="float-none w-auto px-1 fs-6 mb-0">Contacto de emergencia</legend>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label for="contacto_emerg_nombre" class="form-label">Nombre</label>
                                <input type="text" id="contacto_emerg_nombre" name="contacto_emerg_nombre" maxlength="100"
                                       value="{{ old('contacto_emerg_nombre', $paciente->contacto_emerg_nombre) }}"
                                       class="form-control @error('contacto_emerg_nombre') is-invalid @enderror">
                            </div>
                            <div class="col-sm-3">
                                <label for="contacto_emerg_parent" class="form-label">Parentesco</label>
                                <input type="text" id="contacto_emerg_parent" name="contacto_emerg_parent" maxlength="40"
                                       value="{{ old('contacto_emerg_parent', $paciente->contacto_emerg_parent) }}"
                                       class="form-control @error('contacto_emerg_parent') is-invalid @enderror">
                            </div>
                            <div class="col-sm-3">
                                <label for="contacto_emerg_tel" class="form-label">Teléfono</label>
                                <input type="text" id="contacto_emerg_tel" name="contacto_emerg_tel" maxlength="20"
                                       value="{{ old('contacto_emerg_tel', $paciente->contacto_emerg_tel) }}"
                                       class="form-control @error('contacto_emerg_tel') is-invalid @enderror">
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="mt-3">
                        <legend class="fs-6">Preferencias</legend>
                        <div class="form-check">
                            <input type="checkbox" id="notif_correo" name="notif_correo" value="1" class="form-check-input"
                                   @checked(old('notif_correo', $paciente->notif_correo))>
                            <label for="notif_correo" class="form-check-label">Recibir recordatorios por correo</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="notif_alerta" name="notif_alerta" value="1" class="form-check-input"
                                   @checked(old('notif_alerta', $paciente->notif_alerta))>
                            <label for="notif_alerta" class="form-check-label">Recibir alertas dentro del sistema</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="acepta_privacidad" name="acepta_privacidad" value="1" class="form-check-input"
                                   @checked(old('acepta_privacidad', $paciente->acepta_privacidad))>
                            <label for="acepta_privacidad" class="form-check-label">Acepto el uso de mis datos para mi atención (privacidad)</label>
                        </div>
                    </fieldset>
                    <button type="submit" class="btn btn-primary mt-3">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- Seguro activo (solo lectura; lo registra la recepción, CU12) --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5">Seguro</h2>
                @if ($seguro)
                    <p class="mb-1"><strong>{{ $seguro->aseguradora }}</strong> · póliza {{ $seguro->nro_poliza }}</p>
                    <p class="mb-0 small text-muted">{{ $seguro->tipo_cobertura }}, cobertura {{ rtrim(rtrim($seguro->porcentaje_cobertura, '0'), '.') }} %,
                        vigente del {{ $seguro->fecha_inicio->format('d/m/Y') }} al {{ $seguro->fecha_fin->format('d/m/Y') }}
                        @if ($seguro->fecha_fin->isPast()) <span class="badge text-bg-danger">vencido</span> @endif
                    </p>
                @else
                    <p class="text-muted mb-0">No tiene un seguro registrado.</p>
                @endif
            </div>
        </div>

        {{-- Antecedentes: los del médico se ven pero no se modifican; ninguno se borra (A5) --}}
        <div class="card shadow-sm border-0" id="antecedentes">
            <div class="card-body">
                <h2 class="h5">Antecedentes médicos</h2>
                <p class="mb-2">Tipo de sangre: <strong>{{ $historia->tipo_sangre ?? 'sin registrar' }}</strong></p>
                @forelse ($antecedentes as $antecedente)
                    <div class="border-bottom py-1">
                        <span class="badge text-bg-light border">{{ $antecedente->tipo }}</span> {{ $antecedente->descripcion }}
                        <span class="badge {{ $antecedente->origen === 'Paciente' ? 'text-bg-info' : 'text-bg-secondary' }}"
                              title="{{ $antecedente->fecha_registro->format('d/m/Y') }}">
                            {{ $antecedente->origen === 'Paciente' ? 'Declarado por usted' : 'Registrado por su médico' }}
                        </span>
                    </div>
                @empty
                    <p class="text-muted">No hay antecedentes registrados.</p>
                @endforelse

                {{-- declararAntecedente() --}}
                <form method="POST" action="{{ route('perfil.storeAntecedente') }}" class="mt-3">
                    @csrf
                    <h3 class="h6">Declarar un antecedente</h3>
                    @if ($historia->tipo_sangre === null)
                        <div class="mb-2">
                            <label for="tipo_sangre" class="form-label">Tipo de sangre</label>
                            <select id="tipo_sangre" name="tipo_sangre" class="form-select @error('tipo_sangre') is-invalid @enderror">
                                <option value="">No declarar</option>
                                @foreach (Historia::TIPOS_SANGRE as $tipoSangre)
                                    <option value="{{ $tipoSangre }}" @selected(old('tipo_sangre') === $tipoSangre)>{{ $tipoSangre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="row g-2 mb-2">
                        <div class="col-sm-5">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                @foreach (StoreAntecedenteRequest::TIPOS_PACIENTE as $tipo)
                                    <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ $tipo === 'Enfermedad cronica' ? 'Enfermedad crónica' : ($tipo === 'Familiar' ? 'Antecedente familiar' : $tipo) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <input type="text" id="descripcion" name="descripcion" maxlength="200" value="{{ old('descripcion') }}"
                                   class="form-control @error('descripcion') is-invalid @enderror">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Declarar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
