# Mapa del código del ciclo 1

Para cada caso de uso de `docs/casos_de_uso_ciclo1.md`: ruta, Form Request, controlador y método, métodos de modelos, objeto de la base de datos que interviene y prueba. Fuentes: `routes/web.php`, `app/Http`, `app/Models`, `database/sql/01_esquema.sql`, `02_funciones.sql`, `03_procedimientos.sql` y `tests/Feature`.

**Marcas**
- **⚠️** el nombre **no figura en ninguna** tabla de clases del documento.
- **†** el nombre figura en el documento, pero **en la tabla de otro CU** (se indica cuál).
- Sin marca: figura en la tabla de clases del propio CU.

**Notas comunes**
- Todos los controladores escriben en la bitácora con `ServicioBitacora::registrar()` ⚠️ (decisión B8), que llama a `Bitacora::registrar()` (en la tabla de todos los CU que la nombran). En las tablas aparece como «Bitácora».
- Los Form Requests ⚠️ no tienen nombre en el documento; la convención está aprobada en `diferencias_con_documento.md` (paso 4). Sus reglas de negocio están en `after()`.
- Las llamadas estándar de Eloquent y las relaciones (`update`, `where`, `findOrFail`, `$x->rol`, …) se marcan ⚠️B13: no son métodos nuevos, pero tampoco figuran en las tablas.
- El código **no llama a ningún procedimiento ni función almacenada** (`sp_*`, `fn_*`). En la base de datos intervienen **triggers**, **secuencias** (valores por defecto de los códigos) y **restricciones** CHECK, UNIQUE y EXCLUDE, que respaldan las validaciones de los Form Requests (RNF06). «—» significa que no interviene ningún objeto aparte de las tablas.

---

## Módulo Acceso

### CU01 Iniciar sesión

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /login` (`login`) | — | `Route::view('login')` (B1) | — | — | `Acceso/IniciarSesionTest` |
| `POST /login` (`login.autenticar`) | `Acceso/AutenticarRequest` ⚠️ | `Acceso\CtrlSesion` → `autenticar()` | `Usuario::buscarPorLogin()`, `verificarClave()` †CU26, `registrarIntentoFallido()`, `reiniciarIntentos()`, `forceFill(['ultimo_acceso'])->save()` ⚠️B13 · `Rol::obtener()` · Bitácora | — | `Acceso/IniciarSesionTest` (13) |
| `GET /menu` (`menu`) | — | `Route::view('menu')` (B1) | — | — | `Acceso/IniciarSesionTest` |
| `GET /portal` (`portal`) | — | `Route::view('portal')` ⚠️ | — | — | `Acceso/IniciarSesionTest` |

**Notas**
- `Usuario::verificarClave()` †: el paso 6 compara la contraseña con el hash, pero la tabla de CU01 no lista el método; solo figura en CU26. **No está anotado en `diferencias_con_documento.md`.**
- `Usuario::getAuthPassword()` / `getAuthPasswordName()` ⚠️: Laravel los usa para autenticar con la columna `password_hash` (anotado en «Modelos (paso 2)»).
- Vista `portal` ⚠️: aprobada por la autora (CU01, paso 9).

### CU02 Cerrar sesión

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `POST /logout` (`logout`) | — (no recibe datos) | `Acceso\CtrlSesion` → `cerrarSesion()` | Bitácora | — | `Acceso/CerrarSesionTest` (5) |

**Notas**
- El botón «Atrás» lo resuelve el middleware `SinCache` ⚠️ y la sesión expirada, el middleware `auth`.

### CU26 Cambiar contraseña

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /cambiar-clave` (`clave.edit`) | — | `Route::view('cambiarClave')` (B1) | — | — | `Acceso/CambiarClaveTest` |
| `PUT /cambiar-clave` (`clave.update`) | `Acceso/UpdateClaveRequest` ⚠️ | `Acceso\CtrlClave` → `update()` | `Usuario::verificarClave()`, `actualizarClave()`, `registrarIntentoFallido()` †CU01, `reiniciarIntentos()` †CU01, relación `$usuario->rol` ⚠️B13 · Bitácora | — | `Acceso/CambiarClaveTest` (9) |

**Notas**
- `registrarIntentoFallido()` y `reiniciarIntentos()` †: exigidos por la excepción 1 y B9 (anotado en «Revisión del paso 3»).
- Las demás sesiones se cierran con `auth.session` de Laravel; la contraseña temporal la controla el middleware `VerificarClaveTemporal` ⚠️.

