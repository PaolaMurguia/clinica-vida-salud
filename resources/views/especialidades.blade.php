{{--
    UI_Especialidades (CU05 – Gestionar especialidades)
    mostrar() → CtrlEspecialidades@index · crear() → store · editar() → update · cambiarEstado() → cambiarEstado
--}}
@extends('plantilla')

@section('titulo', 'Especialidades')

@section('contenido')
<h1 class="h3 mb-3">Especialidades</h1>

<div class="row g-4">
    {{-- crear() / editar() --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5">{{ $editada ? "Editar {$editada->nombre}" : 'Nueva especialidad' }}</h2>
                <form method="POST" action="{{ $editada ? route('especialidades.update', $editada) : route('especialidades.store') }}" data-validar novalidate>
                    @csrf
                    @if ($editada) @method('PUT') @endif
                    <div class="mb-2">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" id="nombre" name="nombre" maxlength="60" required
                               value="{{ old('nombre', $editada?->nombre) }}" class="form-control @error('nombre') is-invalid @enderror">
                        <div class="invalid-feedback">Ingrese el nombre.</div>
                    </div>
                    <div class="mb-2">
                        <label for="descripcion" class="form-label">Descripción <small class="text-muted">(opcional)</small></label>
                        <input type="text" id="descripcion" name="descripcion" maxlength="150"
                               value="{{ old('descripcion', $editada?->descripcion) }}" class="form-control @error('descripcion') is-invalid @enderror">
                    </div>
                    <div class="mb-2">
                        <label for="costo_consulta" class="form-label">Costo de la consulta (Bs)</label>
                        <input type="number" id="costo_consulta" name="costo_consulta" min="0" step="0.01" required
                               value="{{ old('costo_consulta', $editada?->costo_consulta) }}" class="form-control @error('costo_consulta') is-invalid @enderror">
                        <div class="invalid-feedback">Ingrese un costo igual o mayor a 0.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label for="edad_minima" class="form-label">Edad mínima</label>
                            <input type="number" id="edad_minima" name="edad_minima" min="0" max="120"
                                   value="{{ old('edad_minima', $editada?->edad_minima) }}" class="form-control @error('edad_minima') is-invalid @enderror">
                        </div>
                        <div class="col">
                            <label for="edad_maxima" class="form-label">Edad máxima</label>
                            <input type="number" id="edad_maxima" name="edad_maxima" min="0" max="120"
                                   value="{{ old('edad_maxima', $editada?->edad_maxima) }}" class="form-control @error('edad_maxima') is-invalid @enderror">
                        </div>
                        <div class="form-text">Déjelas vacías si la especialidad atiende a todas las edades (RN15).</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $editada ? 'Guardar cambios' : 'Registrar' }}</button>
                        @if ($editada)
                            <a href="{{ route('especialidades.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- mostrar() --}}
    <div class="col-lg-8">
        <div class="table-responsive bg-white shadow-sm rounded">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Nombre</th><th class="text-end">Costo (Bs)</th><th>Rango de edad</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                @foreach ($especialidades as $especialidad)
                    <tr>
                        <td>{{ $especialidad->nombre }}<br><small class="text-muted">{{ $especialidad->descripcion }}</small></td>
                        <td class="text-end">{{ number_format($especialidad->costo_consulta, 2, ',', '.') }}</td>
                        <td>
                            @if ($especialidad->edad_minima === null && $especialidad->edad_maxima === null)
                                Todas las edades
                            @else
                                {{ $especialidad->edad_minima ?? 0 }} a {{ $especialidad->edad_maxima ?? '…' }} años
                            @endif
                        </td>
                        <td><span class="badge {{ $especialidad->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $especialidad->estado ? 'Activa' : 'Inactiva' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('especialidades.index', ['editar' => $especialidad->id]) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form method="POST" action="{{ route('especialidades.cambiarEstado', $especialidad) }}" class="d-inline">
                                @csrf @method('PATCH')
                                @if ($especialidad->estado)
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
    </div>
</div>
@endsection
