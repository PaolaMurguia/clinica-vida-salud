{{--
    UI_Medicos (CU06 – Gestionar médicos)
    mostrar() → CtrlMedicos@index · crear() → store · editar() → update
    cambiarEstado() → cambiarEstado · buscar() → buscar
--}}
@extends('plantilla')

@section('titulo', 'Médicos')

@section('contenido')
@php
    $seleccionadas = array_map('intval', old('especialidades', $editado?->especialidades->pluck('id')->all() ?? []));
@endphp
<h1 class="h3 mb-3">Médicos</h1>

<div class="row g-4">
    {{-- crear() / editar() --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5">{{ $editado ? "Editar médico {$editado->codigo}" : 'Nuevo médico' }}</h2>
                <form method="POST" action="{{ $editado ? route('medicos.update', $editado) : route('medicos.store') }}" data-validar novalidate>
                    @csrf
                    @if ($editado) @method('PUT') @endif
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="ci" class="form-label">CI</label>
                            <input type="text" id="ci" name="ci" maxlength="20" required
                                   value="{{ old('ci', $editado?->ci) }}" class="form-control @error('ci') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese el CI.</div>
                        </div>
                        <div class="col-6">
                            <label for="nro_registro_prof" class="form-label">Registro prof.</label>
                            <input type="text" id="nro_registro_prof" name="nro_registro_prof" maxlength="30" required
                                   value="{{ old('nro_registro_prof', $editado?->nro_registro_prof) }}" class="form-control @error('nro_registro_prof') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese el registro.</div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="nombres" class="form-label">Nombres</label>
                        <input type="text" id="nombres" name="nombres" maxlength="80" required
                               value="{{ old('nombres', $editado?->nombres) }}" class="form-control @error('nombres') is-invalid @enderror">
                        <div class="invalid-feedback">Ingrese los nombres.</div>
                    </div>
                    <div class="mb-2">
                        <label for="apellidos" class="form-label">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" maxlength="80" required
                               value="{{ old('apellidos', $editado?->apellidos) }}" class="form-control @error('apellidos') is-invalid @enderror">
                        <div class="invalid-feedback">Ingrese los apellidos.</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-5">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" maxlength="20"
                                   value="{{ old('telefono', $editado?->telefono) }}" class="form-control @error('telefono') is-invalid @enderror">
                        </div>
                        <div class="col-7">
                            <label for="correo" class="form-label">Correo</label>
                            <input type="email" id="correo" name="correo" maxlength="120"
                                   value="{{ old('correo', $editado?->correo) }}" class="form-control @error('correo') is-invalid @enderror">
                        </div>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">Especialidades</legend>
                        @foreach ($especialidades as $especialidad)
                            @if ($especialidad->estado || in_array($especialidad->id, $seleccionadas, true))
                                <div class="form-check">
                                    <input type="checkbox" id="esp{{ $especialidad->id }}" name="especialidades[]" value="{{ $especialidad->id }}"
                                           class="form-check-input @error('especialidades') is-invalid @enderror" @checked(in_array($especialidad->id, $seleccionadas, true))>
                                    <label for="esp{{ $especialidad->id }}" class="form-check-label">
                                        {{ $especialidad->nombre }} @unless ($especialidad->estado)<small class="text-muted">(inactiva)</small>@endunless
                                    </label>
                                </div>
                            @endif
                        @endforeach
                    </fieldset>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $editado ? 'Guardar cambios' : 'Registrar' }}</button>
                        @if ($editado)
                            <a href="{{ route('medicos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        {{-- buscar() --}}
        <form method="GET" action="{{ route('medicos.buscar') }}" class="d-flex gap-2 mb-3">
            <input type="search" name="criterio" value="{{ $criterio ?? old('criterio') }}" minlength="2" maxlength="80"
                   class="form-control @error('criterio') is-invalid @enderror" placeholder="Buscar por nombre, CI o especialidad">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
            @if ($criterio)
                <a href="{{ route('medicos.index') }}" class="btn btn-outline-secondary">Ver todos</a>
            @endif
        </form>

        {{-- mostrar() --}}
        @if ($medicos->isEmpty())
            <div class="alert alert-info">No se encontraron médicos{{ $criterio ? " para «{$criterio}»" : '' }}.</div>
        @else
            <div class="table-responsive bg-white shadow-sm rounded">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Código</th><th>Médico</th><th>CI / Registro</th><th>Especialidades</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($medicos as $medico)
                        <tr>
                            <td>{{ $medico->codigo }}</td>
                            <td>{{ $medico->apellidos }}, {{ $medico->nombres }}<br><small class="text-muted">{{ $medico->correo }}</small></td>
                            <td class="small">{{ $medico->ci }}<br>{{ $medico->nro_registro_prof }}</td>
                            <td>
                                @foreach ($medico->especialidades as $especialidad)
                                    <span class="badge text-bg-light border">{{ $especialidad->nombre }}</span>
                                @endforeach
                            </td>
                            <td><span class="badge {{ $medico->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $medico->estado ? 'Activo' : 'Inactivo' }}</span></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('medicos.index', ['editar' => $medico->id]) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                <form method="POST" action="{{ route('medicos.cambiarEstado', $medico) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    @if ($medico->estado)
                                        <button type="submit" name="accion" value="desactivar" class="btn btn-sm btn-outline-danger">Desactivar</button>
                                    @else
                                        <button type="submit" name="accion" value="activar" class="btn btn-sm btn-outline-success">Activar</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
