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
| CU03–CU07 | Nombres de Form Requests con la entidad: `StoreUsuarioRequest`, `UpdateMedicoRequest`, `BuscarMedicoRequest`, etc. Un `CambiarEstadoRequest` compartido (CU05, CU06, CU07) y `CambiarEstadoUsuarioRequest` (CU03, que suma «desbloquear»). | La convención aprobada era `<método>Request`, pero en el módulo hay varios `store`/`update`; se agrega la entidad, como ya se hizo con `UpdateClaveRequest`. | Aprobado por la autora al cerrar el paso 4; se aplica en todo el ciclo 1. |
| CU03–CU07 | Las reglas que no son de formato están en `after()` del Form Request: usuario o correo duplicado (`Usuario::buscarPorLogin`), único administrador, nombre normalizado (`Especialidad::existeNombre`), CI (`Medico::existeCI`), franjas completas y superposición (`Horario::seSuperpone`). | A6 y B13: sin métodos nuevos en los controladores; se usan los métodos del documento. | Aplicado. |
| CU03, CU05, CU06 | Editar se hace con `index()` y `?editar={id}`, que carga el registro en el formulario integrado. | B1: formularios integrados en `index()` sin métodos inventados. | Aplicado. |
| CU03 | `Usuario::obtener()` (para editar) y `Medico::listar()` (selector del médico vinculado) no figuran en la tabla de clases de CU03. | Son métodos del documento (CU04 y CU06). | Aplicado; sin métodos nuevos. |
| CU03 | Una cuenta desactivada con una sesión abierta pierde esa sesión en la siguiente petición (middleware `VerificarCuentaActiva` en el grupo `web`, con registro `SESION_CERRADA` en la bitácora). | El documento no lo pide; la autora lo aprobó al cerrar el paso 4. El nombre del middleware sigue el patrón `Verificar…` aprobado. | Aplicado; prueba `GestionarUsuariosTest::test_la_sesion_de_una_cuenta_desactivada_se_cierra_en_la_siguiente_peticion`. |
| CU03 | `restablecerClave()` no tiene Form Request. | No recibe datos del formulario (solo el usuario de la ruta y el token CSRF). | Aplicado. |
| CU03 | La contraseña temporal tiene 12 caracteres aleatorios y siempre cumple RNF05; se muestra una sola vez (mensaje flash). | CU03, paso 6. | Aplicado. |
| CU04 | `Usuario::listar()` alimenta el filtro por usuario; no figura en la tabla de clases de CU04. | Método del documento (CU03). | Aplicado. |
| CU04 | Si se indica «hasta», «desde» es obligatorio; si solo hay «desde», el rango de 90 días se cuenta hasta hoy. | B11 (máximo de 90 días) necesita las dos fechas. | Aplicado. |
| CU04 | El CSV usa `;` como separador y BOM UTF-8, y la exportación queda en la bitácora (`EXPORTAR`). | Excel en español abre así el archivo con tildes. | Aplicado. |
| CU05 | La edad máxima se compara con la mínima solo si hay mínima (por ejemplo «hasta 18 años»). | Igual que el CHECK `ck_especialidad_edades`. | Aplicado (error encontrado y corregido con una prueba). |
| CU06 | Formato del CI: entre 5 y 12 dígitos con extensión opcional (`1234567-1B`). El buscador pide al menos 2 caracteres. | El documento no fija formatos; CU09 pide 3 caracteres para pacientes. | Aprobado por la autora al cerrar el paso 4. |
| CU06 | Al editar se conservan las especialidades ya asignadas aunque hoy estén inactivas; no se pueden agregar especialidades inactivas nuevas. | Evita que la sincronización (B5) quite una especialidad solo por estar inactiva. | Aplicado. |
| CU07 | `formHorario` se incluye dentro de `horarios`, y las franjas del horario recién guardado se muestran en un aviso. Cada horario de la lista muestra también sus franjas. | V.horarios y V.formHorario del documento; paso 4. | Aplicado. |
| CU07 | Reactivar un horario (B6) solo se permite si no se superpone con otro activo. | Lo exige la restricción EXCLUDE `ex_horario_no_solapado`. | Aplicado. |
| CU03–CU07 | Desactivar un médico con citas futuras no muestra advertencias. | B4: se aplazó al ciclo 2. | Sin cambios. |

## Revisión del paso 5 (Pacientes)

