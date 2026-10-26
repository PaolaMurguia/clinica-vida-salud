{{--
    UI_Horarios (CU07 – Definir horarios de atención): V.horarios y V.formHorario
    mostrar() → CtrlHorarios@index · agregar() → store (formHorario) · cambiarEstado() → cambiarEstado
--}}
@extends('plantilla')

@section('titulo', 'Horarios de atención')

@section('contenido')
@use('App\Models\Horario')
<h1 class="h3 mb-3">Horarios de atención</h1>

{{-- Paso 1: selección del médico --}}
<form method="GET" action="{{ route('horarios.index') }}" class="d-flex flex-wrap gap-2 mb-4">
    <select name="medico_id" class="form-select w-auto" aria-label="Médico" onchange="this.form.submit()">
        <option value="">Seleccione un médico…</option>
        @foreach ($medicos as $opcion)
            <option value="{{ $opcion->id }}" @selected($medico?->id === $opcion->id)>
                {{ $opcion->codigo }} – {{ $opcion->apellidos }}, {{ $opcion->nombres }}{{ $opcion->estado ? '' : ' (inactivo)' }}
            </option>
        @endforeach
    </select>
    <noscript><button type="submit" class="btn btn-outline-primary">Ver horarios</button></noscript>
</form>

@if ($medico)
    {{-- Paso 4: franjas que genera el horario recién registrado --}}
    @if (session('franjas'))
        <div class="alert alert-light border">
            <strong>Franjas del nuevo horario:</strong>
            @foreach (session('franjas') as $franja)
                <span class="badge text-bg-primary">{{ $franja['inicio'] }}–{{ $franja['fin'] }}</span>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            @if ($medico->estado)
                @include('formHorario')
            @else
                <div class="alert alert-warning">El médico está inactivo: no se le pueden agregar horarios.</div>
            @endif
        </div>

        {{-- mostrar() --}}
        <div class="col-lg-8">
            <h2 class="h5">{{ $medico->apellidos }}, {{ $medico->nombres }} <small class="text-muted">{{ $medico->codigo }}</small></h2>
            @if ($horarios->isEmpty())
                <div class="alert alert-info">El médico no tiene horarios registrados.</div>
            @else
                <div class="table-responsive bg-white shadow-sm rounded">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Día</th><th>Horario</th><th>Franja</th><th>Franjas que genera</th><th>Estado</th><th class="text-end">Acción</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($horarios as $horario)
                            @php $franjas = $horario->generarFranjas(); @endphp
                            <tr class="{{ $horario->estado ? '' : 'text-muted' }}">
                                <td>{{ Horario::DIAS[$horario->dia_semana] }}</td>
                                <td class="text-nowrap">{{ substr($horario->hora_inicio, 0, 5) }} – {{ substr($horario->hora_fin, 0, 5) }}</td>
                                <td>{{ $horario->duracion_cita_min }} min</td>
                                <td>
                                    <details>
                                        <summary>{{ count($franjas) }} franjas</summary>
                                        @foreach ($franjas as $franja)
                                            <span class="badge text-bg-light border">{{ $franja['inicio'] }}–{{ $franja['fin'] }}</span>
                                        @endforeach
                                    </details>
                                </td>
                                <td><span class="badge {{ $horario->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $horario->estado ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('horarios.cambiarEstado', $horario) }}">
                                        @csrf @method('PATCH')
                                        @if ($horario->estado)
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
                <p class="form-text">Para cambiar un horario, desactívelo y agregue uno nuevo.</p>
            @endif
        </div>
    </div>
@else
    <div class="alert alert-info">Seleccione un médico para ver y definir sus horarios.</div>
@endif
@endsection
