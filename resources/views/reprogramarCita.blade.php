{{--
    UI_ReprogramarCita (CU15 – Reprogramar cita)
    mostrar() → CtrlCitas@edit (?fecha=) · reprogramar() → CtrlCitas@update
--}}
@extends('plantilla')

@section('titulo', 'Reprogramar cita')

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Reprogramar cita {{ $cita->codigo }}</h1>
    <a href="{{ route('historialCitas.show', $cita) }}" class="btn btn-outline-secondary">Volver</a>
</div>

{{-- Paso 2: datos actuales --}}
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Paciente</dt>
            <dd class="col-sm-9">{{ $cita->paciente->apellidos }}, {{ $cita->paciente->nombres }} ({{ $cita->paciente->codigo }})</dd>
            <dt class="col-sm-3">Médico</dt>
            <dd class="col-sm-9">{{ $medico->apellidos }}, {{ $medico->nombres }} ({{ $cita->especialidad->nombre }})</dd>
            <dt class="col-sm-3">Fecha y horario actuales</dt>
            <dd class="col-sm-9">{{ ucfirst($cita->fecha->translatedFormat('l d/m/Y')) }}, {{ substr($cita->hora_inicio, 0, 5) }}–{{ substr($cita->hora_fin, 0, 5) }}</dd>
            <dt class="col-sm-3">Estado</dt>
            <dd class="col-sm-9">{{ $cita->estado }}</dd>
        </dl>
    </div>
</div>

@if (! in_array($cita->estado, \App\Models\Cita::VIGENTES, true))
    <div class="alert alert-warning">La cita está en estado {{ $cita->estado }}: no se reprograma; registre una nueva cita (CU14).</div>
@elseif (! $enPlazo)
    <div class="alert alert-warning">Faltan menos de {{ \App\Models\Cita::PLAZO_PACIENTE_HORAS }} horas para la cita: para reprogramarla comuníquese con la recepción (RN17).</div>
@elseif (! $medico->estado)
    <div class="alert alert-warning">El médico está inactivo y no recibe citas (RN04). Cancele la cita (CU16) y programe otra con otro médico (CU14).</div>
@else
    <p class="text-muted small">Para cambiar de médico o de especialidad, cancele la cita (CU16) y programe otra (CU14).</p>

    {{-- Paso 3: nueva fecha --}}
    <form method="GET" action="{{ route('citas.edit', $cita) }}" class="card card-body shadow-sm border-0 mb-3" data-validar novalidate>
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="fecha" class="form-label">Nueva fecha</label>
                <input type="date" id="fecha" name="fecha" min="{{ today()->toDateString() }}" required
                       value="{{ $fecha?->toDateString() }}" class="form-control @error('fecha') is-invalid @enderror">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Ver franjas</button>
            </div>
        </div>
    </form>

    @if ($resultado)
        <h2 class="h5">{{ ucfirst($fecha->translatedFormat('l d/m/Y')) }}</h2>
        @if ($resultado['bloqueo'])
            <div class="alert alert-warning">Fecha bloqueada: {{ $resultado['bloqueo']->motivo }}.</div>
        @elseif (! $resultado['atiende'])
            <div class="alert alert-info">El médico no atiende ese día.</div>
        @elseif (! collect($resultado['franjas'])->contains('estado', 'libre'))
            <div class="alert alert-info">El médico no tiene franjas libres ese día.</div>
        @else
            {{-- reprogramar(): JavaScript verifica que se haya elegido una nueva franja (paso 4) --}}
            <form method="POST" action="{{ route('citas.update', $cita) }}" class="card card-body shadow-sm border-0" data-validar novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($resultado['franjas'] as $franja)
                        @if ($franja['estado'] === 'libre')
                            <input type="radio" class="btn-check" name="hora_inicio" id="franja_{{ $loop->index }}" value="{{ $franja['inicio'] }}" required>
                            <label class="btn btn-outline-success" for="franja_{{ $loop->index }}">{{ $franja['inicio'] }}–{{ $franja['fin'] }}</label>
                        @elseif ($franja['estado'] === 'actual')
                            <span class="btn btn-primary disabled" title="Horario actual">{{ $franja['inicio'] }}–{{ $franja['fin'] }} (actual)</span>
                        @else
                            <span class="btn btn-light text-muted border" title="Ocupada"><s>{{ $franja['inicio'] }}–{{ $franja['fin'] }}</s> Ocupada</span>
                        @endif
                    @endforeach
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Reprogramar</button>
                </div>
            </form>
        @endif
    @endif
@endif
@endsection
