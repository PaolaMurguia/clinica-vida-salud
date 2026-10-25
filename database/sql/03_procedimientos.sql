-- =====================================================================
-- Procedimientos y funciones almacenadas
-- =====================================================================

-- Registra una operación en la bitácora (RF15)
CREATE OR REPLACE PROCEDURE sp_registrar_bitacora(
  p_usuario_id INT, p_tipo VARCHAR, p_accion VARCHAR, p_modulo VARCHAR,
  p_registro_id INT, p_descripcion VARCHAR, p_ip VARCHAR DEFAULT NULL, p_navegador VARCHAR DEFAULT NULL)
LANGUAGE plpgsql AS $$
BEGIN
  INSERT INTO bitacora (usuario_id, tipo, accion, modulo, registro_id, descripcion, ip, navegador)
  VALUES (p_usuario_id, p_tipo, p_accion, p_modulo, p_registro_id, LEFT(p_descripcion, 300), p_ip, p_navegador);
END; $$;

-- Registra un paciente nuevo verificando que el CI no exista (RF02, CU08)
CREATE OR REPLACE PROCEDURE sp_registrar_paciente(
  p_ci VARCHAR, p_nombres VARCHAR, p_apellidos VARCHAR, p_fecha_nac DATE, p_sexo CHAR,
  p_telefono VARCHAR, p_correo VARCHAR, p_direccion VARCHAR, p_usuario_id INT,
  INOUT p_paciente_id INT DEFAULT NULL)
LANGUAGE plpgsql AS $$
BEGIN
  IF EXISTS (SELECT 1 FROM paciente WHERE ci = p_ci) THEN
    RAISE EXCEPTION 'Ya existe un paciente con el CI % (RF02).', p_ci;
  END IF;
  INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion)
  VALUES (p_ci, p_nombres, p_apellidos, p_fecha_nac, p_sexo, p_telefono, p_correo, p_direccion)
  RETURNING id INTO p_paciente_id;
  CALL sp_registrar_bitacora(p_usuario_id, 'Accion', 'CREAR', 'Pacientes', p_paciente_id, 'Registro del paciente ' || p_ci);
END; $$;

-- Franjas libres de un médico en una fecha (RF05, CU13)
CREATE OR REPLACE FUNCTION fn_franjas_disponibles(p_medico_id INT, p_fecha DATE)
RETURNS TABLE (hora_inicio TIME, hora_fin TIME)
LANGUAGE sql STABLE AS $$
  SELECT g.ini::time, (g.ini + make_interval(mins => h.duracion_cita_min))::time
  FROM medico md
  JOIN horario_atencion h ON h.medico_id = md.id AND h.estado AND h.dia_semana = EXTRACT(ISODOW FROM p_fecha)
  CROSS JOIN LATERAL generate_series(p_fecha + h.hora_inicio,
        p_fecha + h.hora_fin - make_interval(mins => h.duracion_cita_min),
        make_interval(mins => h.duracion_cita_min)) AS g(ini)
  WHERE md.id = p_medico_id AND md.estado AND g.ini > NOW()
    AND NOT EXISTS (SELECT 1 FROM bloqueo_agenda b WHERE b.fecha = p_fecha AND (b.medico_id IS NULL OR b.medico_id = p_medico_id))
    AND NOT EXISTS (SELECT 1 FROM cita c WHERE c.medico_id = p_medico_id AND c.fecha = p_fecha AND c.estado <> 'Cancelada'
        AND tsrange(c.fecha + c.hora_inicio, c.fecha + c.hora_fin, '[)') &&
            tsrange(g.ini, g.ini + make_interval(mins => h.duracion_cita_min), '[)'))
  ORDER BY g.ini;
$$;

-- Programa una cita (RF06, RF07, CU14): calcula la hora final, registra el historial y el recordatorio
CREATE OR REPLACE PROCEDURE sp_programar_cita(
  p_paciente_id INT, p_medico_id INT, p_especialidad_id INT, p_usuario_id INT,
  p_fecha DATE, p_hora_inicio TIME, p_modalidad VARCHAR, p_canal VARCHAR, p_motivo VARCHAR,
  INOUT p_cita_id INT DEFAULT NULL, INOUT p_codigo VARCHAR DEFAULT NULL)
