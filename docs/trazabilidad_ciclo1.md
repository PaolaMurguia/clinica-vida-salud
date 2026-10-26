# Trazabilidad del ciclo 1

Relaciona cada caso de uso de `docs/casos_de_uso_ciclo1.md` con su implementación. Se actualiza en cada commit.

**Estado:** ✅ implementado y probado · 🟡 parcial · ⬜ pendiente
**Último paso cerrado:** Paso 6 – Disponibilidad (CU13). Los 16 casos de uso del ciclo 1 están implementados y probados (pasos 3 a 6).

## Resumen por caso de uso

| CU | Nombre | Módulo | Vista | Controlador → métodos | Modelos → métodos | Ruta | Test | Estado |
|---|---|---|---|---|---|---|---|---|
| CU01 | Iniciar sesión | Acceso | `login` (+ destino `menu` / `portal`) | `Acceso\CtrlSesion` → autenticar() ✅ | `Usuario` → buscarPorLogin(), reiniciarIntentos(), registrarIntentoFallido() ✅ · `Rol` → obtener() ✅ · `Bitacora` → registrar() ✅ | `GET /login` (`login`), `POST /login` (`login.autenticar`), `GET /menu`, `GET /portal` | `Acceso/IniciarSesionTest` | ✅ |
| CU02 | Cerrar sesión | Acceso | `menu` (botón «Salir» = salir(); también en la plantilla y el portal) | `Acceso\CtrlSesion` → cerrarSesion() ✅ | `Bitacora` → registrar() ✅ | `POST /logout` (`logout`) | `Acceso/CerrarSesionTest` | ✅ |
| CU03 | Gestionar usuarios y roles | Administracion | `usuarios` (formulario integrado, B1) | `Administracion\CtrlUsuarios` → index(), store(), update(), restablecerClave(), cambiarEstado() ✅ | `Usuario` → listar(), crear(), actualizar(), marcarTemporal(), actualizarEstado(), obtener(), buscarPorLogin() ✅ · `Rol` → obtener() ✅ · `Medico` → obtener(), listar() ✅ · `Bitacora` → registrar() ✅ | `GET /usuarios` (`usuarios.index`, `?editar=`), `POST /usuarios`, `PUT /usuarios/{usuario}`, `POST /usuarios/{usuario}/restablecer-clave`, `PATCH /usuarios/{usuario}/estado` | `Administracion/GestionarUsuariosTest` | ✅ |
| CU04 | Consultar bitácora | Administracion | `bitacora` | `Administracion\CtrlBitacora` → index(), filtrar(), exportar() ✅ | `Bitacora` → listar(), buscar() ✅ · `Usuario` → obtener(), listar() ✅ | `GET /bitacora` (`bitacora.index`), `GET /bitacora/filtrar` (`bitacora.filtrar`), `GET /bitacora/exportar` (`bitacora.exportar`, CSV) | `Administracion/ConsultarBitacoraTest` | ✅ |
| CU05 | Gestionar especialidades | Administracion | `especialidades` (formulario integrado, B1) | `Administracion\CtrlEspecialidades` → index(), store(), update(), cambiarEstado() ✅ | `Especialidad` → listar(), existeNombre() (en el Form Request), crear(), actualizar(), cambiarEstado() ✅ · `Bitacora` → registrar() ✅ | `GET /especialidades` (`especialidades.index`, `?editar=`), `POST /especialidades`, `PUT /especialidades/{especialidad}`, `PATCH /especialidades/{especialidad}/estado` | `Administracion/GestionarEspecialidadesTest` | ✅ |
| CU06 | Gestionar médicos | Administracion | `medicos` (formulario integrado y buscador) | `Administracion\CtrlMedicos` → index(), store(), update(), cambiarEstado(), buscar() ✅ | `Medico` → listar(), existeCI() (en el Form Request), crear(), actualizar(), cambiarEstado(), buscar(), obtener() ✅ · `Especialidad` → listar() ✅ · `Atiende` → asignar() ✅ · `Bitacora` → registrar() ✅ | `GET /medicos` (`medicos.index`, `?editar=`), `GET /medicos/buscar` (`medicos.buscar`), `POST /medicos`, `PUT /medicos/{medico}`, `PATCH /medicos/{medico}/estado` | `Administracion/GestionarMedicosTest` | ✅ |
| CU07 | Definir horarios de atención | Administracion | `horarios` + `formHorario` (incluida en `horarios`) | `Administracion\CtrlHorarios` → index(), store(), cambiarEstado() ✅ | `Medico` → obtener(), listar() ✅ · `Horario` → listar(), seSuperpone() (en el Form Request y al reactivar), crear(), generarFranjas(), cambiarEstado() ✅ · `Bitacora` → registrar() ✅ | `GET /horarios` (`horarios.index`, `?medico_id=`), `POST /horarios`, `PATCH /horarios/{horario}/estado` | `Administracion/DefinirHorariosTest` | ✅ |
| CU08 | Gestionar pacientes | Pacientes | `pacientes` (formulario integrado, B1) | `Pacientes\CtrlPacientes` → index(), store(), update(), destroy() ✅ + cambiarEstado() (reactivar, aprobado por la autora) ✅ | `Paciente` → listar(), existeCI() y buscarPorCI() (en el Form Request), crear(), actualizar(), desactivar(), obtener() ✅ · `Historia` → obtener() (corrección del tipo de sangre) ✅ · `Bitacora` → registrar() ✅ | `GET /pacientes` (`pacientes.index`, `?editar=`), `POST /pacientes`, `PUT /pacientes/{paciente}`, `DELETE /pacientes/{paciente}` (desactiva), `PATCH /pacientes/{paciente}/estado` (reactiva) | `Pacientes/GestionarPacientesTest` | ✅ |
| CU09 | Buscar paciente | Pacientes | `buscarPaciente` (resultados y paciente seleccionado) | `Pacientes\CtrlPacientes` → buscar(), show() ✅ | `Paciente` → buscar() (sin tildes, hasta 50), obtener() ✅ | `GET /pacientes/buscar` (`pacientes.buscar`), `GET /pacientes/{paciente}` (`pacientes.show`) | `Pacientes/BuscarPacienteTest` | ✅ |
| CU10 | Registrarse como paciente | Pacientes | `registro` | `Pacientes\CtrlRegistro` → registrar() ✅ | `Paciente` → buscarPorCI(), crear(), actualizar() (al vincular) ✅ · `Rol` → obtener() ✅ · `Usuario` → crear(), buscarPorLogin() ✅ · `Bitacora` → registrar() ✅ | `GET /registro` (`registro`), `POST /registro` (`registro.registrar`) | `Pacientes/RegistrarseTest` | ✅ |
| CU11 | Gestionar perfil del paciente | Pacientes | `perfil` (enlaza a `cambiarClave`, A4) | `Pacientes\CtrlPerfil` → show(), update(), storeAntecedente() ✅ | `Paciente` → obtener(), actualizar() ✅ · `Historia` → obtener() (la crea si no existe, B7) ✅ · `Antecedente` → listar(), crear() ✅ · `Seguro` → obtenerActivo() ✅ · `Usuario` → actualizarCorreo(), buscarPorLogin() ✅ · `Bitacora` → registrar() ✅ | `GET /perfil` (`perfil.show`), `PUT /perfil` (`perfil.update`), `POST /perfil/antecedentes` (`perfil.storeAntecedente`) | `Pacientes/GestionarPerfilTest` | ✅ |
| CU12 | Registrar seguro del paciente | Pacientes | `seguro` (desde el paciente seleccionado en `buscarPaciente`) | `Pacientes\CtrlSeguros` → index(), store(), destroy() ✅ | `Paciente` → obtener() ✅ · `Seguro` → obtenerActivo(), darDeBaja(), crear() ✅ · `Bitacora` → registrar() ✅ | `GET /pacientes/{paciente}/seguro` (`seguros.index`), `POST /pacientes/{paciente}/seguro` (`seguros.store`), `DELETE /pacientes/{paciente}/seguro/{seguro}` (`seguros.destroy`) | `Pacientes/RegistrarSeguroTest` | ✅ |
| CU13 | Consultar disponibilidad | Disponibilidad | `disponibilidad` (3 pasos; también desde el paciente seleccionado en `buscarPaciente`) | `Disponibilidad\CtrlDisponibilidad` → especialidades(), medicos(), franjas() ✅ | `Paciente` → obtener() ✅ · `Especialidad` → listarPorEdad() ✅ · `Medico` → listarPorEspecialidad() ✅ · `Bloqueo` → estaBloqueada() ✅ · `Horario` → obtenerDelDia(), generarFranjas() ✅ · `Cita` → listarOcupadas() ✅ (sin `fn_franjas_disponibles`) | `GET /disponibilidad` (`disponibilidad.especialidades`), `GET /disponibilidad/medicos` (`disponibilidad.medicos`), `GET /disponibilidad/franjas` (`disponibilidad.franjas`) | `Disponibilidad/ConsultarDisponibilidadTest` | ✅ |
| CU26 | Cambiar contraseña | Acceso | `cambiarClave` | `Acceso\CtrlClave` → update() ✅ | `Usuario` → verificarClave(), actualizarClave() ✅ · `Bitacora` → registrar() ✅ | `GET /cambiar-clave` (`clave.edit`), `PUT /cambiar-clave` (`clave.update`) | `Acceso/CambiarClaveTest` | ✅ |
| CU27 | Recuperar contraseña | Acceso | `recuperarClave` (solicitar, restablecer y aviso de enlace inválido) | `Acceso\CtrlRecuperacion` → solicitarEnlace(), restablecer() ✅ | `Usuario` → buscarPorLogin(), actualizarClave(), reiniciarIntentos() ✅ · `Recuperacion` → contarRecientes(), crear(), validar() (al abrir y al enviar), marcarUsado() ✅ · `Bitacora` → registrar() ✅ | `GET /recuperar-clave` (`recuperacion.solicitar`), `POST /recuperar-clave` (`recuperacion.enlace`), `GET /recuperar-clave/{token}` (`recuperacion.form`, valida el enlace), `POST /restablecer-clave` (`recuperacion.restablecer`) | `Acceso/RecuperarClaveTest` | ✅ |

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
| Cuenta desactivada (CU03) | `app/Http/Middleware/VerificarCuentaActiva.php` (grupo `web`) | todas las peticiones con sesión | `GestionarUsuariosTest::test_la_sesion_de_una_cuenta_desactivada_se_cierra_en_la_siguiente_peticion` |
| Contraseña temporal (RN23) | `app/Http/Middleware/VerificarClaveTemporal.php` (`clave.temporal`) | rutas internas | `CambiarClaveTest::test_ingreso_con_clave_temporal_lleva_a_cambiar_y_bloquea_otras_opciones` |
| Cierre de las demás sesiones (CU26, CU27) | `auth.session` de Laravel (`AuthenticateSession`) en las rutas internas | compara el hash de la contraseña guardado en la sesión | `CambiarClaveTest::test_las_demas_sesiones_abiertas_se_cierran` |
| Sin caché (CU02) | `app/Http/Middleware/SinCache.php` (`sin.cache`) | rutas internas | `CerrarSesionTest::test_paginas_internas_no_se_guardan_en_cache` |
| Bitácora centralizada (B8) | `app/Services/ServicioBitacora.php` → `Bitacora::registrar()` | todos los CU | `IniciarSesionTest` |
| Política RNF05 | `Password::defaults()` en `app/Providers/AppServiceProvider.php` | CU03, CU10, CU26, CU27 | `CambiarClaveTest::test_nueva_igual_a_la_actual_o_debil_o_sin_confirmar_se_rechaza` |
| Validación en el navegador (RNF07) | `resources/js/validaciones.js` (`data-validar`, `data-coincide`) | formularios | manual |
| Mensajes en español | `lang/es/validation.php`, `lang/es/pagination.php` | Form Requests | todos |

