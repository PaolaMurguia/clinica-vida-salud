{{--
    UI_HistoriaClinica (CU20 – Gestionar historia clínica; CU21 – Dictar registro clínico por voz)
    mostrar() → CtrlHistoriaClinica@show · registrarAtencion() → @storeAtencion / @updateAtencion
    agregarAntecedente() → @storeAntecedente
--}}
@extends('plantilla')

@section('titulo', 'Historia clínica')

@section('contenido')
@php
    $estados = ['Solicitado' => 'secondary', 'Registrado' => 'info', 'Revisado' => 'success'];
    $formulario = $editar ?? null;
    $mostrarFormulario = $esMedico && ($formulario || $pendientes->isNotEmpty());
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-0">Historia clínica</h1>
        <p class="text-muted mb-0">
            {{ $paciente->apellidos }}, {{ $paciente->nombres }} ({{ $paciente->codigo }}) · {{ $paciente->fecha_nacimiento->age }} años
            · Tipo de sangre: <strong>{{ $historia->tipo_sangre ?? 'no registrado' }}</strong>
        </p>
    </div>
    <small class="text-muted">Actualizada: {{ $historia->fecha_actualizacion->format('d/m/Y H:i') }}</small>
</div>

@if ($mostrarFormulario)
    {{-- registrarAtencion(): pasos 3 a 5 --}}
    <form method="POST" class="card card-body shadow-sm border-0 mb-4" data-validar novalidate id="formAtencion"
          action="{{ $formulario ? route('historiaClinica.updateAtencion', $formulario) : route('historiaClinica.storeAtencion', $paciente) }}">
        @csrf
        @if ($formulario) @method('PUT') @endif
        <h2 class="h5">{{ $formulario ? 'Modificar la atención de la cita ' . $formulario->cita->codigo : 'Registrar atención' }}</h2>

        @unless ($formulario)
            <div class="mb-3">
                <label for="cita_id" class="form-label">Cita atendida</label>
                <select id="cita_id" name="cita_id" class="form-select @error('cita_id') is-invalid @enderror" required>
                    @foreach ($pendientes as $pendiente)
                        <option value="{{ $pendiente->id }}" @selected((int) old('cita_id', $citaElegida) === $pendiente->id)>
                            {{ $pendiente->codigo }} · {{ $pendiente->fecha->format('d/m/Y') }} {{ substr($pendiente->hora_inicio, 0, 5) }} · {{ $pendiente->especialidad->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endunless

        @foreach (['diagnostico' => ['Diagnóstico', 300, true], 'tratamiento' => ['Tratamiento', 500, false], 'indicaciones' => ['Indicaciones', 500, false]] as $campo => [$etiqueta, $maximo, $obligatorio])
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-end">
                    <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}{{ $obligatorio ? '' : ' (opcional)' }}</label>
                </div>
                <textarea id="{{ $campo }}" name="{{ $campo }}" rows="2" maxlength="{{ $maximo }}" @if ($obligatorio) required @endif
                          data-dictable class="form-control @error($campo) is-invalid @enderror">{{ old($campo, $formulario?->$campo) }}</textarea>
                @error($campo) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endforeach

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <label for="peso_kg" class="form-label">Peso (kg)</label>
                <input type="number" step="0.01" min="0.5" max="400" id="peso_kg" name="peso_kg" value="{{ old('peso_kg', $formulario?->peso_kg) }}" class="form-control @error('peso_kg') is-invalid @enderror">
            </div>
            <div class="col-6 col-md-3">
                <label for="talla_cm" class="form-label">Talla (cm)</label>
                <input type="number" step="0.1" min="20" max="250" id="talla_cm" name="talla_cm" value="{{ old('talla_cm', $formulario?->talla_cm) }}" class="form-control @error('talla_cm') is-invalid @enderror">
            </div>
            <div class="col-6 col-md-3">
                <label for="presion_arterial" class="form-label">Presión arterial</label>
                <input type="text" id="presion_arterial" name="presion_arterial" maxlength="7" pattern="\d{2,3}/\d{2,3}" placeholder="120/80"
                       value="{{ old('presion_arterial', $formulario?->presion_arterial) }}" class="form-control @error('presion_arterial') is-invalid @enderror">
            </div>
            <div class="col-6 col-md-3">
                <label for="temperatura_c" class="form-label">Temperatura (°C)</label>
                <input type="number" step="0.1" min="30" max="45" id="temperatura_c" name="temperatura_c" value="{{ old('temperatura_c', $formulario?->temperatura_c) }}" class="form-control @error('temperatura_c') is-invalid @enderror">
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $formulario ? 'Guardar cambios' : 'Registrar atención' }}</button>
            @if ($formulario)
                <a href="{{ route('historiaClinica.show', $paciente) }}" class="btn btn-outline-secondary">Cancelar</a>
            @endif
        </div>
    </form>
