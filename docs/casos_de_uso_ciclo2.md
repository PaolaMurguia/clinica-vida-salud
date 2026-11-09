# Casos de uso del ciclo 2 (CU14 a CU25)

Fuente de verdad funcional del ciclo 2. Continúa `docs/casos_de_uso_ciclo1.md` y usa el mismo formato: **los nombres de vistas, controladores, modelos y métodos del código deben coincidir con los de la sección «Clases» de cada caso de uso**, porque la docente compara los diagramas con el software.

Las reglas de trabajo de `CLAUDE.md` siguen vigentes (nombres idénticos al documento, módulos por carpeta, comentarios con el código del caso de uso, un commit por caso de uso, actualizar `docs/trazabilidad_ciclo1.md` y `docs/diferencias_con_documento.md`, y avisar de cualquier diferencia antes de seguir).

## Módulos nuevos

- **Citas**: CU14, CU15, CU16, CU17, CU18, CU19
- **Clinica**: CU20, CU21, CU22
- **Pagos**: CU25
- **Administracion**: CU23, CU24 (se suman a los del ciclo 1)

## Orden de implementación sugerido

CU14 → CU19 → CU16 → CU15 → CU17 → CU18 → CU24 → CU25 → CU20 → CU22 → CU21 → CU23

Orden aplicado por la autora: CU14 → CU19 → CU16 → CU15 → CU17 → CU18 → CU25 → CU24 → CU20 → CU22 → CU21 → CU23 (CU25 antes que CU24). Los botones de CU17 están en V.agenda, que se crea con CU18.

El orden respeta las dependencias: CU14 crea las citas; CU19 es el punto de partida de CU15 y CU16; CU17 y CU18 usan las citas; CU24 y CU25 completan la agenda y el cobro; CU20 y CU22 usan las citas atendidas; CU21 depende de CU20.

## Reglas del negocio que usa el ciclo 2

- **RN03**: Una cita deberá estar asociada a un paciente y a un médico válidos.
- **RN04**: El médico deberá estar activo para recibir nuevas citas.
- **RN05**: La cita deberá pertenecer al horario de atención del médico.
- **RN06**: No se podrán registrar citas en fechas pasadas.
- **RN07**: Un médico no podrá tener citas superpuestas.
- **RN08**: La duración de la cita deberá respetar la configuración del sistema.
- **RN09**: Las citas canceladas no ocuparán disponibilidad.
- **RN10**: Las citas atendidas conservarán su registro histórico.
- **RN11**: No se eliminarán pacientes o médicos con citas históricas asociadas; se desactivarán.
- **RN12**: Solo los usuarios autorizados podrán realizar cada operación.
- **RN13**: Las operaciones de programación y reprogramación verificarán la disponibilidad en el servidor.
- **RN15**: Una cita solo podrá registrarse en una especialidad cuyo rango de edad incluya la edad del paciente en la fecha de la cita.
- **RN16**: Cada resultado de laboratorio se evaluará con el rango de referencia que corresponde a la edad del paciente en la fecha de la muestra.
- **RN17**: El paciente solo podrá cancelar o reprogramar sus citas desde el portal hasta dos horas antes de la hora programada; después deberá comunicarse con la recepción.
- **RN18**: El paciente solo podrá consultar y modificar su propia información; el médico solo podrá ver la historia clínica de los pacientes que atiende o atendió, y la recepción no accederá a los diagnósticos.
- **RN19**: La cobertura del seguro se aplicará al cobro solo si el seguro está activo y vigente en la fecha de la cita.
- **RN20**: Los análisis de laboratorio solo los solicita un médico; los resultados los registra la recepcionista y el sistema notifica al médico solicitante.
- **RN21**: Los recordatorios se enviarán 24 horas antes de la cita y únicamente para citas en estado Programada o Confirmada.

Las reglas RN15 a RN21 deben estar también en la tabla de reglas del documento Word.

## Decisiones por confirmar con la autora antes de implementar

Cada decisión explica cómo se resolvió algo que el documento no fija. Si la autora corrige alguna, hay que actualizar este archivo y el Word.