Los modelos del ciclo 2 (`HistorialCita`, `Pago`, `RegistroAtencion`, `ParametroLaboratorio`, `RangoReferencia`, `ExamenLaboratorio`, `ResultadoLaboratorio`, `Notificacion`) solo tienen relaciones; sus métodos se agregan con sus CU.

## Pruebas mínimas del enunciado (sección 8)

| N.º | Prueba | Ciclo | Prueba automática | Estado |
|---|---|---|---|---|
| 1 | Registrar un paciente nuevo | 1 | `Pacientes/GestionarPacientesTest::test_registra_un_paciente_con_codigo` | ✅ |
| 2 | Registrar un paciente con CI duplicado | 1 | `Pacientes/GestionarPacientesTest::test_rechaza_un_ci_duplicado_y_muestra_el_paciente_existente` | ✅ |
| 3–8 | Programar, superponer, reservar fuera de horario y cancelar citas; historial del paciente; citas de un médico por fecha | 2 | — (CU14 en adelante) | ⬜ ciclo 2 |
| 9 | Ingresar a una página interna sin iniciar sesión | 1 | `Acceso/IniciarSesionTest::test_pagina_interna_sin_sesion_lleva_al_login`, `Pacientes/GestionarPacientesTest::test_pagina_interna_sin_sesion` | ✅ |
| 10 | Ejecutar una operación con datos inválidos | 1 | `Acceso/IniciarSesionTest::test_campos_vacios_rechazados_en_el_servidor`, `Pacientes/GestionarPacientesTest::test_campos_obligatorios_vacios_y_formatos` (además, cada CU prueba sus validaciones) | ✅ |

