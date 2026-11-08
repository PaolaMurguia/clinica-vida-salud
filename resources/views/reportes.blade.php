{{--
    UI_Reportes (CU23 – Generar reportes)
    elegirReporte() → CtrlReportes@index · generar() → @generar · exportar() → @exportar (CSV o PDF) e imprimir
--}}
@extends('plantilla')

@section('titulo', 'Reportes')

@section('contenido')
@php
    $tipos = \App\Http\Requests\Administracion\GenerarReporteRequest::TIPOS;
    $deUnDia = \App\Http\Requests\Administracion\GenerarReporteRequest::DE_UN_DIA;
    $tipo = $filtros['tipo'] ?? old('tipo');
    $valor = fn ($campo) => old($campo, $filtros[$campo] ?? request($campo));
@endphp
{{-- Hoja de estilos de impresión (D9): solo el reporte --}}
<style>
    @media print {
        nav.navbar, footer, .d-print-none { display: none !important; }
        main.container { max-width: 100%; padding: 0; }
        table { font-size: 11px; }
    }
</style>

<h1 class="h3 mb-3 d-print-none">Reportes</h1>

{{-- elegirReporte() y generar(): pasos 1 a 4; JavaScript muestra los filtros del tipo elegido --}}
<form method="GET" action="{{ route('reportes.generar') }}" class="card card-body shadow-sm border-0 mb-3 d-print-none" id="formReporte" data-validar novalidate>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="tipo" class="form-label">Tipo de reporte</label>
            <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                <option value="">Seleccione…</option>
                @foreach ($tipos as $clave => $nombre)
                    <option value="{{ $clave }}" @selected($tipo === $clave)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2" data-filtro="fecha">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" id="fecha" name="fecha" value="{{ $valor('fecha') }}" class="form-control @error('fecha') is-invalid @enderror">
        </div>
        <div class="col-md-2" data-filtro="periodo">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $valor('desde') }}" class="form-control @error('desde') is-invalid @enderror">
        </div>
        <div class="col-md-2" data-filtro="periodo">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $valor('hasta') }}" class="form-control @error('hasta') is-invalid @enderror">
        </div>
        <div class="col-md-3" data-filtro="medico">
            <label for="medico_id" class="form-label">Médico</label>
            <select id="medico_id" name="medico_id" class="form-select @error('medico_id') is-invalid @enderror">
                <option value="">Seleccione…</option>
                @foreach ($medicos as $medico)
                    <option value="{{ $medico->id }}" @selected((int) $valor('medico_id') === $medico->id)>{{ $medico->apellidos }}, {{ $medico->nombres }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3" data-filtro="especialidad">
            <label for="especialidad_id" class="form-label">Especialidad</label>
            <select id="especialidad_id" name="especialidad_id" class="form-select @error('especialidad_id') is-invalid @enderror">
                <option value="">Seleccione…</option>
                @foreach ($especialidades as $especialidad)
                    <option value="{{ $especialidad->id }}" @selected((int) $valor('especialidad_id') === $especialidad->id)>{{ $especialidad->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2" data-filtro="estado">
            <label for="estado" class="form-label">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                @foreach (\App\Models\Cita::ESTADOS as $estado)
                    <option value="{{ $estado }}" @selected($valor('estado') === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Generar</button>
        </div>
    </div>
</form>
<script>
    (() => {
        // Filtros de cada tipo (paso 2); el servidor vuelve a validar todo (paso 4)
        const deUnDia = @json($deUnDia);
        const conEstado = ['citas_fecha', 'citas_medico', 'citas_especialidad', 'citas_estado'];
        const formulario = document.getElementById('formReporte');
        const tipo = formulario.querySelector('#tipo');
        const mostrar = () => {
            const valor = tipo.value;
            const visibles = {
                fecha: deUnDia.includes(valor),
                periodo: valor !== '' && !deUnDia.includes(valor),
                medico: valor === 'citas_medico',
                especialidad: valor === 'citas_especialidad',
                estado: conEstado.includes(valor),
            };
            formulario.querySelectorAll('[data-filtro]').forEach((bloque) => {
                const visible = visibles[bloque.dataset.filtro];
                bloque.hidden = !visible;
                bloque.querySelectorAll('input, select').forEach((campo) => {
                    campo.disabled = !visible;
                    campo.required = visible && bloque.dataset.filtro !== 'estado';
                });
            });
        };
        tipo.addEventListener('change', mostrar);
        mostrar();
    })();
</script>

@if ($reporte)
    {{-- Paso 5: reporte en pantalla con sus totales --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
            <h2 class="h4 mb-0">{{ $reporte['titulo'] }}</h2>
            <p class="text-muted mb-0">{{ $reporte['periodo'] }}{{ $reporte['subtitulo'] ? ' · ' . $reporte['subtitulo'] : '' }}</p>
            <small class="text-muted d-none d-print-block">{{ config('app.name') }} · generado el {{ now()->format('d/m/Y H:i') }}</small>
        </div>
        @if ($reporte['filas'] !== [])
            {{-- exportar(): paso 6 --}}
            <div class="d-flex gap-2 d-print-none">
                @php $parametros = array_filter($filtros, fn ($v) => $v !== null && $v !== ''); @endphp
                <a href="{{ route('reportes.exportar', $parametros + ['formato' => 'csv']) }}" class="btn btn-outline-success">Excel / CSV</a>
                <a href="{{ route('reportes.exportar', $parametros + ['formato' => 'pdf']) }}" class="btn btn-outline-danger">PDF</a>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
            </div>
        @endif
    </div>

    @if ($reporte['filas'] === [])
        {{-- Excepción 2 --}}
        <div class="alert alert-info">No hay datos para los filtros elegidos.</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped bg-white shadow-sm align-middle">
                <thead class="table-light">
                    <tr>@foreach ($reporte['columnas'] as $columna)<th>{{ $columna }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($reporte['filas'] as $fila)
                        <tr>@foreach ($fila as $celda)<td>{{ $celda }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <h3 class="h6">Totales</h3>
        <table class="table table-sm w-auto bg-white shadow-sm">
            @foreach ($reporte['totales'] as $etiqueta => $total)
                <tr><th class="pe-4">{{ $etiqueta }}</th><td class="text-end">{{ $total }}</td></tr>
            @endforeach
        </table>
    @endif
@endif
@endsection
