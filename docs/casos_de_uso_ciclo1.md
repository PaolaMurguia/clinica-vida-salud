# Casos de uso del ciclo 1 (CU01 a CU13, CU26 y CU27)

Fuente de verdad funcional del software. Cada caso de uso incluye su especificación y las clases de análisis y de diseño que lo realizan: **los nombres de vistas, controladores, modelos y métodos del código deben coincidir con los de esta tabla**, porque la docente compara los diagramas con el software.

## CU01 Iniciar sesión

- **Requisito:** RF01
- **Actores:** Administrador, Recepcionista, Médico, Paciente
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Autenticar al usuario y habilitar el menú que corresponde a su rol (RF01).

**Precondiciones**
- El usuario está registrado y activo.
- Tiene un rol asignado.

**Flujo principal**
1. El usuario ingresa a la página de inicio de sesión.
2. El sistema muestra el formulario (usuario o correo y contraseña).
3. El usuario ingresa sus credenciales y envía el formulario.
4. JavaScript verifica que los campos no estén vacíos.
5. El servidor sanea los datos y busca al usuario con una consulta parametrizada.
6. El sistema verifica que la cuenta esté activa y sin bloqueo vigente, y compara la contraseña con el hash almacenado (password_verify()).
7. El sistema reinicia el contador de intentos fallidos, regenera el identificador de sesión y guarda el usuario y su rol.
8. El sistema registra el acceso en la bitácora (CU04).
9. El sistema redirige al menú, o al portal si es paciente, con las opciones de su rol; si la contraseña es temporal, antes lo lleva a «Cambiar contraseña» (CU26).

**Postcondiciones**
- Existe una sesión válida con el rol del usuario.
- El acceso queda registrado en la bitácora.

**Excepciones (cada una debe manejarse)**
- Credenciales incorrectas: mensaje genérico, sin indicar cuál dato falló; suma un intento fallido y lo registra en la bitácora.
- Cinco intentos fallidos consecutivos: bloqueo temporal progresivo de 5, 15 y luego 60 minutos (RNF04); el administrador puede desbloquear la cuenta antes (CU03).
- Cuenta bloqueada: informa el tiempo restante sin verificar la contraseña.
- Usuario inactivo: acceso denegado.
- Ya existe una sesión abierta en el navegador: redirige al menú.
- El usuario olvidó su contraseña: elige «¿Olvidó su contraseña?» y continúa en CU27.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Login` → vista `V.login`, operaciones: ingresar()
- Control `CtrlSesion`, métodos: autenticar()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: buscarPorLogin(), reiniciarIntentos(), registrarIntentoFallido()
- Entidad `ROL` → modelo `M.Rol`, métodos: obtener()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU02 Cerrar sesión

- **Requisito:** RF01
- **Actores:** Administrador, Recepcionista, Médico, Paciente
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Finalizar de forma segura la sesión del usuario (RF01).

**Precondiciones**
- El usuario inició sesión (CU01).

**Flujo principal**
1. El usuario selecciona la opción «Salir».
2. El sistema registra el cierre de sesión en la bitácora.
3. El sistema invalida la sesión y regenera el token CSRF.
4. El sistema muestra la página de inicio de sesión.

**Postcondiciones**
- No existe una sesión activa.
- Las páginas internas dejan de ser accesibles.

**Excepciones (cada una debe manejarse)**
- Sesión ya expirada: el sistema redirige directamente al inicio de sesión.
- Botón «Atrás» del navegador: no muestra páginas internas, porque no se guardan en caché.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Menu` → vista `V.menu`, operaciones: salir()
- Control `CtrlSesion`, métodos: cerrarSesion()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU03 Gestionar usuarios y roles

- **Requisito:** RF14
- **Actores:** Administrador
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Registrar y administrar las cuentas de acceso, asignar roles y permisos y controlar su estado (RF14).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Administrador.