- **D1 Lógica en modelos y transacciones, no en procedimientos almacenados.** Igual que el ciclo 1: las reglas se implementan en los modelos y en Form Requests, dentro de DB::transaction. Los triggers (trg_validar_cita, trg_validar_transicion, trg_aplicar_cobertura, trg_interpretar_resultado) y la restricción ex_cita_no_solapada actúan como red de seguridad. El código captura sus errores (RAISE EXCEPTION y EXCLUDE) y los traduce a mensajes claros sin mostrar detalles internos (RNF08). Los procedimientos sp_programar_cita, sp_cancelar_cita, sp_reprogramar_cita, sp_registrar_pago, sp_solicitar_examen, sp_registrar_resultado y la función fn_ingresos quedan solo como referencia.
- **D2 Concurrencia al reservar.** Dos usuarios pueden elegir la misma franja. La transacción vuelve a verificar la disponibilidad y, si la base de datos rechaza la inserción por la restricción de exclusión, el usuario recibe «La franja ya fue ocupada» y se le vuelve a mostrar la disponibilidad (CU14, CU15).
- **D3 Notificaciones: tipos, destinatarios y canal.** Tipos de la tabla notificacion: Confirmacion (paciente, al programar), Agenda (médico, al programar), Recordatorio (paciente, 24 horas antes), Reprogramacion y Cancelacion (paciente y médico) y Resultado (médico solicitante y paciente). El canal Sistema crea una notificación en la bandeja; el canal Correo usa el mailer log en desarrollo. Estados: Pendiente, Enviado, Fallido y Anulado, con contador de intentos. Un fallo de envío se registra y no interrumpe la operación principal.
- **D4 Envío de los recordatorios (aprobada).** Los recordatorios se guardan como notificaciones pendientes con fecha_programada. Se envían con el comando de Artisan `notificaciones:enviar` y, además, las notificaciones vencidas se procesan al abrir la bandeja, porque el plan gratuito de Render no tiene tareas programadas.
- **D5 Bandeja de notificaciones.** No tiene caso de uso propio. Se agrega la vista notificaciones y un contador en la plantilla, para mostrar las notificaciones del canal Sistema a cada usuario. Es un nombre nuevo que no figura en el documento: hay que agregarlo al Word.
- **D6 Estados de la cita (CU17).** Según el Word, la recepcionista confirma la asistencia o registra la inasistencia, y solo el médico con el permiso marca la cita como atendida. Atendida e Inasistencia no se aceptan antes de la hora de inicio de la cita; esa regla la aplica el código (la base de datos no la exige). Las transiciones válidas son las de fn_validar_transicion: Programada pasa a Confirmada, Atendida, Cancelada o Inasistencia; Confirmada pasa a Atendida, Cancelada o Inasistencia; los demás estados son finales.
- **D7 Registro de atención (CU20).** Se crea solo para una cita Atendida del propio médico y hay uno por cita (restricción UNIQUE). El médico puede modificar su propio registro y cada modificación queda en la bitácora. La recepción nunca ve diagnósticos (RN18).
- **D8 Resultados de laboratorio (CU22).** El requisito RF20 habla de «adjuntar» resultados; la tabla guarda un valor numérico por parámetro y no archivos, por lo que se implementa el registro estructurado. El paciente ve los exámenes en estado Registrado o Revisado y recibe una notificación cuando están disponibles (RF21).
- **D9 Exportación de reportes (CU23).** PDF con una librería de PDF para Laravel, CSV con separador punto y coma (se abre en Excel, igual que la bitácora del CU04) e impresión con una hoja de estilos de impresión. Los reportes no incluyen diagnósticos.
- **D10 Bloqueos (CU24).** Solo se registran y se eliminan; no se editan (si hay un error, se elimina y se crea otro). No se aceptan fechas pasadas. Crear un bloqueo no cancela las citas existentes: el sistema las muestra y pide confirmación.
- **D11 Pagos (CU25).** Un pago por cita (UNIQUE). Estados Pagado y Pendiente: al cobrar se exige el método (Efectivo o QR); un pago Pendiente se completa después. La cobertura se toma del seguro activo y vigente en la fecha de la cita (RN19); sin seguro vigente se cobra el 100 %. El monto del paciente lo calcula la base de datos (columna generada).
- **D12 Métodos nuevos en los modelos.** M.Cita (obtener, existeConflicto, crear, reprogramar, cancelar, cambiarEstado, listarPorPaciente, listarAgenda, listarReporte, contarPorEstado, existeAtencion, listarPorFecha), M.HistorialCita, M.Notificacion, M.RegistroAtencion, M.ExamenLaboratorio, M.ParametroLaboratorio, M.RangoReferencia, M.ResultadoLaboratorio y M.Pago (hoy solo tienen relaciones); M.Bloqueo (listar, listarPorRango, existe, crear, eliminar), M.Usuario::puedeMarcarAtendida, M.Especialidad::obtenerCosto, M.Seguro::obtenerVigente, M.Paciente::listarRegistrados. Cada nombre figura en la sección Clases de su caso de uso.
- **D13 Datos de prueba.** 04_poblacion.sql ya carga 14 citas (8 pasadas en todos los estados y 6 futuras), pagos y exámenes. Las pruebas del ciclo 2 deben convivir con esos datos y con las secuencias que no retroceden (usar el formato del código, no números fijos).

Las decisiones D1 a D13 fueron aprobadas por la autora al iniciar el ciclo 2.

## Nombres agregados que no figuran en las secciones «Clases» (aprobados por la autora)

- **M.Notificacion::enviarPendientes()**: envía las notificaciones vencidas (fecha_programada ya cumplida y estado Pendiente). Canal Sistema: pasa a Enviado cuando el destinatario abre la bandeja. Canal Correo: se envía con el mailer configurado; si falla, suma un intento y queda Fallido sin interrumpir la operación (D3). La usan el comando, la bandeja y los avisos inmediatos.
- **Bandeja de notificaciones (D5)**: vista `V.notificaciones`, publicada con una ruta closure (sin controlador), como la validación del enlace de CU27.
- **Comando `notificaciones:enviar` (D4)**: closure en `routes/console.php`, sin clase.
- **Traductor de errores de la base de datos (D1, RNF08)**: en `bootstrap/app.php` (`withExceptions`). Convierte los errores de los triggers (RAISE EXCEPTION) y de las restricciones EXCLUDE, UNIQUE y CHECK en mensajes claros, sin detalles internos.
- **Advertencia al desactivar (B4 del ciclo 1)**: implementada al final del ciclo 2, solo para médicos (CU06) y horarios (CU07), con confirmación como en CU24. Excepción que se agrega a CU06 y CU07 en el Word: «El médico (o el horario) tiene citas futuras: el sistema las muestra y exige confirmar; las citas no se cancelan solas».

---

## CU14 Programar cita

- **Requisito:** RF06, RF07, RF21
- **Actores:** Recepcionista, Paciente
- **Actor iniciador:** Recepcionista, Paciente
- **Prioridad:** Alta · **Riesgo:** Crítico
- **Propósito:** Registrar una cita médica en una franja libre, verificando de nuevo la disponibilidad y evitando citas superpuestas (RF06, RF07, RF21).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista o Paciente.
- El paciente está registrado y activo (CU08, CU10).
- Existen médicos activos con horarios registrados (CU06 y CU07).

