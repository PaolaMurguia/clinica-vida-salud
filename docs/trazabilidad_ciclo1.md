# Trazabilidad del ciclo 1

Relaciona cada caso de uso de `docs/casos_de_uso_ciclo1.md` con su implementación. Se actualiza en cada commit.

**Estado:** ✅ implementado y probado · 🟡 parcial · ⬜ pendiente
**Último paso cerrado:** Paso 2 – Base de datos y modelos (commit `d723b14`).

## Resumen por caso de uso

| CU | Nombre | Módulo | Vista | Controlador → métodos | Modelos → métodos | Ruta | Test | Estado |
|---|---|---|---|---|---|---|---|---|
| CU01 | Iniciar sesión | Acceso | `login` | `CtrlSesion` → autenticar() | `Usuario` → buscarPorLogin(), reiniciarIntentos(), registrarIntentoFallido() ✅ · `Rol` → obtener() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU02 | Cerrar sesión | Acceso | `menu` | `CtrlSesion` → cerrarSesion() | `Bitacora` → registrar() ✅ | ⬜ | ⬜ | 🟡 modelos |
| CU03 | Gestionar usuarios y roles | Administracion | `usuarios` | `CtrlUsuarios` → index(), store(), update(), restablecerClave(), cambiarEstado() | `Usuario` → listar(), crear(), actualizar(), marcarTemporal(), actualizarEstado() ✅ · `Rol` → obtener() ✅ · `Medico` → obtener() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU04 | Consultar bitácora | Administracion | `bitacora` | `CtrlBitacora` → index(), filtrar(), exportar() | `Bitacora` → listar(), buscar() ✅ · `Usuario` → obtener() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU05 | Gestionar especialidades | Administracion | `especialidades` | `CtrlEspecialidades` → index(), store(), update(), cambiarEstado() | `Especialidad` → listar(), existeNombre(), crear(), actualizar(), cambiarEstado() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU06 | Gestionar médicos | Administracion | `medicos` | `CtrlMedicos` → index(), store(), update(), cambiarEstado(), buscar() | `Medico` → listar(), existeCI(), crear(), actualizar(), cambiarEstado(), buscar() ✅ · `Especialidad` → listar() ✅ · `Atiende` → asignar() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU07 | Definir horarios de atención | Administracion | `horarios`, `formHorario` | `CtrlHorarios` → index(), store(), cambiarEstado() | `Medico` → obtener() ✅ · `Horario` → listar(), seSuperpone(), crear(), generarFranjas(), cambiarEstado() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU08 | Gestionar pacientes | Pacientes | `pacientes` | `CtrlPacientes` → index(), store(), update(), destroy() | `Paciente` → listar(), existeCI(), crear(), actualizar(), desactivar() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU09 | Buscar paciente | Pacientes | `buscarPaciente` | `CtrlPacientes` → buscar(), show() | `Paciente` → buscar(), obtener() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU10 | Registrarse como paciente | Pacientes | `registro` | `CtrlRegistro` → registrar() | `Paciente` → buscarPorCI(), crear() ✅ · `Rol` → obtener() ✅ · `Usuario` → crear() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU11 | Gestionar perfil del paciente | Pacientes | `perfil` | `CtrlPerfil` → show(), update(), storeAntecedente() | `Paciente` → obtener(), actualizar() ✅ · `Historia` → obtener() ✅ · `Antecedente` → listar(), crear() ✅ · `Seguro` → obtenerActivo() ✅ · `Usuario` → actualizarCorreo() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU12 | Registrar seguro del paciente | Pacientes | `seguro` | `CtrlSeguros` → index(), store(), destroy() | `Paciente` → obtener() ✅ · `Seguro` → obtenerActivo(), darDeBaja(), crear() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU13 | Consultar disponibilidad | Disponibilidad | `disponibilidad` | `CtrlDisponibilidad` → especialidades(), medicos(), franjas() | `Paciente` → obtener() ✅ · `Especialidad` → listarPorEdad() ✅ · `Medico` → listarPorEspecialidad() ✅ · `Bloqueo` → estaBloqueada() ✅ · `Horario` → obtenerDelDia(), generarFranjas() ✅ · `Cita` → listarOcupadas() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU26 | Cambiar contraseña | Acceso | `cambiarClave` | `CtrlClave` → update() | `Usuario` → verificarClave(), actualizarClave() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |
| CU27 | Recuperar contraseña | Acceso | `recuperarClave` | `CtrlRecuperacion` → solicitarEnlace(), restablecer() | `Usuario` → buscarPorLogin(), actualizarClave() ✅ · `Recuperacion` → contarRecientes(), crear(), validar(), marcarUsado() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |

## Base común (pasos 1 y 2)

| Elemento | Archivo | Test |
|---|---|---|
| Esquema, funciones y procedimientos (01, 02, 03) | `database/migrations/2026_10_25_00000{1,2,3}_*.php` | `ModelosTest::test_existen_las_22_tablas_y_los_triggers` |
| Población (04) + usuario = CI del paciente (A7) | `database/seeders/PoblacionSeeder.php` | `ModelosTest::test_usuarios_de_pacientes_usan_su_ci` |
| 22 modelos sin timestamps | `app/Models/*.php` | `ModelosTest::test_cada_modelo_lee_su_tabla_sin_timestamps` |
| Base de pruebas aislada | `phpunit.xml`, `tests/TestCase.php` | todas |

Los modelos del ciclo 2 (`HistorialCita`, `Pago`, `RegistroAtencion`, `ParametroLaboratorio`, `RangoReferencia`, `ExamenLaboratorio`, `ResultadoLaboratorio`, `Notificacion`) solo tienen relaciones; sus métodos se agregan con sus CU.