### CU27 Recuperar contraseña

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /recuperar-clave` (`recuperacion.solicitar`) | — | `Route::view('recuperarClave')` (B1) | — | — | `Acceso/RecuperarClaveTest` |
| `POST /recuperar-clave` (`recuperacion.enlace`) | `Acceso/SolicitarEnlaceRequest` ⚠️ | `Acceso\CtrlRecuperacion` → `solicitarEnlace()` | `Usuario::buscarPorLogin()` · `Recuperacion::contarRecientes()`, `crear()` · `Mail::raw()` · Bitácora | trigger `trg_limitar_recuperaciones` → `fn_limitar_recuperaciones()` (BEFORE INSERT en `recuperacion_clave`, RN22); UNIQUE `token_hash`; `ck_recuperacion_vigencia` | `Acceso/RecuperarClaveTest` (11) |
| `GET /recuperar-clave/{token}` (`recuperacion.form`) | — | **closure de ruta** ⚠️ | `Recuperacion::validar()` | — | `Acceso/RecuperarClaveTest` |
| `POST /restablecer-clave` (`recuperacion.restablecer`) | `Acceso/RestablecerRequest` ⚠️ | `Acceso\CtrlRecuperacion` → `restablecer()` | `Recuperacion::validar()`, `marcarUsado()` · `Usuario::actualizarClave()`, `reiniciarIntentos()` †CU01 · Bitácora | `ck_recuperacion_uso` | `Acceso/RecuperarClaveTest` |

**Notas**
- Closure de `GET /recuperar-clave/{token}` ⚠️: valida el enlace al abrirlo sin agregar métodos a `CtrlRecuperacion` (aprobado; «CU27 – Validación del enlace al abrirlo»).
- `reiniciarIntentos()` †: paso 8, «desbloquea la cuenta».
- `Mail::raw()` ⚠️: se usa para no crear una clase Mailable con un nombre fuera del documento.

---

## Módulo Administración

### CU03 Gestionar usuarios y roles

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /usuarios` (`usuarios.index`, `?editar=`) | — | `Administracion\CtrlUsuarios` → `index()` | `Usuario::listar()`, `obtener()` †CU04 · `Rol::obtener()` · `Medico::listar()` †CU06 | — | `Administracion/GestionarUsuariosTest` (14) |
| `POST /usuarios` (`usuarios.store`) | `Administracion/StoreUsuarioRequest` ⚠️ | `store()` | `Usuario::crear()`, `buscarPorLogin()` †CU01 (en el FR) · `Rol::obtener()` · `Medico::obtener()` · Bitácora | UNIQUE `nombre_usuario`, `correo`, `medico_id`; `ck_usuario_vinculo` | `GestionarUsuariosTest` |
| `PUT /usuarios/{usuario}` (`usuarios.update`) | `Administracion/UpdateUsuarioRequest` ⚠️ | `update()` | `Usuario::actualizar()`, `buscarPorLogin()` †CU01 (FR), `Usuario::where(...)->count()` ⚠️B13 (único administrador, FR) · `Rol::obtener()` · `Medico::obtener()` · Bitácora | UNIQUE `nombre_usuario`, `correo`, `medico_id`; `ck_usuario_vinculo` | `GestionarUsuariosTest` |
| `POST /usuarios/{usuario}/restablecer-clave` (`usuarios.restablecerClave`) | — (no recibe datos) | `restablecerClave()` | `Usuario::marcarTemporal()` (llama a `reiniciarIntentos()` †CU01) · Bitácora | — (`sp_restablecer_clave` no se usa) | `GestionarUsuariosTest` |
| `PATCH /usuarios/{usuario}/estado` (`usuarios.cambiarEstado`) | `Administracion/CambiarEstadoUsuarioRequest` ⚠️ | `cambiarEstado()` | `Usuario::actualizarEstado()` (con `desbloquear` llama a `reiniciarIntentos()` †CU01), `Usuario::where(...)->count()` ⚠️B13 (FR) · `Rol::obtener()` (FR) · Bitácora | — | `GestionarUsuariosTest` |