**Flujo principal**
1. El administrador ingresa al módulo «Usuarios» y el sistema los lista con su rol y estado.
2. Selecciona «Nuevo usuario» o edita uno existente.
3. Ingresa nombre de usuario, correo, contraseña inicial y rol (Administrador, Recepcionista o Médico); si el rol es Médico, elige el médico vinculado y define si puede marcar citas como atendidas.
4. El sistema valida que el usuario y el correo sean únicos y que la contraseña cumpla la política (RNF05).
5. El sistema guarda la contraseña cifrada y registra la cuenta; la contraseña inicial queda como temporal (RN23).
6. Para restablecer una contraseña, el administrador elige la acción: el sistema genera una contraseña temporal, marca la cuenta como «debe cambiar contraseña» y la muestra una sola vez; el usuario deberá cambiarla al ingresar (CU26). Para desbloquear una cuenta o activarla o desactivarla, el sistema aplica la acción elegida.
7. El sistema registra la operación en la bitácora.

**Postcondiciones**
- La cuenta queda registrada o actualizada con su rol y sus permisos.
- Las cuentas de paciente se crean en el autorregistro (CU10), no en este caso de uso.

**Excepciones (cada una debe manejarse)**
- Usuario o correo duplicado.
- Contraseña que no cumple la política.
- Médico vinculado inexistente, inactivo o que ya tiene una cuenta.
- Desactivar o cambiar el rol del único administrador activo: el sistema lo impide.
- El administrador no puede desactivar su propia cuenta mientras tiene la sesión abierta.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Usuarios` → vista `V.usuarios`, operaciones: mostrar(), crear(), editar(), restablecerClave(), cambiarEstado()
- Control `CtrlUsuarios`, métodos: index(), store(), update(), restablecerClave(), cambiarEstado()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: listar(), crear(), actualizar(), marcarTemporal(), actualizarEstado()
- Entidad `ROL` → modelo `M.Rol`, métodos: obtener()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: obtener()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU04 Consultar bitácora

- **Requisito:** RF15
- **Actores:** Administrador
- **Prioridad:** Media · **Riesgo:** Normal
- **Propósito:** Consultar los accesos y las acciones críticas realizadas en el sistema (RF15).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Administrador.

**Flujo principal**
1. El administrador ingresa a «Bitácora» y el sistema muestra los registros más recientes: fecha y hora, usuario, tipo (acceso o acción), módulo, descripción e IP.
2. Define los filtros: usuario, rango de fechas y módulo.
3. El sistema valida los filtros y muestra los resultados paginados.
4. Opcionalmente, el administrador exporta el resultado a CSV.

**Postcondiciones**
- La bitácora se muestra sin modificar datos; sus registros no se pueden editar ni eliminar desde el sistema.

**Excepciones (cada una debe manejarse)**
- Rango de fechas inválido (la fecha inicial es posterior a la final).
- No hay registros para los filtros indicados.
- Rango mayor a 90 días: el sistema pide acotar el período.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Bitacora` → vista `V.bitacora`, operaciones: mostrar(), filtrar(), exportar()
- Control `CtrlBitacora`, métodos: index(), filtrar(), exportar()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: listar(), buscar()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: obtener()

---

## CU05 Gestionar especialidades

- **Requisito:** RF04
- **Actores:** Administrador
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Administrar las especialidades de la clínica, su costo de consulta y el rango de edad que atienden (RF04).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Administrador.

**Flujo principal**
1. El administrador ingresa a «Especialidades» y el sistema las lista.
2. Selecciona «Nueva especialidad» o edita una existente.
3. Ingresa nombre, descripción (opcional), costo de la consulta y, si corresponde, la edad mínima y máxima que atiende (RN15).
4. El sistema normaliza el nombre y valida que no exista otra especialidad con el mismo nombre normalizado.
5. El sistema guarda el registro y confirma la operación.
6. El administrador puede activar o desactivar la especialidad.

**Postcondiciones**
- La especialidad queda disponible o inactiva para asociarla a médicos y citas.