Los controladores tienen exactamente los métodos del documento: `CtrlPacientes` (index, store, update, destroy, buscar, show), `CtrlRegistro` (registrar), `CtrlPerfil` (show, update, storeAntecedente) y `CtrlSeguros` (index, store, destroy). Las vistas son `pacientes`, `buscarPaciente`, `registro`, `perfil` y `seguro`.

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU08 | Un paciente desactivado no se puede reactivar desde el sistema. | El documento solo define `destroy()` / `desactivar()`; B6 habla de `cambiarEstado()` en los demás módulos. | **Para decidir.** |
| CU08, CU10 | El sexo es obligatorio en los formularios, aunque la columna admite nulos. | Los dos CU lo piden entre los datos del paciente. | Aplicado. |
| CU08, CU10, CU11 | El CI de pacientes usa el mismo formato aprobado para médicos (5 a 12 dígitos con extensión opcional). | Coherencia con la aprobación del paso 4. | Aplicado. |
| CU08 | Con un CI duplicado, el mensaje muestra el código y el nombre del paciente existente («Ya existe el paciente P-0001 – …»). | Excepción 1: «muestra al paciente existente». `existeCI()` y `buscarPorCI()` se usan en el Form Request. | Aplicado. |
| CU09 | Con más de 50 coincidencias no se muestra la lista, solo el aviso para precisar la búsqueda. | Excepción 3. | Aplicado. |
| CU09 | Desde el paciente seleccionado se puede editar sus datos (CU08) y, para la recepcionista, ir a su seguro (CU12). Citas, historial y resultados llegan con el ciclo 2. | Paso 4 y postcondición. | Aplicado. |
| CU10 | El registro pide contacto de madre, padre o tutor si quien se registra es menor de 18 años. | B10 de `CLAUDE.md`; el formulario del documento no lo menciona. | Aplicado. |
| CU10 | Los apellidos se comparan sin distinguir mayúsculas, tildes ni espacios repetidos. Una ficha inactiva se trata como «los datos no coinciden: acuda a recepción». | Paso 5 y excepción 2. | Aplicado. |
| CU10 | Al vincular una ficha existente, se marca la aceptación de privacidad y se completa el correo si la ficha no tenía. Usa `Paciente::actualizar()` y `Usuario::buscarPorLogin()`, que no figuran en la tabla de CU10. | Son métodos del documento (CU08 y CU01). | Aplicado. |
| CU10 | `Usuario::crear()` ahora recarga el registro después de guardarlo. | Sin eso, la cuenta recién creada no tenía en memoria los valores por defecto (`estado`) y `VerificarCuentaActiva` cerraba la sesión. Lo detectó una prueba. | Aplicado. |
| CU11 | El tipo de sangre se declara una sola vez; si ya está registrado, el paciente no lo cambia (lo cambia la clínica). | El documento no dice quién lo corrige; así se evita que el paciente sobrescriba un dato de la clínica. | **Para decidir.** |
| CU11 | El paciente declara solo los cuatro tipos del paso 4: alergia, enfermedad crónica, medicamento y antecedente familiar. «Cirugía» y «Otro» quedan para el médico. | Paso 4. | Aplicado. |
| CU11 | El correo del perfil y el de la cuenta se mantienen iguales (`Usuario::actualizarCorreo()`). La preferencia de privacidad es la casilla `acepta_privacidad`. | Pasos 3 y 5. | Aplicado. |
| CU11 | Intentar enviar CI, nombres, apellidos o fecha de nacimiento devuelve el error «Solo la recepción puede cambiar…». | Excepción 2. | Aplicado. |
| CU12 | Solo la recepcionista registra seguros; el administrador no tiene acceso. | Actor del documento: Recepcionista (A1 da al administrador el CRUD de pacientes, no el de seguros). | **Para decidir.** |
| CU12 | La póliza duplicada se busca sin distinguir mayúsculas, con una consulta de Eloquent en el Form Request (el documento no tiene un método para eso). | Excepción 1 y B13. | Aplicado. |

## Nombres que no están en el documento (aprobados por la autora)

| Elemento | Nombre | Motivo |
|---|---|---|
| Plantilla Blade | `plantilla.blade.php` | Estructura común (barra superior, mensajes, pie). Las vistas siguen planas. |
| Página «/» | sin vista: redirige a `V.login` | Se eliminó `inicio.blade.php`. |
| Portal del paciente | `portal.blade.php` | CU01, paso 9. |
| Form Requests | `<método>Request` por módulo (`Acceso/AutenticarRequest`, …) | Obligatorios (RNF07); el documento no los nombra. |
| Middlewares | `VerificarRol`, `VerificarClaveTemporal`, `SinCache`, `VerificarCuentaActiva` | RNF03, RN23, CU02 (botón «Atrás») y cuenta desactivada (CU03). |
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