LANGUAGE plpgsql AS $$
DECLARE v_dur SMALLINT; v_usuario_pac INT;
BEGIN
  SELECT h.duracion_cita_min INTO v_dur FROM horario_atencion h
  WHERE h.medico_id = p_medico_id AND h.estado AND h.dia_semana = EXTRACT(ISODOW FROM p_fecha)
    AND p_hora_inicio >= h.hora_inicio AND p_hora_inicio < h.hora_fin;
  IF v_dur IS NULL THEN RAISE EXCEPTION 'El médico no atiende en ese día y hora (RN05).'; END IF;
  INSERT INTO cita (paciente_id, medico_id, especialidad_id, usuario_registro_id, fecha, hora_inicio, hora_fin, modalidad, canal, motivo)
  VALUES (p_paciente_id, p_medico_id, p_especialidad_id, p_usuario_id, p_fecha, p_hora_inicio,
          p_hora_inicio + make_interval(mins => v_dur), COALESCE(p_modalidad, 'Presencial'), COALESCE(p_canal, 'Recepcion'), p_motivo)
  RETURNING id, codigo INTO p_cita_id, p_codigo;           -- los triggers validan horario, edad y superposición
  INSERT INTO historial_estado_cita (cita_id, usuario_id, estado_anterior, estado_nuevo)
  VALUES (p_cita_id, p_usuario_id, NULL, 'Programada');
  SELECT id INTO v_usuario_pac FROM usuario WHERE paciente_id = p_paciente_id AND estado;
  IF v_usuario_pac IS NOT NULL AND (p_fecha + p_hora_inicio) - INTERVAL '24 hours' > NOW() THEN
    INSERT INTO notificacion (usuario_id, cita_id, canal, tipo, mensaje, fecha_programada)
    VALUES (v_usuario_pac, p_cita_id, 'Correo', 'Recordatorio',
            'Recuerde su cita ' || p_codigo || ' el ' || to_char(p_fecha, 'DD/MM/YYYY') || ' a las ' || to_char(p_hora_inicio, 'HH24:MI'),
            (p_fecha + p_hora_inicio) - INTERVAL '24 hours');
  END IF;
  CALL sp_registrar_bitacora(p_usuario_id, 'Accion', 'CREAR', 'Citas', p_cita_id, 'Registro de la cita ' || p_codigo);
END; $$;

-- Cancela una cita (RF09, CU16): motivo obligatorio y regla de 2 horas para el paciente (RN17)
CREATE OR REPLACE PROCEDURE sp_cancelar_cita(p_cita_id INT, p_usuario_id INT, p_motivo VARCHAR)
LANGUAGE plpgsql AS $$
DECLARE v_c cita%ROWTYPE; v_rol VARCHAR;
BEGIN
  IF p_motivo IS NULL OR btrim(p_motivo) = '' THEN RAISE EXCEPTION 'El motivo de la cancelación es obligatorio (RF09).'; END IF;
  SELECT * INTO v_c FROM cita WHERE id = p_cita_id FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'La cita no existe.'; END IF;
  IF v_c.estado = 'Cancelada' THEN RAISE EXCEPTION 'La cita ya está cancelada.'; END IF;
  SELECT r.nombre INTO v_rol FROM usuario u JOIN rol r ON r.id = u.rol_id WHERE u.id = p_usuario_id;
  IF v_rol = 'Paciente' AND (v_c.fecha + v_c.hora_inicio) - NOW() < INTERVAL '2 hours' THEN
    RAISE EXCEPTION 'El paciente solo puede cancelar hasta 2 horas antes de la cita (RN17).';
  END IF;
  UPDATE cita SET estado = 'Cancelada', motivo_cancelacion = p_motivo WHERE id = p_cita_id;   -- el trigger valida la transición
  INSERT INTO historial_estado_cita (cita_id, usuario_id, estado_anterior, estado_nuevo, observacion)
  VALUES (p_cita_id, p_usuario_id, v_c.estado, 'Cancelada', p_motivo);
  UPDATE notificacion SET estado = 'Anulado' WHERE cita_id = p_cita_id AND estado = 'Pendiente';
  CALL sp_registrar_bitacora(p_usuario_id, 'Accion', 'CANCELAR', 'Citas', p_cita_id, 'Cancelación de la cita ' || v_c.codigo);
END; $$;

