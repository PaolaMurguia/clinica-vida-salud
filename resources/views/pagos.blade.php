{{--
    UI_Pagos (CU25 – Registrar pago de consulta)
    mostrar() → CtrlPagos@create · cobrar() y dejarPendiente() → CtrlPagos@store
    completar() → CtrlPagos@update · comprobante → CtrlPagos@show
--}}
@extends('plantilla')

@section('titulo', $modo === 'comprobante' ? 'Comprobante de pago' : 'Pagos')

@section('contenido')
@php
    $bs = fn ($monto) => 'Bs ' . number_format((float) $monto, 2, ',', '.');
@endphp

@if ($modo === 'lista')
    <h1 class="h3 mb-3">Pagos</h1>
    {{-- Paso 1: abrir la cita por su código --}}
    <form method="GET" action="{{ route('pagos.create') }}" class="card card-body shadow-sm border-0 mb-3" data-validar novalidate>
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="codigo" class="form-label">Código de la cita</label>
                <input type="text" id="codigo" name="codigo" required maxlength="12" placeholder="CM-0001" value="{{ old('codigo', request('codigo')) }}"
                       class="form-control @error('codigo') is-invalid @enderror">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Abrir</button>
            </div>
        </div>
    </form>

    {{-- Pagos pendientes para su seguimiento (postcondición) --}}
    <h2 class="h5">Pagos pendientes</h2>
    @if ($pendientes->isEmpty())
        <div class="alert alert-info">No hay pagos pendientes.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover bg-white shadow-sm align-middle">
                <thead class="table-light">
                    <tr><th>Comprobante</th><th>Cita</th><th>Paciente</th><th>Especialidad</th><th class="text-end">A pagar</th><th>Registrado</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($pendientes as $fila)
                        <tr>
                            <td>{{ $fila->nro_comprobante }}</td>
                            <td>{{ $fila->cita->codigo }}</td>
                            <td>{{ $fila->cita->paciente->apellidos }}, {{ $fila->cita->paciente->nombres }}</td>
                            <td>{{ $fila->cita->especialidad->nombre }}</td>
                            <td class="text-end">{{ $bs($fila->monto_paciente) }}</td>
                            <td>{{ $fila->fecha_registro->format('d/m/Y') }}</td>
                            <td><a href="{{ route('pagos.show', $fila) }}" class="btn btn-sm btn-outline-primary">Completar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@elseif ($modo === 'cobro')
    <h1 class="h3 mb-3">Cobrar cita {{ $cita->codigo }}</h1>
    {{-- Paso 2: datos de la cita y costo --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Paciente</dt>
                <dd class="col-sm-9">{{ $cita->paciente->apellidos }}, {{ $cita->paciente->nombres }} ({{ $cita->paciente->codigo }})</dd>
                <dt class="col-sm-3">Médico y especialidad</dt>
                <dd class="col-sm-9">{{ $cita->medico->apellidos }}, {{ $cita->medico->nombres }} · {{ $cita->especialidad->nombre }}</dd>
                <dt class="col-sm-3">Fecha</dt>
                <dd class="col-sm-9">{{ $cita->fecha->format('d/m/Y') }} {{ substr($cita->hora_inicio, 0, 5) }} · {{ $cita->estado }}</dd>
                <dt class="col-sm-3">Costo de la consulta</dt>
                <dd class="col-sm-9">{{ $bs($costo) }}</dd>
            </dl>
        </div>
    </div>

    @if ($cita->estado === 'Cancelada')
        {{-- Excepción 1 --}}
        <div class="alert alert-warning">La cita está cancelada: no se cobra.</div>
    @else
        {{-- Paso 3: cobertura del seguro (RN19) --}}
        @if ($vigente)
            <div class="alert alert-info">
                Seguro vigente: {{ $vigente->aseguradora }} (póliza {{ $vigente->nro_poliza }}) cubre el {{ rtrim(rtrim(number_format($porcentaje, 2, '.', ''), '0'), '.') }} %.
            </div>
        @elseif ($noVigente)
            <div class="alert alert-warning">
                El seguro {{ $noVigente->aseguradora }} (póliza {{ $noVigente->nro_poliza }}) no está vigente en la fecha de la cita: se cobra sin cobertura (RN19).
            </div>
        @else
            <div class="alert alert-secondary">El paciente no tiene seguro: paga el 100 %.</div>
        @endif

        <div class="row g-3">
            <div class="col-md-5">
                <table class="table bg-white shadow-sm mb-0">
                    <tr><th>Costo</th><td class="text-end">{{ $bs($costo) }}</td></tr>
                    <tr><th>Cubierto por el seguro</th><td class="text-end">{{ $bs($cubierto) }}</td></tr>
                    <tr class="table-primary"><th>Paga el paciente</th><td class="text-end fw-bold">{{ $bs($aPagar) }}</td></tr>
                </table>
            </div>
            <div class="col-md-7">
                {{-- cobrar() y dejarPendiente(): paso 4; JavaScript exige el método al cobrar (paso 5) --}}
                <form method="POST" action="{{ route('pagos.store') }}" class="card card-body shadow-sm border-0 h-100" id="formPago" novalidate>
                    @csrf
                    <input type="hidden" name="cita_id" value="{{ $cita->id }}">
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">Método de pago</legend>
                        @foreach (\App\Http\Requests\Pagos\StoreRequest::METODOS as $metodo)
                            <div class="form-check form-check-inline">
                                <input type="radio" id="metodo_{{ $metodo }}" name="metodo_pago" value="{{ $metodo }}"
                                       class="form-check-input @error('metodo_pago') is-invalid @enderror" @checked(old('metodo_pago') === $metodo)>
                                <label for="metodo_{{ $metodo }}" class="form-check-label">{{ $metodo }}</label>
                            </div>
                        @endforeach
                        <div class="invalid-feedback d-block" id="avisoMetodo" hidden>Elija el método de pago (efectivo o QR) para cobrar.</div>
                    </fieldset>
                    <div class="d-flex flex-wrap gap-2 mt-auto">
                        <button type="submit" name="accion" value="cobrar" class="btn btn-primary">Cobrar</button>
                        <button type="submit" name="accion" value="pendiente" class="btn btn-outline-secondary">Dejar pendiente</button>
                    </div>
                </form>
                <script>
                    document.getElementById('formPago').addEventListener('submit', (evento) => {
                        const cobrar = evento.submitter?.value === 'cobrar';
                        const elegido = evento.target.querySelector('input[name="metodo_pago"]:checked');
                        document.getElementById('avisoMetodo').hidden = !cobrar || elegido !== null;
                        if (cobrar && !elegido) evento.preventDefault();
                    });
                </script>
            </div>
        </div>
    @endif

@else
    {{-- Paso 7: comprobante --}}
    @php $cita = $pago->cita; @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Comprobante de pago</h1>
        <button type="button" class="btn btn-outline-secondary d-print-none" onclick="window.print()">Imprimir</button>
    </div>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <p class="display-6 text-primary mb-3">{{ $pago->nro_comprobante }}</p>
                <span class="badge align-self-start fs-6 {{ $pago->estado === 'Pagado' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $pago->estado }}</span>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-3">Cita</dt>
                <dd class="col-sm-9">{{ $cita->codigo }} · {{ $cita->fecha->format('d/m/Y') }} {{ substr($cita->hora_inicio, 0, 5) }}</dd>
                <dt class="col-sm-3">Paciente</dt>
                <dd class="col-sm-9">{{ $cita->paciente->apellidos }}, {{ $cita->paciente->nombres }} ({{ $cita->paciente->codigo }})</dd>
                <dt class="col-sm-3">Médico y especialidad</dt>
                <dd class="col-sm-9">{{ $cita->medico->apellidos }}, {{ $cita->medico->nombres }} · {{ $cita->especialidad->nombre }}</dd>
                <dt class="col-sm-3">Costo de la consulta</dt>
                <dd class="col-sm-9">{{ $bs($pago->monto_consulta) }}</dd>
                <dt class="col-sm-3">Cobertura</dt>
                <dd class="col-sm-9">
                    {{ rtrim(rtrim(number_format((float) $pago->porcentaje_cobertura, 2, '.', ''), '0'), '.') }} %
                    {{ $pago->seguro ? '(' . $pago->seguro->aseguradora . ', póliza ' . $pago->seguro->nro_poliza . ')' : '(sin seguro vigente)' }}
                </dd>
                <dt class="col-sm-3">Paga el paciente</dt>
                <dd class="col-sm-9 fw-bold">{{ $bs($pago->monto_paciente) }}</dd>
                <dt class="col-sm-3">Método de pago</dt>
                <dd class="col-sm-9">{{ $pago->metodo_pago ?? '—' }}</dd>
                <dt class="col-sm-3">Fecha de cobro</dt>
                <dd class="col-sm-9">{{ $pago->fecha_pago?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt class="col-sm-3">Registrado por</dt>
                <dd class="col-sm-9">{{ $pago->usuario->nombre_usuario }} · {{ $pago->fecha_registro->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>
    </div>

    @if ($pago->estado === 'Pendiente')
        {{-- completar(): paso 8 --}}
        <form method="POST" action="{{ route('pagos.update', $pago) }}" class="card card-body shadow-sm border-0 d-print-none" data-validar novalidate>
            @csrf
            @method('PATCH')
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Completar el pago: método de pago</legend>
                @foreach (\App\Http\Requests\Pagos\StoreRequest::METODOS as $metodo)
                    <div class="form-check form-check-inline">
                        <input type="radio" id="completar_{{ $metodo }}" name="metodo_pago" value="{{ $metodo }}" required class="form-check-input">
                        <label for="completar_{{ $metodo }}" class="form-check-label">{{ $metodo }}</label>
                    </div>
                @endforeach
            </fieldset>
            <div><button type="submit" class="btn btn-primary">Completar pago</button></div>
        </form>
    @endif
    <a href="{{ route('pagos.create') }}" class="btn btn-link d-print-none mt-2">Volver a pagos</a>
@endif
@endsection
