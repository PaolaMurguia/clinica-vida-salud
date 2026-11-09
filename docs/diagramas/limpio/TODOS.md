# Diagramas de secuencia del ciclo 1 (versión limpia)

Código PlantUML listo para pegar en draw.io (Organizar > Insertar > Avanzado > PlantUML). Cada bloque es el contenido completo de `sd_CUxx.puml`.

## CU01 Iniciar sesión

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU01 Iniciar sesión

actor "Administrador, Recepcionista,\nMédico o Paciente" as A
boundary "V.login" as VL
boundary "V.menu" as VM
control "CtrlSesion" as CS
entity "M.Usuario" as MU
entity "M.Rol" as MR
entity "M.Bitacora" as MB

activate A
A -> VL : ingresar(login, clave)
activate VL
VL -> CS : autenticar(login, clave)
deactivate VL
activate CS
CS -> MU : usuario = buscarPorLogin(login)
activate MU
MU -->> CS : usuario
deactivate MU

alt credenciales correctas, activa y sin bloqueo
  CS -> MU : reiniciarIntentos()
  activate MU
  deactivate MU
  CS -> MR : rol = obtener(usuario.rol_id)
  activate MR
  MR -->> CS : rol
  deactivate MR
  CS -> MB : registrar(LOGIN)
  activate MB
  deactivate MB
  CS -> VM : view(rol)
  activate VM
  deactivate VM
else contraseña incorrecta
  CS -> MU : registrarIntentoFallido()
  activate MU
  deactivate MU
  CS -> MB : registrar(LOGIN_FALLIDO)
  activate MB
  deactivate MB
  CS -> VL : view(error)
  activate VL
  deactivate VL
else cuenta inexistente, inactiva o bloqueada
  CS -> VL : view(error)
  activate VL
  deactivate VL
end
deactivate CS
deactivate A
@enduml
```

## CU02 Cerrar sesión

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU02 Cerrar sesión

actor "Administrador, Recepcionista,\nMédico o Paciente" as A
boundary "V.menu" as VM
boundary "V.login" as VL
control "CtrlSesion" as CS
entity "M.Bitacora" as MB

activate A
A -> VM : salir()
activate VM
VM -> CS : cerrarSesion()
deactivate VM
activate CS
CS -> MB : registrar(LOGOUT)
activate MB
deactivate MB
CS -> VL : view(login)
activate VL
deactivate VL
deactivate CS
deactivate A
@enduml
```

## CU03 Gestionar usuarios y roles

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU03 Gestionar usuarios y roles

actor "Administrador" as A
boundary "V.usuarios" as VU
control "CtrlUsuarios" as CU
entity "M.Usuario" as MU
entity "M.Rol" as MR
entity "M.Medico" as MM
entity "M.Bitacora" as MB

activate A
alt mostrar
  A -> VU : mostrar()
  activate VU
  VU -> CU : index()
  deactivate VU
  activate CU
  CU -> MU : usuarios = listar()
  activate MU
  MU -->> CU : usuarios
  deactivate MU
  CU -> VU : view(usuarios)
  activate VU
  deactivate VU
  deactivate CU
end

alt crear
  A -> VU : crear(datos)
  activate VU
  VU -> CU : store(datos)
  deactivate VU
  activate CU
  CU -> MR : rol = obtener(rol_id)
  activate MR
  MR -->> CU : rol
  deactivate MR
  opt rol Médico
    CU -> MM : medico = obtener(medico_id)
    activate MM
    MM -->> CU : medico
    deactivate MM
  end
  CU -> MU : crear(datos, rol, medico)
  activate MU
  deactivate MU
else editar
  A -> VU : editar(usuario, datos)
  activate VU
  VU -> CU : update(usuario, datos)
  deactivate VU
  CU -> MU : actualizar(datos)
  activate MU
  deactivate MU
end
CU -> MB : registrar(datos)
activate MB
deactivate MB
CU -> VU : view(usuarios)
activate VU
deactivate VU
deactivate CU

