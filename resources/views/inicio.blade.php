@extends('layouts.app')

@section('titulo', 'Bienvenido')

@section('contenido')
<div class="row align-items-center g-4 py-4">
    <div class="col-lg-7">
        <h1 class="display-6 fw-semibold text-primary">Clínica Vida Salud</h1>
        <p class="lead">Reserve y gestione sus citas médicas en línea, consulte la disponibilidad de nuestros especialistas
            y mantenga actualizados sus datos desde el portal del paciente.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ Route::has('login') ? route('login') : '#' }}" class="btn btn-primary btn-lg">Iniciar sesión</a>
            <a href="{{ Route::has('registro') ? route('registro') : '#' }}" class="btn btn-outline-primary btn-lg">Crear cuenta de paciente</a>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5">Nuestros servicios</h2>
                <ul class="list-unstyled mb-0">
                    <li class="py-1">✔ Medicina general, pediatría y geriatría</li>
                    <li class="py-1">✔ Ginecología, odontología y cardiología</li>
                    <li class="py-1">✔ Reserva de citas en tres pasos</li>
                    <li class="py-1">✔ Recordatorios antes de su cita</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