@elseif ($esMedico)
    <div class="alert alert-info">No hay citas atendidas pendientes de registro con este paciente (la cita debe estar en estado Atendida, CU17).</div>
@endif

{{-- Atenciones anteriores --}}
<h2 class="h5">Atenciones</h2>
@if ($atenciones->isEmpty())
    <p class="text-muted">Sin atenciones registradas.</p>
@else
    <div class="d-flex flex-column gap-2 mb-4">
        @foreach ($atenciones as $atencion)
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong>{{ $atencion->cita->fecha->format('d/m/Y') }} · {{ $atencion->cita->especialidad->nombre }} · {{ $atencion->cita->medico->apellidos }}, {{ $atencion->cita->medico->nombres }}</strong>
                        <span class="d-flex gap-2 align-items-center">
                            @if ($atencion->dictado_voz) <span class="badge text-bg-light border">Dictado por voz</span> @endif
                            @if ($esMedico && $atencion->cita->medico_id === auth()->user()->medico_id)
                                <a href="{{ route('historiaClinica.show', ['paciente' => $paciente->id, 'editar' => $atencion->id]) }}" class="btn btn-sm btn-outline-primary">Modificar</a>
                            @endif
                        </span>
                    </div>
                    <dl class="row mb-0 mt-2 small">
                        <dt class="col-sm-3">Diagnóstico</dt><dd class="col-sm-9">{{ $atencion->diagnostico }}</dd>
                        <dt class="col-sm-3">Tratamiento</dt><dd class="col-sm-9">{{ $atencion->tratamiento ?? '—' }}</dd>
                        <dt class="col-sm-3">Indicaciones</dt><dd class="col-sm-9">{{ $atencion->indicaciones ?? '—' }}</dd>
                        <dt class="col-sm-3">Signos vitales</dt>
                        <dd class="col-sm-9">
                            Peso {{ $atencion->peso_kg ?? '—' }} kg · Talla {{ $atencion->talla_cm ?? '—' }} cm ·
                            PA {{ $atencion->presion_arterial ?? '—' }} · T° {{ $atencion->temperatura_c ?? '—' }} °C
                        </dd>
                    </dl>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        {{-- Antecedentes (del paciente y del médico); no se eliminan --}}
        <h2 class="h5">Antecedentes</h2>
        @if ($antecedentes->isEmpty())
            <p class="text-muted">Sin antecedentes registrados.</p>
        @else
            <ul class="list-group mb-3 shadow-sm">
                @foreach ($antecedentes as $antecedente)
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span><strong>{{ $antecedente->tipo }}:</strong> {{ $antecedente->descripcion }}</span>
                        <small class="text-muted text-nowrap">{{ $antecedente->origen }} · {{ $antecedente->fecha_registro->format('d/m/Y') }}</small>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($esMedico)
            {{-- agregarAntecedente(): paso 6 --}}
            <form method="POST" action="{{ route('historiaClinica.storeAntecedente', $paciente) }}" class="card card-body shadow-sm border-0" data-validar novalidate>
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select" required>
                            @foreach (\App\Models\Antecedente::TIPOS as $tipo)
                                <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <input type="text" id="descripcion" name="descripcion" maxlength="200" required value="{{ old('descripcion') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary w-100">Agregar</button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    <div class="col-lg-6">
        {{-- Exámenes de laboratorio (CU22); el paciente ve los que tienen resultados (D8) --}}
        <h2 class="h5">Exámenes de laboratorio</h2>
        @if ($examenes->isEmpty())
            <p class="text-muted">Sin exámenes.</p>
        @else
            <ul class="list-group shadow-sm">
                @foreach ($examenes as $examen)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between gap-2">
                            <span>
                                <strong>{{ $examen->codigo }}</strong> · {{ $examen->tipo_examen }}
                                <span class="badge text-bg-{{ $estados[$examen->estado] }}">{{ $examen->estado }}</span>
                            </span>
                            @if (Route::has('examenes.show'))
                                <a href="{{ route('examenes.show', $examen) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                            @endif
                        </div>
                        <small class="text-muted">Solicitado el {{ $examen->fecha_solicitud->format('d/m/Y') }} por {{ $examen->medico->apellidos }}</small>
                        @if ($examen->resultados->isNotEmpty())
                            <div class="small mt-1">
                                @foreach ($examen->resultados as $resultado)
                                    <span class="me-2">{{ $resultado->parametro->nombre }}: {{ $resultado->valor }} {{ $resultado->parametro->unidad }}
                                        ({{ $resultado->interpretacion }})</span>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