alt restablecer contraseña
  A -> VU : restablecerClave(usuario)
  activate VU
  VU -> CU : restablecerClave(usuario)
  deactivate VU
  activate CU
  CU -> MU : marcarTemporal(temporal)
  activate MU
  deactivate MU
else activar, desactivar o desbloquear
  A -> VU : cambiarEstado(usuario, accion)
  activate VU
  VU -> CU : cambiarEstado(usuario, accion)
  deactivate VU
  CU -> MU : actualizarEstado(accion)
  activate MU
  deactivate MU
end
CU -> MB : registrar(datos)
activate MB
deactivate MB
CU -> VU : view(usuarios)
activate VU
deactivate VU
deactivate CU
deactivate A
@enduml
```

## CU04 Consultar bitácora

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU04 Consultar bitácora

actor "Administrador" as A
boundary "V.bitacora" as VB
control "CtrlBitacora" as CB
entity "M.Bitacora" as MB
entity "M.Usuario" as MU

activate A
alt mostrar
  A -> VB : mostrar()
  activate VB
  VB -> CB : index()
  deactivate VB
  activate CB
  CB -> MB : registros = listar()
  activate MB
  MB -->> CB : registros
  deactivate MB
  CB -> VB : view(registros)
  activate VB
  deactivate VB
  deactivate CB
end

alt filtrar
  A -> VB : filtrar(filtros)
  activate VB
  VB -> CB : filtrar(filtros)
  deactivate VB
  activate CB
  CB -> MB : registros = buscar(filtros)
  activate MB
  MB -->> CB : registros
  deactivate MB
  opt filtro por usuario
    CB -> MU : usuario = obtener(usuario_id)
    activate MU
    MU -->> CB : usuario
    deactivate MU
  end
  CB -> VB : view(registros, usuario)
  activate VB
  deactivate VB
  deactivate CB
end

alt exportar
  A -> VB : exportar(filtros)
  activate VB
  VB -> CB : exportar(filtros)
  deactivate VB
  activate CB
  CB -> MB : registros = buscar(filtros)
  activate MB
  MB -->> CB : registros
  deactivate MB
  CB -> MB : registrar(EXPORTAR)
  activate MB
  deactivate MB
  CB -> VB : view(archivo CSV)
  activate VB
  deactivate VB
  deactivate CB
end
deactivate A
@enduml
```

## CU05 Gestionar especialidades

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU05 Gestionar especialidades

actor "Administrador" as A
boundary "V.especialidades" as VE
control "CtrlEspecialidades" as CE
entity "M.Especialidad" as ME
entity "M.Bitacora" as MB

activate A
alt mostrar
  A -> VE : mostrar()
  activate VE
  VE -> CE : index()
  deactivate VE
  activate CE
  CE -> ME : especialidades = listar()
  activate ME
  ME -->> CE : especialidades
  deactivate ME
  CE -> VE : view(especialidades)
  activate VE
  deactivate VE
  deactivate CE
end

alt crear
  A -> VE : crear(datos)
  activate VE
  VE -> CE : store(datos)
  deactivate VE
  activate CE
  CE -> ME : existeNombre(nombre)
  activate ME
  deactivate ME
  CE -> ME : crear(datos)
  activate ME
  deactivate ME
  CE -> MB : registrar(CREAR)
  activate MB
  deactivate MB
  CE -> VE : view(especialidades)
  activate VE
  deactivate VE
  deactivate CE
end

alt editar
  A -> VE : editar(especialidad, datos)
  activate VE
  VE -> CE : update(especialidad, datos)
  deactivate VE
  activate CE
  CE -> ME : existeNombre(nombre)
  activate ME
  deactivate ME
  CE -> ME : actualizar(datos)
  activate ME
  deactivate ME
  CE -> MB : registrar(MODIFICAR)
  activate MB
  deactivate MB
  CE -> VE : view(especialidades)
  activate VE
  deactivate VE
  deactivate CE
