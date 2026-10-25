@php
    // Opciones del menú según el rol (RNF03, RNF09). Solo se muestran las rutas ya implementadas.
    $opcionesPorRol = [
        'Administrador' => [
            'Usuarios'       => 'usuarios.index',
            'Bitácora'       => 'bitacora.index',
            'Especialidades' => 'especialidades.index',
            'Médicos'        => 'medicos.index',
            'Horarios'       => 'horarios.index',
            'Pacientes'      => 'pacientes.index',
        ],
        'Recepcionista' => [
            'Pacientes'       => 'pacientes.index',
            'Buscar paciente' => 'pacientes.buscar',
            'Disponibilidad'  => 'disponibilidad.especialidades',
        ],
        'Médico' => [],
        'Paciente' => [
            'Mi perfil'      => 'perfil.show',
            'Disponibilidad' => 'disponibilidad.especialidades',
        ],
    ];
    $usuario = auth()->user();
    $rol = $usuario?->rol?->nombre;
    $opciones = array_filter($opcionesPorRol[$rol] ?? [], fn ($ruta) => Route::has($ruta));
@endphp
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="{{ $usuario && Route::has('menu') ? route('menu') : url('/') }}">
            {{ config('app.name') }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal" aria-expanded="false" aria-label="Mostrar menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto">
                @foreach ($opciones as $texto => $ruta)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs(Str::before($ruta, '.') . '.*') ? 'active' : '' }}"
                           href="{{ route($ruta) }}">{{ $texto }}</a>
                    </li>
                @endforeach
            </ul>
            <ul class="navbar-nav">
                @if ($usuario)
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $usuario->nombre_usuario }} <span class="badge text-bg-light">{{ $rol }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if (Route::has('clave.edit'))
                                <li><a class="dropdown-item" href="{{ route('clave.edit') }}">Cambiar contraseña</a></li>
                            @endif
                            @if (Route::has('logout'))
                                <li><hr class="dropdown-divider"></li>
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
                @else
                    @if (Route::has('login'))
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Iniciar sesión</a></li>
                    @endif
                    @if (Route::has('registro'))
                        <li class="nav-item"><a class="nav-link" href="{{ route('registro') }}">Crear cuenta</a></li>
                    @endif
                @endif
            </ul>
        </div>
    </div>
</nav>
