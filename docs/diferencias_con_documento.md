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
| CU08 | Se agrega `CtrlPacientes@cambiarEstado()` con `accion=activar` (`PATCH /pacientes/{paciente}/estado`, `CambiarEstadoPacienteRequest`) para reactivar a un paciente desactivado, con registro `ACTIVAR` en la bitácora. Desactivar sigue siendo `destroy()` del documento. | El documento solo define `destroy()` / `desactivar()`; la autora aprobó la reactivación al cerrar el paso 5 (B2, B6). Es un método que no está en el documento. | Aplicado; prueba `GestionarPacientesTest::test_reactiva_un_paciente_desactivado_y_lo_registra`. |
| CU08, CU10 | El sexo es obligatorio en los formularios, aunque la columna admite nulos. | Los dos CU lo piden entre los datos del paciente. | Aplicado. |
| CU08, CU10, CU11 | El CI de pacientes usa el mismo formato aprobado para médicos (5 a 12 dígitos con extensión opcional). | Coherencia con la aprobación del paso 4. | Aplicado. |
| CU08 | Con un CI duplicado, el mensaje muestra el código y el nombre del paciente existente («Ya existe el paciente P-0001 – …»). | Excepción 1: «muestra al paciente existente». `existeCI()` y `buscarPorCI()` se usan en el Form Request. | Aplicado. |
| CU09 | Con más de 50 coincidencias no se muestra la lista, solo el aviso para precisar la búsqueda. | Excepción 3. | Aplicado. |
| CU09 | Desde el paciente seleccionado se puede editar sus datos (CU08) y, para la recepcionista, ir a su seguro (CU12). Citas, historial y resultados llegan con el ciclo 2. | Paso 4 y postcondición. | Aplicado. |
| CU10 | El registro pide contacto de madre, padre o tutor si quien se registra es menor de 18 años. | B10 de `CLAUDE.md`; el formulario del documento no lo menciona. | Aplicado. |
| CU10 | Los apellidos se comparan sin distinguir mayúsculas, tildes ni espacios repetidos. Una ficha inactiva se trata como «los datos no coinciden: acuda a recepción». | Paso 5 y excepción 2. | Aplicado. |
| CU10 | Al vincular una ficha existente, se marca la aceptación de privacidad y se completa el correo si la ficha no tenía. Usa `Paciente::actualizar()` y `Usuario::buscarPorLogin()`, que no figuran en la tabla de CU10. | Son métodos del documento (CU08 y CU01). | Aplicado. |
| CU10 | `Usuario::crear()` ahora recarga el registro después de guardarlo. | Sin eso, la cuenta recién creada no tenía en memoria los valores por defecto (`estado`) y `VerificarCuentaActiva` cerraba la sesión. Lo detectó una prueba. | Aplicado. |
| CU11 | El tipo de sangre se declara una sola vez; si ya está registrado, el paciente no lo cambia (lo cambia la clínica). | El documento no dice quién lo corrige; así se evita que el paciente sobrescriba un dato de la clínica. La autora lo aprobó al cerrar el paso 5: la recepcionista y el administrador lo corrigen en CU08 (campo «Tipo de sangre» del formulario de pacientes, guardado en la historia clínica, B7, y registrado en la bitácora con el valor anterior y el nuevo). | Aplicado; prueba `GestionarPacientesTest::test_recepcionista_y_administrador_corrigen_el_tipo_de_sangre`. |
| CU11 | El paciente declara solo los cuatro tipos del paso 4: alergia, enfermedad crónica, medicamento y antecedente familiar. «Cirugía» y «Otro» quedan para el médico. | Paso 4. | Aplicado. |
| CU11 | El correo del perfil y el de la cuenta se mantienen iguales (`Usuario::actualizarCorreo()`). La preferencia de privacidad es la casilla `acepta_privacidad`. | Pasos 3 y 5. | Aplicado. |
| CU11 | Intentar enviar CI, nombres, apellidos o fecha de nacimiento devuelve el error «Solo la recepción puede cambiar…». | Excepción 2. | Aplicado. |
| CU12 | Solo la recepcionista registra seguros; el administrador no tiene acceso. | Actor del documento: Recepcionista (A1 da al administrador el CRUD de pacientes, no el de seguros). | Aprobado por la autora al cerrar el paso 5; prueba `RegistrarSeguroTest::test_solo_la_recepcionista_registra_seguros`. |
| CU12 | La póliza duplicada se busca sin distinguir mayúsculas, con una consulta de Eloquent en el Form Request (el documento no tiene un método para eso). | Excepción 1 y B13. | Aplicado. |