end

alt cambiar estado
  A -> VE : cambiarEstado(especialidad, accion)
  activate VE
  VE -> CE : cambiarEstado(especialidad, accion)
  deactivate VE
  activate CE
  CE -> ME : cambiarEstado(activar)
  activate ME
  deactivate ME
  CE -> MB : registrar(accion)
  activate MB
  deactivate MB
  CE -> VE : view(especialidades)
  activate VE
  deactivate VE
  deactivate CE
end
deactivate A
@enduml
```

## CU06 Gestionar médicos

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU06 Gestionar médicos

actor "Administrador" as A
boundary "V.medicos" as VM
control "CtrlMedicos" as CM
entity "M.Medico" as MM
entity "M.Especialidad" as ME
entity "M.Atiende" as MA
entity "M.Bitacora" as MB

activate A
alt mostrar
  A -> VM : mostrar()
  activate VM
  VM -> CM : index()
  deactivate VM
  activate CM
  CM -> MM : medicos = listar()
  activate MM
  MM -->> CM : medicos
  deactivate MM
else buscar
  A -> VM : buscar(criterio)
  activate VM
  VM -> CM : buscar(criterio)
  deactivate VM
  CM -> MM : medicos = buscar(criterio)
  activate MM
  MM -->> CM : medicos
  deactivate MM
end
CM -> ME : especialidades = listar()
activate ME
ME -->> CM : especialidades
deactivate ME
CM -> VM : view(medicos, especialidades)
activate VM
deactivate VM
deactivate CM

alt crear
  A -> VM : crear(datos, especialidades)
  activate VM
  VM -> CM : store(datos, especialidades)
  deactivate VM
  activate CM
  CM -> MM : existeCI(ci)
  activate MM
  deactivate MM
  CM -> MM : crear(datos)
  activate MM
  deactivate MM
  CM -> MA : asignar(medico, especialidades)
  activate MA
  deactivate MA
else editar
  A -> VM : editar(medico, datos, especialidades)
  activate VM
  VM -> CM : update(medico, datos, especialidades)
  deactivate VM
  CM -> MM : actualizar(datos)
  activate MM
  deactivate MM
  CM -> MA : asignar(medico, especialidades)
  activate MA
  deactivate MA
else cambiar estado
  A -> VM : cambiarEstado(medico, accion)
  activate VM
  VM -> CM : cambiarEstado(medico, accion)
  deactivate VM
  CM -> MM : cambiarEstado(activar)
  activate MM
  deactivate MM
end
CM -> MB : registrar(datos)
activate MB
deactivate MB
CM -> VM : view(medicos)
activate VM
deactivate VM
deactivate CM
deactivate A
@enduml
```

## CU07 Definir horarios de atención

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU07 Definir horarios de atención

actor "Administrador" as A
boundary "V.horarios" as VH
boundary "V.formHorario" as VF
control "CtrlHorarios" as CH
entity "M.Medico" as MM
entity "M.Horario" as MH
entity "M.Bitacora" as MB

activate A
alt mostrar
  A -> VH : mostrar(medico_id)
  activate VH
  VH -> CH : index(medico_id)
  deactivate VH
  activate CH
  CH -> MM : medico = obtener(medico_id)
  activate MM
  MM -->> CH : medico
  deactivate MM
  CH -> MH : horarios = listar(medico.id)
  activate MH
  MH -->> CH : horarios
  deactivate MH
  CH -> VH : view(medico, horarios)
  activate VH
  deactivate VH
  deactivate CH
end

