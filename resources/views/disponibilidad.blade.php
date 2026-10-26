{{--
    UI_Disponibilidad (CU13 – Consultar disponibilidad)
    elegirEspecialidad() → CtrlDisponibilidad@especialidades · elegirMedico() → medicos · consultar() → franjas
--}}
@extends('plantilla')

@section('titulo', 'Disponibilidad')

@section('contenido')
@php
    $base = ['paciente_id' => $paciente?->id];
@endphp
<h1 class="h3 mb-3">Consultar disponibilidad</h1>

@if (! $paciente)
    {{-- La recepcionista consulta para un paciente seleccionado (CU09), por la regla de edad RN15 --}}
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
        Seleccione primero un paciente para ver las especialidades que corresponden a su edad.
        @if (Route::has('pacientes.buscar'))
            <a href="{{ route('pacientes.buscar') }}" class="btn btn-sm btn-primary">Buscar paciente</a>
        @endif
    </div>
@else
    {{-- Selección actual --}}
    <nav aria-label="Pasos" class="mb-3">
        <ol class="breadcrumb bg-white rounded shadow-sm px-3 py-2 mb-0">
            <li class="breadcrumb-item">{{ $paciente->apellidos }}, {{ $paciente->nombres }} ({{ $paciente->fecha_nacimiento->age }} años)</li>
            <li class="breadcrumb-item {{ $paso === 1 ? 'active' : '' }}">
                @if ($paso > 1) <a href="{{ route('disponibilidad.especialidades', $base) }}">{{ $especialidad->nombre }}</a>
                @else 1. Especialidad @endif
            </li>
            <li class="breadcrumb-item {{ $paso === 2 ? 'active' : '' }}">{{ $paso === 3 ? $medico->apellidos . ', ' . $medico->nombres : '2. Médico y fecha' }}</li>
            <li class="breadcrumb-item {{ $paso === 3 ? 'active' : '' }}">3. Franjas</li>
        </ol>
    </nav>

    @if ($paso === 1)
        {{-- elegirEspecialidad(): paso 1 --}}
        @if ($especialidades->isEmpty())
            {{-- Excepción 4 --}}
            <div class="alert alert-warning">No hay especialidades disponibles para la edad del paciente.</div>
        @else
            <div class="row g-3">
                @foreach ($especialidades as $opcion)
                    <div class="col-sm-6 col-lg-4">
                        <a href="{{ route('disponibilidad.medicos', $base + ['especialidad_id' => $opcion->id]) }}"
                           class="card card-modulo h-100 text-decoration-none shadow-sm border-0">
                            <div class="card-body">
                                <h2 class="h5 text-primary">{{ $opcion->nombre }}</h2>
                                <p class="text-muted mb-0 small">{{ $opcion->descripcion }}</p>
                                <p class="mb-0 small">Consulta: Bs {{ number_format($opcion->costo_consulta, 2, ',', '.') }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- elegirMedico(): pasos 2 y 3 (también para cambiar la fecha en el paso 3) --}}
        @if ($medicos->isEmpty())
            <div class="alert alert-warning">No hay médicos activos para {{ $especialidad->nombre }}.</div>
        @else
            <form method="GET" action="{{ route('disponibilidad.franjas') }}" class="card card-body shadow-sm border-0 mb-3" data-validar novalidate>
                <input type="hidden" name="paciente_id" value="{{ $paciente->id }}">
                <input type="hidden" name="especialidad_id" value="{{ $especialidad->id }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label for="medico_id" class="form-label">Médico</label>
                        <select id="medico_id" name="medico_id" class="form-select @error('medico_id') is-invalid @enderror" required>
                            <option value="">Seleccione…</option>
                            @foreach ($medicos as $opcion)
                                <option value="{{ $opcion->id }}" @selected((int) old('medico_id', ($medico ?? null)?->id) === $opcion->id)>
                                    {{ $opcion->apellidos }}, {{ $opcion->nombres }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="date" id="fecha" name="fecha" min="{{ today()->toDateString() }}" required
                               value="{{ old('fecha', isset($fecha) ? $fecha->toDateString() : '') }}" class="form-control @error('fecha') is-invalid @enderror">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Consultar</button>
                    </div>
                </div>
            </form>
        @endif
    @endif

    @if ($paso === 3)
        {{-- consultar(): pasos 4 a 6 --}}
        <h2 class="h5">{{ ucfirst($fecha->translatedFormat('l d/m/Y')) }}</h2>
        @if ($resultado['bloqueo'])
            {{-- Excepción: fecha bloqueada, con su motivo --}}
            <div class="alert alert-warning">Fecha bloqueada: {{ $resultado['bloqueo']->motivo }}.</div>
        @elseif (! $resultado['atiende'])
            <div class="alert alert-info">El médico no atiende ese día.</div>
        @else
            @php $libres = collect($resultado['franjas'])->where('estado', 'libre')->count(); @endphp
            @if ($resultado['franjas'] === [])
                <div class="alert alert-info">No quedan franjas para ese día.</div>
            @else
                <p class="mb-2">
                    <span class="badge text-bg-success">{{ $libres }} libre(s)</span>
                    <span class="badge text-bg-secondary">{{ count($resultado['franjas']) - $libres }} ocupada(s)</span>
                </p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($resultado['franjas'] as $franja)
                        @if ($franja['estado'] === 'libre')
                            <span class="btn btn-outline-success disabled-link" title="Libre">{{ $franja['inicio'] }}–{{ $franja['fin'] }}</span>
                        @else
                            {{-- ocupada: sin datos del paciente que la tiene --}}
                            <span class="btn btn-light text-muted border" title="Ocupada"><s>{{ $franja['inicio'] }}–{{ $franja['fin'] }}</s> Ocupada</span>
                        @endif
                    @endforeach
                </div>
            @endif
            @if ($libres === 0 && $resultado['franjas'] !== [])
                <div class="alert alert-info">El médico no tiene franjas libres ese día.</div>
            @endif
        @endif

        @if (! collect($resultado['franjas'])->contains('estado', 'libre'))
            {{-- Excepciones 2 y 3: siguiente fecha con disponibilidad --}}
            @if ($siguiente)
                <a href="{{ route('disponibilidad.franjas', $base + ['especialidad_id' => $especialidad->id, 'medico_id' => $medico->id, 'fecha' => $siguiente->toDateString()]) }}"
                   class="btn btn-primary">Siguiente fecha disponible: {{ $siguiente->translatedFormat('l d/m/Y') }}</a>
            @else
                <p class="text-muted">No hay disponibilidad en los próximos 60 días.</p>
            @endif
        @endif
    @endif
@endif
@endsection
