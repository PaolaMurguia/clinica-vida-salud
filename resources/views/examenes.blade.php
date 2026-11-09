{{--
    UI_Examenes (CU22 – Gestionar resultados de laboratorio)
    solicitar() → CtrlExamenes@store (desde V.historiaClinica) · buscar() → @index
    registrarResultados() → @registrarResultados · verResultados() → @show · marcarRevisado() → @marcarRevisado
--}}
@extends('plantilla')

@section('titulo', 'Exámenes de laboratorio')

@section('contenido')
@php
    $usuario = auth()->user();
    $rol = $usuario->rol->nombre;
    $estados = ['Solicitado' => 'secondary', 'Registrado' => 'info', 'Revisado' => 'success'];
    $interpretaciones = ['Bajo' => 'warning', 'Normal' => 'success', 'Alto' => 'danger'];
@endphp

@if ($modo === 'lista')
    <h1 class="h3 mb-3">{{ $rol === 'Paciente' ? 'Mis resultados de laboratorio' : 'Exámenes de laboratorio' }}</h1>

    @if ($rol === 'Recepcionista')
        {{-- buscar(): paso 3 --}}
        <form method="GET" action="{{ route('examenes.index') }}" class="card card-body shadow-sm border-0 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="q" class="form-label">Código del examen o CI / nombre del paciente</label>
                    <input type="text" id="q" name="q" value="{{ $criterio }}" class="form-control" placeholder="EX-00001, 1234567, López…">
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Buscar</button></div>
            </div>
            @if ($criterio === '') <small class="text-muted mt-2">Se muestran los exámenes solicitados pendientes de registrar.</small> @endif
        </form>
    @endif

    @if ($examenes->isEmpty())
        <div class="alert alert-info">{{ $criterio !== '' ? 'No se encontraron exámenes.' : 'No hay exámenes para mostrar.' }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white shadow-sm align-middle">
                <thead class="table-light"><tr><th>Código</th><th>Paciente</th><th>Tipo</th><th>Solicitante</th><th>Solicitado</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @foreach ($examenes as $examen)
                        <tr>
                            <td>{{ $examen->codigo }}</td>
                            <td>{{ $examen->paciente?->apellidos }}, {{ $examen->paciente?->nombres }}</td>
                            <td>{{ $examen->tipo_examen }}</td>
                            <td>{{ $examen->medico->apellidos }}</td>
                            <td>{{ $examen->fecha_solicitud->format('d/m/Y') }}</td>
                            <td><span class="badge text-bg-{{ $estados[$examen->estado] }}">{{ $examen->estado }}</span></td>
                            <td><a href="{{ route('examenes.show', $examen) }}" class="btn btn-sm btn-outline-primary">{{ $rol === 'Recepcionista' && $examen->estado !== 'Revisado' ? 'Registrar' : 'Ver' }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@else
    {{-- verResultados(): pasos 8 y 9 --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Examen {{ $examen->codigo }} <span class="badge text-bg-{{ $estados[$examen->estado] }}">{{ $examen->estado }}</span></h1>
        <div class="d-flex gap-2 d-print-none">
            @if ($rol === 'Médico' && $examen->estado === 'Registrado' && $examen->medico_id === $usuario->medico_id)
                <form method="POST" action="{{ route('examenes.marcarRevisado', $examen) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-success">Marcar como revisado</button>
                </form>
            @endif
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
        </div>
    </div>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Paciente</dt>
                <dd class="col-sm-9">{{ $examen->paciente->apellidos }}, {{ $examen->paciente->nombres }} ({{ $examen->paciente->codigo }})</dd>
                <dt class="col-sm-3">Tipo de examen</dt><dd class="col-sm-9">{{ $examen->tipo_examen }}</dd>
                <dt class="col-sm-3">Solicitado por</dt>
                <dd class="col-sm-9">{{ $examen->medico->apellidos }}, {{ $examen->medico->nombres }} · {{ $examen->fecha_solicitud->format('d/m/Y H:i') }}</dd>
                <dt class="col-sm-3">Observación</dt><dd class="col-sm-9">{{ $examen->observacion ?? '—' }}</dd>
                <dt class="col-sm-3">Fecha de la muestra</dt><dd class="col-sm-9">{{ $examen->fecha_muestra?->format('d/m/Y') ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <h2 class="h5">Resultados</h2>
    @if ($resultados->isEmpty())
        <p class="text-muted">Sin resultados registrados.</p>
    @else
        <div class="table-responsive">
            <table class="table bg-white shadow-sm align-middle">
                <thead class="table-light"><tr><th>Parámetro</th><th class="text-end">Valor</th><th>Rango de referencia aplicado</th><th>Interpretación</th></tr></thead>
                <tbody>
                    @foreach ($resultados as $resultado)
                        <tr>
                            <td>{{ $resultado->parametro->nombre }}</td>
                            <td class="text-end">{{ $resultado->valor }} {{ $resultado->parametro->unidad }}</td>
                            <td>{{ $resultado->valor_minimo_ref }} – {{ $resultado->valor_maximo_ref }} {{ $resultado->parametro->unidad }}</td>
                            <td><span class="badge text-bg-{{ $interpretaciones[$resultado->interpretacion] }}">{{ $resultado->interpretacion }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($parametros->isNotEmpty())
        {{-- registrarResultados(): pasos 4 y 5 (recepción) --}}
        <form method="POST" action="{{ route('examenes.registrarResultados', $examen) }}" class="card card-body shadow-sm border-0 d-print-none" data-validar novalidate>
            @csrf
            <h2 class="h5">Registrar resultados</h2>
            <div class="mb-3 col-md-4">
                <label for="fecha_muestra" class="form-label">Fecha de la muestra</label>
                <input type="date" id="fecha_muestra" name="fecha_muestra" required max="{{ today()->toDateString() }}"
                       min="{{ $examen->fecha_solicitud->toDateString() }}" value="{{ old('fecha_muestra', $examen->fecha_muestra?->toDateString()) }}"
                       class="form-control @error('fecha_muestra') is-invalid @enderror" @if ($examen->fecha_muestra) readonly @endif>
            </div>
            <div class="row g-2 mb-3">
                @foreach ($parametros as $parametro)
                    <div class="col-sm-6 col-lg-3">
                        <label for="valor_{{ $parametro->id }}" class="form-label">{{ $parametro->nombre }} ({{ $parametro->unidad }})</label>
                        <input type="text" inputmode="decimal" id="valor_{{ $parametro->id }}" name="valores[{{ $parametro->nombre }}]"
                               value="{{ old('valores.' . $parametro->nombre) }}" class="form-control" pattern="-?\d+([.,]\d+)?">
                    </div>
                @endforeach
            </div>
            <small class="text-muted mb-2">Deje vacío el parámetro que no corresponde a este examen.</small>
            <div><button type="submit" class="btn btn-primary">Registrar resultados</button></div>
        </form>
    @endif
@endif
@endsection
