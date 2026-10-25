-- =====================================================================
-- Consultas (30): simples, múltiples y subconsultas
-- =====================================================================

-- ---------- Consultas simples ----------
-- 1. mostrar las especialidades activas ordenadas por nombre
SELECT id, nombre, costo_consulta, edad_minima, edad_maxima FROM especialidad WHERE estado ORDER BY nombre;

-- 2. listar los pacientes con su teléfono y correo
SELECT codigo, ci, apellidos, nombres, telefono, correo FROM paciente ORDER BY apellidos, nombres;

-- 3. mostrar los médicos activos
SELECT codigo, nro_registro_prof, nombres, apellidos, telefono FROM medico WHERE estado ORDER BY apellidos;

-- 4. citas programadas ordenadas por fecha y hora
SELECT codigo, fecha, hora_inicio, hora_fin, modalidad FROM cita WHERE estado = 'Programada' ORDER BY fecha, hora_inicio;

-- 5. cantidad de citas por estado
SELECT estado, COUNT(*) AS cantidad FROM cita GROUP BY estado ORDER BY cantidad DESC;

-- 6. horarios de atención de los lunes
SELECT medico_id, hora_inicio, hora_fin, duracion_cita_min FROM horario_atencion WHERE dia_semana = 1 AND estado ORDER BY hora_inicio;

-- 7. usuarios con contraseña temporal o con bloqueo vigente
SELECT nombre_usuario, correo, debe_cambiar_clave, bloqueado_hasta FROM usuario WHERE debe_cambiar_clave OR bloqueado_hasta > NOW();

-- 8. pacientes adultos mayores (60 años o más)
SELECT codigo, apellidos, nombres, fn_edad(fecha_nacimiento, CURRENT_DATE) AS edad FROM paciente WHERE fn_edad(fecha_nacimiento, CURRENT_DATE) >= 60 ORDER BY edad DESC;

-- 9. exámenes solicitados que aún esperan resultados
SELECT codigo, tipo_examen, fecha_solicitud FROM examen_laboratorio WHERE estado = 'Solicitado' ORDER BY fecha_solicitud;

-- 10. feriados y bloqueos de agenda ordenados por fecha
SELECT fecha, motivo, medico_id FROM bloqueo_agenda ORDER BY fecha;

-- ---------- Consultas múltiples (combinación de tablas) ----------
-- 11. agenda de los médicos con paciente y especialidad
SELECT m.apellidos AS medico, c.fecha, c.hora_inicio, c.codigo, p.apellidos || ' ' || p.nombres AS paciente, e.nombre AS especialidad, c.estado
FROM cita c
JOIN medico m ON m.id = c.medico_id
JOIN paciente p ON p.id = c.paciente_id
JOIN especialidad e ON e.id = c.especialidad_id
ORDER BY m.apellidos, c.fecha, c.hora_inicio;

-- 12. historial de citas de un paciente
SELECT c.codigo, c.fecha, c.hora_inicio, m.apellidos AS medico, e.nombre AS especialidad, c.estado
FROM paciente p
JOIN cita c ON c.paciente_id = p.id
JOIN medico m ON m.id = c.medico_id
JOIN especialidad e ON e.id = c.especialidad_id
WHERE p.ci = '1234567'
ORDER BY c.fecha DESC;

-- 13. médicos con sus especialidades
SELECT m.codigo, m.apellidos || ' ' || m.nombres AS medico, STRING_AGG(e.nombre, ', ' ORDER BY e.nombre) AS especialidades
FROM medico m
JOIN medico_especialidad me ON me.medico_id = m.id
JOIN especialidad e ON e.id = me.especialidad_id
GROUP BY m.id, m.codigo, m.apellidos, m.nombres
ORDER BY m.apellidos;

-- 14. usuarios con su rol y la ficha a la que están vinculados
SELECT u.nombre_usuario, r.nombre AS rol, COALESCE(m.apellidos, p.apellidos) AS vinculado_a
FROM usuario u
JOIN rol r ON r.id = u.rol_id
LEFT JOIN medico m ON m.id = u.medico_id
LEFT JOIN paciente p ON p.id = u.paciente_id
ORDER BY r.nombre, u.nombre_usuario;

-- 15. pagos con su cita, paciente y seguro aplicado
SELECT pg.nro_comprobante, c.codigo AS cita, p.apellidos AS paciente, s.aseguradora, pg.monto_consulta, pg.porcentaje_cobertura, pg.monto_paciente, pg.estado
FROM pago pg
JOIN cita c ON c.id = pg.cita_id
JOIN paciente p ON p.id = c.paciente_id
LEFT JOIN seguro_paciente s ON s.id = pg.seguro_id
ORDER BY pg.nro_comprobante;