**Notas**
- `Usuario::obtener()` †CU04, `Medico::listar()` †CU06 y `buscarPorLogin()` †CU01: anotados en «Revisión del paso 4».
- `reiniciarIntentos()` †CU01 dentro de `marcarTemporal()` y `actualizarEstado()`: la cuenta se desbloquea al restablecer o al elegir «desbloquear» (B2, B9).
- El cierre de sesión de una cuenta desactivada lo hace el middleware `VerificarCuentaActiva` ⚠️ (aprobado).

### CU04 Consultar bitácora

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /bitacora` (`bitacora.index`) | — | `Administracion\CtrlBitacora` → `index()` | `Bitacora::listar()`, `Bitacora::whereNotNull('modulo')->distinct()` ⚠️B13 · `Usuario::listar()` †CU03 | — | `Administracion/ConsultarBitacoraTest` (8) |
| `GET /bitacora/filtrar` (`bitacora.filtrar`) | `Administracion/FiltrarBitacoraRequest` ⚠️ | `filtrar()` | `Bitacora::buscar()`, `whereNotNull('modulo')` ⚠️B13 · `Usuario::listar()` †CU03, `obtener()` | — | `ConsultarBitacoraTest` |
| `GET /bitacora/exportar` (`bitacora.exportar`) | `Administracion/FiltrarBitacoraRequest` ⚠️ | `exportar()` | `Bitacora::buscar()` · Bitácora (`EXPORTAR`) | — | `ConsultarBitacoraTest` |

**Notas**
- `Usuario::listar()` †CU03: alimenta el filtro por usuario (anotado en «Revisión del paso 4»).
- La lista de módulos del filtro sale de una consulta Eloquent ⚠️B13 porque el documento no tiene un método para eso.

### CU05 Gestionar especialidades

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /especialidades` (`especialidades.index`, `?editar=`) | — | `Administracion\CtrlEspecialidades` → `index()` | `Especialidad::listar()`, `Especialidad::findOrFail()` ⚠️B13 | — | `Administracion/GestionarEspecialidadesTest` (8) |
| `POST /especialidades` (`especialidades.store`) | `Administracion/StoreEspecialidadRequest` ⚠️ | `store()` | `Especialidad::existeNombre()` (FR), `crear()` · Bitácora | trigger `trg_normalizar_especialidad` → `fn_normalizar_especialidad()`; UNIQUE `nombre_normalizado`; `ck_especialidad_edades` | `GestionarEspecialidadesTest` |
| `PUT /especialidades/{especialidad}` (`especialidades.update`) | `Administracion/UpdateEspecialidadRequest` ⚠️ | `update()` | `Especialidad::existeNombre()` (FR), `actualizar()` · Bitácora | trigger `trg_normalizar_especialidad` → `fn_normalizar_especialidad()`; UNIQUE `nombre_normalizado`; `ck_especialidad_edades` | `GestionarEspecialidadesTest` |
| `PATCH /especialidades/{especialidad}/estado` (`especialidades.cambiarEstado`) | `Administracion/CambiarEstadoRequest` ⚠️ (compartido con CU06 y CU07) | `cambiarEstado()` | `Especialidad::cambiarEstado()` · Bitácora | — | `GestionarEspecialidadesTest` |

**Notas**
- `Especialidad::findOrFail()` ⚠️B13: carga la especialidad a editar; la tabla de CU05 no tiene `obtener()`.
- `existeNombre()` aplica la misma normalización que el trigger (minúsculas y espacios simples).

