{{--
    Bandeja de notificaciones (D5, aprobada por la autora): avisos del canal Sistema del usuario
    (confirmaciones, recordatorios, reprogramaciones, cancelaciones, agenda y resultados).
--}}
@extends('plantilla')

@section('titulo', 'Notificaciones')

@section('contenido')
<h1 class="h3 mb-3">Notificaciones</h1>

@if ($notificaciones->isEmpty())
    <div class="alert alert-info">No tiene notificaciones.</div>
@else
    <div class="list-group shadow-sm mb-3">
        @foreach ($notificaciones as $notificacion)
            <div class="list-group-item">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <span class="fw-semibold">{{ \App\Models\Notificacion::ASUNTOS[$notificacion->tipo] }}</span>
                    <small class="text-muted">{{ $notificacion->fecha_programada->format('d/m/Y H:i') }}</small>
                </div>
                <p class="mb-0">{{ $notificacion->mensaje }}</p>
            </div>
        @endforeach
    </div>
    {{ $notificaciones->links() }}
@endif
@endsection