-- 16. resultados de laboratorio con su interpretación según la edad
SELECT ex.codigo, p.apellidos AS paciente, pl.nombre AS parametro, r.valor, pl.unidad, r.valor_minimo_ref, r.valor_maximo_ref, r.interpretacion
FROM resultado_laboratorio r
JOIN examen_laboratorio ex ON ex.id = r.examen_id
JOIN paciente p ON p.id = ex.paciente_id
JOIN parametro_laboratorio pl ON pl.id = r.parametro_id
ORDER BY ex.codigo, pl.nombre;

-- 17. ingresos por especialidad (monto cobrado y cubierto por seguros)
SELECT e.nombre AS especialidad, COUNT(*) AS pagos, SUM(pg.monto_paciente) AS cobrado, SUM(pg.monto_consulta - pg.monto_paciente) AS cubierto_por_seguro
FROM pago pg
JOIN cita c ON c.id = pg.cita_id
JOIN especialidad e ON e.id = c.especialidad_id
WHERE pg.estado = 'Pagado'
GROUP BY e.nombre
ORDER BY cobrado DESC;

-- 18. cantidad de citas por médico y estado
SELECT m.apellidos AS medico, c.estado, COUNT(*) AS cantidad
FROM cita c
JOIN medico m ON m.id = c.medico_id
GROUP BY m.apellidos, c.estado
ORDER BY m.apellidos, c.estado;

-- 19. pacientes con su seguro activo (incluye a quienes no tienen)
SELECT p.codigo, p.apellidos, p.nombres, s.aseguradora, s.porcentaje_cobertura, s.fecha_fin
FROM paciente p
LEFT JOIN seguro_paciente s ON s.paciente_id = p.id AND s.estado
ORDER BY p.codigo;

-- 20. bitácora con el nombre del usuario que realizó cada operación
SELECT b.fecha_hora, COALESCE(u.nombre_usuario, 'sin identificar') AS usuario, b.tipo, b.accion, b.modulo, b.descripcion
FROM bitacora b
LEFT JOIN usuario u ON u.id = b.usuario_id
ORDER BY b.fecha_hora DESC;

-- ---------- Subconsultas ----------
-- 21. pacientes que nunca han tenido una cita
SELECT codigo, apellidos, nombres FROM paciente p
WHERE NOT EXISTS (SELECT 1 FROM cita c WHERE c.paciente_id = p.id);

-- 22. médicos con más citas que el promedio
SELECT m.apellidos AS medico, COUNT(*) AS citas
FROM cita c JOIN medico m ON m.id = c.medico_id
GROUP BY m.id, m.apellidos
HAVING COUNT(*) > (SELECT AVG(n) FROM (SELECT COUNT(*) AS n FROM cita GROUP BY medico_id) t);

-- 23. especialidades que ningún médico atiende
SELECT nombre FROM especialidad WHERE id NOT IN (SELECT especialidad_id FROM medico_especialidad);

-- 24. pacientes con algún resultado de laboratorio fuera de rango
SELECT p.codigo, p.apellidos, p.nombres FROM paciente p
WHERE EXISTS (SELECT 1 FROM examen_laboratorio ex JOIN resultado_laboratorio r ON r.examen_id = ex.id
              WHERE ex.paciente_id = p.id AND r.interpretacion <> 'Normal');

-- 25. citas del último paciente registrado
SELECT codigo, fecha, hora_inicio, estado FROM cita WHERE paciente_id = (SELECT MAX(id) FROM paciente);

-- 26. especialidad con el costo de consulta más alto
SELECT nombre, costo_consulta FROM especialidad WHERE costo_consulta = (SELECT MAX(costo_consulta) FROM especialidad);

-- 27. citas con el pago pendiente
SELECT c.codigo, c.fecha, c.estado FROM cita c WHERE c.id IN (SELECT cita_id FROM pago WHERE estado = 'Pendiente');

-- 28. médicos que no atienden los viernes
SELECT apellidos, nombres FROM medico m
WHERE m.estado AND NOT EXISTS (SELECT 1 FROM horario_atencion h WHERE h.medico_id = m.id AND h.dia_semana = 5 AND h.estado);

-- 29. pacientes con más de una cita
SELECT codigo, apellidos, nombres FROM paciente
WHERE id IN (SELECT paciente_id FROM cita GROUP BY paciente_id HAVING COUNT(*) > 1);

-- 30. usuarios que registraron citas y nunca tuvieron un acceso fallido
SELECT u.nombre_usuario FROM usuario u
WHERE EXISTS (SELECT 1 FROM cita c WHERE c.usuario_registro_id = u.id)
  AND NOT EXISTS (SELECT 1 FROM bitacora b WHERE b.usuario_id = u.id AND b.accion = 'LOGIN_FALLIDO');
