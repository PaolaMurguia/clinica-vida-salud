{{--
    UI_BuscarPaciente (CU09 – Buscar paciente)
    buscar() → CtrlPacientes@buscar · seleccionar() → CtrlPacientes@show
--}}
@extends('plantilla')

@section('titulo', 'Buscar paciente')

@section('contenido')
<h1 class="h3 mb-3">Buscar paciente</h1>

{{-- buscar(): pasos 1 y 2 --}}
<form method="GET" action="{{ route('pacientes.buscar') }}" class="d-flex gap-2 mb-3" data-validar novalidate>
    <input type="search" name="criterio" value="{{ $criterio ?? old('criterio') }}" minlength="3" maxlength="80" required autofocus
           class="form-control @error('criterio') is-invalid @enderror" placeholder="CI, nombre o apellido (al menos 3 caracteres)">
    <button type="submit" class="btn btn-primary">Buscar</button>
</form>

@if ($demasiados)
    {{-- Excepción 3 --}}
    <div class="alert alert-warning">Hay más de 50 coincidencias para «{{ $criterio }}». Precise la búsqueda.</div>
@elseif ($resultados !== null && $resultados->isEmpty())
    {{-- Excepción 1: se ofrece registrar un paciente nuevo (CU08) --}}
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
        No se encontraron pacientes para «{{ $criterio }}».
        <a href="{{ route('pacientes.index') }}" class="btn btn-sm btn-primary">Registrar nuevo paciente</a>
    </div>
@elseif ($resultados !== null)
    {{-- Paso 3: código, CI, nombre, teléfono y estado --}}
    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Código</th><th>CI</th><th>Paciente</th><th>Teléfono</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($resultados as $paciente)
                <tr>
                    <td>{{ $paciente->codigo }}</td>
                    <td>{{ $paciente->ci }}</td>
                    <td>{{ $paciente->apellidos }}, {{ $paciente->nombres }}</td>
                    <td>{{ $paciente->telefono }}</td>
                    <td><span class="badge {{ $paciente->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $paciente->estado ? 'Activo' : 'Inactivo' }}</span></td>
                    <td class="text-end">
                        {{-- seleccionar(): paso 4 --}}
                        <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-sm btn-outline-primary">Seleccionar</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="form-text">{{ $resultados->count() }} resultado(s).</p>
@endif

@if ($seleccionado)
    {{-- Paso 4: paciente seleccionado --}}
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-1">{{ $seleccionado->apellidos }}, {{ $seleccionado->nombres }}
                        <small class="text-muted">{{ $seleccionado->codigo }}</small></h2>
                    <span class="badge {{ $seleccionado->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $seleccionado->estado ? 'Activo' : 'Inactivo' }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <a href="{{ route('pacientes.index', ['editar' => $seleccionado->id]) }}" class="btn btn-sm btn-outline-primary">Editar datos</a>
                    @if (Route::has('seguros.index') && auth()->user()->rol->nombre === 'Recepcionista')
                        <a href="{{ route('seguros.index', $seleccionado) }}" class="btn btn-sm btn-outline-primary">Seguro</a>
                    @endif
                </div>
            </div>
            <dl class="row mt-3 mb-0">
                <dt class="col-sm-3">CI</dt><dd class="col-sm-9">{{ $seleccionado->ci }}</dd>
                <dt class="col-sm-3">Nacimiento</dt><dd class="col-sm-9">{{ $seleccionado->fecha_nacimiento->format('d/m/Y') }} ({{ $seleccionado->fecha_nacimiento->age }} años)</dd>
                <dt class="col-sm-3">Teléfono</dt><dd class="col-sm-9">{{ $seleccionado->telefono }}</dd>
                <dt class="col-sm-3">Correo</dt><dd class="col-sm-9">{{ $seleccionado->correo ?? '—' }}</dd>
                <dt class="col-sm-3">Dirección</dt><dd class="col-sm-9">{{ $seleccionado->direccion ?? '—' }}</dd>
                <dt class="col-sm-3">Contacto de emergencia</dt>
                <dd class="col-sm-9">
                    @if ($seleccionado->contacto_emerg_nombre)
                        {{ $seleccionado->contacto_emerg_nombre }} ({{ $seleccionado->contacto_emerg_parent }}) · {{ $seleccionado->contacto_emerg_tel }}
                    @else
                        —
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endif
@endsection