alt agregar
  A -> VF : agregar(datos)
  activate VF
  VF -> CH : store(datos)
  deactivate VF
  activate CH
  CH -> MH : ok = seSuperpone(medico_id, dia, inicio, fin)
  activate MH
  MH -->> CH : ok
  deactivate MH
  alt no se superpone
    CH -> MH : crear(datos)
    activate MH
    deactivate MH
    CH -> MH : franjas = generarFranjas()
    activate MH
    MH -->> CH : franjas
    deactivate MH
    CH -> MB : registrar(CREAR)
    activate MB
    deactivate MB
    CH -> VH : view(horarios, franjas)
    activate VH
    deactivate VH
  else se superpone
    CH -> VF : view(error)
    activate VF
    deactivate VF
  end
  deactivate CH
end

alt cambiar estado
  A -> VH : cambiarEstado(horario, accion)
  activate VH
  VH -> CH : cambiarEstado(horario, accion)
  deactivate VH
  activate CH
  CH -> MH : cambiarEstado(activar)
  activate MH
  deactivate MH
  CH -> MB : registrar(accion)
  activate MB
  deactivate MB
  CH -> VH : view(horarios)
  activate VH
  deactivate VH
  deactivate CH
end
deactivate A
@enduml
```

## CU08 Gestionar pacientes

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU08 Gestionar pacientes

actor "Recepcionista o\nAdministrador" as R
boundary "V.pacientes" as VP
control "CtrlPacientes" as CP
entity "M.Paciente" as MP
entity "M.Bitacora" as MB

activate R
alt mostrar
  R -> VP : mostrar()
  activate VP
  VP -> CP : index()
  deactivate VP
  activate CP
  CP -> MP : pacientes = listar()
  activate MP
  MP -->> CP : pacientes
  deactivate MP
  CP -> VP : view(pacientes)
  activate VP
  deactivate VP
  deactivate CP
end

alt crear
  R -> VP : crear(datos)
  activate VP
  VP -> CP : store(datos)
  deactivate VP
  activate CP
  CP -> MP : existeCI(ci)
  activate MP
  deactivate MP
  CP -> MP : crear(datos)
  activate MP
  deactivate MP
  CP -> MB : registrar(CREAR)
  activate MB
  deactivate MB
  CP -> VP : view(pacientes)
  activate VP
  deactivate VP
  deactivate CP
end

alt editar
  R -> VP : editar(paciente, datos)
  activate VP
  VP -> CP : update(paciente, datos)
  deactivate VP
  activate CP
  CP -> MP : existeCI(ci)
  activate MP
  deactivate MP
  CP -> MP : actualizar(datos)
  activate MP
  deactivate MP
  CP -> MB : registrar(MODIFICAR)
  activate MB
  deactivate MB
  CP -> VP : view(pacientes)
  activate VP
  deactivate VP
  deactivate CP
end

alt desactivar
  R -> VP : desactivar(paciente)
  activate VP
  VP -> CP : destroy(paciente)
  deactivate VP
  activate CP
  CP -> MP : desactivar()
  activate MP
  deactivate MP
  CP -> MB : registrar(DESACTIVAR)
  activate MB
  deactivate MB
  CP -> VP : view(pacientes)
  activate VP
  deactivate VP
  deactivate CP
end
deactivate R
@enduml
```

## CU09 Buscar paciente

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU09 Buscar paciente

actor "Recepcionista o\nAdministrador" as R
boundary "V.buscarPaciente" as VB
control "CtrlPacientes" as CP
entity "M.Paciente" as MP

activate R
== Buscar ==
R -> VB : buscar(criterio)
activate VB
VB -> CP : buscar(criterio)
deactivate VB
activate CP
CP -> MP : pacientes = buscar(criterio)
activate MP
MP -->> CP : pacientes
deactivate MP
CP -> VB : view(pacientes)
activate VB
deactivate VB
deactivate CP