## Cierre del ciclo 1

- **Pruebas:** 170 pruebas automáticas en verde sobre `clinica_vida_salud_test` (`phpunit.xml` y `tests/TestCase.php`).
- **Instalación limpia verificada:** se clonó el repositorio en una carpeta nueva y se siguió solo el `README.md` (composer, npm, `.env`, `key:generate`, base nueva, `migrate:fresh --seed`, `serve`). Los usuarios de prueba de cada rol ingresan y las pruebas pasan. Se agregó al README la nota de rutas largas en Windows (`core.longpaths`).
- **Claves:** `.env` no está en el repositorio ni en el historial; `.env.example` no tiene claves.
- **Diferencias con el documento:** `docs/diferencias_con_documento.md`.

## Despliegue (Render con Docker)

| Elemento | Archivo | Requisito |
|---|---|---|
| Imagen multi-etapa (Node 22 + PHP 8.2/Apache con `pdo_pgsql`, Composer `--no-dev`) | `Dockerfile`, `.dockerignore` | RNF13 |
| Arranque: puerto `PORT`, `APP_INIT_DB` → `migrate:fresh --seed` o `migrate`, cachés | `docker/entrypoint.sh` | RNF13, RN14 |
| Conexión por `DB_URL` conservando `timezone=America/La_Paz` | `config/database.php` | RN14 |
| Proxy de Render y https forzado en producción | `bootstrap/app.php` (`trustProxies`), `app/Providers/AppServiceProvider.php` (`URL::forceScheme`) | RNF15 |
| Logs en el panel de Render (enlace de CU27 en modo `log`) | `LOG_CHANNEL=stderr`, `MAIL_MAILER=log` | CU27, RNF08 |
| Variables de entorno sin claves reales | `README.md`, sección «Despliegue en Render» | — |