**Excepciones (cada una debe manejarse)**
- Nombre duplicado.
- Rango de edad inválido (mínima mayor que la máxima) o costo negativo.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Especialidades` → vista `V.especialidades`, operaciones: mostrar(), crear(), editar(), cambiarEstado()
- Control `CtrlEspecialidades`, métodos: index(), store(), update(), cambiarEstado()
- Entidad `ESPECIALIDAD` → modelo `M.Especialidad`, métodos: listar(), existeNombre(), crear(), actualizar(), cambiarEstado()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU06 Gestionar médicos

- **Requisito:** RF03
- **Actores:** Administrador
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Registrar y administrar a los médicos y las especialidades en las que atienden (RF03).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Administrador.
- Existen especialidades activas (CU05).

**Flujo principal**
1. El administrador ingresa a «Médicos»; el sistema los lista y permite buscar por nombre, CI o especialidad.
2. Selecciona «Nuevo médico» o edita uno existente.
3. Ingresa CI, número de registro profesional, nombres, apellidos, teléfono y correo, y elige una o varias especialidades.
4. El sistema valida los campos y que el CI y el número de registro profesional sean únicos.
5. El sistema genera el código (M-0001) y guarda al médico con sus especialidades.
6. Para desactivarlo, el administrador cambia su estado: el médico no recibe citas nuevas y conserva su historial (RN04, RN11).

**Postcondiciones**
- El médico queda registrado con sus especialidades.

**Excepciones (cada una debe manejarse)**
- CI o número de registro profesional duplicado.
- Correo con formato inválido.
- Médico sin al menos una especialidad.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Medicos` → vista `V.medicos`, operaciones: mostrar(), crear(), editar(), cambiarEstado(), buscar()
- Control `CtrlMedicos`, métodos: index(), store(), update(), cambiarEstado(), buscar()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: listar(), existeCI(), crear(), actualizar(), cambiarEstado(), buscar()
- Entidad `ESPECIALIDAD` → modelo `M.Especialidad`, métodos: listar()
- Entidad `ATIENDE` → modelo `M.Atiende`, métodos: asignar()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU07 Definir horarios de atención

- **Requisito:** RF05
- **Actores:** Administrador
- **Prioridad:** Alta · **Riesgo:** Crítico
- **Propósito:** Registrar los horarios de atención de cada médico y la duración de sus franjas (RF05).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Administrador.
- El médico existe y está activo (CU06).

**Flujo principal**
1. El administrador selecciona un médico en el módulo «Horarios» y el sistema muestra sus horarios actuales.
2. Agrega un horario: día de la semana, hora inicial, hora final y duración de la franja.
3. El sistema valida que la hora final sea posterior a la inicial, que el rango se divida en franjas completas y que no se superponga con otro horario del mismo día.
4. El sistema guarda el horario y muestra las franjas que generará.
5. El administrador puede desactivar un horario; para cambiarlo, lo desactiva y agrega otro.

**Postcondiciones**
- El horario queda registrado y se usa para calcular la disponibilidad (CU13).

**Excepciones (cada una debe manejarse)**
- Hora final menor o igual a la inicial.
- Rango que no se divide en franjas completas.
- Horario superpuesto con otro del mismo médico y día.
- Duración de franja fuera del rango de 10 a 120 minutos.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Horarios` → vista `V.horarios y V.formHorario`, operaciones: mostrar(), agregar(), cambiarEstado()
- Control `CtrlHorarios`, métodos: index(), store(), cambiarEstado()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: obtener()
- Entidad `HORARIO` → modelo `M.Horario`, métodos: listar(), seSuperpone(), crear(), generarFranjas(), cambiarEstado()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU08 Gestionar pacientes

- **Requisito:** RF02
- **Actores:** Recepcionista, Administrador
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Registrar, modificar, consultar y desactivar pacientes (RF02).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista o Administrador.

**Flujo principal**
1. La recepcionista ingresa a «Pacientes»; el sistema los lista y permite localizar uno existente (CU09).
2. Selecciona «Nuevo paciente» o edita uno existente.
3. Ingresa CI, nombres, apellidos, fecha de nacimiento, sexo, teléfono, correo, dirección y contacto de emergencia.
4. El sistema valida los campos obligatorios, los formatos y que el CI no esté registrado.
5. El sistema genera el código (P-0001), guarda al paciente y confirma.
6. Para desactivar, el sistema cambia el estado a inactivo sin eliminar su historial (RN11).
7. El sistema registra la operación en la bitácora.

**Postcondiciones**
- El paciente queda registrado o actualizado y su historial se conserva.

**Excepciones (cada una debe manejarse)**
- CI duplicado: el sistema rechaza el registro y muestra al paciente existente.
- Fecha de nacimiento futura.
- Campos obligatorios vacíos.
- Paciente menor de 18 años sin contacto de emergencia: el sistema pide el de su padre, madre o tutor.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Pacientes` → vista `V.pacientes`, operaciones: mostrar(), crear(), editar(), desactivar()
- Control `CtrlPacientes`, métodos: index(), store(), update(), destroy()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: listar(), existeCI(), crear(), actualizar(), desactivar()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU09 Buscar paciente