### CU06 Gestionar médicos

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /medicos` (`medicos.index`, `?editar=`) | — | `Administracion\CtrlMedicos` → `index()` | `Medico::listar()`, `obtener()` †CU03/CU07 · `Especialidad::listar()` | — | `Administracion/GestionarMedicosTest` (9) |
| `GET /medicos/buscar` (`medicos.buscar`) | `Administracion/BuscarMedicoRequest` ⚠️ | `buscar()` | `Medico::buscar()` · `Especialidad::listar()` | — | `GestionarMedicosTest` |
| `POST /medicos` (`medicos.store`) | `Administracion/StoreMedicoRequest` ⚠️ | `store()` | `Medico::existeCI()` (FR), `crear()` · `Atiende::asignar()` · Bitácora | secuencia `seq_medico_codigo` (DEFAULT `M-0001`); UNIQUE `ci`, `nro_registro_prof` | `GestionarMedicosTest` |
| `PUT /medicos/{medico}` (`medicos.update`) | `Administracion/UpdateMedicoRequest` ⚠️ | `update()` | `Medico::existeCI()` (FR), `actualizar()` · `Atiende::asignar()` · Bitácora | UNIQUE `ci`, `nro_registro_prof` | `GestionarMedicosTest` |
| `PATCH /medicos/{medico}/estado` (`medicos.cambiarEstado`) | `Administracion/CambiarEstadoRequest` ⚠️ | `cambiarEstado()` | `Medico::cambiarEstado()` · Bitácora | — | `GestionarMedicosTest` |

**Notas**
- `Medico::obtener()` †CU03/CU07: carga el médico en el formulario integrado (`?editar=`, B1).
- El registro profesional se valida en el Form Request (decisión A6), sin métodos adicionales.

### CU07 Definir horarios de atención

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /horarios` (`horarios.index`, `?medico_id=`) | — | `Administracion\CtrlHorarios` → `index()` | `Medico::obtener()`, `listar()` †CU06 · `Horario::listar()` (la vista llama a `generarFranjas()`) | — | `Administracion/DefinirHorariosTest` (10) |
| `POST /horarios` (`horarios.store`) | `Administracion/StoreHorarioRequest` ⚠️ | `store()` | `Horario::seSuperpone()` (FR), `crear()`, `generarFranjas()`, relación `$horario->medico` ⚠️B13 · Bitácora | `ck_horario_horas`, `ck_horario_fichas`, CHECK `duracion_cita_min` 10–120, EXCLUDE `ex_horario_no_solapado` | `DefinirHorariosTest` |
| `PATCH /horarios/{horario}/estado` (`horarios.cambiarEstado`) | `Administracion/CambiarEstadoRequest` ⚠️ | `cambiarEstado()` | `Horario::seSuperpone()` (al reactivar), `cambiarEstado()`, relación `$horario->medico` ⚠️B13 · Bitácora | EXCLUDE `ex_horario_no_solapado` | `DefinirHorariosTest` |

**Notas**
- `Medico::listar()` †CU06: selector de médicos del módulo.
- `V.formHorario` se incluye dentro de `horarios`; no hay edición de horarios (B3).

---

## Módulo Pacientes

### CU08 Gestionar pacientes

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /pacientes` (`pacientes.index`, `?editar=`) | — | `Pacientes\CtrlPacientes` → `index()` | `Paciente::listar()`, `obtener()` †CU09 | — | `Pacientes/GestionarPacientesTest` (13) |
| `POST /pacientes` (`pacientes.store`) | `Pacientes/StorePacienteRequest` ⚠️ | `store()` | `Paciente::existeCI()` y `buscarPorCI()` †CU10 (FR), `crear()` · `Historia::obtener()` †CU11 + `update(['tipo_sangre'])` ⚠️B13 · Bitácora | secuencia `seq_paciente_codigo` (DEFAULT `P-0001`); UNIQUE `ci` (`sp_registrar_paciente` no se usa) | `GestionarPacientesTest` |
| `PUT /pacientes/{paciente}` (`pacientes.update`) | `Pacientes/UpdatePacienteRequest` ⚠️ | `update()` | `Paciente::existeCI()` y `buscarPorCI()` †CU10 (FR), `actualizar()` · `Historia::obtener()` †CU11 + `update(['tipo_sangre'])` ⚠️B13 · Bitácora | UNIQUE `ci` | `GestionarPacientesTest` |
| `DELETE /pacientes/{paciente}` (`pacientes.destroy`) | — (no recibe datos) | `destroy()` | `Paciente::desactivar()` · Bitácora | — | `GestionarPacientesTest` |
| `PATCH /pacientes/{paciente}/estado` (`pacientes.cambiarEstado`) | `Pacientes/CambiarEstadoPacienteRequest` ⚠️ | `cambiarEstado()` ⚠️ | `Paciente::actualizar(['estado' => true])` · Bitácora | — | `GestionarPacientesTest::test_reactiva_un_paciente_desactivado_y_lo_registra` |

**Notas**
- `CtrlPacientes@cambiarEstado()` ⚠️: único método de controlador del ciclo 1 que no está en el documento; reactiva pacientes (aprobado por la autora, B2, B6).
- `Historia::obtener()` †CU11: la recepción corrige aquí el tipo de sangre (aprobado al cerrar el paso 5, B7).
- `buscarPorCI()` †CU10: muestra al paciente existente cuando el CI está duplicado (excepción 1).

### CU09 Buscar paciente

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /pacientes/buscar` (`pacientes.buscar`) | `Pacientes/BuscarPacienteRequest` ⚠️ | `Pacientes\CtrlPacientes` → `buscar()` | `Paciente::buscar()` | función `unaccent()` (extensión `unaccent`); índice `idx_paciente_nombre` | `Pacientes/BuscarPacienteTest` (8) |
| `GET /pacientes/{paciente}` (`pacientes.show`) | — | `show()` | `Paciente::obtener()` | — | `BuscarPacienteTest` |