== Seleccionar ==
R -> VB : seleccionar(paciente)
activate VB
VB -> CP : show(paciente)
deactivate VB
activate CP
CP -> MP : paciente = obtener(id)
activate MP
MP -->> CP : paciente
deactivate MP
CP -> VB : view(paciente)
activate VB
deactivate VB
deactivate CP
deactivate R
@enduml
```

## CU10 Registrarse como paciente

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU10 Registrarse como paciente

actor "Paciente" as P
boundary "V.registro" as VR
control "CtrlRegistro" as CR
entity "M.Paciente" as MP
entity "M.Rol" as MR
entity "M.Usuario" as MU
entity "M.Bitacora" as MB

activate P
P -> VR : registrarse(datos)
activate VR
VR -> CR : registrar(datos)
deactivate VR
activate CR
CR -> MP : ficha = buscarPorCI(ci)
activate MP
MP -->> CR : ficha
deactivate MP
CR -> MU : cuenta = buscarPorLogin(ci)
activate MU
MU -->> CR : cuenta
deactivate MU

alt CI nuevo o ficha sin cuenta con datos iguales
  CR -> MP : crear(datos)
  activate MP
  deactivate MP
  CR -> MR : rol = obtener(Paciente)
  activate MR
  MR -->> CR : rol
  deactivate MR
  CR -> MU : crear(ci, correo, clave, rol)
  activate MU
  deactivate MU
  CR -> MB : registrar(REGISTRO_PACIENTE)
  activate MB
  deactivate MB
else CI con cuenta o datos que no coinciden
  CR -> MB : registrar(REGISTRO_RECHAZADO)
  activate MB
  deactivate MB
  CR -> VR : view(error)
  activate VR
  deactivate VR
end
deactivate CR
deactivate P
@enduml
```

## CU11 Gestionar perfil del paciente

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU11 Gestionar perfil del paciente

actor "Paciente" as P
boundary "V.perfil" as VP
control "CtrlPerfil" as CP
entity "M.Paciente" as MP
entity "M.Historia" as MH
entity "M.Antecedente" as MA
entity "M.Seguro" as MS
entity "M.Usuario" as MU
entity "M.Bitacora" as MB

activate P
alt mostrar
  P -> VP : mostrar()
  activate VP
  VP -> CP : show()
  deactivate VP
  activate CP
  CP -> MP : paciente = obtener(paciente_id)
  activate MP
  MP -->> CP : paciente
  deactivate MP
  CP -> MH : historia = obtener(paciente_id)
  activate MH
  MH -->> CP : historia
  deactivate MH
  CP -> MA : antecedentes = listar(historia.id)
  activate MA
  MA -->> CP : antecedentes
  deactivate MA
  CP -> MS : seguro = obtenerActivo(paciente_id)
  activate MS
  MS -->> CP : seguro
  deactivate MS
  CP -> VP : view(paciente, antecedentes, seguro)
  activate VP
  deactivate VP
  deactivate CP
end

alt guardar
  P -> VP : guardar(datos)
  activate VP
  VP -> CP : update(datos)
  deactivate VP
  activate CP
  CP -> MP : actualizar(datos)
  activate MP
  deactivate MP
  opt correo distinto
    CP -> MU : actualizarCorreo(correo)
    activate MU
    deactivate MU
  end
  CP -> MB : registrar(MODIFICAR)
  activate MB
  deactivate MB
  CP -> VP : view(perfil)
  activate VP
  deactivate VP
  deactivate CP
end

alt declarar antecedente
  P -> VP : declararAntecedente(datos)
  activate VP
  VP -> CP : storeAntecedente(datos)
  deactivate VP
  activate CP
  CP -> MH : historia = obtener(paciente_id)
  activate MH
  MH -->> CP : historia
  deactivate MH
  CP -> MA : crear(historia.id, datos)
  activate MA
  deactivate MA
  CP -> MB : registrar(DECLARAR_ANTECEDENTE)
  activate MB
  deactivate MB
  CP -> VP : view(perfil)
  activate VP
  deactivate VP
  deactivate CP