- **Requisito:** RF02
- **Actores:** Recepcionista, Administrador
- **Prioridad:** Alta · **Riesgo:** Normal
- **Propósito:** Localizar rápidamente a un paciente por CI, nombre o apellido (RF02).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista o Administrador.

**Flujo principal**
1. El usuario escribe un criterio en el buscador de pacientes.
2. El sistema valida que tenga al menos tres caracteres y ejecuta una búsqueda parametrizada, sin distinguir mayúsculas ni tildes.
3. El sistema muestra las coincidencias con código, CI, nombre, teléfono y estado (hasta 50 resultados).
4. El usuario selecciona un paciente para continuar.

**Postcondiciones**
- El paciente queda seleccionado para registrar citas, seguro, historial o resultados.

**Excepciones (cada una debe manejarse)**
- Sin resultados: el sistema ofrece registrar un nuevo paciente (CU08).
- Criterio con menos de tres caracteres.
- Más de 50 coincidencias: el sistema pide precisar la búsqueda.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_BuscarPaciente` → vista `V.buscarPaciente`, operaciones: buscar(), seleccionar()
- Control `CtrlPacientes`, métodos: buscar(), show()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: buscar(), obtener()

---

## CU10 Registrarse como paciente

- **Requisito:** RF02
- **Actores:** Paciente
- **Prioridad:** Alta · **Riesgo:** Crítico
- **Propósito:** Permitir que una persona cree su cuenta y su ficha de paciente desde el portal web (RF02).

**Precondiciones**
- La persona no tiene una cuenta en el sistema.

**Flujo principal**
1. El visitante elige «Crear cuenta de paciente».
2. El sistema muestra el formulario de registro.
3. El visitante ingresa CI, nombres, apellidos, fecha de nacimiento, sexo, teléfono, correo y contraseña, y acepta la política de privacidad.
4. El sistema valida los campos, que el correo no esté en uso y que la contraseña cumpla la política.
5. Si el CI no existe, el sistema crea la ficha del paciente (P-xxxx); si ya existe sin cuenta y coinciden la fecha de nacimiento y los apellidos, la vincula.
6. El sistema crea la cuenta con rol Paciente en la misma transacción; su nombre de usuario es su CI.
7. El sistema registra la operación en la bitácora, inicia la sesión y muestra el portal.

**Postcondiciones**
- El paciente tiene una cuenta activa vinculada a su ficha.

**Excepciones (cada una debe manejarse)**
- El CI ya tiene una cuenta: el sistema rechaza el registro e indica cómo recuperar el acceso.
- El CI existe pero los datos no coinciden: el sistema pide acudir a recepción.
- Correo ya registrado.
- No acepta la política de privacidad.
- Contraseña que no cumple la política.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Registro` → vista `V.registro`, operaciones: registrarse()
- Control `CtrlRegistro`, métodos: registrar()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: buscarPorCI(), crear()
- Entidad `ROL` → modelo `M.Rol`, métodos: obtener()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: crear()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU11 Gestionar perfil del paciente

