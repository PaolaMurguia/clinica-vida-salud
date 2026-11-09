# Diagramas de secuencia del ciclo 1

Hay dos versiones de cada diagrama:

| Carpeta | Para qué | Contenido |
|---|---|---|
| `docs/diagramas/sd_CUxx.puml` | Referencia técnica | Fiel al código: notas con Form Requests, middlewares y `ServicioBitacora`, retornos con tipos PHP, todas las variantes. |
| `docs/diagramas/limpio/sd_CUxx.puml` | Documento Word (draw.io → PNG) | Solo los nombres del documento, sin notas, compatible con draw.io. `limpio/TODOS.md` reúne los 15 para copiarlos uno por uno. |

Las dos versiones se generaron **leyendo el código real** (`routes/web.php`, controladores, Form Requests y modelos). Los participantes usan los nombres de `docs/casos_de_uso_ciclo1.md`.

## Convenciones

**Comunes a las dos versiones**
- Participantes en este orden: actor, vistas (`boundary`), controlador (`control`) y modelos (`entity`), con `activate`/`deactivate`: el actor y el controlador mientras ejecutan, las vistas y los modelos solo durante la llamada.
- Mensajes síncronos `->` y retornos `-->>`.
- Variantes del mismo flujo: un solo `alt` con `else`. Pasos en orden (CU09, CU13, CU27): divisores `== … ==`. Operaciones distintas del menú: un `alt` por operación.

**Solo en la versión limpia (Word)**
- Actor con la etiqueta del Word: «Administrador, Recepcionista, Médico o Paciente» (CU01, CU02, CU26, CU27) o el actor del CU.
- Cada flujo termina con `view()` del controlador hacia la vista de destino. Sin `redirect()`, `back()` ni mensajes de vuelta al actor.
- Todo mensaje `x = operación()` hacia un modelo lleva justo después su retorno `M.Modelo -->> Ctrl : x`. Solo se escribe `x =` cuando el dato se usa después: en otra llamada, en una condición o en el `view()`.
- `registrar()`, `crear()`, `actualizar()` y `cambiarEstado()` no tienen retorno ni asignación. Por eso `medico = crear(datos)` (CU06), `horario = crear(datos)` (CU07) y `paciente = crear(datos)` (CU10) quedaron como `crear(datos)`.
- Las validaciones sin `alt` propio (`existeCI`, `existeNombre`) se dibujan sin asignación ni retorno. `seSuperpone` en CU07 sí lleva `ok =` y retorno, porque decide el `alt`.
- Las validaciones de las tablas de clases (`existeCI`, `existeNombre`, `seSuperpone`, `buscarPorLogin`) van como mensajes del controlador al modelo, aunque el código las ejecute en el Form Request.
- Las vistas nunca llaman a modelos.
- `registrar(X)` indica la acción que queda en la bitácora (`LOGIN`, `CREAR`…).
- Condiciones de `alt`, `opt` y `else` sin corchetes; sin `«»`, `|`, `<`, `>` ni `": "` en las etiquetas; etiquetas de 50 caracteres como máximo.

**Límites de mensajes (versión limpia):** 20 en general; 24 en CU05 y CU08; 25 en CU07, CU11 y CU13; 28 en CU03 y CU06.

| CU | Mensajes | Límite | Estado |
|---|---|---|---|
| CU01 | 13 | 20 | Cumple |
| CU02 | 4 | 20 | Cumple |
| CU03 | 25 | 28 | Cumple |
| CU04 | 18 | 20 | Cumple |
| CU05 | 22 | 24 | Cumple |
| CU06 | 25 | 28 | Cumple |
| CU07 | 22 | 25 | Cumple |
| CU08 | 22 | 24 | Cumple |
| CU09 | 10 | 20 | Cumple |
| CU10 | 13 | 20 | Cumple |
| CU11 | 24 | 25 | Cumple |
| CU12 | 20 | 20 | Cumple |
| CU13 | 25 | 25 | Cumple |
| CU26 | 11 | 20 | Cumple |
| CU27 | 19 | 20 | Cumple |

**Verificación:** no hay PlantUML instalado en el equipo, así que **no se renderizó ningún diagrama**. Se verificó con un script: número de mensajes, que cada asignación tenga su retorno, sin notas, sin caracteres prohibidos, largo de las etiquetas, bloques cerrados y activaciones balanceadas.

## Destinos y elementos que el documento no nombra (omitidos en la versión limpia)

