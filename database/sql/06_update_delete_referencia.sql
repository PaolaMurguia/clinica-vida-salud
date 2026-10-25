-- =====================================================================
-- Actualización de tuplas: Update() y Delete()
-- =====================================================================

-- ----- Update() -----
-- confirmar la asistencia de una cita programada
UPDATE cita SET estado = 'Confirmada' WHERE codigo = 'CM-0011' AND estado = 'Programada';

-- reprogramar una cita a otra franja libre del mismo médico
UPDATE cita SET hora_inicio = '10:00', hora_fin = '10:30' WHERE codigo = 'CM-0009' AND estado = 'Programada';

-- cancelar una cita guardando el motivo
UPDATE cita SET estado = 'Cancelada', motivo_cancelacion = 'El paciente no puede asistir' WHERE codigo = 'CM-0012';

-- marcar una cita confirmada como atendida
UPDATE cita SET estado = 'Atendida' WHERE codigo = 'CM-0010' AND estado = 'Confirmada';

-- desactivar a un médico (conserva su historial, RN11)
UPDATE medico SET estado = FALSE WHERE nro_registro_prof = 'RP-1003';

-- actualizar el costo de una especialidad
UPDATE especialidad SET costo_consulta = 160 WHERE nombre_normalizado = 'medicina general';

-- desbloquear una cuenta y reiniciar sus intentos fallidos
UPDATE usuario SET intentos_fallidos = 0, nivel_bloqueo = 0, bloqueado_hasta = NULL WHERE nombre_usuario = 'dra.mendoza';

-- renovar la vigencia de un seguro
UPDATE seguro_paciente SET fecha_fin = CURRENT_DATE + 365 WHERE nro_poliza = 'AA-2025-0871';

-- actualizar el teléfono de un paciente
UPDATE paciente SET telefono = '72222000' WHERE ci = '7654321';

-- completar la fecha de la muestra de un examen solicitado
UPDATE examen_laboratorio SET fecha_muestra = CURRENT_DATE WHERE codigo = 'EX-00005' AND estado = 'Solicitado';

-- el médico marca un examen como revisado
UPDATE examen_laboratorio SET estado = 'Revisado' WHERE codigo = 'EX-00004' AND estado = 'Registrado';

-- marcar como enviadas las notificaciones cuya hora ya llegó
UPDATE notificacion SET estado = 'Enviado', fecha_envio = NOW() WHERE estado = 'Pendiente' AND fecha_programada <= NOW();

-- ----- Delete() -----
-- eliminar los enlaces de recuperación vencidos hace más de un día
DELETE FROM recuperacion_clave WHERE fecha_expira < NOW() - INTERVAL '1 day';

-- eliminar los feriados que ya pasaron
DELETE FROM bloqueo_agenda WHERE fecha < CURRENT_DATE;

-- eliminar las notificaciones anuladas de hace más de 30 días
DELETE FROM notificacion WHERE estado = 'Anulado' AND fecha_programada < NOW() - INTERVAL '30 days';

-- el paciente quita un antecedente que él mismo declaró (los del médico no se pueden quitar)
DELETE FROM antecedente
WHERE origen = 'Paciente' AND descripcion = 'Penicilina'
  AND historia_clinica_id = (SELECT h.id FROM historia_clinica h JOIN paciente p ON p.id = h.paciente_id WHERE p.ci = '1234567');

-- eliminar un horario sin citas asociadas
DELETE FROM horario_atencion
WHERE dia_semana = 6 AND medico_id = (SELECT id FROM medico WHERE nro_registro_prof = 'RP-1006');

-- un paciente con citas no se elimina: la clave foránea lo impide y se debe desactivar (RN11)
-- DELETE FROM paciente WHERE ci = '1234567';   -- ERROR: viola la clave foránea de cita