- **Requisito:** RF16
- **Actores:** Paciente
- **Prioridad:** Media · **Riesgo:** Normal
- **Propósito:** Permitir que el paciente mantenga sus datos de contacto, sus antecedentes y sus preferencias (RF16).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Paciente.

**Flujo principal**
1. El paciente ingresa a «Mi perfil».
2. El sistema muestra datos personales, contacto, antecedentes médicos, contacto de emergencia, seguro y preferencias.
3. El paciente modifica su teléfono, correo, dirección, contacto de emergencia y preferencias de notificación y de privacidad.
4. El paciente declara sus antecedentes: tipo de sangre, alergias, enfermedades crónicas, medicamentos y antecedentes familiares.
5. El sistema valida los datos y guarda los cambios, indicando que los antecedentes fueron declarados por el paciente.
6. Para cambiar la contraseña, el perfil enlaza a «Cambiar contraseña» (CU26).; el sistema verifica la actual y la política, y actualiza.
7. El sistema registra la operación en la bitácora.

**Postcondiciones**
- El perfil queda actualizado.

**Excepciones (cada una debe manejarse)**
- Correo en uso por otra cuenta.
- Intento de modificar CI, nombres o fecha de nacimiento: solo la recepción puede cambiarlos.
- Antecedentes registrados por un médico: el paciente puede verlos, pero no modificarlos ni quitarlos.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Perfil` → vista `V.perfil`, operaciones: mostrar(), guardar(), declararAntecedente()
- Control `CtrlPerfil`, métodos: show(), update(), storeAntecedente()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: obtener(), actualizar()
- Entidad `HISTORIA` → modelo `M.Historia`, métodos: obtener()
- Entidad `ANTECEDENTE` → modelo `M.Antecedente`, métodos: listar(), crear()
- Entidad `SEGURO` → modelo `M.Seguro`, métodos: obtenerActivo()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: actualizarCorreo()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU12 Registrar seguro del paciente

- **Requisito:** RF19
- **Actores:** Recepcionista
- **Prioridad:** Media · **Riesgo:** Normal
- **Propósito:** Registrar el seguro médico con el que se atiende el paciente (RF19).

**Precondiciones**
- El usuario inició sesión (CU01) con el rol Recepcionista.
- El paciente está registrado y activo.

**Flujo principal**
1. La recepcionista localiza al paciente (CU09) y elige «Seguro».
2. El sistema muestra el seguro activo del paciente, si existe.
3. La recepcionista ingresa aseguradora, número de póliza, tipo de cobertura, porcentaje de cobertura y fechas de vigencia.
4. El sistema valida que la póliza no esté registrada en la misma aseguradora, que el porcentaje esté entre 0 y 100 y que las fechas sean coherentes.
5. Si existe un seguro activo, el sistema lo da de baja y guarda el nuevo.
6. El sistema registra la operación en la bitácora.

**Postcondiciones**
- El paciente tiene un único seguro activo, que se aplica al cobrar la consulta (CU25).

**Excepciones (cada una debe manejarse)**
- Póliza duplicada (también al renovar la misma póliza: se registra con el número de la nueva póliza).
- Fecha de fin anterior a la de inicio.
- Porcentaje de cobertura fuera de rango.
- Seguro vencido al momento del registro: el sistema lo guarda e informa que está vencido.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Seguro` → vista `V.seguro`, operaciones: mostrar(), registrar(), darDeBaja()
- Control `CtrlSeguros`, métodos: index(), store(), destroy()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: obtener()
- Entidad `SEGURO` → modelo `M.Seguro`, métodos: obtenerActivo(), darDeBaja(), crear()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU13 Consultar disponibilidad

- **Requisito:** RF05, RF06
- **Actores:** Recepcionista, Paciente
- **Prioridad:** Alta · **Riesgo:** Crítico
- **Propósito:** Mostrar las franjas libres de un médico en una fecha (RF05, RF06).