end
deactivate P
@enduml
```

## CU12 Registrar seguro del paciente

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU12 Registrar seguro del paciente

actor "Recepcionista" as R
boundary "V.seguro" as VS
control "CtrlSeguros" as CS
entity "M.Paciente" as MP
entity "M.Seguro" as MS
entity "M.Bitacora" as MB

activate R
alt mostrar
  R -> VS : mostrar(paciente)
  activate VS
  VS -> CS : index(paciente)
  deactivate VS
  activate CS
  CS -> MP : paciente = obtener(id)
  activate MP
  MP -->> CS : paciente
  deactivate MP
  CS -> MS : seguro = obtenerActivo(paciente.id)
  activate MS
  MS -->> CS : seguro
  deactivate MS
  CS -> VS : view(paciente, seguro)
  activate VS
  deactivate VS
  deactivate CS
end

alt registrar
  R -> VS : registrar(paciente, datos)
  activate VS
  VS -> CS : store(paciente, datos)
  deactivate VS
  activate CS
  CS -> MS : activo = obtenerActivo(paciente.id)
  activate MS
  MS -->> CS : activo
  deactivate MS
  opt existe un seguro activo
    CS -> MS : darDeBaja()
    activate MS
    deactivate MS
  end
  CS -> MS : crear(datos)
  activate MS
  deactivate MS
  CS -> MB : registrar(CREAR)
  activate MB
  deactivate MB
  CS -> VS : view(seguro)
  activate VS
  deactivate VS
  deactivate CS
end

alt dar de baja
  R -> VS : darDeBaja(seguro)
  activate VS
  VS -> CS : destroy(paciente, seguro)
  deactivate VS
  activate CS
  CS -> MS : darDeBaja()
  activate MS
  deactivate MS
  CS -> MB : registrar(DAR_DE_BAJA)
  activate MB
  deactivate MB
  CS -> VS : view(seguro)
  activate VS
  deactivate VS
  deactivate CS
end
deactivate R
@enduml
```

## CU13 Consultar disponibilidad

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU13 Consultar disponibilidad

actor "Recepcionista o Paciente" as U
boundary "V.disponibilidad" as VD
control "CtrlDisponibilidad" as CD
entity "M.Paciente" as MP
entity "M.Especialidad" as ME
entity "M.Medico" as MM
entity "M.Bloqueo" as MBQ
entity "M.Horario" as MH
entity "M.Cita" as MC

activate U
== Elegir especialidad ==
U -> VD : elegirEspecialidad()
activate VD
VD -> CD : especialidades(paciente_id)
deactivate VD
activate CD
CD -> MP : paciente = obtener(paciente_id)
activate MP
MP -->> CD : paciente
deactivate MP
CD -> ME : especialidades = listarPorEdad(edad)
activate ME
ME -->> CD : especialidades
deactivate ME
CD -> VD : view(especialidades)
activate VD
deactivate VD
deactivate CD

== Elegir médico ==
U -> VD : elegirMedico(especialidad_id)
activate VD
VD -> CD : medicos(especialidad_id)
deactivate VD
activate CD
CD -> MM : medicos = listarPorEspecialidad(especialidad_id)
activate MM
MM -->> CD : medicos
deactivate MM
CD -> VD : view(medicos)
activate VD
deactivate VD
deactivate CD

== Consultar franjas ==
U -> VD : consultar(medico_id, fecha)
activate VD
VD -> CD : franjas(medico_id, fecha)
deactivate VD
activate CD
CD -> MBQ : bloqueo = estaBloqueada(medico_id, fecha)
activate MBQ
MBQ -->> CD : bloqueo
deactivate MBQ

alt fecha bloqueada
  CD -> VD : view(motivo)
  activate VD
  deactivate VD