## Revisión del paso 6 (Disponibilidad)

`CtrlDisponibilidad` tiene exactamente los métodos del documento: especialidades(), medicos() y franjas(). La vista es `disponibilidad`. Comparación con el esquema indicado por la autora:

| Carril del esquema | Implementación | Prueba |
|---|---|---|
| Elegir especialidad | `especialidades()` → `Paciente::obtener()` + `Especialidad::listarPorEdad()` | `test_el_paciente_ve_solo_las_especialidades_de_su_edad`, `test_paciente_sin_especialidad_para_su_edad` |
| Elegir médico | `medicos()` → `Medico::listarPorEspecialidad()` | `test_lista_los_medicos_activos_de_la_especialidad` |
| Fecha con horario | `franjas()`: `Bloqueo::estaBloqueada()` → `Horario::obtenerDelDia()` → `Horario::generarFranjas()` → `Cita::listarOcupadas()`, en ese orden | `test_franjas_libres_y_ocupadas_sin_datos_de_otros_pacientes`, `test_descarta_las_franjas_pasadas_del_dia`, `test_las_citas_canceladas_no_ocupan_la_franja` |
| Fecha bloqueada con motivo | `estaBloqueada()` devuelve el bloqueo y la vista muestra su motivo | `test_fecha_bloqueada_del_medico_muestra_el_motivo_y_la_siguiente_fecha`, `test_fecha_bloqueada_para_toda_la_clinica` |
| No atiende ese día → siguiente fecha | `obtenerDelDia()` vacío → búsqueda de la siguiente fecha con franjas libres | `test_medico_que_no_atiende_ese_dia_sugiere_la_siguiente_fecha`, `test_sin_franjas_libres_sugiere_la_siguiente_fecha` |

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU13 | No se usa `fn_franjas_disponibles` ni en el código ni en las pruebas. | Indicación de la autora en el paso 6 (A3 permitía usarla en pruebas para contrastar). | Aplicado. |
| CU13 | En la revisión se corrigió el orden de llamadas: `listarOcupadas()` se llamaba antes de `generarFranjas()`. | El esquema pide `estaBloqueada → obtenerDelDia → generarFranjas → listarOcupadas`. | Corregido. |
| CU13 | El cálculo de un día es una función local (closure) dentro de `franjas()`; se reutiliza para buscar la siguiente fecha disponible. | Regla 1 y B13: sin métodos nuevos en el controlador. | Aplicado. |
| CU13 | La siguiente fecha se busca en los 60 días siguientes (saltando bloqueos y días sin horario). Si no hay ninguna: «No hay disponibilidad en los próximos 60 días». También se sugiere cuando el médico atiende pero no tiene franjas libres. | El documento no fija el plazo de búsqueda. | Aplicado. |
| CU13 | La recepcionista consulta para el paciente seleccionado en CU09 (botón «Disponibilidad»); sin paciente, la vista pide seleccionarlo. Un paciente siempre consulta para sí mismo, aunque envíe otro `paciente_id` (RN18). | Paso 1: las especialidades dependen de la edad del paciente (RN15). | Aplicado. |
| CU13 | En el paso 1 la edad se calcula a hoy; al consultar franjas se usa la edad en la fecha elegida. | RN15: «edad del paciente en la fecha de la cita». | Aplicado. |
| CU13 | El médico se obtiene con `Medico::listarPorEspecialidad()` y la especialidad con `Especialidad::listarPorEdad()` (los métodos de la tabla de CU13), no con `obtener()`. | Fidelidad con la tabla de clases de CU13. | Aplicado. |
| CU13 | Las franjas pasadas del día no se muestran; las ocupadas se muestran como «Ocupada», sin datos de la cita ni del paciente. Desde CU14 (ciclo 2) cada franja libre es un enlace para programar la cita. | Pasos 5 y 6; programar la cita es CU14. | Aplicado (actualizado en CU14). |
| CU13 | Form Requests `EspecialidadesRequest`, `MedicosRequest` y `FranjasRequest` (`<método>Request`, porque el módulo tiene un solo controlador). | Convención aprobada. | Aplicado. |

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

