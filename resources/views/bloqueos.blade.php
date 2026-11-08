{{--
    UI_Bloqueos (CU24 – Gestionar feriados y bloqueos)
    mostrar() → CtrlBloqueos@index · agregar() → CtrlBloqueos@store · eliminar() → CtrlBloqueos@destroy
--}}
@extends('plantilla')

@section('titulo', 'Feriados y bloqueos')

@section('contenido')
<h1 class="h3 mb-3">Feriados y bloqueos</h1>

<div class="row g-3">
    <div class="col-lg-4">
        {{-- agregar(): pasos 2 y 3 --}}
        <form method="POST" action="{{ route('bloqueos.store') }}" class="card card-body shadow-sm border-0" data-validar novalidate>
            @csrf
            <h2 class="h5">Nuevo bloqueo</h2>
            <div class="mb-3">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" id="fecha" name="fecha" min="{{ today()->toDateString() }}" required value="{{ old('fecha') }}"
                       class="form-control @error('fecha') is-invalid @enderror">
            </div>
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Ámbito</legend>
                <div class="form-check">
                    <input type="radio" id="ambito_clinica" name="ambito" value="clinica" class="form-check-input" required @checked(old('ambito', 'clinica') === 'clinica')>
                    <label for="ambito_clinica" class="form-check-label">Toda la clínica (feriado)</label>
                </div>
                <div class="form-check">
                    <input type="radio" id="ambito_medico" name="ambito" value="medico" class="form-check-input" @checked(old('ambito') === 'medico')>
                    <label for="ambito_medico" class="form-check-label">Un médico</label>
                </div>
            </fieldset>
            <div class="mb-3">
                <label for="medico_id" class="form-label">Médico</label>
                <select id="medico_id" name="medico_id" class="form-select @error('medico_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach ($medicos as $medico)
                        <option value="{{ $medico->id }}" @selected((int) old('medico_id') === $medico->id)>{{ $medico->apellidos }}, {{ $medico->nombres }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label for="motivo" class="form-label">Motivo</label>
                <input type="text" id="motivo" name="motivo" maxlength="120" required value="{{ old('motivo') }}"
                       placeholder="Feriado, vacaciones, baja, permiso…" class="form-control @error('motivo') is-invalid @enderror">
            </div>

            @if (session('citasEnFecha'))
                {{-- Paso 5: citas existentes en la fecha; se exige confirmar --}}
                <div class="alert alert-warning">
                    <p class="fw-semibold mb-1">Hay {{ count(session('citasEnFecha')) }} cita(s) no canceladas en esa fecha:</p>
                    <ul class="small mb-2">
                        @foreach (session('citasEnFecha') as $cita)
                            <li>{{ $cita['hora'] }} · {{ $cita['codigo'] }} · {{ $cita['paciente'] }} ({{ $cita['medico'] }}, {{ $cita['estado'] }})</li>
                        @endforeach
                    </ul>
                    <p class="small mb-0">Las citas no se cancelan solas: la recepción debe reprogramarlas o cancelarlas.</p>
                </div>
                <input type="hidden" name="confirmar" value="1">
                <button type="submit" class="btn btn-warning">Confirmar el bloqueo</button>
            @else
                <button type="submit" class="btn btn-primary">Agregar bloqueo</button>
            @endif
        </form>
    </div>

    <div class="col-lg-8">
        {{-- mostrar(): paso 1 con filtros --}}
        <form method="GET" action="{{ route('bloqueos.index') }}" class="card card-body shadow-sm border-0 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="filtro_medico" class="form-label">Ámbito</label>
                    <select id="filtro_medico" name="medico_id" class="form-select">
                        <option value="">Todos</option>
                        <option value="clinica" @selected(($filtros['medico_id'] ?? '') === 'clinica')>Toda la clínica</option>
                        @foreach ($medicos as $medico)
                            <option value="{{ $medico->id }}" @selected(($filtros['medico_id'] ?? '') === (string) $medico->id)>{{ $medico->apellidos }}, {{ $medico->nombres }}</option>
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
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>

        @if ($bloqueos->isEmpty())
            <div class="alert alert-info">No hay bloqueos con esos filtros.</div>
        @else
            <div class="table-responsive">
                <table class="table bg-white shadow-sm align-middle">
                    <thead class="table-light"><tr><th>Fecha</th><th>Ámbito</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($bloqueos as $bloqueo)
                            <tr>
                                <td class="text-nowrap">{{ $bloqueo->fecha->format('d/m/Y') }}</td>
                                <td>{{ $bloqueo->medico ? $bloqueo->medico->apellidos . ', ' . $bloqueo->medico->nombres : 'Toda la clínica' }}</td>
                                <td>{{ $bloqueo->motivo }}</td>
                                <td class="text-end">
                                    {{-- eliminar(): paso 7, con confirmación --}}
                                    <form method="POST" action="{{ route('bloqueos.destroy', $bloqueo) }}"
                                          onsubmit="return confirm('¿Eliminar el bloqueo del {{ $bloqueo->fecha->format('d/m/Y') }}? La fecha volverá a estar disponible.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
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