### CU10 Registrarse como paciente

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /registro` (`registro`) | — | `Route::view('registro')` (B1) | — | — | `Pacientes/RegistrarseTest` |
| `POST /registro` (`registro.registrar`) | `Pacientes/RegistrarRequest` ⚠️ | `Pacientes\CtrlRegistro` → `registrar()` | `Paciente::buscarPorCI()`, `crear()`, `actualizar()` †CU08 (al vincular), relación `$ficha->usuario` ⚠️B13 · `Rol::obtener()` · `Usuario::crear()`, `buscarPorLogin()` †CU01 (controlador y FR) · Bitácora | secuencia `seq_paciente_codigo`; UNIQUE `usuario.nombre_usuario`, `correo`, `paciente_id`; `ck_usuario_vinculo` | `Pacientes/RegistrarseTest` (10) |

**Notas**
- `Paciente::actualizar()` †CU08 y `Usuario::buscarPorLogin()` †CU01: anotados en «Revisión del paso 5».

### CU11 Gestionar perfil del paciente

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /perfil` (`perfil.show`) | — | `Pacientes\CtrlPerfil` → `show()` | `Paciente::obtener()` · `Historia::obtener()` · `Antecedente::listar()` · `Seguro::obtenerActivo()` | — | `Pacientes/GestionarPerfilTest` (9) |
| `PUT /perfil` (`perfil.update`) | `Pacientes/UpdatePerfilRequest` ⚠️ | `update()` | `Paciente::obtener()`, `actualizar()` · `Usuario::actualizarCorreo()`, `buscarPorLogin()` †CU01 (FR) · Bitácora | UNIQUE `usuario.correo` | `GestionarPerfilTest` |
| `POST /perfil/antecedentes` (`perfil.storeAntecedente`) | `Pacientes/StoreAntecedenteRequest` ⚠️ | `storeAntecedente()` | `Historia::obtener()` (también en el FR) + `update(['tipo_sangre'])` ⚠️B13 · `Antecedente::crear()` · Bitácora | trigger `trg_tocar_historia` → `fn_tocar_historia()` (AFTER INSERT en `antecedente`) | `GestionarPerfilTest` |

**Notas**
- `Usuario::buscarPorLogin()` †CU01: detecta el correo en uso por otra cuenta (excepción 1).
- El cambio de contraseña enlaza a CU26 (A4); los antecedentes no se borran (A5).