---

# Ciclo 2 (`docs/casos_de_uso_ciclo2.md`)

Las decisiones D1 a D13 del documento del ciclo 2 fueron aprobadas por la autora al iniciar el ciclo. En D4, el envío se hace con el comando `notificaciones:enviar` y, además, al abrir la bandeja (Render gratuito no tiene tareas programadas).

## Nombres que no están en las secciones «Clases» (aprobados por la autora)

| Elemento | Nombre | Motivo |
|---|---|---|
| Método de modelo | `Notificacion::enviarPendientes()` | D3 y D4: un solo punto de envío para el comando, la bandeja y los avisos inmediatos. |
| Bandeja (D5) | vista `notificaciones` con ruta closure (sin controlador) | Mismo patrón aprobado en CU27 (validación del enlace). |
| Comando (D4) | `notificaciones:enviar`, closure en `routes/console.php` | Sin clase con nombre nuevo. |
| Traductor de errores de la BD (D1, RNF08) | `withExceptions` en `bootstrap/app.php` | Errores de triggers y restricciones convertidos en mensajes claros, sin clase nueva. |
| Advertencia al desactivar (B4) | CU06 y CU07, con confirmación como en CU24 | Se implementa al final del ciclo 2. |

## Cambios al documento del ciclo 2 pedidos por la autora

- CU14 y CU15: excepción «el médico no atiende la especialidad seleccionada» (la exige `fn_validar_cita`).
- CU14: nota de que un paciente (o médico) sin cuenta no recibe notificaciones (`notificacion.usuario_id` es obligatorio).
- Orden de implementación: CU25 antes que CU24.

## Revisión por caso de uso

### CU14 – Programar cita

