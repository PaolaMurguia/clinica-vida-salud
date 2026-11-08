{{--
    UI_CancelarCita (CU16 – Cancelar cita)
    mostrar() → CtrlCitas@show · cancelar() → CtrlCitas@cancelar
--}}
@extends('plantilla')

@section('titulo', 'Cancelar cita')

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Cancelar cita {{ $cita->codigo }}</h1>
    <a href="{{ route('historialCitas.show', $cita) }}" class="btn btn-outline-secondary">Volver</a>
</div>

{{-- Paso 2: datos de la cita --}}
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Paciente</dt>
            <dd class="col-sm-9">{{ $cita->paciente->apellidos }}, {{ $cita->paciente->nombres }} ({{ $cita->paciente->codigo }})</dd>
            <dt class="col-sm-3">Fecha y horario</dt>
            <dd class="col-sm-9">{{ ucfirst($cita->fecha->translatedFormat('l d/m/Y')) }}, {{ substr($cita->hora_inicio, 0, 5) }}–{{ substr($cita->hora_fin, 0, 5) }}</dd>
            <dt class="col-sm-3">Médico</dt>
            <dd class="col-sm-9">{{ $cita->medico->apellidos }}, {{ $cita->medico->nombres }} ({{ $cita->especialidad->nombre }})</dd>
            <dt class="col-sm-3">Estado</dt>
            <dd class="col-sm-9">{{ $cita->estado }}</dd>
        </dl>
    </div>
</div>

@if ($cita->estado === 'Cancelada')
    <div class="alert alert-info">La cita ya está cancelada. Motivo: {{ $cita->motivo_cancelacion }}</div>
@elseif (! in_array($cita->estado, \App\Models\Cita::VIGENTES, true))
    {{-- RN10 --}}
    <div class="alert alert-warning">La cita está en estado {{ $cita->estado }}: no se cancela como si nunca hubiera ocurrido (RN10).</div>
@elseif (! $enPlazo)
    {{-- RN17 --}}
    <div class="alert alert-warning">Faltan menos de {{ \App\Models\Cita::PLAZO_PACIENTE_HORAS }} horas para la cita: para cancelarla comuníquese con la recepción (RN17).</div>
@else
    {{-- cancelar(): JavaScript verifica que el motivo no esté vacío (paso 3) --}}
    <form method="POST" action="{{ route('citas.cancelar', $cita) }}" class="card card-body shadow-sm border-0" data-validar novalidate
          onsubmit="return this.checkValidity() ? confirm('¿Confirma la cancelación de la cita {{ $cita->codigo }}?') : true">
        @csrf
        @method('PATCH')
        <div class="mb-3">
            <label for="motivo_cancelacion" class="form-label">Motivo de la cancelación</label>
            <textarea id="motivo_cancelacion" name="motivo_cancelacion" rows="2" maxlength="200" required
                      class="form-control @error('motivo_cancelacion') is-invalid @enderror">{{ old('motivo_cancelacion') }}</textarea>
            <div class="invalid-feedback">@error('motivo_cancelacion') {{ $message }} @else Indique el motivo de la cancelación. @enderror</div>
        </div>
        <div>
            <button type="submit" class="btn btn-danger">Cancelar la cita</button>
        </div>
    </form>
@endif
@endsection
