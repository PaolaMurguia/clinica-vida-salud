@php
    // Opciones del rol en sesión (config/menu.php); solo las rutas ya implementadas
    $usuario = auth()->user();
    $rol = $usuario?->rol?->nombre;
    $opciones = array_filter(config("menu.opciones.$rol", []), fn ($opcion) => Route::has($opcion['ruta']));
    $inicio = $rol === 'Paciente' ? 'portal' : 'menu';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Inicio') · {{ config('app.name') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="{{ $usuario ? route($inicio) : route('login') }}">{{ config('app.name') }}</a>
            @if ($usuario)
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#barraPrincipal"
                        aria-controls="barraPrincipal" aria-expanded="false" aria-label="Mostrar menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="barraPrincipal">
                    <ul class="navbar-nav me-auto">
                        @foreach ($opciones as $opcion)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs(Str::before($opcion['ruta'], '.') . '.*') ? 'active' : '' }}"
                                   href="{{ route($opcion['ruta']) }}">{{ $opcion['texto'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ $usuario->nombre_usuario }} <span class="badge text-bg-light">{{ $rol }}</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @if (Route::has('clave.edit'))
                                    <li><a class="dropdown-item" href="{{ route('clave.edit') }}">Cambiar contraseña</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                @endif
                                @if (Route::has('logout'))
                                <li>
                                    {{-- UI_Menu.salir() → CtrlSesion@cerrarSesion (CU02) --}}
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Salir</button>
                                    </form>
                                </li>
                                @endif
                            </ul>
                        </li>
                    </ul>
                </div>
            @endif
        </div>
    </nav>

    <main class="container py-4 flex-grow-1">
        {{-- Mensajes de éxito, aviso y error (RNF09) --}}
        @foreach (['exito' => 'success', 'aviso' => 'warning', 'error' => 'danger'] as $clave => $color)
            @if (session($clave))
                <div class="alert alert-{{ $color }} alert-dismissible fade show" role="alert">
                    {{ session($clave) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif
        @endforeach
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="mb-1 fw-semibold">Revise los datos ingresados:</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $mensaje)
                        <li>{{ $mensaje }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('contenido')
    </main>

    <footer class="bg-white border-top py-3 text-center text-muted small">
        &copy; {{ date('Y') }} {{ config('app.name') }} · Sistema de gestión de citas médicas
    </footer>
</body>
</html>