**Flujo principal**
1. La recepcionista busca al paciente (CU09) y abre «Disponibilidad»; el paciente entra desde su portal y siempre programa la cita para sí mismo (RN18).
2. El usuario elige la especialidad, el médico, la fecha y una franja libre con la consulta de disponibilidad (CU13).
3. El sistema muestra el resumen (paciente, especialidad, médico, fecha y horario) y pide la modalidad (presencial o virtual) y, de forma opcional, un motivo breve.
4. JavaScript verifica que los datos obligatorios estén completos.
5. El servidor, dentro de una transacción, vuelve a verificar que el paciente y el médico estén activos (RN03, RN04), que la especialidad corresponda a la edad del paciente (RN15), que la franja pertenezca al horario del médico y respete la duración configurada (RN05, RN08), que la fecha no sea pasada ni esté bloqueada (RN06) y que no exista otra cita no cancelada que se superponga (RN07, RN09, RN13).
6. El sistema guarda la cita en estado Programada con un código único correlativo (CM-0001), la fecha de creación y el canal de registro (Recepción o Web).
7. El sistema registra el estado inicial en el historial de estados de la cita.
8. El sistema programa el recordatorio para 24 horas antes de la cita (RN21), envía la confirmación al paciente y avisa al médico en su agenda (RF21).
9. El sistema registra la operación en la bitácora (CU04) y muestra el comprobante de la cita.

**Postcondiciones**
- La cita queda en estado Programada y ocupa la franja del médico.
- El historial de estados, las notificaciones y la bitácora quedan registrados.

**Excepciones (cada una debe manejarse)**
- La franja fue ocupada por otro usuario mientras tanto: el sistema rechaza la operación, informa el motivo y vuelve a mostrar la disponibilidad.
- Fecha u hora pasada.
- Médico o paciente inactivo.
- La especialidad no corresponde a la edad del paciente (RN15).
- El médico no atiende la especialidad seleccionada.
- Franja fuera del horario del médico o con duración inválida.
- Fecha bloqueada por feriado o bloqueo de agenda (CU24).
- El paciente intenta programar una cita para otra persona (RN18).
- Datos obligatorios incompletos o inválidos: el servidor rechaza la operación.

**Nota:** un paciente sin cuenta de usuario (registrado solo por la recepción) no recibe notificaciones, porque cada notificación necesita un usuario destinatario. Lo mismo ocurre con un médico sin cuenta.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_ProgramarCita` → vista `V.programarCita`, operaciones: mostrar(), programar()
- Control `CtrlCitas`, métodos: create(), store()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: obtener()
- Entidad `ESPECIALIDAD` → modelo `M.Especialidad`, métodos: listarPorEdad()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: obtener()
- Entidad `BLOQUEO` → modelo `M.Bloqueo`, métodos: estaBloqueada()
- Entidad `HORARIO` → modelo `M.Horario`, métodos: obtenerDelDia(), generarFranjas()
- Entidad `CITA` → modelo `M.Cita`, métodos: existeConflicto(), crear()
- Entidad `HISTORIAL_CITA` → modelo `M.HistorialCita`, métodos: registrar()
- Entidad `NOTIFICACION` → modelo `M.Notificacion`, métodos: programar()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** trigger trg_validar_cita (fn_validar_cita), restricción ex_cita_no_solapada (EXCLUDE), secuencia seq_cita_codigo; tablas cita, historial_estado_cita, notificacion.

---

## CU15 Reprogramar cita

- **Requisito:** RF08, RF21
- **Actores:** Recepcionista, Paciente
- **Actor iniciador:** Recepcionista, Paciente
- **Prioridad:** Alta · **Riesgo:** Crítico
- **Propósito:** Cambiar la fecha y la hora de una cita Programada o Confirmada sin conflictos de agenda (RF08, RF21).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista o Paciente.
- La cita existe y está en estado Programada o Confirmada.
- Si el usuario es paciente, la cita es suya (RN18).

**Flujo principal**
1. El usuario elige la cita desde el historial de citas (CU19) y selecciona «Reprogramar».
2. El sistema muestra los datos actuales de la cita y las fechas y franjas libres del mismo médico (CU13).
3. El usuario elige la nueva fecha y franja.
4. JavaScript verifica que se haya elegido una nueva franja.
5. Si el usuario es paciente, el servidor verifica que falten al menos dos horas para la cita (RN17).
6. El servidor, dentro de una transacción, verifica que el médico siga activo, que la franja pertenezca a su horario y respete la duración (RN05, RN08), que la fecha no sea pasada ni esté bloqueada (RN06) y que no se superponga con otra cita no cancelada, sin contar la propia cita (RN07, RN09, RN13).
7. El sistema actualiza la fecha y el horario; la cita conserva su código, su paciente, su médico, su especialidad y su estado.
8. El sistema registra la reprogramación en el historial de estados, con el usuario responsable y la nueva fecha.
9. El sistema anula los recordatorios pendientes, programa uno nuevo para 24 horas antes (RN21) y envía el aviso de reprogramación al paciente y al médico (RF21).
10. El sistema registra la operación en la bitácora (CU04) y muestra el comprobante actualizado.

**Postcondiciones**
- La cita queda con la nueva fecha y horario, y la franja anterior vuelve a estar libre.
- El historial de estados, las notificaciones y la bitácora quedan registrados.

**Excepciones (cada una debe manejarse)**
- Cita Atendida, Cancelada o con Inasistencia: no se reprograma; se registra una nueva cita (CU14).
- Paciente fuera del plazo de dos horas: el sistema le indica que debe comunicarse con la recepción (RN17).
- La nueva franja fue ocupada mientras tanto: el sistema rechaza la operación y vuelve a mostrar la disponibilidad.
- Nueva fecha pasada, bloqueada o fuera del horario del médico.
- Médico inactivo.
- El médico no atiende la especialidad seleccionada (por ejemplo, se le quitó después de programar la cita).
- Se elige la misma fecha y hora: no hay cambios.
- Para cambiar de médico o de especialidad se cancela la cita (CU16) y se programa otra (CU14).
- El paciente intenta reprogramar una cita ajena (RN18).

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_ReprogramarCita` → vista `V.reprogramarCita`, operaciones: mostrar(), reprogramar()
- Control `CtrlCitas`, métodos: edit(), update()
- Entidad `CITA` → modelo `M.Cita`, métodos: obtener(), existeConflicto(), reprogramar()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: obtener()
- Entidad `BLOQUEO` → modelo `M.Bloqueo`, métodos: estaBloqueada()
- Entidad `HORARIO` → modelo `M.Horario`, métodos: obtenerDelDia(), generarFranjas()
- Entidad `HISTORIAL_CITA` → modelo `M.HistorialCita`, métodos: registrar()
- Entidad `NOTIFICACION` → modelo `M.Notificacion`, métodos: anularPendientes(), programar()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** trigger trg_validar_cita (BEFORE UPDATE de fecha y horas), restricción ex_cita_no_solapada; tablas cita, historial_estado_cita, notificacion.