### CU12 Registrar seguro del paciente

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /pacientes/{paciente}/seguro` (`seguros.index`) | — | `Pacientes\CtrlSeguros` → `index()` | `Paciente::obtener()`, relación `$paciente->seguros()` ⚠️B13 · `Seguro::obtenerActivo()` | — | `Pacientes/RegistrarSeguroTest` (9) |
| `POST /pacientes/{paciente}/seguro` (`seguros.store`) | `Pacientes/StoreSeguroRequest` ⚠️ (con `Seguro::whereRaw()` ⚠️B13 para la póliza duplicada) | `store()` | `Seguro::obtenerActivo()`, `darDeBaja()`, `crear()` · Bitácora | UNIQUE `uq_seguro_poliza`, índice único parcial `ux_seguro_activo`, `ck_seguro_vigencia` | `RegistrarSeguroTest` |
| `DELETE /pacientes/{paciente}/seguro/{seguro}` (`seguros.destroy`) | — (no recibe datos) | `destroy()` | `Seguro::darDeBaja()` · Bitácora | — | `RegistrarSeguroTest` |

**Notas**
- `$paciente->seguros()` ⚠️B13: lista los seguros anteriores; el documento no tiene un método para eso.
- `Seguro::whereRaw()` ⚠️B13: la póliza duplicada se busca sin distinguir mayúsculas (anotado en «Revisión del paso 5»).

---

## Módulo Disponibilidad

### CU13 Consultar disponibilidad

| Ruta (nombre) | Form Request | Controlador → método | Métodos de modelos | BD | Prueba |
|---|---|---|---|---|---|
| `GET /disponibilidad` (`disponibilidad.especialidades`) | `Disponibilidad/EspecialidadesRequest` ⚠️ | `Disponibilidad\CtrlDisponibilidad` → `especialidades()` | `Paciente::obtener()` · `Especialidad::listarPorEdad()` | — | `Disponibilidad/ConsultarDisponibilidadTest` (16) |
| `GET /disponibilidad/medicos` (`disponibilidad.medicos`) | `Disponibilidad/MedicosRequest` ⚠️ | `medicos()` | `Paciente::obtener()` · `Especialidad::listarPorEdad()` · `Medico::listarPorEspecialidad()` (también en el FR) | — | `ConsultarDisponibilidadTest` |
| `GET /disponibilidad/franjas` (`disponibilidad.franjas`) | `Disponibilidad/FranjasRequest` ⚠️ | `franjas()` | `Paciente::obtener()` · `Especialidad::listarPorEdad()` · `Medico::listarPorEspecialidad()` · `Bloqueo::estaBloqueada()` → `Horario::obtenerDelDia()` → `generarFranjas()` → `Cita::listarOcupadas()` | — (`fn_franjas_disponibles` y `fn_edad` no se usan, A3) | `ConsultarDisponibilidadTest` |

**Notas**
- Todos los métodos de modelos están en la tabla de CU13. El cálculo de un día es una función local dentro de `franjas()`, no un método nuevo (anotado en «Revisión del paso 6»).

---

## Elementos sin nombre en el documento (comunes)

| Elemento | Dónde | Uso | Aprobación |
|---|---|---|---|
| `VerificarRol` ⚠️ (`rol:`) | `app/Http/Middleware` | roles autorizados en las rutas internas (RNF03, RN12) | `diferencias_con_documento.md` |
| `VerificarClaveTemporal` ⚠️ (`clave.temporal`) | `app/Http/Middleware` | RN23, CU26 | ídem |
| `SinCache` ⚠️ (`sin.cache`) | `app/Http/Middleware` | CU02, botón «Atrás» | ídem |
| `VerificarCuentaActiva` ⚠️ | `app/Http/Middleware` (grupo `web`) | cuenta desactivada en CU03 | ídem |
| `ServicioBitacora::registrar()` ⚠️ | `app/Services` | bitácora de todos los CU (B8) | `CLAUDE.md` B8 |
| `plantilla` ⚠️, `config/menu.php` ⚠️ | `resources/views`, `config` | barra y menú por rol | `diferencias_con_documento.md` |
| Relaciones Eloquent y `getAuthPassword*()` ⚠️B13 | `app/Models` | carga de relaciones y autenticación | «Modelos (paso 2)» |

## Objetos de la base de datos que el ciclo 1 no usa

| Objeto | Archivo | Motivo |
|---|---|---|
| `sp_registrar_bitacora` | `03_procedimientos.sql` | `Bitacora::registrar()` inserta con Eloquent. |
| `sp_registrar_paciente` | `03_procedimientos.sql` | CU08 usa `Paciente::crear()` y valida el CI en el Form Request. |
| `sp_restablecer_clave` | `03_procedimientos.sql` | CU03 usa `Usuario::marcarTemporal()`. |
| `fn_franjas_disponibles` | `03_procedimientos.sql` | CU13 calcula con los modelos (A3, paso 6). |
| `fn_edad` | `02_funciones.sql` | La edad se calcula con Carbon. |
| `fn_validar_cita`, `fn_validar_transicion`, `fn_interpretar_resultado`, `fn_aplicar_cobertura` y sus triggers; `sp_programar_cita`, `sp_cancelar_cita`, `sp_reprogramar_cita`, `sp_registrar_pago`, `sp_solicitar_examen`, `sp_registrar_resultado`, `fn_ingresos` | `02_funciones.sql`, `03_procedimientos.sql` | Corresponden a los CU del ciclo 2. |

## Prueba transversal de modelos

`tests/Feature/ModelosTest.php` (17 pruebas) respalda los métodos de modelos de todos los CU; por ejemplo `test_existen_las_22_tablas_y_los_triggers`, `test_bloqueo_progresivo_5_15_60_y_reinicio`, `test_especialidad_nombre_normalizado_y_edad`, `test_horario_franjas_y_superposicion`, `test_recuperacion_enlace_de_un_solo_uso` y `test_bitacora_registrar_y_buscar`.
