# Clínica Vida Salud - Guía de Desarrollo del Proyecto

## Contexto del Proyecto
Sistema Web de Gestión de Citas Médicas para la Clínica Vida Salud (Examen Parcial II - Sistemas 1).
- **Backend**: Laravel 12 / PHP 8.2+ con `pdo_pgsql`.
- **Base de datos**: PostgreSQL 15+ (`clinica_vida_salud`).
- **Frontend**: Blade, Bootstrap 5, Sass, Vite, JavaScript nativo para validaciones.
- **Seguridad**: Hash bcrypt (`password_hash` / `Hash::make`), bloqueo progresivo (5, 15, 60 min), Form Requests obligatorios, protección CSRF y sesiones regeneradas.

---

## Decisiones de Diseño y Reglas del Negocio (Ciclo 1)

### A. Resoluciones a Contradicciones
1. **CRUD de Pacientes**: El Administrador tiene el CRUD completo de pacientes, utilizando el mismo `CtrlPacientes`.
2. **Política de contraseñas**: Definida en RNF05 (mínimo 8 caracteres, mayúsculas, minúsculas, números y al menos un símbolo).
3. **Disponibilidad (CU13)**: Sigue el diagrama de clases (cálculo en los modelos Eloquent, no en BD). Muestra franjas libres y ocupadas con motivo de bloqueo. La función `fn_franjas_disponibles` de BD se usa en pruebas para contrastar.
4. **Mi Perfil**: Enlaza directamente a CU26 para cambio de contraseña.
5. **Antecedentes médicos**: No se borran (preservación histórica).
6. **CU06 (Médicos)**: Solicita CI y valida CI y registro profesional mediante el Form Request, sin métodos adicionales en el controlador.
7. **Usuario de Pacientes**: El `nombre_usuario` para los pacientes es su número de CI.
8. **Médico**: Acceso a inicio de sesión y a cambiar contraseña (CU01, CU02, CU26).

### B. Huecos de Diseño Resueltos
1. **Rutas y vistas**: `Route::view` o rutas simples para formularios puros, el menú principal y el portal. Los formularios de gestión van integrados en la vista `index()` correspondiente sin métodos inventados.
2. **Acciones de estado**: Desbloquear, activar y desactivar se canalizan en `cambiarEstado()` recibiendo un parámetro de acción.
3. **Horarios**: No hay edición de horarios en el ciclo 1 (solo registrar y desactivar).
4. **Advertencias**: Advertencias por citas futuras al desactivar se aplazan al ciclo 2.
5. **Asignación de especialidades**: `M.Atiende::asignar()` sincroniza las especialidades del médico (agrega y quita).
6. **Reactivación**: `cambiarEstado()` también reactiva registros inactivos.
7. **Historia Clínica**: `M.Historia::obtener()` crea automáticamente la historia si no existe; el tipo de sangre se guarda con `update`.
8. **Bitácora**: Servicio centralizado `ServicioBitacora` para auditoría y registro de eventos.
9. **Bloqueo progresivo y reintentos**: `reiniciarIntentos()` restablece `nivel_bloqueo` a 0. Tras el nivel 3 (60 min) se repiten 60 minutos ante nuevos fallos. En CU26, 5 fallos consecutivos bloquean la cuenta y cierran la sesión.
10. **Minoría de edad**: Menor de 18 años requiere contacto de emergencia (madre/padre/tutor).
11. **Bitácora**: Exportación a CSV y filtro con rango máximo de 90 días.
12. **Contraseñas iniciales**: Toda contraseña creada o restablecida por el administrador es temporal (`debe_cambiar_clave = true`).
13. **Regla de llamadas Eloquent**: Las llamadas estándar de Eloquent (`save`, `update`, `create`, `delete`, `find`, `where`) no cuentan como métodos nuevos; los métodos con nombre propio deben apegarse estrictamente a `docs/casos_de_uso_ciclo1.md`.

---

## Modelos y Tablas (22 tablas, sin timestamps automáticos de Eloquent)
1. `Rol` (`rol`)
2. `Especialidad` (`especialidad`)
3. `Medico` (`medico`)
4. `Atiende` (`medico_especialidad`)
5. `Horario` (`horario_atencion`)
6. `Bloqueo` (`bloqueo_agenda`)
7. `Paciente` (`paciente`)
8. `Usuario` (`usuario`)
9. `Recuperacion` (`recuperacion_clave`)
10. `Seguro` (`seguro_paciente`)
11. `Cita` (`cita`)
12. `HistorialCita` (`historial_estado_cita`)
13. `Pago` (`pago`)
14. `Historia` (`historia_clinica`)
15. `Antecedente` (`antecedente`)
16. `RegistroAtencion` (`registro_atencion`)
17. `ParametroLaboratorio` (`parametro_laboratorio`)
18. `RangoReferencia` (`rango_referencia`)
19. `ExamenLaboratorio` (`examen_laboratorio`)
20. `ResultadoLaboratorio` (`resultado_laboratorio`)
21. `Notificacion` (`notificacion`)
22. `Bitacora` (`bitacora`)

Todos los modelos deben incluir `public $timestamps = false;`.

