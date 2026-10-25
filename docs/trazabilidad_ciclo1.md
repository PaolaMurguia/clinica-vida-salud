# Trazabilidad del ciclo 1

Relaciona cada caso de uso de `docs/casos_de_uso_ciclo1.md` con su implementación. Se actualiza en cada commit.

**Estado:** ✅ implementado y probado · 🟡 parcial · ⬜ pendiente
**Último paso cerrado:** Paso 2 – Base de datos y modelos. **En curso:** Paso 3 – Acceso (CU01, CU02 y CU26 listos).

## Resumen por caso de uso

| CU | Nombre | Módulo | Vista | Controlador → métodos | Modelos → métodos | Ruta | Test | Estado |
|---|---|---|---|---|---|---|---|---|
| CU01 | Iniciar sesión | Acceso | `login` (+ destino `menu` / `portal`) | `Acceso\CtrlSesion` → autenticar() ✅ | `Usuario` → buscarPorLogin(), reiniciarIntentos(), registrarIntentoFallido() ✅ · `Rol` → obtener() ✅ · `Bitacora` → registrar() ✅ | `GET /login` (`login`), `POST /login` (`login.autenticar`), `GET /menu`, `GET /portal` | `Acceso/IniciarSesionTest` | ✅ |
| CU02 | Cerrar sesión | Acceso | `menu` (botón «Salir» = salir(); también en la plantilla y el portal) | `Acceso\CtrlSesion` → cerrarSesion() ✅ | `Bitacora` → registrar() ✅ | `POST /logout` (`logout`) | `Acceso/CerrarSesionTest` | ✅ |
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
| CU26 | Cambiar contraseña | Acceso | `cambiarClave` | `Acceso\CtrlClave` → update() ✅ | `Usuario` → verificarClave(), actualizarClave() ✅ · `Bitacora` → registrar() ✅ | `GET /cambiar-clave` (`clave.edit`), `PUT /cambiar-clave` (`clave.update`) | `Acceso/CambiarClaveTest` | ✅ |
| CU27 | Recuperar contraseña | Acceso | `recuperarClave` | `CtrlRecuperacion` → solicitarEnlace(), restablecer() | `Usuario` → buscarPorLogin(), actualizarClave() ✅ · `Recuperacion` → contarRecientes(), crear(), validar(), marcarUsado() ✅ · `Bitacora` → registrar() ✅ | ⬜ | `ModelosTest` (modelos) | 🟡 modelos |

## Base común (pasos 1 y 2)

| Elemento | Archivo | Test |
|---|---|---|
| Esquema, funciones y procedimientos (01, 02, 03) | `database/migrations/2026_10_25_00000{1,2,3}_*.php` | `ModelosTest::test_existen_las_22_tablas_y_los_triggers` |
| Población (04) + usuario = CI del paciente (A7) | `database/seeders/PoblacionSeeder.php` | `ModelosTest::test_usuarios_de_pacientes_usan_su_ci` |
| 22 modelos sin timestamps | `app/Models/*.php` | `ModelosTest::test_cada_modelo_lee_su_tabla_sin_timestamps` |
| Base de pruebas aislada | `phpunit.xml`, `tests/TestCase.php` | todas |

## Base común del paso 3

| Elemento | Archivo | Uso | Test |
|---|---|---|---|
| Plantilla (barra superior por rol, mensajes) | `resources/views/plantilla.blade.php` | todas las vistas | `IniciarSesionTest` |
| Opciones del menú por rol | `config/menu.php` | plantilla, `menu`, `portal` | `IniciarSesionTest` |
| Roles autorizados (RNF03, RN12) | `app/Http/Middleware/VerificarRol.php` (`rol:`) | rutas internas | `IniciarSesionTest::test_paciente_va_al_portal_con_su_ci` |
| Contraseña temporal (RN23) | `app/Http/Middleware/VerificarClaveTemporal.php` (`clave.temporal`) | rutas internas | `CambiarClaveTest::test_ingreso_con_clave_temporal_lleva_a_cambiar_y_bloquea_otras_opciones` |
| Cierre de las demás sesiones (CU26, CU27) | `auth.session` de Laravel (`AuthenticateSession`) en las rutas internas | compara el hash de la contraseña guardado en la sesión | `CambiarClaveTest::test_las_demas_sesiones_abiertas_se_cierran` |
| Sin caché (CU02) | `app/Http/Middleware/SinCache.php` (`sin.cache`) | rutas internas | `CerrarSesionTest::test_paginas_internas_no_se_guardan_en_cache` |
| Bitácora centralizada (B8) | `app/Services/ServicioBitacora.php` → `Bitacora::registrar()` | todos los CU | `IniciarSesionTest` |
| Política RNF05 | `Password::defaults()` en `app/Providers/AppServiceProvider.php` | CU03, CU10, CU26, CU27 | `CambiarClaveTest::test_nueva_igual_a_la_actual_o_debil_o_sin_confirmar_se_rechaza` |
| Validación en el navegador (RNF07) | `resources/js/validaciones.js` (`data-validar`, `data-coincide`) | formularios | manual |
| Mensajes en español | `lang/es/validation.php`, `lang/es/pagination.php` | Form Requests | todos |

Los modelos del ciclo 2 (`HistorialCita`, `Pago`, `RegistroAtencion`, `ParametroLaboratorio`, `RangoReferencia`, `ExamenLaboratorio`, `ResultadoLaboratorio`, `Notificacion`) solo tienen relaciones; sus métodos se agregan con sus CU.