`CtrlCitas` tiene solo create() y store() (más adelante, los métodos de CU15, CU16 y CU17). Vista `programarCita`. Todos los métodos de la tabla de clases de CU14 se usan; el cálculo de la franja es una expresión con `Horario::obtenerDelDia()` y `generarFranjas()`, sin métodos nuevos en el controlador.

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU14 | El comprobante (paso 9) se muestra en `programarCita` con `GET /citas/programar?cita={id}` (create()). Usa `Cita::obtener()`, que es de la tabla de CU15/CU16. Un paciente solo ve el comprobante de sus citas (403). | El documento no nombra una vista para el comprobante; mismo patrón que `?editar=` (B1). | Aplicado. |
| CU14 | Form Requests `Citas/CreateCitaRequest` (mostrar) y `StoreCitaRequest` (programar, agrega modalidad y motivo). Las reglas del paso 5 están en el método protegido `validarFranja()` del Form Request, que reutilizará CU15 (con `citaExcluida()`), como `esMenor()` o `reglasMedico()` del ciclo 1. En el Form Request el paciente y el médico se cargan con `find()` (B13) para informar si no existen. | Convención aprobada de Form Requests; B13. | Aplicado. |
| CU14 | La hora de fin no la envía el navegador: se toma de la franja del horario (RN08). | Evita duraciones manipuladas. | Aplicado. |
| CU14 | El paciente siempre programa para sí: si no envía `paciente_id` se usa su ficha; si envía el de otra persona, se rechaza con «Solo puede programar citas para usted (RN18)». | Excepción RN18 (en CU13 se reemplazaba en silencio; aquí el documento pide manejar la excepción). | Aplicado. |
| CU14 | D2: si la restricción `ex_cita_no_solapada` rechaza la inserción (SQLSTATE 23P01), `store()` lleva de nuevo a la disponibilidad del mismo médico y fecha con «La franja ya fue ocupada por otro usuario». | Excepción 1 y D2. | Aplicado; probado con un insert concurrente simulado. |
| CU14 | Notificaciones: confirmación y recordatorio al paciente, aviso «Agenda» al médico, solo si tienen cuenta. El paciente las recibe por los canales que eligió en su perfil (CU11): `notif_alerta` = bandeja y `notif_correo` = correo; el médico, solo en la bandeja. Si faltan menos de 24 horas para la cita, el recordatorio se programa para el momento. | D3; el documento no fija qué pasa con las preferencias de CU11 ni con citas cercanas. | Aplicado. |
| CU14 | El contador de la bandeja cuenta los avisos del canal Sistema ya vencidos y en estado Pendiente; al abrir la bandeja pasan a Enviado (equivale a leídos). | La tabla no tiene una columna de lectura; no se agregan columnas. | Aplicado. |
| CU14 | Un correo inmediato se envía al confirmar la transacción (`DB::afterCommit`), no dentro de ella. | Si la transacción se revierte, no se envía un aviso falso. | Aplicado. |
| CU14 | El traductor de errores (bootstrap/app.php) muestra el texto de los RAISE EXCEPTION de los triggers (ya redactados para el usuario) y un mensaje por nombre de restricción para EXCLUDE, UNIQUE y CHECK; vuelve a la página anterior con los datos ingresados. | D1, RNF08. | Aplicado. |

### CU19 – Consultar historial de citas

`CtrlHistorialCitas` tiene exactamente index() y show(). Vista `historialCitas` (mostrar, filtrar y verDetalle en la misma vista).

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU19 | Una sola ruta para el personal y el paciente: `GET /historial-citas?paciente_id=`. El paciente no envía `paciente_id` (se usa el suyo); si envía el de otra persona se rechaza (RN18). | Paso 1: «Mis citas» del paciente y el historial abierto desde CU09. | Aplicado. |
| CU19 | El detalle (show) usa `Cita::obtener()`, de la tabla de CU15/CU16. Un paciente que abre una cita ajena recibe 403. | Paso 4; RN18. | Aplicado. |
| CU19 | Form Request `Citas/FiltrarHistorialCitasRequest` (como `FiltrarBitacoraRequest` de CU04). El rango de fechas no tiene límite de días. | El documento solo pide rechazar el rango invertido. | Aplicado. |
| CU19 | Paginación de 20 citas por página. | Pacientes con muchas citas. | Aplicado. |
| CU19 | Los botones «Reprogramar» y «Cancelar» aparecen solo para la recepcionista y el paciente, en citas Programadas o Confirmadas; para el paciente, solo si faltan al menos 2 horas (si no, el aviso de RN17). El administrador solo consulta. | Paso 5; actores de CU15 y CU16. Los botones se activan cuando existen las rutas de CU15 y CU16. | Aplicado. |

### CU16 – Cancelar cita

`CtrlCitas` suma show() y cancelar(), exactamente los de la tabla de CU16. Vista `cancelarCita`.

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU16 | Dentro de la transacción la cita se vuelve a leer con `lockForUpdate()` (B13) antes de cancelarla; si otro usuario la cambió, se informa «La cita cambió de estado mientras tanto». `trg_validar_transicion` respalda la regla. | Paso 5. | Aplicado. |
| CU16 | La confirmación (paso 2) es un `confirm()` del navegador al enviar el formulario. | El documento pide confirmación sin fijar la forma. | Aplicado. |
| CU16 | El aviso de cancelación incluye el motivo y va al paciente y al médico (si tienen cuenta). Solo se anulan los recordatorios (tipo Recordatorio) pendientes o fallidos. | Paso 9 y D3. | Aplicado. |
| CU16 | La recepcionista puede cancelar sin el plazo de 2 horas; el paciente fuera de plazo ve el aviso de RN17 en lugar del formulario. Form Request `Citas/CancelarCitaRequest`. | RN17 aplica solo al portal. | Aplicado. |
| CU16 | Tras cancelar se muestra el detalle de la cita en `historialCitas` (CU19). | Paso 10: «confirma la cancelación». | Aplicado. |

