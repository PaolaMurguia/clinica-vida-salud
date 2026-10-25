{{-- UI_Menu: menú principal según el rol (CU01); operación salir() → CtrlSesion@cerrarSesion (CU02) --}}
@extends('plantilla')

@section('titulo', 'Menú principal')

@section('contenido')
@php
    $usuario = auth()->user();
    $opciones = array_filter(config('menu.opciones.' . $usuario->rol->nombre, []), fn ($opcion) => Route::has($opcion['ruta']));
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="h3 mb-0">Bienvenido, {{ $usuario->nombre_usuario }}</h1>
        <p class="text-muted mb-0">Rol: {{ $usuario->rol->nombre }}</p>
    </div>
    @if (Route::has('logout'))
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Salir</button>
        </form>
    @endif
</div>

<div class="row g-3">
    @foreach ($opciones as $opcion)
        <div class="col-sm-6 col-lg-4">
            <a href="{{ route($opcion['ruta']) }}" class="card card-modulo h-100 text-decoration-none shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h5 text-primary">{{ $opcion['texto'] }}</h2>
                    <p class="text-muted mb-0">{{ $opcion['descripcion'] }}</p>
                </div>
            </a>
        </div>
    @endforeach
    @if (Route::has('clave.edit'))
    <div class="col-sm-6 col-lg-4">
        <a href="{{ route('clave.edit') }}" class="card card-modulo h-100 text-decoration-none shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5 text-primary">Cambiar contraseña</h2>
                <p class="text-muted mb-0">Actualice su contraseña de acceso (CU26)</p>
            </div>
        </a>
    </div>
    @endif
</div>
@endsection