| CU | Elemento del código | En la versión limpia |
|---|---|---|
| CU01 | Paciente → `portal.blade.php`; contraseña temporal → `V.cambiarClave` (CU26) | Solo `view(rol)` hacia `V.menu`. |
| CU10 | Registro correcto → `portal.blade.php` | El operando correcto termina sin `view()`. |
| CU26 | Paciente → `portal.blade.php` | Solo `view(menu)` hacia `V.menu`. |
| CU26 | Quinto fallo: cierre de sesión y `V.login` (B9) | No se dibuja; el operando de error termina en `V.cambiarClave`. |
| CU27 | Al abrir el enlace, la ruta `GET /recuperar-clave/{token}` llama a `validar()` sin pasar por el controlador | No se dibuja la apertura; `validar()` lo llama `CtrlRecuperacion@restablecer()`, como también ocurre en el código. |
| CU08 | Reactivar paciente (`CtrlPacientes@cambiarEstado`, aprobado por la autora) | Omitido: no está en el documento. |
| Todos | Notas: Form Requests, middlewares, `ServicioBitacora`, `DB::transaction`, `Auth::login/logout` | Omitidas. |

## Ajustes de estructura

| CU | Ajuste | Motivo |
|---|---|---|
| CU03 | «crear» y «editar» comparten un `alt … else` (con `registrar()` y `view()` comunes al final); «restablecer contraseña» y «cambiar estado» comparten otro. | Agrupación aprobada. El documento junta cada par en un mismo paso (paso 2: «nuevo o edita»; paso 6: restablecer, desbloquear, activar o desactivar). |
| CU06 | «mostrar» y «buscar» comparten un `alt … else`, con `M.Especialidad.listar()` y `view()` comunes. «crear», «editar» y «cambiar estado» comparten otro, con `registrar()` y `view()` comunes. | Agrupación aprobada. |
| CU07 | En «agregar», `ok = seSuperpone(...)` decide un `alt`: «no se superpone» (crear, generarFranjas, registrar y `view()` a `V.horarios`) o «se superpone» (`view(error)` a `V.formHorario`). | En el código la superposición la rechaza el Form Request y vuelve al formulario. |
| CU13 | `generarFranjas()` se llama una sola vez, sobre `horario = obtenerDelDia(...)`, como en el diagrama original. Un `opt` «médico no atiende ese día o sin franjas libres» muestra `view(siguiente fecha)`. | En el código `obtenerDelDia()` devuelve una colección y se generan las franjas de cada horario. La búsqueda de la siguiente fecha (closure `calcularDia`, hasta 60 días) no se detalla. |

---

## Diferencias por caso de uso

Columnas: **Código** = lo que hace el software (y la versión técnica). **Tabla de clases** = `docs/casos_de_uso_ciclo1.md`. **Word** = lo que muestra la versión limpia que va al documento.

### CU01 Iniciar sesión

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `M.Usuario::verificarClave(clave)` | Lo llama `autenticar()`. | No está en CU01 (solo en CU26). | **Omitido**: la comparación queda implícita en las condiciones del `alt`. |
| `buscarPorLogin(login)` | Devuelve el usuario o `null`. | Está. | `usuario = buscarPorLogin(login)` con retorno `usuario`. |
| `registrarIntentoFallido()` | Devuelve minutos de bloqueo o `null`. | Está. | Sin retorno (no se usa en el diagrama). |
| `M.Rol::obtener(usuario.rol_id)` | Acepta id, nombre o nada. | Está. | `rol = obtener(usuario.rol_id)` con retorno `rol`. |
| Tercer operando | Inexistente, inactiva o bloqueada: registra en la bitácora (`LOGIN_FALLIDO`, `LOGIN_DENEGADO`, `LOGIN_BLOQUEADO`). | — | **Solo `view(error)`**, sin `registrar()`. |
| `registrar(BLOQUEO_CUENTA)` al quinto fallo | Sí. | — | Omitido. |
| Destino | `V.menu`, portal o `V.cambiarClave`. | `V.login` → menú. | `view(rol)` hacia `V.menu`. |

### CU02 Cerrar sesión

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `cerrarSesion(request)` | Recibe `Request`. | Está. | `cerrarSesion()`. |
| Sesión expirada | La resuelve el middleware `auth`. | Excepción del CU. | Omitida. |
| Destino | `redirect(login)`. | — | `view(login)` hacia `V.login` (participante de CU01). |
| `V.menu.salir()` | El botón también está en la barra de `plantilla.blade.php`. | `V.menu`. | `V.menu`. |

