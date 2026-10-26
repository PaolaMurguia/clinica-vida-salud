# Diferencias con el documento de casos de uso

Registro de decisiones en las que la implementación agrega algo que `docs/casos_de_uso_ciclo1.md` no nombra o interpreta un paso. Cada entrada indica quién la aprobó.

## Decisiones por caso de uso

### CU27 – Validación del enlace al abrirlo (aprobada por la autora)
- **Documento:** paso 6, «el usuario abre el enlace y el sistema valida que exista, no esté usado ni vencido». `CtrlRecuperacion` solo tiene `solicitarEnlace()` y `restablecer()`.
- **Implementación:** la ruta del formulario (`GET /recuperar-clave/{token}`) llama directamente a `Recuperacion::validar()`, que sí está en el documento, y muestra `V.recuperarClave`. Si el enlace venció, ya se usó o fue alterado, la vista muestra el aviso y el enlace para solicitar otro. **No se agregan métodos a `CtrlRecuperacion`.**
- **Seguridad:** `restablecer()` vuelve a ejecutar `Recuperacion::validar()` al enviar el formulario, porque el enlace pudo vencer o usarse entre la apertura y el envío.

### CU01 – Destino del paciente
- **Documento:** paso 9, «redirige al menú, o al portal si es paciente». El documento no nombra la vista del portal.
- **Implementación:** vista `portal.blade.php` (aprobada por la autora), publicada con `Route::view` (B1) y solo para el rol Paciente.

## Revisión del paso 3 (Acceso)

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU01 | Se usaba la relación `$usuario->rol` y el rol no se guardaba en la sesión. | El documento lista `M.Rol::obtener()` y el paso 7 dice «guarda el usuario y su rol». | **Corregido:** `CtrlSesion@autenticar` llama a `Rol::obtener()` y guarda `rol` en la sesión. |
| CU26 | `CtrlClave@update` también usa `Usuario::registrarIntentoFallido()` y `reiniciarIntentos()`, que la tabla de clases de CU26 no lista. | La excepción «suma un intento fallido (RNF04)» y B9 (5 fallos bloquean y cierran la sesión) lo exigen. Son métodos que ya están en el documento (CU01). | Aplicado; sin métodos nuevos. |
| CU27 | `CtrlRecuperacion@restablecer` usa `Usuario::reiniciarIntentos()`, que la tabla de clases de CU27 no lista. | El paso 8 exige «desbloquea la cuenta». El método ya está en el documento (CU01). | Aplicado; sin métodos nuevos. |
| CU27 | La excepción «usuario sin correo registrado» no puede ocurrir. | `usuario.correo` es `NOT NULL` en `01_esquema.sql`. | Sin cambios; la cubre el administrador con CU03 si hiciera falta. |
| CU27 | El correo se envía con `Mail::raw()`, sin clase Mailable. | Así no se crea una clase con un nombre que no está en el documento. En desarrollo el correo queda en `storage/logs/laravel.log` (mailer `log`). | Aplicado. |
| CU01, CU26, CU27 | Los Form Requests usan `prepareForValidation()` para recortar los espacios del usuario o correo. | Es un método propio de Laravel (paso 5 de CU01: «sanea los datos»). | Aplicado. |
| Todos | El código y las pruebas del paso 3 no agregan métodos con nombre propio a los controladores ni a los modelos. | Regla 1 y B13. | Verificado. |

## Revisión del paso 4 (Administración)