**Precondiciones**
- El usuario inició sesión (CU01).
- Existen médicos activos con horarios registrados (CU06 y CU07).

**Flujo principal**
1. El usuario selecciona la especialidad; el sistema solo muestra las que corresponden a la edad del paciente (RN15).
2. El sistema lista los médicos activos de esa especialidad.
3. El usuario selecciona el médico y la fecha.
4. El sistema obtiene el horario de ese día y lo divide en franjas según la duración configurada.
5. El sistema descarta las franjas pasadas, las de fechas bloqueadas y las ocupadas por citas no canceladas (RN09).
6. El sistema calcula las franjas con los modelos (no con una función de la base de datos) y muestra las libres y las ocupadas, sin datos de otros pacientes.

**Postcondiciones**
- Las franjas disponibles quedan visibles para programar o reprogramar una cita.

**Excepciones (cada una debe manejarse)**
- Fecha pasada.
- El médico no atiende ese día o no tiene franjas libres: el sistema sugiere la siguiente fecha con disponibilidad.
- Fecha bloqueada: el sistema muestra el motivo.
- Paciente sin especialidad disponible para su edad.

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_Disponibilidad` → vista `V.disponibilidad`, operaciones: elegirEspecialidad(), elegirMedico(), consultar()
- Control `CtrlDisponibilidad`, métodos: especialidades(), medicos(), franjas()
- Entidad `PACIENTE` → modelo `M.Paciente`, métodos: obtener()
- Entidad `ESPECIALIDAD` → modelo `M.Especialidad`, métodos: listarPorEdad()
- Entidad `MEDICO` → modelo `M.Medico`, métodos: listarPorEspecialidad()
- Entidad `BLOQUEO` → modelo `M.Bloqueo`, métodos: estaBloqueada()
- Entidad `HORARIO` → modelo `M.Horario`, métodos: obtenerDelDia(), generarFranjas()
- Entidad `CITA` → modelo `M.Cita`, métodos: listarOcupadas()

---

## CU26 Cambiar contraseña

- **Requisito:** RF01
- **Actores:** Administrador, Recepcionista, Médico, Paciente
- **Prioridad:** Media · **Riesgo:** Normal
- **Propósito:** Permitir que el usuario cambie su contraseña y obligar el cambio cuando es temporal (RF01).

**Precondiciones**
- El usuario inició sesión (CU01).

**Flujo principal**
1. El usuario ingresa a «Cambiar contraseña» desde el menú, o el sistema lo lleva ahí porque ingresó con una contraseña temporal.
2. El sistema muestra el formulario: contraseña actual, contraseña nueva y confirmación.
3. JavaScript verifica que los campos no estén vacíos y que la nueva y la confirmación coincidan.
4. El servidor verifica la contraseña actual contra el hash almacenado.
5. El servidor valida que la nueva cumpla la política (RNF05) y sea distinta de la actual.
6. El sistema guarda el nuevo hash, quita la marca de contraseña temporal e invalida las demás sesiones abiertas del usuario.
7. El sistema registra el cambio en la bitácora y confirma la operación.

**Postcondiciones**
- La contraseña queda actualizada y ya no es temporal.
- El cambio queda registrado en la bitácora.

**Excepciones (cada una debe manejarse)**
- Contraseña actual incorrecta: rechaza la operación y suma un intento fallido (bloqueo temporal progresivo, RNF04).
- La nueva contraseña es igual a la actual o no cumple la política.
- La confirmación no coincide con la nueva contraseña.
- Contraseña temporal: el usuario no puede usar otras opciones del sistema hasta cambiarla (RN23).

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_CambiarClave` → vista `V.cambiarClave`, operaciones: cambiar()
- Control `CtrlClave`, métodos: update()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: verificarClave(), actualizarClave()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

## CU27 Recuperar contraseña

- **Requisito:** RF01
- **Actores:** Administrador, Recepcionista, Médico, Paciente
- **Prioridad:** Media · **Riesgo:** Crítico
- **Propósito:** Permitir que un usuario que olvidó su contraseña la restablezca mediante un enlace enviado a su correo (RF01).