### CU03 Gestionar usuarios y roles

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `M.Rol::obtener()` | En `index()` (todos) y en `store()`/`update()` (`'Médico'`). | Está. | En «crear»: `rol = obtener(rol_id)` con retorno. No se dibuja en «editar». |
| `M.Medico::obtener(medico_id)` | Cuando el rol es Médico. | Está. | En «crear», dentro de `opt rol Médico`, con retorno `medico`. No se dibuja en «editar». |
| `M.Medico::listar()`, `M.Usuario::obtener(id)` | En `index()`. | No están en CU03. | Omitidos. |
| `Usuario::buscarPorLogin()` | En `StoreUsuarioRequest::after()`. | No está en CU03. | Omitido. |
| `marcarTemporal(temporal)` | También desbloquea (`reiniciarIntentos()` interno). | Está. | `marcarTemporal(temporal)`. |
| `actualizarEstado(accion)` | activar, desactivar o desbloquear (B2). | Está. | `actualizarEstado(accion)`. |
| Estructura | — | — | Dos pares de operaciones en `alt … else` (ver ajustes). |

### CU04 Consultar bitácora

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `M.Usuario::listar()` | En `index()` y `filtrar()`. | No está en CU04. | Omitido. |
| `M.Usuario::obtener(usuario_id)` | Cuando se filtra por usuario. | Está. | Dentro de `opt filtro por usuario`. |
| `M.Bitacora::buscar(filtros)` | Devuelve la consulta (`Builder`); el controlador pagina. | Está. | `registros = buscar(filtros)`. |
| `M.Bitacora::registrar(EXPORTAR)` | Al exportar. | No está en CU04. | **Incluido** (es un método del documento en otros CU). |
| Destino de exportar | `streamDownload` (CSV), no una vista. | — | `view(archivo CSV)` hacia `V.bitacora`. |

### CU05 Gestionar especialidades

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| Cargar la especialidad a editar | `findOrFail(id)` en `index()`. | No hay método `obtener()` en `M.Especialidad`. | **Omitido**: el documento no tiene un nombre para esa llamada. |
| `existeNombre(nombre)` | En `StoreEspecialidadRequest::after()` (también al editar). | Está. | Mensaje del controlador al modelo en «crear» y en «editar». |
| `cambiarEstado(activar)` | Recibe un booleano. | Está. | `cambiarEstado(activar)`. |

### CU06 Gestionar médicos

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `M.Especialidad::listar()` | En `index()` y `buscar()` (selector del formulario). | Está. | Común a «mostrar» y «buscar»: `especialidades = listar()` con retorno. |
| `existeCI(ci)` | En `StoreMedicoRequest::after()` (también al editar). | Está. | Mensaje del controlador, solo en «crear». |
| `M.Atiende::asignar(medico, especialidades)` | En `store()` y `update()` (sincroniza, B5). | Está. | En «crear» y en «editar». |
| `medico = crear(datos)` | `crear()` devuelve el médico, que se pasa a `asignar()`. | — | `crear(datos)` sin asignación (sin retorno en `crear()`); `asignar(medico, …)` lo usa igual. |
| `M.Medico::obtener(id)` | En `index()` para editar. | No está en CU06. | Omitido. |
| Estructura | — | — | Dos `alt` agrupados (ver ajustes). |

### CU07 Definir horarios de atención

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `generarFranjas()` | Lo llama `CtrlHorarios@store()` después de `crear()`; la vista `horarios.blade.php` también lo llama por cada horario. | Está. | Solo lo llama `CtrlHorarios` después de `crear()`, con retorno `franjas`. |
| `seSuperpone(medico_id, dia, inicio, fin)` | En `StoreHorarioRequest::after()`; al reactivar, en el controlador. | Está. | `ok = seSuperpone(...)` con retorno en «agregar», seguido de `alt` «no se superpone» o «se superpone» (`view(error)` a `V.formHorario`). No se dibuja en «cambiar estado». |
| `horario = crear(datos)` | Devuelve el horario sobre el que se llama `generarFranjas()`. | — | `crear(datos)` sin asignación (sin retorno en `crear()`). |
| `M.Medico::listar()` | En `index()` (selector). | No está en CU07. | Omitido. |
| `V.formHorario` | Incluido dentro de `V.horarios`. | `V.horarios y V.formHorario`. | Participante propio; el destino de «agregar» es `V.horarios`. |

### CU08 Gestionar pacientes

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| Reactivar (`cambiarEstado`) | Existe (aprobado por la autora). | No está. | Omitido. |
| `M.Historia::obtener()` (tipo de sangre, B7) | En `store()` y `update()`. | No está en CU08. | Omitido. |
| `existeCI(ci)` | En `StorePacienteRequest::after()`, también al editar. | Está. | Mensaje del controlador en «crear» y en «editar». |
| `destroy(paciente)` → `desactivar()` | Sí. | Está. | Igual. |