---

## CU16 Cancelar cita

- **Requisito:** RF09, RF21
- **Actores:** Recepcionista, Paciente
- **Actor iniciador:** Recepcionista, Paciente
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Cancelar una cita conservando su registro y liberando la franja para otros pacientes (RF09, RF21).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista o Paciente.
- La cita existe y está en estado Programada o Confirmada.
- Si el usuario es paciente, la cita es suya (RN18).

**Flujo principal**
1. El usuario elige la cita desde el historial de citas (CU19) y selecciona «Cancelar».
2. El sistema muestra los datos de la cita y pide el motivo de la cancelación y la confirmación.
3. JavaScript verifica que el motivo no esté vacío.
4. Si el usuario es paciente, el servidor verifica que falten al menos dos horas para la cita (RN17).
5. El servidor, dentro de una transacción, verifica que la cita esté en estado Programada o Confirmada.
6. El sistema cambia el estado a Cancelada y guarda el motivo; el registro no se elimina (RN10).
7. La franja queda libre de inmediato para nuevas reservas (RN09).
8. El sistema registra el cambio en el historial de estados (usuario responsable, estado anterior y motivo).
9. El sistema anula los recordatorios pendientes y envía el aviso de cancelación al paciente y al médico (RF21).
10. El sistema registra la operación en la bitácora (CU04) y confirma la cancelación.

**Postcondiciones**
- La cita queda en estado Cancelada y permanece visible para las consultas históricas.
- La franja queda libre.
- El historial de estados, las notificaciones y la bitácora quedan registrados.

**Excepciones (cada una debe manejarse)**
- Motivo de cancelación vacío.
- La cita ya está cancelada.
- Cita Atendida o con Inasistencia: no se cancela como si nunca hubiera ocurrido (RN10).
- Paciente fuera del plazo de dos horas: el sistema le indica que debe comunicarse con la recepción (RN17).
- El paciente intenta cancelar una cita ajena (RN18).

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_CancelarCita` → vista `V.cancelarCita`, operaciones: mostrar(), cancelar()
- Control `CtrlCitas`, métodos: show(), cancelar()
- Entidad `CITA` → modelo `M.Cita`, métodos: obtener(), cancelar()
- Entidad `HISTORIAL_CITA` → modelo `M.HistorialCita`, métodos: registrar()
- Entidad `NOTIFICACION` → modelo `M.Notificacion`, métodos: anularPendientes(), programar()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** trigger trg_validar_transicion (fn_validar_transicion); tablas cita, historial_estado_cita, notificacion.

---

## CU17 Registrar asistencia

- **Requisito:** RF10
- **Actores:** Recepcionista, Médico
- **Actor iniciador:** Recepcionista, Médico
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Controlar el ciclo de vida de la cita: confirmar la asistencia, marcarla como atendida o registrar la inasistencia (RF10).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista o Médico.
- La cita existe y está en estado Programada o Confirmada.
- Si el usuario es médico, la cita es suya y tiene asignado el permiso de marcar citas como atendidas (CU03).

**Flujo principal**
1. El usuario abre la agenda del día (CU18) y elige la cita.
2. El sistema muestra las acciones permitidas según el estado de la cita y el rol: la recepcionista puede confirmar la asistencia o registrar la inasistencia; el médico puede marcar la cita como atendida.
3. El usuario elige la acción.
4. El servidor verifica que la transición sea válida: Programada pasa a Confirmada, Atendida o Inasistencia; Confirmada pasa a Atendida o Inasistencia.
5. El sistema verifica que Atendida e Inasistencia no se registren antes de la hora de inicio de la cita.
6. El sistema cambia el estado y lo registra en el historial de estados (usuario, estado anterior y estado nuevo).
7. Si el estado nuevo es Atendida o Inasistencia, el sistema anula los recordatorios pendientes (RN21).
8. El sistema registra la operación en la bitácora (CU04) y muestra el nuevo estado.

**Postcondiciones**
- La cita queda en su nuevo estado.
- Una cita Atendida queda disponible para el registro clínico (CU20) y el cobro (CU25); una Inasistencia se cuenta en los reportes (CU23).
- El historial de estados y la bitácora quedan registrados.

**Excepciones (cada una debe manejarse)**
- Transición no permitida (por ejemplo, de Cancelada a Atendida): el sistema la rechaza y explica el motivo (RF10).
- El médico no tiene el permiso de marcar citas como atendidas.
- El médico intenta cambiar el estado de una cita de otro médico.
- La recepcionista intenta marcar una cita como atendida: solo lo hace el médico con el permiso.
- Atendida o Inasistencia antes de la hora de inicio de la cita.
- Dos usuarios cambian la misma cita a la vez: se conserva el primer cambio y el segundo recibe el aviso de transición no permitida.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Agenda` → vista `V.agenda`, operaciones: confirmar(), atender(), registrarInasistencia()
- Control `CtrlCitas`, métodos: cambiarEstado()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: puedeMarcarAtendida()
- Entidad `CITA` → modelo `M.Cita`, métodos: obtener(), cambiarEstado()
- Entidad `HISTORIAL_CITA` → modelo `M.HistorialCita`, métodos: registrar()
- Entidad `NOTIFICACION` → modelo `M.Notificacion`, métodos: anularPendientes()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** trigger trg_validar_transicion (fn_validar_transicion); tablas cita, historial_estado_cita, notificacion.

