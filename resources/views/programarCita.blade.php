{{--
    UI_ProgramarCita (CU14 – Programar cita)
    mostrar() → CtrlCitas@create (resumen de la franja, o comprobante con ?cita=) · programar() → CtrlCitas@store
--}}
@extends('plantilla')

@section('titulo', $cita ? 'Comprobante de cita' : 'Programar cita')

@section('contenido')
@if ($cita)
    {{-- Paso 9: comprobante de la cita --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Comprobante de cita</h1>
        <button type="button" class="btn btn-outline-secondary d-print-none" onclick="window.print()">Imprimir</button>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <p class="display-6 text-primary mb-3">{{ $cita->codigo }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-3">Paciente</dt>
                <dd class="col-sm-9">{{ $cita->paciente->apellidos }}, {{ $cita->paciente->nombres }} ({{ $cita->paciente->codigo }})</dd>
                <dt class="col-sm-3">Especialidad</dt>
                <dd class="col-sm-9">{{ $cita->especialidad->nombre }}</dd>
                <dt class="col-sm-3">Médico</dt>
                <dd class="col-sm-9">{{ $cita->medico->apellidos }}, {{ $cita->medico->nombres }}</dd>
                <dt class="col-sm-3">Fecha y horario</dt>
                <dd class="col-sm-9">{{ ucfirst($cita->fecha->translatedFormat('l d/m/Y')) }}, {{ substr($cita->hora_inicio, 0, 5) }}–{{ substr($cita->hora_fin, 0, 5) }}</dd>
                <dt class="col-sm-3">Modalidad</dt>
                <dd class="col-sm-9">{{ $cita->modalidad }}</dd>
                <dt class="col-sm-3">Motivo</dt>
                <dd class="col-sm-9">{{ $cita->motivo ?: '—' }}</dd>
                <dt class="col-sm-3">Estado</dt>
                <dd class="col-sm-9"><span class="badge text-bg-primary">{{ $cita->estado }}</span></dd>
                <dt class="col-sm-3">Registrada</dt>
                <dd class="col-sm-9">{{ $cita->fecha_creacion->format('d/m/Y H:i') }} ({{ $cita->canal === 'Web' ? 'Web' : 'Recepción' }})</dd>
            </dl>
        </div>
    </div>
    <div class="mt-3 d-print-none d-flex flex-wrap gap-2">
        @if (Route::has('historialCitas.index'))
            <a href="{{ auth()->user()->paciente_id ? route('historialCitas.index') : route('historialCitas.index', ['paciente_id' => $cita->paciente_id]) }}"
               class="btn btn-primary">Ver historial de citas</a>
        @endif
        <a href="{{ route('disponibilidad.especialidades', ['paciente_id' => $cita->paciente_id]) }}" class="btn btn-outline-primary">Programar otra cita</a>
    </div>
@else
    {{-- Paso 3: resumen y datos de la cita --}}
    <h1 class="h3 mb-3">Programar cita</h1>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5">Resumen</h2>
                    <dl class="mb-0">
                        <dt>Paciente</dt>
                        <dd>{{ $paciente->apellidos }}, {{ $paciente->nombres }} ({{ $paciente->codigo }})</dd>
                        <dt>Especialidad</dt>
                        <dd>{{ $especialidad->nombre }} · Bs {{ number_format($especialidad->costo_consulta, 2, ',', '.') }}</dd>
                        <dt>Médico</dt>
                        <dd>{{ $medico->apellidos }}, {{ $medico->nombres }}</dd>
                        <dt>Fecha y horario</dt>
                        <dd class="mb-0">{{ ucfirst($fecha->translatedFormat('l d/m/Y')) }}, {{ $franja['inicio'] }}–{{ $franja['fin'] }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            {{-- programar(): JavaScript verifica los obligatorios (paso 4); el servidor vuelve a validar todo (paso 5) --}}
            <form method="POST" action="{{ route('citas.store') }}" class="card card-body shadow-sm border-0 h-100" data-validar novalidate>
                @csrf
                <input type="hidden" name="paciente_id" value="{{ $paciente->id }}">
                <input type="hidden" name="especialidad_id" value="{{ $especialidad->id }}">
                <input type="hidden" name="medico_id" value="{{ $medico->id }}">
                <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                <input type="hidden" name="hora_inicio" value="{{ $franja['inicio'] }}">

                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Modalidad</legend>
                    @foreach (['Presencial', 'Virtual'] as $opcion)
                        <div class="form-check form-check-inline">
                            <input type="radio" id="modalidad_{{ $opcion }}" name="modalidad" value="{{ $opcion }}" required
                                   class="form-check-input @error('modalidad') is-invalid @enderror" @checked(old('modalidad', 'Presencial') === $opcion)>
                            <label for="modalidad_{{ $opcion }}" class="form-check-label">{{ $opcion }}</label>
                        </div>
                    @endforeach
                </fieldset>

                <div class="mb-3">
                    <label for="motivo" class="form-label">Motivo (opcional)</label>
                    <input type="text" id="motivo" name="motivo" maxlength="200" value="{{ old('motivo') }}"
                           class="form-control @error('motivo') is-invalid @enderror" placeholder="Por ejemplo: control general">
                    @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex flex-wrap gap-2 mt-auto">
                    <button type="submit" class="btn btn-primary">Programar cita</button>
                    <a href="{{ route('disponibilidad.franjas', ['paciente_id' => $paciente->id, 'especialidad_id' => $especialidad->id, 'medico_id' => $medico->id, 'fecha' => $fecha->toDateString()]) }}"
                       class="btn btn-outline-secondary">Elegir otra franja</a>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