### CU09 Buscar paciente

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| Más de 50 coincidencias y «sin resultados» | El controlador corta a 50 y la vista avisa. | Excepciones del CU. | Omitidas: no cambian los mensajes. |
| `show(paciente)` → `obtener(id)` | Sí. | Está. | Igual. |

### CU10 Registrarse como paciente

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `M.Usuario::buscarPorLogin(ci)` | El controlador verifica si el CI ya tiene cuenta. | No está en CU10 (es de CU01). | **Incluido** con retorno `cuenta`, porque decide el `alt`. |
| Vincular una ficha existente | `M.Paciente::actualizar()`. | No está en CU10. | Omitido: el operando dice «CI nuevo o ficha sin cuenta con datos iguales» y muestra `crear(datos)`, sin asignación. |
| `M.Rol::obtener(Paciente)` | Sí. | Está. | Con retorno `rol`. |
| Destino | Portal. | — | El operando correcto termina sin `view()`; el de error, `view(error)` hacia `V.registro`. |

### CU11 Gestionar perfil del paciente

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| Tipo de sangre | `historia->update()` (Eloquent). | — | Omitido. |
| `M.Historia::obtener()` | La crea si no existe (B7). | Está. | Con retorno `historia` en «mostrar» y en «declarar antecedente». |
| `actualizarCorreo(correo)` | Solo si el correo cambió. | Está. | `opt correo distinto`. |
| `M.Seguro::obtenerActivo()` | Sí. | Está. | Igual. |

### CU12 Registrar seguro del paciente

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| Seguros anteriores | `paciente->seguros()` (relación Eloquent). | — | Omitido. |
| Póliza duplicada | Consulta Eloquent en `StoreSeguroRequest`. | Sin método. | Omitida. |
| Seguro vencido | Se guarda con aviso. | Excepción del CU. | Omitido. |
| `darDeBaja()` antes de `crear()` | Si hay seguro activo. | Está. | `opt existe un seguro activo`. |

### CU13 Consultar disponibilidad

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `estaBloqueada(medico_id, fecha)` | Devuelve el bloqueo con su motivo, o `null`. | Está (sin firma). | Retorno `bloqueo`; `alt fecha bloqueada` → `view(motivo)`. |
| `M.Paciente::obtener()` y `M.Especialidad::listarPorEdad()` | Se repiten en `medicos()` y `franjas()`. | Están. | Solo en «Elegir especialidad». |
| `M.Medico::listarPorEspecialidad()` | También en `franjas()`. | Está. | Solo en «Elegir médico». |
| Siguiente fecha disponible | Closure `calcularDia`, hasta 60 días. | Excepción del CU. | `opt médico no atiende ese día o sin franjas libres` con `view(siguiente fecha)`; la búsqueda no se detalla. |
| `generarFranjas()` | Por cada horario del día (`obtenerDelDia()` devuelve una colección). | Está. | Una sola vez, sin `loop`, sobre `horario = obtenerDelDia(...)`, como en el diagrama original. |

### CU26 Cambiar contraseña

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `registrarIntentoFallido()` | Contraseña actual incorrecta. | No está en CU26 (es de CU01). | Incluido. |
| `reiniciarIntentos()` | Tras un cambio correcto. | No está en CU26 (es de CU01). | Incluido. |
| `verificarClave(clave_actual)` | Devuelve `bool`. | Está. | `ok = verificarClave(clave_actual)` con retorno `ok`. |
| Quinto fallo (B9) | Cierra la sesión y lleva a `V.login`. | — | Omitido. |
| Destino | Portal o menú. | — | `view(menu)` hacia `V.menu`; el error, `view(error)` hacia `V.cambiarClave`. |

### CU27 Recuperar contraseña

| Elemento | Código | Tabla de clases | Word |
|---|---|---|---|
| `validar(token)` al abrir el enlace | Lo llama la ruta (closure), no el controlador. | Está. | Solo en «Restablecer», llamado por `CtrlRecuperacion`. |
| `M.Usuario::reiniciarIntentos()` | Desbloquea la cuenta (paso 8). | No está en CU27 (es de CU01). | Incluido. |
| `Recuperacion::crear(usuario.id, ip)` | Devuelve el token en claro para el correo. | Está. | `crear(usuario.id)`, sin retorno. |
| Envío del correo (`Mail::raw`) y fallas | Sí, con registro `ENVIO_CORREO_FALLIDO`. | Excepción del CU. | Omitido. |
| Límite alcanzado y cuenta inexistente | Registran en la bitácora. | Excepciones del CU. | Quedan fuera de los dos `opt`; ambos terminan en `view(mensaje)`. |
| Destino | `V.login` o aviso en `V.recuperarClave`. | — | `view(login)` hacia `V.login`; `view(aviso)` hacia `V.recuperarClave`. |