---

## CU18 Consultar agenda médica

- **Requisito:** RF11
- **Actores:** Recepcionista, Médico, Administrador
- **Actor iniciador:** Recepcionista, Médico, Administrador
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Mostrar las citas de un médico en un día o en una semana, con filtros por fecha, médico y estado (RF11).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista, Médico o Administrador.
- El médico consultado está registrado (CU06).

**Flujo principal**
1. El usuario ingresa a «Agenda». El médico ve su propia agenda; la recepcionista y el administrador eligen el médico (RN18).
2. El sistema muestra las citas de hoy; el usuario puede elegir otra fecha o la vista semanal y filtrar por estado.
3. El servidor valida los filtros y consulta las citas del médico en ese rango, ordenadas por fecha y hora.
4. El sistema muestra la hora, el código de la cita, el paciente, la especialidad, el motivo, el estado y la modalidad, e indica las fechas bloqueadas con su motivo.
5. Desde cada cita, el usuario autorizado puede registrar la asistencia (CU17) y el médico puede abrir la historia clínica del paciente (CU20).

**Postcondiciones**
- La agenda queda visible con las citas ordenadas por hora.
- La consulta no modifica datos.

**Excepciones (cada una debe manejarse)**
- El médico no tiene citas en el rango: el sistema lo informa.
- Fecha o rango inválido (la fecha final es anterior a la inicial).
- El médico intenta ver la agenda de otro médico (RN18).
- Médico inactivo: se muestra su agenda histórica, pero no recibe nuevas citas (RN04).
- Fecha bloqueada: el sistema muestra el motivo.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Agenda` → vista `V.agenda`, operaciones: mostrar(), filtrar()
- Control `CtrlAgenda`, métodos: index(), filtrar()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: obtener(), listar()
- Entidad `CITA` → modelo `M.Cita`, métodos: listarAgenda()
- Entidad `BLOQUEO` → modelo `M.Bloqueo`, métodos: listarPorRango()

**Objetos de la base de datos:** sin triggers; consulta sobre cita, paciente, especialidad y bloqueo_agenda.

---

## CU19 Consultar historial de citas

- **Requisito:** RF12
- **Actores:** Recepcionista, Administrador, Paciente
- **Actor iniciador:** Recepcionista, Administrador, Paciente
- **Prioridad:** Media · **Riesgo:** Normal
- **Propósito:** Mostrar la relación cronológica de las citas de un paciente, incluidas las pasadas, las canceladas y las inasistencias (RF12).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista, Administrador o Paciente.
- El paciente está registrado (CU08, CU10).

**Flujo principal**
1. La recepcionista o el administrador buscan al paciente (CU09) y abren su historial; el paciente abre «Mis citas» y solo ve las suyas (RN18).
2. El sistema lista las citas de la más reciente a la más antigua, con el código, la fecha y el horario, el médico, la especialidad, el estado, el motivo y la fecha de registro.
3. El usuario puede filtrar por estado y por rango de fechas.
4. Al abrir una cita, el sistema muestra su historial de estados (quién hizo cada cambio y cuándo) y el motivo de cancelación, si lo hay.
5. Si la cita está Programada o Confirmada, el sistema ofrece reprogramarla (CU15) o cancelarla (CU16) según el rol y el plazo (RN17).

**Postcondiciones**
- El historial queda visible con las citas pasadas y canceladas, sin perder la trazabilidad (RN10).
- La consulta no muestra diagnósticos ni datos clínicos, y no modifica datos.

**Excepciones (cada una debe manejarse)**
- El paciente no tiene citas registradas: el sistema lo informa.
- Paciente inactivo: su historial se puede consultar igualmente (RN11).
- El paciente intenta ver el historial de otro paciente (RN18).
- Filtro inválido (por ejemplo, rango de fechas invertido).

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_HistorialCitas` → vista `V.historialCitas`, operaciones: mostrar(), filtrar(), verDetalle()
- Control `CtrlHistorialCitas`, métodos: index(), show()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: obtener()
- Entidad `CITA` → modelo `M.Cita`, métodos: listarPorPaciente()
- Entidad `HISTORIAL_CITA` → modelo `M.HistorialCita`, métodos: listarPorCita()

**Objetos de la base de datos:** sin triggers; consulta sobre cita e historial_estado_cita.

---

## CU20 Gestionar historia clínica

- **Requisito:** RF16
- **Actores:** Médico, Paciente
- **Actor iniciador:** Médico
- **Prioridad:** Alta · **Riesgo:** Crítico
- **Propósito:** Registrar y consultar la atención clínica del paciente (diagnóstico, tratamiento, indicaciones y signos vitales) y sus antecedentes, con acceso restringido por rol (RF16, RN18).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Médico o Paciente.
- Para registrar una atención, la cita es del médico y está en estado Atendida (CU17).

