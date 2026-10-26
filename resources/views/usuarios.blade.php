{{--
    UI_Usuarios (CU03 – Gestionar usuarios y roles)
    mostrar() → CtrlUsuarios@index · crear() → store · editar() → update
    restablecerClave() → restablecerClave · cambiarEstado() → cambiarEstado
--}}
@extends('plantilla')

@section('titulo', 'Usuarios')

@section('contenido')
@php
    $esPaciente = $editado?->rol?->nombre === 'Paciente';
@endphp
<h1 class="h3 mb-3">Usuarios y roles</h1>

{{-- Paso 6: la contraseña temporal se muestra una sola vez --}}
@if (session('claveTemporal'))
    <div class="alert alert-info">
        Contraseña temporal de <strong>{{ session('claveTemporal')['usuario'] }}</strong>:
        <code class="fs-5 user-select-all">{{ session('claveTemporal')['clave'] }}</code><br>
        <small>Cópiela ahora; no se volverá a mostrar. El usuario deberá cambiarla al ingresar.</small>
    </div>
@endif

<div class="row g-4">
    {{-- crear() / editar() --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h5">{{ $editado ? "Editar usuario {$editado->nombre_usuario}" : 'Nuevo usuario' }}</h2>
                <form method="POST" action="{{ $editado ? route('usuarios.update', $editado) : route('usuarios.store') }}" data-validar novalidate>
                    @csrf
                    @if ($editado) @method('PUT') @endif

                    <div class="mb-2">
                        <label for="nombre_usuario" class="form-label">Nombre de usuario</label>
                        <input type="text" id="nombre_usuario" name="nombre_usuario" maxlength="40" required
                               value="{{ old('nombre_usuario', $editado?->nombre_usuario) }}"
                               class="form-control @error('nombre_usuario') is-invalid @enderror">
                        <div class="invalid-feedback">Ingrese el nombre de usuario.</div>
                    </div>
                    <div class="mb-2">
                        <label for="correo" class="form-label">Correo</label>
                        <input type="email" id="correo" name="correo" maxlength="120" required
                               value="{{ old('correo', $editado?->correo) }}"
                               class="form-control @error('correo') is-invalid @enderror">
                        <div class="invalid-feedback">Ingrese un correo válido.</div>
                    </div>
                    @unless ($editado)
                        <div class="mb-2">
                            <label for="clave" class="form-label">Contraseña inicial (temporal)</label>
                            <input type="password" id="clave" name="clave" maxlength="255" required autocomplete="new-password"
                                   class="form-control @error('clave') is-invalid @enderror">
                            <div class="form-text">8 caracteres o más, con mayúsculas, minúsculas, números y un símbolo.</div>
                        </div>
                    @endunless
                    <div class="mb-2">
                        <label for="rol_id" class="form-label">Rol</label>
                        @if ($esPaciente)
                            <input type="hidden" name="rol_id" value="{{ $editado->rol_id }}">
                            <input type="text" id="rol_id" class="form-control" value="Paciente (se crea en el autorregistro)" disabled>
                        @else
                            <select id="rol_id" name="rol_id" class="form-select @error('rol_id') is-invalid @enderror" required>
                                <option value="">Seleccione…</option>
                                @foreach ($roles as $rol)
                                    <option value="{{ $rol->id }}" @selected((int) old('rol_id', $editado?->rol_id) === $rol->id)>{{ $rol->nombre }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    @unless ($esPaciente)
                        <div class="mb-2">
                            <label for="medico_id" class="form-label">Médico vinculado <small class="text-muted">(solo rol Médico)</small></label>
                            <select id="medico_id" name="medico_id" class="form-select @error('medico_id') is-invalid @enderror">
                                <option value="">Ninguno</option>
                                @foreach ($medicos as $medico)
                                    <option value="{{ $medico->id }}" @selected((int) old('medico_id', $editado?->medico_id) === $medico->id)>
                                        {{ $medico->codigo }} – {{ $medico->apellidos }}, {{ $medico->nombres }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" id="puede_marcar_atendida" name="puede_marcar_atendida" value="1" class="form-check-input"
                                   @checked(old('puede_marcar_atendida', $editado?->puede_marcar_atendida))>
                            <label for="puede_marcar_atendida" class="form-check-label">Puede marcar citas como atendidas</label>
                        </div>
                    @endunless
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $editado ? 'Guardar cambios' : 'Registrar' }}</button>
                        @if ($editado)
                            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
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
                    <tr><th>Usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                @foreach ($usuarios as $usuario)
                    @php $bloqueado = $usuario->bloqueado_hasta?->isFuture(); @endphp
                    <tr>
                        <td>
                            {{ $usuario->nombre_usuario }}
                            @if ($usuario->debe_cambiar_clave) <span class="badge text-bg-warning">temporal</span> @endif
                        </td>
                        <td class="small">{{ $usuario->correo }}</td>
                        <td>{{ $usuario->rol->nombre }}@if ($usuario->medico)<br><small class="text-muted">{{ $usuario->medico->codigo }}</small>@endif</td>
                        <td>
                            <span class="badge {{ $usuario->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $usuario->estado ? 'Activo' : 'Inactivo' }}</span>
                            @if ($bloqueado)
                                <span class="badge text-bg-danger" title="Hasta {{ $usuario->bloqueado_hasta->format('d/m/Y H:i') }}">Bloqueado</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('usuarios.index', ['editar' => $usuario->id]) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form method="POST" action="{{ route('usuarios.restablecerClave', $usuario) }}" class="d-inline"
                                  onsubmit="return confirm('¿Generar una contraseña temporal para {{ $usuario->nombre_usuario }}?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning">Restablecer</button>
                            </form>
                            <form method="POST" action="{{ route('usuarios.cambiarEstado', $usuario) }}" class="d-inline">
                                @csrf @method('PATCH')
                                @if ($bloqueado)
                                    <button type="submit" name="accion" value="desbloquear" class="btn btn-sm btn-outline-success">Desbloquear</button>
                                @endif
                                @if ($usuario->estado)
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
