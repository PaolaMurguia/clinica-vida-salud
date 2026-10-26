{{--
    UI_Bitacora (CU04 – Consultar bitácora)
    mostrar() → CtrlBitacora@index · filtrar() → filtrar · exportar() → exportar
--}}
@extends('plantilla')

@section('titulo', 'Bitácora')

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Bitácora</h1>
    {{-- exportar(): mismo resultado que en pantalla --}}
    <a href="{{ route('bitacora.exportar', $filtros) }}" class="btn btn-outline-success">Exportar a CSV</a>
</div>

{{-- filtrar(): usuario, rango de fechas (máximo 90 días) y módulo --}}
<form method="GET" action="{{ route('bitacora.filtrar') }}" class="card card-body shadow-sm border-0 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="usuario_id" class="form-label">Usuario</label>
            <select id="usuario_id" name="usuario_id" class="form-select @error('usuario_id') is-invalid @enderror">
                <option value="">Todos</option>
                @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario->id }}" @selected((int) ($filtros['usuario_id'] ?? old('usuario_id')) === $usuario->id)>{{ $usuario->nombre_usuario }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? old('desde') }}" class="form-control @error('desde') is-invalid @enderror">
        </div>
        <div class="col-md-2">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? old('hasta') }}" class="form-control @error('hasta') is-invalid @enderror">
        </div>
        <div class="col-md-3">
            <label for="modulo" class="form-label">Módulo</label>
            <select id="modulo" name="modulo" class="form-select">
                <option value="">Todos</option>
                @foreach ($modulos as $modulo)
                    <option value="{{ $modulo }}" @selected(($filtros['modulo'] ?? old('modulo')) === $modulo)>{{ $modulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
            <a href="{{ route('bitacora.index') }}" class="btn btn-outline-secondary" title="Quitar filtros">✕</a>
        </div>
    </div>
    <div class="form-text">El rango de fechas no puede superar los 90 días.</div>
</form>

@if ($filtrado)
    <p class="text-muted">Registros del usuario <strong>{{ $filtrado->nombre_usuario }}</strong> ({{ $filtrado->rol->nombre }}).</p>
@endif

@if ($registros->isEmpty())
    {{-- CU04, excepción 2 --}}
    <div class="alert alert-info">No hay registros para los filtros indicados.</div>
@else
    <div class="table-responsive bg-white shadow-sm rounded">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Fecha y hora</th><th>Usuario</th><th>Tipo</th><th>Acción</th><th>Módulo</th><th>Descripción</th><th>IP</th></tr>
            </thead>
            <tbody>
            @foreach ($registros as $registro)
                <tr>
                    <td class="text-nowrap">{{ $registro->fecha_hora->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $registro->usuario?->nombre_usuario ?? '—' }}</td>
                    <td><span class="badge {{ $registro->tipo === 'Acceso' ? 'text-bg-info' : 'text-bg-secondary' }}">{{ $registro->tipo }}</span></td>
                    <td class="small">{{ $registro->accion }}</td>
                    <td>{{ $registro->modulo }}</td>
                    <td class="small">{{ $registro->descripcion }}</td>
                    <td class="small text-nowrap">{{ $registro->ip }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $registros->links() }}</div>
@endif
@endsection
