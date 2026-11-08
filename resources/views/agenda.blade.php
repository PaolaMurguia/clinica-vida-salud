{{--
    UI_Agenda (CU18 – Consultar agenda médica y CU17 – Registrar asistencia)
    mostrar() → CtrlAgenda@index · filtrar() → CtrlAgenda@filtrar
    confirmar(), atender(), registrarInasistencia() → CtrlCitas@cambiarEstado (accion)
--}}
@extends('plantilla')

@section('titulo', 'Agenda')

@section('contenido')
@php
    $usuario = auth()->user();
    $rol = $usuario->rol->nombre;
    $colores = ['Programada' => 'primary', 'Confirmada' => 'info', 'Atendida' => 'success', 'Cancelada' => 'secondary', 'Inasistencia' => 'warning'];
    $porFecha = $citas->groupBy(fn ($cita) => $cita->fecha->toDateString());
    $bloqueosPorFecha = $bloqueos->keyBy(fn ($bloqueo) => $bloqueo->fecha->toDateString());
    $filtrosBase = array_filter(['medico_id' => $medico?->id, 'estado' => $estado]);
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-0">Agenda</h1>
        @if ($medico)
            <p class="text-muted mb-0">
                {{ $medico->apellidos }}, {{ $medico->nombres }} ({{ $medico->especialidades->pluck('nombre')->join(', ') }})
                @unless ($medico->estado)
                    <span class="badge text-bg-secondary">Inactivo: agenda histórica, no recibe nuevas citas (RN04)</span>
                @endunless
            </p>
        @endif
    </div>
    @if ($medico)
        <div class="btn-group">
            <a href="{{ route('agenda.filtrar', $filtrosBase + ['desde' => today()->toDateString()]) }}" class="btn btn-outline-primary">Hoy</a>
            <a href="{{ route('agenda.filtrar', $filtrosBase + ['desde' => $desde->toDateString(), 'vista' => 'semana']) }}"
               class="btn btn-outline-primary {{ $vista === 'semana' ? 'active' : '' }}">Semana</a>
        </div>
    @endif
</div>

{{-- filtrar(): paso 2 --}}
<form method="GET" action="{{ route('agenda.filtrar') }}" class="card card-body shadow-sm border-0 mb-3">
    <div class="row g-2 align-items-end">
        @if ($medicos->isNotEmpty())
            <div class="col-md-4">
                <label for="medico_id" class="form-label">Médico</label>
                <select id="medico_id" name="medico_id" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach ($medicos as $opcion)
                        <option value="{{ $opcion->id }}" @selected($medico?->id === $opcion->id)>
                            {{ $opcion->apellidos }}, {{ $opcion->nombres }}{{ $opcion->estado ? '' : ' (inactivo)' }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-md-2">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $desde->toDateString() }}" class="form-control">
        </div>
        <div class="col-md-2">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $hasta->toDateString() }}" class="form-control">
        </div>
        <div class="col-md-2">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                @foreach (\App\Models\Cita::ESTADOS as $opcion)
                    <option value="{{ $opcion }}" @selected($estado === $opcion)>{{ $opcion }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
    </div>
</form>

@if (! $medico)
    <div class="alert alert-info">Elija un médico para ver su agenda.</div>
@else
    <h2 class="h5">
        {{ ucfirst($desde->translatedFormat('l d/m/Y')) }}
        @unless ($desde->equalTo($hasta)) al {{ $hasta->translatedFormat('l d/m/Y') }} @endunless
    </h2>

    {{-- Paso 4 y excepción 5: fechas bloqueadas con su motivo --}}
    @foreach ($bloqueos as $bloqueo)
        <div class="alert alert-warning py-2 mb-2">
            {{ $bloqueo->fecha->format('d/m/Y') }}: fecha bloqueada {{ $bloqueo->medico_id ? 'para el médico' : 'para toda la clínica' }} — {{ $bloqueo->motivo }}.
        </div>
    @endforeach

    @if ($citas->isEmpty())
        {{-- Excepción 1 --}}
        <div class="alert alert-info">El médico no tiene citas {{ $estado ? "en estado $estado " : '' }}en ese rango.</div>
    @else
        @foreach ($porFecha as $dia => $delDia)
            @if ($vista === 'semana' || ! $desde->equalTo($hasta))
                <h3 class="h6 mt-3">{{ ucfirst(\Illuminate\Support\Carbon::parse($dia)->translatedFormat('l d/m/Y')) }}</h3>
            @endif
            <div class="table-responsive">
                <table class="table table-hover bg-white shadow-sm align-middle">
                    <thead class="table-light">
                        <tr><th>Hora</th><th>Código</th><th>Paciente</th><th>Especialidad</th><th>Motivo</th><th>Estado</th><th>Modalidad</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($delDia as $cita)
                            @php
                                $empezo = now()->gte($cita->fecha->copy()->setTimeFromTimeString($cita->hora_inicio));
                                $vigente = in_array($cita->estado, \App\Models\Cita::VIGENTES, true);
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ substr($cita->hora_inicio, 0, 5) }}–{{ substr($cita->hora_fin, 0, 5) }}</td>
                                <td>{{ $cita->codigo }}</td>
                                <td>{{ $cita->paciente->apellidos }}, {{ $cita->paciente->nombres }}</td>
                                <td>{{ $cita->especialidad->nombre }}</td>
                                <td>{{ $cita->motivo ?: '—' }}</td>
                                <td><span class="badge text-bg-{{ $colores[$cita->estado] }}">{{ $cita->estado }}</span></td>
                                <td>{{ $cita->modalidad }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                        {{-- Paso 5: CU17 según el rol y el estado --}}
                                        @if ($rol === 'Recepcionista' && $cita->estado === 'Programada')
                                            <form method="POST" action="{{ route('citas.cambiarEstado', $cita) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="accion" value="confirmar">
                                                <button type="submit" class="btn btn-sm btn-outline-info">Confirmar</button>
                                            </form>
                                        @endif
                                        @if ($rol === 'Recepcionista' && $vigente && $empezo)
                                            <form method="POST" action="{{ route('citas.cambiarEstado', $cita) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="accion" value="registrarInasistencia">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Inasistencia</button>
                                            </form>
                                        @endif
                                        @if ($rol === 'Médico' && $vigente && $empezo && $usuario->puedeMarcarAtendida())
                                            <form method="POST" action="{{ route('citas.cambiarEstado', $cita) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="accion" value="atender">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Atendida</button>
                                            </form>
                                        @endif
                                        {{-- CU20: el médico abre la historia clínica del paciente --}}
                                        @if ($rol === 'Médico' && Route::has('historiaClinica.show'))
                                            <a href="{{ route('historiaClinica.show', ['paciente' => $cita->paciente_id, 'cita' => $cita->id]) }}" class="btn btn-sm btn-outline-primary">Historia clínica</a>
                                        @endif
                                        {{-- CU25: cobro desde la agenda --}}
                                        @if ($rol === 'Recepcionista' && $cita->estado !== 'Cancelada' && Route::has('pagos.create'))
                                            <a href="{{ route('pagos.create', ['cita' => $cita->id]) }}" class="btn btn-sm btn-outline-secondary">Cobrar</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
@endif
@endsection