Los controladores tienen exactamente los métodos del documento: `CtrlUsuarios` (index, store, update, restablecerClave, cambiarEstado), `CtrlBitacora` (index, filtrar, exportar), `CtrlEspecialidades` (index, store, update, cambiarEstado), `CtrlMedicos` (index, store, update, cambiarEstado, buscar) y `CtrlHorarios` (index, store, cambiarEstado). Las vistas son `usuarios`, `bitacora`, `especialidades`, `medicos`, `horarios` y `formHorario`.

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU03–CU07 | Nombres de Form Requests con la entidad: `StoreUsuarioRequest`, `UpdateMedicoRequest`, `BuscarMedicoRequest`, etc. Un `CambiarEstadoRequest` compartido (CU05, CU06, CU07) y `CambiarEstadoUsuarioRequest` (CU03, que suma «desbloquear»). | La convención aprobada era `<método>Request`, pero en el módulo hay varios `store`/`update`; se agrega la entidad, como ya se hizo con `UpdateClaveRequest`. | **Pendiente de aprobación.** |
| CU03–CU07 | Las reglas que no son de formato están en `after()` del Form Request: usuario o correo duplicado (`Usuario::buscarPorLogin`), único administrador, nombre normalizado (`Especialidad::existeNombre`), CI (`Medico::existeCI`), franjas completas y superposición (`Horario::seSuperpone`). | A6 y B13: sin métodos nuevos en los controladores; se usan los métodos del documento. | Aplicado. |
| CU03, CU05, CU06 | Editar se hace con `index()` y `?editar={id}`, que carga el registro en el formulario integrado. | B1: formularios integrados en `index()` sin métodos inventados. | Aplicado. |
| CU03 | `Usuario::obtener()` (para editar) y `Medico::listar()` (selector del médico vinculado) no figuran en la tabla de clases de CU03. | Son métodos del documento (CU04 y CU06). | Aplicado; sin métodos nuevos. |
| CU03 | Al desactivar una cuenta con una sesión abierta, esa sesión sigue activa hasta que expire o se cierre. | El documento no lo pide. Se puede cerrar al instante con un control en el middleware si se desea. | **Para decidir.** |
| CU03 | `restablecerClave()` no tiene Form Request. | No recibe datos del formulario (solo el usuario de la ruta y el token CSRF). | Aplicado. |
| CU03 | La contraseña temporal tiene 12 caracteres aleatorios y siempre cumple RNF05; se muestra una sola vez (mensaje flash). | CU03, paso 6. | Aplicado. |
| CU04 | `Usuario::listar()` alimenta el filtro por usuario; no figura en la tabla de clases de CU04. | Método del documento (CU03). | Aplicado. |
| CU04 | Si se indica «hasta», «desde» es obligatorio; si solo hay «desde», el rango de 90 días se cuenta hasta hoy. | B11 (máximo de 90 días) necesita las dos fechas. | Aplicado. |
| CU04 | El CSV usa `;` como separador y BOM UTF-8, y la exportación queda en la bitácora (`EXPORTAR`). | Excel en español abre así el archivo con tildes. | Aplicado. |
| CU05 | La edad máxima se compara con la mínima solo si hay mínima (por ejemplo «hasta 18 años»). | Igual que el CHECK `ck_especialidad_edades`. | Aplicado (error encontrado y corregido con una prueba). |
| CU06 | Formato del CI: entre 5 y 12 dígitos con extensión opcional (`1234567-1B`). El buscador pide al menos 2 caracteres. | El documento no fija formatos; CU09 pide 3 caracteres para pacientes. | **Pendiente de aprobación.** |
| CU06 | Al editar se conservan las especialidades ya asignadas aunque hoy estén inactivas; no se pueden agregar especialidades inactivas nuevas. | Evita que la sincronización (B5) quite una especialidad solo por estar inactiva. | Aplicado. |
| CU07 | `formHorario` se incluye dentro de `horarios`, y las franjas del horario recién guardado se muestran en un aviso. Cada horario de la lista muestra también sus franjas. | V.horarios y V.formHorario del documento; paso 4. | Aplicado. |
| CU07 | Reactivar un horario (B6) solo se permite si no se superpone con otro activo. | Lo exige la restricción EXCLUDE `ex_horario_no_solapado`. | Aplicado. |
| CU03–CU07 | Desactivar un médico con citas futuras no muestra advertencias. | B4: se aplazó al ciclo 2. | Sin cambios. |

## Nombres que no están en el documento (aprobados por la autora)

| Elemento | Nombre | Motivo |
|---|---|---|
| Plantilla Blade | `plantilla.blade.php` | Estructura común (barra superior, mensajes, pie). Las vistas siguen planas. |
| Página «/» | sin vista: redirige a `V.login` | Se eliminó `inicio.blade.php`. |
| Portal del paciente | `portal.blade.php` | CU01, paso 9. |
| Form Requests | `<método>Request` por módulo (`Acceso/AutenticarRequest`, …) | Obligatorios (RNF07); el documento no los nombra. |
| Middlewares | `VerificarRol`, `VerificarClaveTemporal`, `SinCache` | RNF03, RN23 y CU02 (botón «Atrás»). |
| Servicio | `ServicioBitacora` | Decisión B8 de `CLAUDE.md`. |
| Política RNF05 | `Password::defaults()` de Laravel | Sin clase con nombre nuevo. |
| Opciones del menú por rol | `config/menu.php` | Una sola lista para la barra, `V.menu` y el portal. Aprobado por la autora al cerrar el paso 3. |

## Modelos (paso 2)

- **Métodos que no están en el documento:** relaciones de Eloquent (`rol()`, `especialidades()`, `paciente()`, …) y `getAuthPassword()` / `getAuthPasswordName()` en `Usuario`, que Laravel necesita para autenticar con la columna `password_hash`. Se consideran equivalentes a las llamadas estándar de Eloquent (B13).
- **Qué reciben y qué devuelven algunos métodos (el documento solo da los nombres):**
  - `Bloqueo::estaBloqueada()` devuelve el bloqueo con su motivo, o `null`, porque CU13 debe mostrar el motivo.
  - `Rol::obtener()` acepta un id, un nombre o nada (en ese caso devuelve todos).
  - `Usuario::actualizarEstado($accion)` recibe la acción: `activar`, `desactivar` o `desbloquear` (B2).
  - `Usuario::registrarIntentoFallido()` devuelve los minutos de bloqueo aplicados, o `null`.
  - `Recuperacion::crear()` devuelve el token en claro para enviarlo por correo; en la base solo queda su hash.

## Base de datos y pruebas

- **Usuario de los pacientes:** `04_poblacion.sql` crea `maria.lopez` y `luis.fernandez`, pero la decisión A7 exige el CI. El archivo no se modifica: `PoblacionSeeder` actualiza `nombre_usuario` con el CI después de ejecutarlo.
- **Secuencias:** la primera migración borra las 5 secuencias de los códigos antes de ejecutar `01_esquema.sql`, porque `migrate:fresh` solo borra tablas.
- **`tests/Feature/ModelosTest.php`:** queda fuera de los módulos porque prueba los 22 modelos y la población, no un caso de uso.
