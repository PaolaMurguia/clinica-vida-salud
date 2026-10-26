{{--
    UI_Pacientes (CU08 – Gestionar pacientes)
    mostrar() → CtrlPacientes@index · crear() → store · editar() → update · desactivar() → destroy
--}}
@extends('plantilla')

@section('titulo', 'Pacientes')

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Pacientes</h1>
    @if (Route::has('pacientes.buscar'))
        {{-- Paso 1: localizar un paciente existente (CU09) --}}
        <a href="{{ route('pacientes.buscar') }}" class="btn btn-outline-primary">Buscar paciente</a>
    @endif
</div>

<div class="row g-4">
    {{-- crear() / editar() --}}
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5">{{ $editado ? "Editar paciente {$editado->codigo}" : 'Nuevo paciente' }}</h2>
                <form method="POST" action="{{ $editado ? route('pacientes.update', $editado) : route('pacientes.store') }}" data-validar novalidate>
                    @csrf
                    @if ($editado) @method('PUT') @endif
                    <div class="row g-2">
                        <div class="col-sm-5">
                            <label for="ci" class="form-label">CI</label>
                            <input type="text" id="ci" name="ci" maxlength="20" required value="{{ old('ci', $editado?->ci) }}"
                                   class="form-control @error('ci') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese el CI.</div>
                        </div>
                        <div class="col-sm-4">
                            <label for="fecha_nacimiento" class="form-label">Nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required max="{{ today()->toDateString() }}"
                                   value="{{ old('fecha_nacimiento', $editado?->fecha_nacimiento?->toDateString()) }}"
                                   class="form-control @error('fecha_nacimiento') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese la fecha.</div>
                        </div>
                        <div class="col-sm-3">
                            <label for="sexo" class="form-label">Sexo</label>
                            <select id="sexo" name="sexo" required class="form-select @error('sexo') is-invalid @enderror">
                                <option value="">…</option>
                                <option value="F" @selected(old('sexo', $editado?->sexo) === 'F')>F</option>
                                <option value="M" @selected(old('sexo', $editado?->sexo) === 'M')>M</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="nombres" class="form-label">Nombres</label>
                            <input type="text" id="nombres" name="nombres" maxlength="80" required value="{{ old('nombres', $editado?->nombres) }}"
                                   class="form-control @error('nombres') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese los nombres.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="apellidos" class="form-label">Apellidos</label>
                            <input type="text" id="apellidos" name="apellidos" maxlength="80" required value="{{ old('apellidos', $editado?->apellidos) }}"
                                   class="form-control @error('apellidos') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese los apellidos.</div>
                        </div>
                        <div class="col-sm-5">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" maxlength="20" required value="{{ old('telefono', $editado?->telefono) }}"
                                   class="form-control @error('telefono') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese el teléfono.</div>
                        </div>
                        <div class="col-sm-7">
                            <label for="correo" class="form-label">Correo</label>
                            <input type="email" id="correo" name="correo" maxlength="120" value="{{ old('correo', $editado?->correo) }}"
                                   class="form-control @error('correo') is-invalid @enderror">
                        </div>
                        <div class="col-12">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" id="direccion" name="direccion" maxlength="150" value="{{ old('direccion', $editado?->direccion) }}"
                                   class="form-control @error('direccion') is-invalid @enderror">
                        </div>
                    </div>
                    <div class="mt-2">
                        <label for="tipo_sangre" class="form-label">Tipo de sangre <small class="text-muted">(corrige el declarado por el paciente)</small></label>
                        <select id="tipo_sangre" name="tipo_sangre" class="form-select @error('tipo_sangre') is-invalid @enderror">
                            <option value="">Sin registrar</option>
                            @foreach (\App\Models\Historia::TIPOS_SANGRE as $tipoSangre)
                                <option value="{{ $tipoSangre }}" @selected(old('tipo_sangre', $editado?->historia?->tipo_sangre) === $tipoSangre)>{{ $tipoSangre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <fieldset class="border rounded p-2 mt-3">
                        <legend class="float-none w-auto px-1 fs-6 mb-0">Contacto de emergencia</legend>
                        <div class="form-text mb-1">Obligatorio para menores de 18 años: madre, padre o tutor.</div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label for="contacto_emerg_nombre" class="form-label">Nombre</label>
                                <input type="text" id="contacto_emerg_nombre" name="contacto_emerg_nombre" maxlength="100"
                                       value="{{ old('contacto_emerg_nombre', $editado?->contacto_emerg_nombre) }}"
                                       class="form-control @error('contacto_emerg_nombre') is-invalid @enderror">
                            </div>
                            <div class="col-sm-3">
                                <label for="contacto_emerg_parent" class="form-label">Parentesco</label>
                                <input type="text" id="contacto_emerg_parent" name="contacto_emerg_parent" maxlength="40" list="parentescos"
                                       value="{{ old('contacto_emerg_parent', $editado?->contacto_emerg_parent) }}"
                                       class="form-control @error('contacto_emerg_parent') is-invalid @enderror">
                                <datalist id="parentescos"><option value="Madre"><option value="Padre"><option value="Tutor"><option value="Hermano"><option value="Hija"><option value="Esposo"></datalist>
                            </div>
                            <div class="col-sm-3">
                                <label for="contacto_emerg_tel" class="form-label">Teléfono</label>
                                <input type="text" id="contacto_emerg_tel" name="contacto_emerg_tel" maxlength="20"
                                       value="{{ old('contacto_emerg_tel', $editado?->contacto_emerg_tel) }}"
                                       class="form-control @error('contacto_emerg_tel') is-invalid @enderror">
                            </div>
                        </div>
                    </fieldset>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">{{ $editado ? 'Guardar cambios' : 'Registrar' }}</button>
                        @if ($editado)
                            <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- mostrar() --}}
    <div class="col-lg-7">
        <div class="table-responsive bg-white shadow-sm rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Código</th><th>CI</th><th>Paciente</th><th>Teléfono</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                @foreach ($pacientes as $paciente)
                    <tr>
                        <td>{{ $paciente->codigo }}</td>
                        <td>{{ $paciente->ci }}</td>
                        <td>{{ $paciente->apellidos }}, {{ $paciente->nombres }}</td>
                        <td>{{ $paciente->telefono }}</td>
                        <td><span class="badge {{ $paciente->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $paciente->estado ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('pacientes.index', ['editar' => $paciente->id]) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            @if ($paciente->estado)
                                <form method="POST" action="{{ route('pacientes.destroy', $paciente) }}" class="d-inline"
                                      onsubmit="return confirm('¿Desactivar al paciente {{ $paciente->codigo }}? Su historial se conserva.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Desactivar</button>
                                </form>
                            @else
                                {{-- cambiarEstado(): reactivación aprobada por la autora --}}
                                <form method="POST" action="{{ route('pacientes.cambiarEstado', $paciente) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" name="accion" value="activar" class="btn btn-sm btn-outline-success">Reactivar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $pacientes->links() }}</div>
    </div>
</div>
@endsection