**Flujo principal**
1. El médico abre la agenda (CU18), elige un paciente que atiende o atendió y abre su historia clínica.
2. El sistema muestra el tipo de sangre, los antecedentes (del paciente y del médico), las atenciones anteriores y los exámenes de laboratorio. Si el paciente no tiene historia clínica, el sistema la crea.
3. Para una cita atendida sin registro, el médico escribe el diagnóstico (obligatorio), el tratamiento, las indicaciones y los signos vitales (peso, talla, presión arterial y temperatura); también puede dictar el texto (CU21).
4. JavaScript y el servidor validan los datos: diagnóstico obligatorio y signos vitales dentro de los rangos permitidos.
5. El sistema guarda un único registro de atención para la cita y actualiza la fecha de modificación de la historia clínica.
6. El médico puede agregar antecedentes (alergia, enfermedad crónica, medicamento, cirugía o familiar); los antecedentes no se eliminan.
7. Si hace falta, el médico solicita un análisis de laboratorio (CU22).
8. El sistema registra la operación en la bitácora (CU04).
9. El paciente consulta su propia historia clínica (atenciones, antecedentes y resultados) sin poder modificar los datos médicos (RN18).

**Postcondiciones**
- La atención queda registrada y vinculada a la cita y a la historia clínica del paciente.
- El paciente puede consultarla en su portal.
- La operación queda registrada en la bitácora.

**Excepciones (cada una debe manejarse)**
- Diagnóstico vacío o signos vitales fuera de rango.
- La cita no está en estado Atendida: no se registra la atención.
- La cita ya tiene registro de atención: se modifica el existente, no se crea otro.
- Un médico intenta abrir la historia de un paciente que no atiende ni atendió (RN18).
- La recepcionista intenta ver diagnósticos: acceso denegado (RN18).
- Un paciente intenta ver la historia clínica de otro (RN18).

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_HistoriaClinica` → vista `V.historiaClinica`, operaciones: mostrar(), registrarAtencion(), agregarAntecedente()
- Control `CtrlHistoriaClinica`, métodos: show(), storeAtencion(), updateAtencion(), storeAntecedente()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: obtener()
- Entidad `CITA` → modelo `M.Cita`, métodos: obtener(), existeAtencion()
- Entidad `HISTORIA` → modelo `M.Historia`, métodos: obtener()
- Entidad `ANTECEDENTE` → modelo `M.Antecedente`, métodos: listar(), crear()
- Entidad `REGISTRO_ATENCION` → modelo `M.RegistroAtencion`, métodos: obtenerPorCita(), listarPorPaciente(), crear(), actualizar()
- Entidad `EXAMEN_LABORATORIO` → modelo `M.ExamenLaboratorio`, métodos: listarPorPaciente()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** trigger trg_tocar_historia (fn_tocar_historia) al agregar antecedentes; restricción UNIQUE en registro_atencion.cita_id; CHECK de peso, talla y temperatura.

---

## CU21 Dictar registro clínico por voz

- **Requisito:** RF22
- **Actores:** Médico
- **Actor iniciador:** Médico
- **Prioridad:** Media · **Riesgo:** Crítico
- **Propósito:** Permitir que el médico dicte el diagnóstico, el tratamiento o las indicaciones y que el navegador convierta la voz en texto dentro del formulario (RF22).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Médico.
- El médico está registrando una atención (CU20).
- El navegador es compatible con el reconocimiento de voz (Web Speech API) y el equipo tiene micrófono.

**Flujo principal**
1. En el formulario de registro de atención (CU20), el médico pulsa «Dictar» junto al campo que desea llenar (diagnóstico, tratamiento o indicaciones).
2. El navegador solicita permiso para usar el micrófono.
3. El médico dicta; el reconocimiento de voz del navegador convierte la voz en texto y el sistema lo muestra en el campo mientras se dicta.
4. El médico pulsa «Detener», revisa el texto y lo corrige con el teclado si hace falta.
5. Al guardar, el servidor valida el texto igual que si se hubiera escrito (CU20) y marca el registro como dictado por voz.
6. El sistema no guarda el audio.

**Postcondiciones**
- El campo queda con el texto dictado y revisado por el médico.
- El registro de atención indica que se usó el dictado por voz.

**Excepciones (cada una debe manejarse)**
- Navegador sin soporte de reconocimiento de voz: el sistema oculta el botón e indica que se escriba con el teclado.
- Permiso de micrófono denegado.
- No se reconoce la voz (ruido o pronunciación): el sistema permite reintentar.
- Falla el reconocimiento o se pierde la conexión: el formulario sigue funcionando con el teclado y el resto del sistema no se afecta.
- El texto dictado supera la longitud máxima del campo: el servidor lo rechaza al guardar.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_HistoriaClinica` → vista `V.historiaClinica`, operaciones: dictar(), detener()
- Control `CtrlHistoriaClinica`, métodos: storeAtencion()
- Entidad `REGISTRO_ATENCION` → modelo `M.RegistroAtencion`, métodos: crear()

**Objetos de la base de datos:** columna registro_atencion.dictado_voz (booleano); el reconocimiento de voz corre en el navegador, no en el servidor.

---

## CU22 Gestionar resultados de laboratorio

- **Requisito:** RF20, RF21
- **Actores:** Recepcionista, Médico, Paciente
- **Actor iniciador:** Médico
- **Prioridad:** Alta · **Riesgo:** Crítico
- **Propósito:** Solicitar análisis de laboratorio, registrar sus resultados interpretados según la edad del paciente y notificar a los usuarios (RF20, RF21).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista, Médico o Paciente.
- El paciente está registrado y el médico lo atiende (CU20).
- Los parámetros de laboratorio y sus rangos de referencia por edad están registrados.

