{{--
    UI_HistorialCitas (CU19 – Consultar historial de citas)
    mostrar() y filtrar() → CtrlHistorialCitas@index · verDetalle() → CtrlHistorialCitas@show
    Sin diagnósticos ni datos clínicos (RN18).
--}}
@extends('plantilla')

@section('titulo', 'Historial de citas')

@section('contenido')
@php
    $usuario = auth()->user();
    $esPaciente = $usuario->paciente_id !== null;
    $colores = ['Programada' => 'primary', 'Confirmada' => 'info', 'Atendida' => 'success', 'Cancelada' => 'secondary', 'Inasistencia' => 'warning'];
    $base = $esPaciente ? [] : ['paciente_id' => $paciente->id];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-0">{{ $esPaciente ? 'Mis citas' : 'Historial de citas' }}</h1>
        <p class="text-muted mb-0">
            {{ $paciente->apellidos }}, {{ $paciente->nombres }} ({{ $paciente->codigo }})
            @unless ($paciente->estado) <span class="badge text-bg-secondary">Paciente inactivo</span> @endunless
        </p>
    </div>
    @if ($cita)
        <a href="{{ route('historialCitas.index', $base) }}" class="btn btn-outline-secondary">Volver al historial</a>
    @elseif ($paciente->estado && Route::has('disponibilidad.especialidades') && $usuario->rol->nombre !== 'Administrador')
        <a href="{{ route('disponibilidad.especialidades', $base) }}" class="btn btn-primary">Programar cita</a>
    @endif
</div>

@if ($cita)
    {{-- verDetalle(): paso 4 --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <h2 class="h4 mb-2">{{ $cita->codigo }} <span class="badge text-bg-{{ $colores[$cita->estado] }}">{{ $cita->estado }}</span></h2>
                {{-- Paso 5: reprogramar (CU15) o cancelar (CU16) según el rol y el plazo (RN17) --}}
                @if (in_array($cita->estado, \App\Models\Cita::VIGENTES, true) && in_array($usuario->rol->nombre, ['Recepcionista', 'Paciente'], true))
                    @php
                        $inicio = $cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio);
                        $enPlazo = ! $esPaciente || now()->addHours(\App\Models\Cita::PLAZO_PACIENTE_HORAS)->lte($inicio);
                    @endphp
                    @if ($enPlazo)
                        <div class="d-flex gap-2 align-items-start">
                            @if (Route::has('citas.edit'))
                                <a href="{{ route('citas.edit', $cita) }}" class="btn btn-sm btn-outline-primary">Reprogramar</a>
                            @endif
                            @if (Route::has('citas.show'))
                                <a href="{{ route('citas.show', $cita) }}" class="btn btn-sm btn-outline-danger">Cancelar</a>
                            @endif
                        </div>
                    @else
                        <p class="text-muted small mb-0">Faltan menos de {{ \App\Models\Cita::PLAZO_PACIENTE_HORAS }} horas: para cambiar o cancelar la cita comuníquese con la recepción (RN17).</p>
                    @endif
                @endif
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-3">Fecha y horario</dt>
                <dd class="col-sm-9">{{ ucfirst($cita->fecha->translatedFormat('l d/m/Y')) }}, {{ substr($cita->hora_inicio, 0, 5) }}–{{ substr($cita->hora_fin, 0, 5) }}</dd>
                <dt class="col-sm-3">Médico</dt>
                <dd class="col-sm-9">{{ $cita->medico->apellidos }}, {{ $cita->medico->nombres }}</dd>
                <dt class="col-sm-3">Especialidad</dt>
                <dd class="col-sm-9">{{ $cita->especialidad->nombre }}</dd>
                <dt class="col-sm-3">Modalidad</dt>
                <dd class="col-sm-9">{{ $cita->modalidad }}</dd>
                <dt class="col-sm-3">Motivo</dt>
                <dd class="col-sm-9">{{ $cita->motivo ?: '—' }}</dd>
                @if ($cita->motivo_cancelacion)
                    <dt class="col-sm-3">Motivo de cancelación</dt>
                    <dd class="col-sm-9">{{ $cita->motivo_cancelacion }}</dd>
                @endif
                <dt class="col-sm-3">Registrada</dt>
                <dd class="col-sm-9">{{ $cita->fecha_creacion->format('d/m/Y H:i') }} ({{ $cita->canal === 'Web' ? 'Web' : 'Recepción' }})</dd>
            </dl>
        </div>
    </div>

    <h2 class="h5">Historial de estados</h2>
    <div class="table-responsive">
        <table class="table table-sm bg-white shadow-sm align-middle">
            <thead class="table-light">
                <tr><th>Fecha</th><th>Cambio</th><th>Usuario</th><th>Observación</th></tr>
            </thead>
            <tbody>
                @foreach ($historial as $cambio)
                    <tr>
                        <td class="text-nowrap">{{ $cambio->fecha_cambio->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($cambio->estado_anterior === $cambio->estado_nuevo)
                                Reprogramación ({{ $cambio->estado_nuevo }})
                            @else
                                {{ $cambio->estado_anterior ? $cambio->estado_anterior . ' → ' : '' }}{{ $cambio->estado_nuevo }}
                            @endif
                        </td>
                        <td>{{ $cambio->usuario->nombre_usuario }}</td>
                        <td>{{ $cambio->observacion ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    {{-- filtrar(): paso 3 --}}
    <form method="GET" action="{{ route('historialCitas.index') }}" class="card card-body shadow-sm border-0 mb-3">
        @unless ($esPaciente) <input type="hidden" name="paciente_id" value="{{ $paciente->id }}"> @endunless
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Cita::ESTADOS as $estado)
                        <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ $estado }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="desde" class="form-label">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="hasta" class="form-label">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
                <a href="{{ route('historialCitas.index', $base) }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </div>
    </form>

    @if ($citas->isEmpty())
        {{-- Excepción 1 --}}
        <div class="alert alert-info">
            {{ array_filter(\Illuminate\Support\Arr::only($filtros, ['estado', 'desde', 'hasta'])) ? 'No hay citas con los filtros elegidos.' : 'El paciente no tiene citas registradas.' }}
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white shadow-sm align-middle">
                <thead class="table-light">
                    <tr><th>Código</th><th>Fecha y horario</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th>Motivo</th><th>Registrada</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($citas as $fila)
                        <tr>
                            <td>{{ $fila->codigo }}</td>
                            <td class="text-nowrap">{{ $fila->fecha->format('d/m/Y') }} {{ substr($fila->hora_inicio, 0, 5) }}–{{ substr($fila->hora_fin, 0, 5) }}</td>
                            <td>{{ $fila->medico->apellidos }}, {{ $fila->medico->nombres }}</td>
                            <td>{{ $fila->especialidad->nombre }}</td>
                            <td><span class="badge text-bg-{{ $colores[$fila->estado] }}">{{ $fila->estado }}</span></td>
                            <td>{{ $fila->motivo ?: '—' }}</td>
                            <td class="text-nowrap">{{ $fila->fecha_creacion->format('d/m/Y') }}</td>
                            <td><a href="{{ route('historialCitas.show', $fila) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $citas->links() }}
    @endif
@endif
@endsection