### CU15 – Reprogramar cita

`CtrlCitas` suma edit() y update(), exactamente los de la tabla de CU15. Vista `reprogramarCita`.

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU15 | Para mostrar las franjas ocupadas, edit() usa `Cita::listarOcupadas()` (tabla de CU13); la propia cita se marca como «actual». El cálculo es una expresión dentro de edit(), sin métodos nuevos. | Paso 2: «fechas y franjas libres del mismo médico (CU13)». | Aplicado. |
| CU15 | Form Requests `EditCitaRequest` (fecha a consultar) y `UpdateCitaRequest`, que hereda de `CreateCitaRequest` y reutiliza `validarFranja()` sin contar la propia cita. El paciente, el médico y la especialidad se toman siempre de la cita (no del formulario). | Paso 7 y convención de Form Requests. | Aplicado. |
| CU15 | El historial guarda la reprogramación con estado anterior = estado nuevo y la observación «Reprogramada del dd/mm/aaaa hh:mm al dd/mm/aaaa hh:mm»; en `historialCitas` se muestra como «Reprogramación». | Paso 8: «con el usuario responsable y la nueva fecha»; la tabla no tiene columnas de fechas. | Aplicado. |
| CU15 | El comprobante actualizado (paso 10) es el de CU14 (`programarCita` con `?cita=`). | Mismo comprobante, sin otra vista. | Aplicado. |
| CU15 | D2: si la restricción EXCLUDE rechaza la nueva franja, se vuelve a `reprogramarCita` con la misma fecha y el aviso. Dentro de la transacción la cita se lee con `lockForUpdate()` (B13). | Excepción 3. | Aplicado. |
| CU15 | Con el médico inactivo, la vista no ofrece franjas e indica cancelar y programar otra cita. | Excepciones 5 y 7. | Aplicado. |

### CU17 – Registrar asistencia

`CtrlCitas` suma cambiarEstado(), el método de la tabla de CU17. Las operaciones confirmar(), atender() y registrarInasistencia() de `V.agenda` envían el parámetro `accion` (`confirmar`, `atender`, `registrarInasistencia`), igual que B2 del ciclo 1.

| CU | Diferencia | Motivo | Estado |
|---|---|---|---|
| CU17 | Se implementa antes que CU18 (orden de la autora): el commit de CU17 lleva el servidor y las pruebas; los botones se agregan a `V.agenda` en CU18. | Orden aplicado. | Aplicado. |
| CU17 | Las reglas están en el Form Request `Citas/CambiarEstadoCitaRequest` (llama a `Usuario::puedeMarcarAtendida()`); las transiciones están en la constante `Cita::TRANSICIONES`, igual que `fn_validar_transicion`, que las respalda. | Convención del ciclo 1 (reglas en `after()`). | Aplicado. |
| CU17 | La recepcionista puede confirmar en cualquier momento antes del cambio a un estado final; Atendida e Inasistencia solo desde la hora de inicio (D6). El médico solo marca Atendida, y solo en sus citas. | Paso 2 y D6. | Aplicado. |
| CU17 | Excepción 6 (dos usuarios a la vez): la transacción vuelve a leer la cita con `lockForUpdate()`; si su estado ya cambió, el segundo recibe «Transición no permitida: la cita ya cambió de estado». | Excepción 6. | Aplicado; probado. |
| CU17 | `Usuario::puedeMarcarAtendida()` exige además que la cuenta sea de un médico y esté activa. | RN12. | Aplicado. |