else fecha sin bloqueo
  CD -> MH : horario = obtenerDelDia(medico_id, fecha)
  activate MH
  MH -->> CD : horario
  deactivate MH
  CD -> MH : franjas = generarFranjas()
  activate MH
  MH -->> CD : franjas
  deactivate MH
  CD -> MC : ocupadas = listarOcupadas(medico_id, fecha)
  activate MC
  MC -->> CD : ocupadas
  deactivate MC
  opt médico no atiende ese día o sin franjas libres
    CD -> VD : view(siguiente fecha)
    activate VD
    deactivate VD
  end
  CD -> VD : view(franjas libres y ocupadas)
  activate VD
  deactivate VD
end
deactivate CD
deactivate U
@enduml
```

## CU26 Cambiar contraseña

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU26 Cambiar contraseña

actor "Administrador, Recepcionista,\nMédico o Paciente" as A
boundary "V.cambiarClave" as VC
boundary "V.menu" as VM
control "CtrlClave" as CC
entity "M.Usuario" as MU
entity "M.Bitacora" as MB

activate A
A -> VC : cambiar(clave_actual, clave_nueva)
activate VC
VC -> CC : update(clave_actual, clave_nueva)
deactivate VC
activate CC
CC -> MU : ok = verificarClave(clave_actual)
activate MU
MU -->> CC : ok
deactivate MU

alt contraseña actual correcta
  CC -> MU : actualizarClave(clave_nueva)
  activate MU
  deactivate MU
  CC -> MU : reiniciarIntentos()
  activate MU
  deactivate MU
  CC -> MB : registrar(CAMBIO_CLAVE)
  activate MB
  deactivate MB
  CC -> VM : view(menu)
  activate VM
  deactivate VM
else contraseña actual incorrecta
  CC -> MU : registrarIntentoFallido()
  activate MU
  deactivate MU
  CC -> MB : registrar(CAMBIO_CLAVE_FALLIDO)
  activate MB
  deactivate MB
  CC -> VC : view(error)
  activate VC
  deactivate VC
end
deactivate CC
deactivate A
@enduml
```

## CU27 Recuperar contraseña

```plantuml
@startuml
skinparam shadowing false
skinparam sequenceMessageAlign center
hide footbox
title sd CU27 Recuperar contraseña

actor "Administrador, Recepcionista,\nMédico o Paciente" as A
boundary "V.recuperarClave" as VR
boundary "V.login" as VL
control "CtrlRecuperacion" as CR
entity "M.Usuario" as MU
entity "M.Recuperacion" as MRC
entity "M.Bitacora" as MB

activate A
== Solicitar enlace ==
A -> VR : solicitar(login)
activate VR
VR -> CR : solicitarEnlace(login)
deactivate VR
activate CR
CR -> MU : usuario = buscarPorLogin(login)
activate MU
MU -->> CR : usuario
deactivate MU
opt cuenta activa
  CR -> MRC : n = contarRecientes(usuario.id)
  activate MRC
  MRC -->> CR : n
  deactivate MRC
  opt menos de 3 solicitudes en la hora
    CR -> MRC : crear(usuario.id)
    activate MRC
    deactivate MRC
    CR -> MB : registrar(SOLICITUD_RECUPERACION)
    activate MB
    deactivate MB
  end
end
CR -> VR : view(mensaje)
activate VR
deactivate VR
deactivate CR

== Restablecer ==
A -> VR : restablecer(token, clave)
activate VR
VR -> CR : restablecer(token, clave)
deactivate VR
activate CR
CR -> MRC : solicitud = validar(token)
activate MRC
MRC -->> CR : solicitud
deactivate MRC

alt enlace válido
  CR -> MU : actualizarClave(clave)
  activate MU
  deactivate MU
  CR -> MU : reiniciarIntentos()
  activate MU
  deactivate MU
  CR -> MRC : marcarUsado()
  activate MRC
  deactivate MRC
  CR -> MB : registrar(RESTABLECER_CLAVE)
  activate MB
  deactivate MB
  CR -> VL : view(login)
  activate VL
  deactivate VL
else enlace vencido, usado o alterado
  CR -> VR : view(aviso)
  activate VR
  deactivate VR
end
deactivate CR
deactivate A
@enduml
```