**Precondiciones**
- El usuario tiene una cuenta activa con un correo registrado.
- No tiene una sesión iniciada.

**Flujo principal**
1. El usuario elige «¿Olvidó su contraseña?» en la pantalla de inicio de sesión.
2. El sistema pide el correo o el nombre de usuario.
3. El usuario lo ingresa y envía el formulario.
4. El sistema responde siempre con el mismo mensaje («si la cuenta existe, recibirá un enlace»), exista o no la cuenta.
5. Si la cuenta existe y está activa, el sistema verifica el límite de solicitudes, genera un enlace aleatorio, guarda solo su hash con vencimiento a los 60 minutos y lo envía por correo (RN22).
6. El usuario abre el enlace y el sistema valida que exista, no esté usado ni vencido.
7. El usuario ingresa la contraseña nueva y su confirmación; el sistema valida la política (RNF05).
8. El sistema guarda el nuevo hash, marca el enlace como usado, desbloquea la cuenta, invalida las sesiones abiertas y lo registra en la bitácora.
9. El sistema redirige al inicio de sesión.

**Postcondiciones**
- La contraseña queda renovada y la cuenta desbloqueada.
- El enlace no se puede volver a usar.
- La operación queda registrada en la bitácora.

**Excepciones (cada una debe manejarse)**
- Cuenta inexistente o inactiva: se muestra el mismo mensaje y no se envía correo.
- Más de 3 solicitudes en una hora: no se genera un enlace nuevo y se muestra el mismo mensaje (RN22).
- Enlace vencido, ya usado o alterado: el sistema informa y permite solicitar otro.
- La nueva contraseña no cumple la política.
- Falla en el envío del correo: queda registrada y el usuario puede volver a solicitar.
- Usuario sin correo registrado: el administrador restablece la contraseña (CU03).

**Clases (deben coincidir con los diagramas)**

- Interfaz `UI_RecuperarClave` → vista `V.recuperarClave`, operaciones: solicitar(), restablecer()
- Control `CtrlRecuperacion`, métodos: solicitarEnlace(), restablecer()
- Entidad `USUARIO` → modelo `M.Usuario`, métodos: buscarPorLogin(), actualizarClave()
- Entidad `RECUPERACION` → modelo `M.Recuperacion`, métodos: contarRecientes(), crear(), validar(), marcarUsado()
- Entidad `BITACORA` → modelo `M.Bitacora`, métodos: registrar()

---

# Reglas del negocio

- **RN01**: Cada paciente tendrá un identificador único.
- **RN02**: Cada médico tendrá un identificador único.
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
- **RN14**: Los datos se almacenarán de forma persistente en PostgreSQL.
- **RN15**: Una cita solo podrá registrarse en una especialidad cuyo rango de edad incluya la edad del paciente en la fecha de la cita: Pediatría de 0 a 14 años, Medicina general de 15 a 59 años y Geriatría desde los 60 años. Las demás especialidades no tienen restricción de edad.
- **RN16**: Cada resultado de laboratorio se evaluará con el rango de referencia que corresponde a la edad del paciente en la fecha de la muestra.
- **RN17**: El paciente solo podrá cancelar o reprogramar sus citas desde el portal hasta dos horas antes de la hora programada; después deberá comunicarse con la recepción.
- **RN18**: El paciente solo podrá consultar y modificar su propia información; el médico solo podrá ver la historia clínica de los pacientes que atiende o atendió, y la recepción no accederá a los diagnósticos.
- **RN19**: La cobertura del seguro se aplicará al cobro solo si el seguro está activo y vigente en la fecha de la cita.
- **RN20**: Los análisis de laboratorio solo los solicita un médico; los resultados los registra la recepcionista y el sistema notifica al médico solicitante.
- **RN21**: Los recordatorios se enviarán 24 horas antes de la cita y únicamente para citas en estado Programada o Confirmada.
- **RN22**: Un enlace de recuperación de contraseña se puede usar una sola vez, vence a los 60 minutos y se limita a 3 solicitudes por usuario por hora; el sistema responde siempre lo mismo, exista o no la cuenta.
- **RN23**: Una contraseña restablecida por el administrador es temporal: el usuario debe cambiarla en su siguiente ingreso antes de usar el sistema.