**Flujo principal**
1. El médico solicita un análisis desde la historia clínica del paciente (CU20): elige el tipo de examen y, si quiere, escribe una observación.
2. El sistema genera el código del examen (EX-00001) en estado Solicitado y registra la solicitud en la bitácora (CU04).
3. El laboratorio entrega los resultados en recepción; la recepcionista busca el examen por código o por paciente.
4. La recepcionista registra la fecha de la muestra y el valor de cada parámetro.
5. El servidor valida que el examen no esté revisado, que la fecha de la muestra no sea futura ni anterior a la solicitud, que el parámetro exista y esté activo, que el valor sea numérico y que no se repita el parámetro en el examen.
6. El sistema obtiene el rango de referencia que corresponde a la edad del paciente en la fecha de la muestra (RN16), guarda el rango aplicado e interpreta cada valor como Bajo, Normal o Alto.
7. El examen pasa a Registrado y queda vinculado a la historia clínica del paciente; el sistema notifica al médico solicitante en su bandeja (RN20, RF21) y avisa al paciente.
8. El médico consulta los resultados con su interpretación y marca el examen como Revisado.
9. El paciente consulta en el portal los resultados de sus exámenes con el rango de referencia aplicado.
10. El sistema registra cada operación en la bitácora (CU04).

**Postcondiciones**
- Los resultados quedan registrados, interpretados y vinculados a la historia clínica.
- El médico y el paciente quedan notificados.
- Un examen Revisado ya no se modifica.

**Excepciones (cada una debe manejarse)**
- Solo un médico solicita análisis: la recepcionista y el paciente no pueden (RN20).
- El examen no existe o ya está Revisado: el sistema no lo modifica.
- Fecha de muestra inválida.
- Parámetro inexistente o inactivo.
- No existe rango de referencia para la edad del paciente: el sistema rechaza el valor e informa el motivo.
- Parámetro repetido en el mismo examen.
- Valor no numérico.
- Un paciente intenta ver los exámenes de otro (RN18), o un médico los de un paciente que no atiende.
- Falla el envío de la notificación por correo: se registra y el resultado se conserva.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Examenes` → vista `V.examenes`, operaciones: solicitar(), buscar(), registrarResultados(), verResultados(), marcarRevisado()
- Control `CtrlExamenes`, métodos: index(), store(), registrarResultados(), show(), marcarRevisado()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: obtener()
- Entidad `EXAMEN_LABORATORIO` → modelo `M.ExamenLaboratorio`, métodos: crear(), buscar(), obtener(), registrarMuestra(), marcarRevisado(), listarPorPaciente()
- Entidad `PARAMETRO_LABORATORIO` → modelo `M.ParametroLaboratorio`, métodos: listar(), obtenerPorNombre()
- Entidad `RANGO_REFERENCIA` → modelo `M.RangoReferencia`, métodos: obtenerPorEdad()
- Entidad `RESULTADO_LABORATORIO` → modelo `M.ResultadoLaboratorio`, métodos: registrar(), listarPorExamen()
- Entidad `NOTIFICACION` → modelo `M.Notificacion`, métodos: programar()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** trigger trg_interpretar_resultado (fn_interpretar_resultado), restricción ex_rango_no_solapado, UNIQUE (examen_id, parametro_id), CHECK ck_examen_registrado; secuencia seq_examen_codigo.

---

## CU23 Generar reportes

- **Requisito:** RF13
- **Actores:** Administrador
- **Actor iniciador:** Administrador
- **Prioridad:** Media · **Riesgo:** Normal
- **Propósito:** Obtener información consolidada de citas, pacientes e ingresos con filtros y totales, y exportarla (RF13).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Administrador.
- Existen citas, pacientes o pagos registrados.

**Flujo principal**
1. El administrador ingresa a «Reportes» y elige el tipo: citas programadas para una fecha, citas por médico, citas por especialidad, citas por estado, pacientes registrados, atendidas, canceladas e inasistencias en un período, agenda diaria de cada médico, o ingresos por método de pago.
2. El sistema muestra los filtros que corresponden al reporte: fecha o rango de fechas, médico, especialidad y estado.
3. El administrador completa los filtros y genera el reporte.
4. JavaScript y el servidor validan los filtros: la fecha obligatoria cuando el reporte la exige y la fecha final no anterior a la inicial.
5. El sistema consulta los datos con consultas parametrizadas y muestra el reporte en pantalla con sus totales.
6. El administrador puede exportar el reporte a PDF, a Excel/CSV o imprimirlo.
7. El sistema registra la generación y la exportación en la bitácora (CU04).

**Postcondiciones**
- El reporte queda en pantalla con sus totales y, si se pidió, exportado.
- Los reportes no incluyen diagnósticos ni datos clínicos (RN18).

**Excepciones (cada una debe manejarse)**
- Rango de fechas inválido o filtro obligatorio faltante.
- No hay datos para los filtros elegidos: el sistema lo informa y no genera un archivo vacío.
- Falla la exportación: el reporte en pantalla sigue disponible y se informa el error sin detalles internos.
- Un usuario sin rol Administrador intenta generar reportes: acceso denegado (RN12).

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Reportes` → vista `V.reportes`, operaciones: elegirReporte(), generar(), exportar()
- Control `CtrlReportes`, métodos: index(), generar(), exportar()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: listar()
- Entidad `ESPECIALIDAD` → modelo `M.Especialidad`, métodos: listar()
- Entidad `CITA` → modelo `M.Cita`, métodos: listarReporte(), contarPorEstado()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: listarRegistrados()
- Entidad `PAGO` → modelo `M.Pago`, métodos: ingresosPorMetodo()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** consultas de solo lectura sobre cita, paciente y pago; la función fn_ingresos(desde, hasta) existe como referencia, pero el código calcula con el modelo.