-- Reprograma una cita programada o confirmada (RF08, CU15)
CREATE OR REPLACE PROCEDURE sp_reprogramar_cita(p_cita_id INT, p_usuario_id INT, p_fecha DATE, p_hora_inicio TIME)
LANGUAGE plpgsql AS $$
DECLARE v_c cita%ROWTYPE; v_rol VARCHAR; v_dur SMALLINT; v_usuario_pac INT;
BEGIN
  SELECT * INTO v_c FROM cita WHERE id = p_cita_id FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'La cita no existe.'; END IF;
  IF v_c.estado NOT IN ('Programada', 'Confirmada') THEN
    RAISE EXCEPTION 'Una cita % no se reprograma; se registra una nueva cita.', lower(v_c.estado);
  END IF;
  SELECT r.nombre INTO v_rol FROM usuario u JOIN rol r ON r.id = u.rol_id WHERE u.id = p_usuario_id;
  IF v_rol = 'Paciente' AND (v_c.fecha + v_c.hora_inicio) - NOW() < INTERVAL '2 hours' THEN
    RAISE EXCEPTION 'El paciente solo puede reprogramar hasta 2 horas antes de la cita (RN17).';
  END IF;
  SELECT h.duracion_cita_min INTO v_dur FROM horario_atencion h
  WHERE h.medico_id = v_c.medico_id AND h.estado AND h.dia_semana = EXTRACT(ISODOW FROM p_fecha)
    AND p_hora_inicio >= h.hora_inicio AND p_hora_inicio < h.hora_fin;
  IF v_dur IS NULL THEN RAISE EXCEPTION 'El médico no atiende en ese día y hora (RN05).'; END IF;
  UPDATE cita SET fecha = p_fecha, hora_inicio = p_hora_inicio, hora_fin = p_hora_inicio + make_interval(mins => v_dur) WHERE id = p_cita_id;
  INSERT INTO historial_estado_cita (cita_id, usuario_id, estado_anterior, estado_nuevo, observacion)
  VALUES (p_cita_id, p_usuario_id, v_c.estado, v_c.estado, 'Reprogramada al ' || to_char(p_fecha, 'DD/MM/YYYY') || ' ' || to_char(p_hora_inicio, 'HH24:MI'));
  UPDATE notificacion SET estado = 'Anulado' WHERE cita_id = p_cita_id AND estado = 'Pendiente';
  SELECT id INTO v_usuario_pac FROM usuario WHERE paciente_id = v_c.paciente_id AND estado;
  IF v_usuario_pac IS NOT NULL AND (p_fecha + p_hora_inicio) - INTERVAL '24 hours' > NOW() THEN
    INSERT INTO notificacion (usuario_id, cita_id, canal, tipo, mensaje, fecha_programada)
    VALUES (v_usuario_pac, p_cita_id, 'Correo', 'Recordatorio',
            'Recuerde su cita ' || v_c.codigo || ' el ' || to_char(p_fecha, 'DD/MM/YYYY') || ' a las ' || to_char(p_hora_inicio, 'HH24:MI'),
            (p_fecha + p_hora_inicio) - INTERVAL '24 hours');
  END IF;
  CALL sp_registrar_bitacora(p_usuario_id, 'Accion', 'MODIFICAR', 'Citas', p_cita_id, 'Reprogramación de la cita ' || v_c.codigo);
END; $$;

-- Registra el cobro de una consulta aplicando la cobertura del seguro vigente (RF18, CU25)
CREATE OR REPLACE PROCEDURE sp_registrar_pago(p_cita_id INT, p_usuario_id INT, p_metodo VARCHAR)
LANGUAGE plpgsql AS $$
DECLARE v_c cita%ROWTYPE; v_costo NUMERIC; v_seguro INT;
BEGIN
  SELECT * INTO v_c FROM cita WHERE id = p_cita_id;
  IF NOT FOUND THEN RAISE EXCEPTION 'La cita no existe.'; END IF;
  IF v_c.estado = 'Cancelada' THEN RAISE EXCEPTION 'No se cobra una cita cancelada.'; END IF;
  IF EXISTS (SELECT 1 FROM pago WHERE cita_id = p_cita_id) THEN RAISE EXCEPTION 'La cita ya tiene un pago registrado.'; END IF;
  SELECT costo_consulta INTO v_costo FROM especialidad WHERE id = v_c.especialidad_id;
  SELECT s.id INTO v_seguro FROM seguro_paciente s
  WHERE s.paciente_id = v_c.paciente_id AND s.estado AND v_c.fecha BETWEEN s.fecha_inicio AND s.fecha_fin;   -- RN19
  INSERT INTO pago (cita_id, seguro_id, usuario_id, monto_consulta, metodo_pago, estado, fecha_pago)
  VALUES (p_cita_id, v_seguro, p_usuario_id, v_costo, p_metodo, 'Pagado', NOW());       -- el trigger aplica la cobertura
  CALL sp_registrar_bitacora(p_usuario_id, 'Accion', 'CREAR', 'Pagos', p_cita_id, 'Pago de la cita ' || v_c.codigo);
END; $$;

-- El médico solicita un análisis de laboratorio (RF20, CU22)
CREATE OR REPLACE PROCEDURE sp_solicitar_examen(
  p_paciente_id INT, p_medico_id INT, p_cita_id INT, p_tipo VARCHAR, p_usuario_id INT,
  INOUT p_codigo VARCHAR DEFAULT NULL)