# Requisitos no funcionales

- **RNF01 Seguridad de contraseñas**: Las contraseñas se almacenan cifradas y nunca en texto plano. Se usan password_hash() y password_verify() (bcrypt) a través del componente Hash de Laravel.
- **RNF02 Protección contra inyección y XSS**: Las consultas son parametrizadas y la salida se escapa. Eloquent y el Query Builder envían los valores como parámetros mediante PDO (pdo_pgsql); Blade escapa la salida con {{ }} y los formularios usan token CSRF.
- **RNF03 Control de acceso**: Toda página interna verifica la sesión y el rol. Middleware de autenticación y de rol en todas las rutas internas; el identificador de sesión se regenera al iniciar sesión.
- **RNF04 Bloqueo por intentos fallidos**: La cuenta se bloquea de forma temporal y progresiva ante intentos fallidos. Tras cinco intentos fallidos consecutivos se bloquea 5 minutos; si se repite, 15 minutos y luego 60 minutos. El administrador puede desbloquearla antes.
- **RNF05 Política de contraseñas**: La contraseña cumple una complejidad mínima. Al menos 8 caracteres con mayúsculas, minúsculas, números y un símbolo.
- **RNF06 Integridad de la información**: Los datos se guardan de forma persistente y consistente. PostgreSQL con claves primarias y foráneas, restricciones UNIQUE, CHECK y de exclusión, y transacciones en las operaciones críticas.
- **RNF07 Validación de datos**: Los datos se validan en el cliente y en el servidor. HTML5 y JavaScript en el navegador; Form Requests de Laravel en el servidor, de forma obligatoria.
- **RNF08 Manejo de errores**: Los errores no muestran credenciales ni detalles internos. El usuario ve un mensaje claro; el detalle técnico se guarda en el log de Laravel y APP_DEBUG se desactiva en producción.
- **RNF09 Usabilidad**: La interfaz es profesional, coherente y fácil de usar. Menús según el rol, mensajes de éxito y error, y una cita se reserva en no más de tres pasos.
- **RNF10 Rendimiento**: Las consultas habituales responden rápido. Búsqueda de pacientes, fichas disponibles y agenda en menos de 3 segundos con el volumen previsto.
- **RNF11 Compatibilidad y adaptabilidad**: El sistema funciona en distintos navegadores y pantallas. Chrome, Edge, Firefox y Safari actuales; diseño adaptable a computadoras y celulares, en especial el portal del paciente.
- **RNF12 Mantenibilidad**: El código está organizado y documentado. Patrón MVC de Laravel, servicios para las reglas del negocio, código comentado y versionado en GitHub.
- **RNF13 Portabilidad**: El sistema se instala en entornos estándar. PHP 8.2 o superior con pdo_pgsql y PostgreSQL 15 o superior; en desarrollo con XAMPP o el servidor de Laravel y en producción en un VPS Linux.
- **RNF14 Trazabilidad**: Se conserva la historia de las operaciones. No hay eliminación física de citas; se guarda el historial de estados y la bitácora de accesos y acciones.
- **RNF15 Confidencialidad de la información clínica**: Solo los usuarios autorizados ven los datos médicos. El paciente solo ve su información, el médico la de los pacientes que atiende y la recepción no ve diagnósticos; la comunicación usa HTTPS.
- **RNF16 Respaldo de información**: Se realizan copias de seguridad periódicas. Respaldo diario con pg_dump programado en cron, conservando las últimas siete copias.
- **RNF17 Disponibilidad**: El sistema está disponible mientras atiende la clínica. Funcionamiento continuo en el horario de atención; si el servicio de IA o de correo falla, el resto del sistema sigue operando.