---

## CU24 Gestionar feriados y bloqueos

- **Requisito:** RF17
- **Actores:** Administrador
- **Actor iniciador:** Administrador
- **Prioridad:** Baja · **Riesgo:** Normal
- **Propósito:** Registrar feriados de la clínica o bloqueos de agenda de un médico para excluir esas fechas del cálculo de disponibilidad (RF17).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Administrador.
- Para un bloqueo por médico, el médico está registrado (CU06).

**Flujo principal**
1. El administrador ingresa a «Bloqueos» y el sistema lista los bloqueos (fecha, ámbito y motivo), con filtros por médico y por rango de fechas.
2. Selecciona «Nuevo bloqueo» e ingresa la fecha, el ámbito (toda la clínica o un médico) y el motivo (por ejemplo, feriado, vacaciones, baja o permiso).
3. JavaScript verifica que los campos obligatorios estén completos.
4. El servidor valida que la fecha no sea pasada, que el motivo no pase de 120 caracteres y que no exista ya un bloqueo para la misma fecha y el mismo ámbito.
5. Si hay citas no canceladas en esa fecha, el sistema las muestra y pide confirmar; las citas existentes no se cancelan solas y la recepción debe reprogramarlas o cancelarlas (CU15, CU16).
6. El sistema guarda el bloqueo; desde ese momento la fecha se excluye de la disponibilidad (CU13) y no admite nuevas reservas (CU14).
7. Para liberar una fecha, el administrador elimina el bloqueo y confirma.
8. El sistema registra cada operación en la bitácora (CU04).

**Postcondiciones**
- La fecha queda excluida de la disponibilidad y de las nuevas reservas.
- Al eliminar el bloqueo, la fecha vuelve a estar disponible.
- La operación queda registrada en la bitácora.

**Excepciones (cada una debe manejarse)**
- Bloqueo duplicado: misma fecha y mismo ámbito.
- Fecha vacía o pasada.
- Motivo vacío o de más de 120 caracteres.
- Médico inexistente.
- Hay citas programadas en la fecha: el sistema advierte y exige confirmar.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Bloqueos` → vista `V.bloqueos`, operaciones: mostrar(), agregar(), eliminar()
- Control `CtrlBloqueos`, métodos: index(), store(), destroy()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: listar()
- Entidad `BLOQUEO` → modelo `M.Bloqueo`, métodos: listar(), existe(), crear(), eliminar()
- Entidad `CITA` → modelo `M.Cita`, métodos: listarPorFecha()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** restricción uq_bloqueo (UNIQUE NULLS NOT DISTINCT en medico_id y fecha); medico_id nulo significa toda la clínica.

---

## CU25 Registrar pago de consulta

- **Requisito:** RF18
- **Actores:** Recepcionista
- **Actor iniciador:** Recepcionista
- **Prioridad:** Media · **Riesgo:** Normal
- **Propósito:** Registrar el cobro de una cita aplicando la cobertura del seguro vigente y emitir el comprobante (RF18, RF19).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista.
- La cita existe y no está Cancelada.
- Si el paciente tiene seguro, está registrado (CU12).

**Flujo principal**
1. La recepcionista abre la cita desde la agenda (CU18), desde el historial (CU19) o por su código, y selecciona «Cobrar».
2. El sistema muestra los datos de la cita y el costo de la consulta de su especialidad.
3. El sistema busca el seguro del paciente: si está activo y vigente en la fecha de la cita, calcula el porcentaje cubierto, el monto cubierto y el monto que paga el paciente (RN19); si no, el paciente paga el 100 %.
4. La recepcionista elige el método de pago (efectivo o QR) y confirma el cobro, o deja el pago como pendiente.
5. JavaScript y el servidor validan los datos: método de pago obligatorio al cobrar y un solo pago por cita.
6. El sistema guarda el pago con un número de comprobante correlativo (PG-00001), el estado (Pagado o Pendiente), los montos y la fecha del cobro.
7. El sistema emite el comprobante para pantalla e impresión.
8. Un pago pendiente se completa más adelante registrando el método de pago.
9. El sistema registra la operación en la bitácora (CU04).

**Postcondiciones**
- La cita queda con un único pago y su comprobante.
- Los pagos pendientes quedan identificados para su seguimiento.
- La operación queda registrada en la bitácora.

**Excepciones (cada una debe manejarse)**
- Cita cancelada: no se cobra.
- La cita ya tiene un pago registrado: el sistema muestra el existente.
- Seguro vencido o inactivo en la fecha de la cita: se cobra sin cobertura y el sistema lo avisa (RN19).
- Falta el método de pago al cobrar.
- Cita inexistente.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Pagos` → vista `V.pagos`, operaciones: mostrar(), cobrar(), dejarPendiente(), completar()
- Control `CtrlPagos`, métodos: create(), store(), update(), show()
- Entidad `CITA` → modelo `M.Cita`, métodos: obtener()
- Entidad `ESPECIALIDAD` → modelo `M.Especialidad`, métodos: obtenerCosto()
- Entidad `SEGURO` → modelo `M.Seguro`, métodos: obtenerVigente()
- Entidad `PAGO` → modelo `M.Pago`, métodos: obtenerPorCita(), crear(), completar(), listarPendientes()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

**Objetos de la base de datos:** trigger trg_aplicar_cobertura (fn_aplicar_cobertura), columna generada monto_paciente, UNIQUE cita_id, CHECK ck_pago_completo; secuencia seq_pago_comprobante.

---