LANGUAGE plpgsql AS $$
DECLARE v_id INT;
BEGIN
  INSERT INTO examen_laboratorio (paciente_id, medico_id, cita_id, tipo_examen)
  VALUES (p_paciente_id, p_medico_id, p_cita_id, p_tipo) RETURNING id, codigo INTO v_id, p_codigo;
  CALL sp_registrar_bitacora(p_usuario_id, 'Accion', 'CREAR', 'Laboratorio', v_id, 'Solicitud del examen ' || p_codigo);
END; $$;

-- La recepción registra el valor de un parámetro; el trigger lo interpreta según la edad (RF20, CU22)
CREATE OR REPLACE PROCEDURE sp_registrar_resultado(
  p_codigo_examen VARCHAR, p_usuario_id INT, p_fecha_muestra DATE, p_parametro VARCHAR, p_valor NUMERIC)
LANGUAGE plpgsql AS $$
DECLARE v_e examen_laboratorio%ROWTYPE; v_p INT; v_usuario_medico INT;
BEGIN
  SELECT * INTO v_e FROM examen_laboratorio WHERE codigo = p_codigo_examen FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'El examen % no existe.', p_codigo_examen; END IF;
  IF v_e.estado = 'Revisado' THEN RAISE EXCEPTION 'El examen ya fue revisado y no se modifica.'; END IF;
  IF p_fecha_muestra > CURRENT_DATE OR p_fecha_muestra < v_e.fecha_solicitud::date THEN
    RAISE EXCEPTION 'La fecha de la muestra es inválida.';
  END IF;
  SELECT id INTO v_p FROM parametro_laboratorio WHERE lower(nombre) = lower(p_parametro) AND estado;
  IF v_p IS NULL THEN RAISE EXCEPTION 'El parámetro % no existe.', p_parametro; END IF;
  IF v_e.estado = 'Solicitado' THEN
    UPDATE examen_laboratorio SET fecha_muestra = p_fecha_muestra WHERE id = v_e.id;
  END IF;
  INSERT INTO resultado_laboratorio (examen_id, parametro_id, valor, valor_minimo_ref, valor_maximo_ref, interpretacion)
  VALUES (v_e.id, v_p, p_valor, 0, 0, 'Normal');            -- el trigger fija el rango y la interpretación
  IF v_e.estado = 'Solicitado' THEN
    UPDATE examen_laboratorio SET estado = 'Registrado', usuario_registro_id = p_usuario_id, fecha_registro = NOW() WHERE id = v_e.id;
    SELECT id INTO v_usuario_medico FROM usuario WHERE medico_id = v_e.medico_id AND estado;
    IF v_usuario_medico IS NOT NULL THEN
      INSERT INTO notificacion (usuario_id, cita_id, canal, tipo, mensaje, fecha_programada)
      VALUES (v_usuario_medico, v_e.cita_id, 'Sistema', 'Resultado', 'Resultados disponibles del examen ' || v_e.codigo, NOW());
    END IF;
  END IF;
  CALL sp_registrar_bitacora(p_usuario_id, 'Accion', 'CREAR', 'Laboratorio', v_e.id, 'Resultado de ' || p_parametro || ' en ' || v_e.codigo);
END; $$;

-- El administrador restablece una contraseña: queda temporal y la cuenta se desbloquea (CU03, RN23)
CREATE OR REPLACE PROCEDURE sp_restablecer_clave(p_usuario_id INT, p_admin_id INT, p_hash VARCHAR)
LANGUAGE plpgsql AS $$
BEGIN
  UPDATE usuario SET password_hash = p_hash, debe_cambiar_clave = TRUE, intentos_fallidos = 0, nivel_bloqueo = 0, bloqueado_hasta = NULL
  WHERE id = p_usuario_id;
  IF NOT FOUND THEN RAISE EXCEPTION 'El usuario no existe.'; END IF;
  CALL sp_registrar_bitacora(p_admin_id, 'Accion', 'RESTABLECER_CLAVE', 'Usuarios', p_usuario_id, 'Restablecimiento de contraseña');
END; $$;

-- Ingresos por método de pago en un período (RF13)
CREATE OR REPLACE FUNCTION fn_ingresos(p_desde DATE, p_hasta DATE)
RETURNS TABLE (metodo VARCHAR, pagos BIGINT, cobrado_paciente NUMERIC, cubierto_seguro NUMERIC)
LANGUAGE sql STABLE AS $$
  SELECT metodo_pago::varchar, COUNT(*), SUM(monto_paciente), SUM(monto_consulta - monto_paciente)
  FROM pago WHERE estado = 'Pagado' AND fecha_pago::date BETWEEN p_desde AND p_hasta
  GROUP BY metodo_pago ORDER BY metodo_pago;
$$;
