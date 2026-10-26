{{--
    UI_Seguro (CU12 – Registrar seguro del paciente)
    mostrar() → CtrlSeguros@index · registrar() → store · darDeBaja() → destroy
--}}
@extends('plantilla')

@section('titulo', 'Seguro del paciente')

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-0">Seguro del paciente</h1>
        <p class="text-muted mb-0">{{ $paciente->codigo }} – {{ $paciente->apellidos }}, {{ $paciente->nombres }} (CI {{ $paciente->ci }})</p>
    </div>
    <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-outline-secondary">Volver al paciente</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        {{-- mostrar(): seguro activo --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h2 class="h5">Seguro activo</h2>
                @if ($activo)
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <p class="mb-1"><strong>{{ $activo->aseguradora }}</strong> · póliza {{ $activo->nro_poliza }}</p>
                            <p class="mb-0 small">{{ $activo->tipo_cobertura }} · cobertura {{ rtrim(rtrim($activo->porcentaje_cobertura, '0'), '.') }} %
                                · del {{ $activo->fecha_inicio->format('d/m/Y') }} al {{ $activo->fecha_fin->format('d/m/Y') }}
                                @if ($activo->fecha_fin->isBefore(today())) <span class="badge text-bg-danger">vencido</span>
                                @else <span class="badge text-bg-success">vigente</span> @endif
                            </p>
                        </div>
                        {{-- darDeBaja() --}}
                        <form method="POST" action="{{ route('seguros.destroy', [$paciente, $activo]) }}"
                              onsubmit="return confirm('¿Dar de baja este seguro?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Dar de baja</button>
                        </form>
                    </div>
                @else
                    <p class="text-muted mb-0">El paciente no tiene un seguro activo.</p>
                @endif
            </div>
        </div>

        @if ($anteriores->isNotEmpty())
            <h2 class="h6">Seguros anteriores</h2>
            <ul class="list-group shadow-sm">
                @foreach ($anteriores as $seguro)
                    <li class="list-group-item small">
                        {{ $seguro->aseguradora }} · póliza {{ $seguro->nro_poliza }} · {{ $seguro->fecha_inicio->format('d/m/Y') }}–{{ $seguro->fecha_fin->format('d/m/Y') }}
                        <span class="badge text-bg-secondary">dado de baja</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- registrar(): si hay un seguro activo, se da de baja al guardar el nuevo --}}
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5">{{ $activo ? 'Registrar un seguro nuevo' : 'Registrar seguro' }}</h2>
                @if ($activo)
                    <p class="form-text">El seguro activo se dará de baja al guardar el nuevo.</p>
                @endif
                <form method="POST" action="{{ route('seguros.store', $paciente) }}" data-validar novalidate>
                    @csrf
                    <div class="mb-2">
                        <label for="aseguradora" class="form-label">Aseguradora</label>
                        <input type="text" id="aseguradora" name="aseguradora" maxlength="80" required value="{{ old('aseguradora') }}"
                               class="form-control @error('aseguradora') is-invalid @enderror">
                        <div class="invalid-feedback">Ingrese la aseguradora.</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="nro_poliza" class="form-label">Número de póliza</label>
                            <input type="text" id="nro_poliza" name="nro_poliza" maxlength="40" required value="{{ old('nro_poliza') }}"
                                   class="form-control @error('nro_poliza') is-invalid @enderror">
                            <div class="invalid-feedback">Ingrese la póliza.</div>
                        </div>
                        <div class="col-6">
                            <label for="tipo_cobertura" class="form-label">Tipo de cobertura</label>
                            <input type="text" id="tipo_cobertura" name="tipo_cobertura" maxlength="40" required list="tiposCobertura"
                                   value="{{ old('tipo_cobertura') }}" class="form-control @error('tipo_cobertura') is-invalid @enderror">
                            <datalist id="tiposCobertura"><option value="Ambulatoria"><option value="Integral"><option value="Hospitalaria"></datalist>
                            <div class="invalid-feedback">Ingrese el tipo.</div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="porcentaje_cobertura" class="form-label">Porcentaje de cobertura</label>
                        <input type="number" id="porcentaje_cobertura" name="porcentaje_cobertura" min="0" max="100" step="0.01" required
                               value="{{ old('porcentaje_cobertura') }}" class="form-control @error('porcentaje_cobertura') is-invalid @enderror">
                        <div class="invalid-feedback">Entre 0 y 100.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="fecha_inicio" class="form-label">Vigente desde</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" required value="{{ old('fecha_inicio') }}"
                                   class="form-control @error('fecha_inicio') is-invalid @enderror">
                        </div>
                        <div class="col-6">
                            <label for="fecha_fin" class="form-label">Vigente hasta</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" required value="{{ old('fecha_fin') }}"
                                   class="form-control @error('fecha_fin') is-invalid @enderror">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Registrar seguro</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
