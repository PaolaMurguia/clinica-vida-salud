-- =====================================================================
-- Funciones y disparadores
-- =====================================================================

-- Edad en años cumplidos a una fecha dada
CREATE OR REPLACE FUNCTION fn_edad(p_nacimiento DATE, p_fecha DATE)
RETURNS INT AS $$
  SELECT EXTRACT(YEAR FROM age(p_fecha, p_nacimiento))::int;
$$ LANGUAGE sql IMMUTABLE;

-- Normaliza el nombre de la especialidad (minúsculas y espacios simples) - RF04
CREATE OR REPLACE FUNCTION fn_normalizar_especialidad() RETURNS trigger AS $$
BEGIN
  NEW.nombre_normalizado := lower(btrim(regexp_replace(NEW.nombre, '\s+', ' ', 'g')));
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_normalizar_especialidad
  BEFORE INSERT OR UPDATE OF nombre ON especialidad
  FOR EACH ROW EXECUTE FUNCTION fn_normalizar_especialidad();

-- Valida RN03, RN04, RN05, RN06, RN08 y RN15 al registrar o reprogramar una cita
CREATE OR REPLACE FUNCTION fn_validar_cita() RETURNS trigger AS $$
DECLARE
  v_dia  SMALLINT;
  v_edad INT;
  v_esp  especialidad%ROWTYPE;
BEGIN
  IF NOT EXISTS (SELECT 1 FROM paciente WHERE id = NEW.paciente_id AND estado) THEN
    RAISE EXCEPTION 'El paciente no está activo (RN03).';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM medico WHERE id = NEW.medico_id AND estado) THEN
    RAISE EXCEPTION 'El médico no está activo (RN04).';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM medico_especialidad
                 WHERE medico_id = NEW.medico_id AND especialidad_id = NEW.especialidad_id) THEN
    RAISE EXCEPTION 'El médico no atiende la especialidad seleccionada.';
  END IF;
  IF (NEW.fecha + NEW.hora_inicio) < NOW() THEN
    RAISE EXCEPTION 'No se pueden registrar citas en fechas u horas pasadas (RN06).';
  END IF;
  -- RN15: la especialidad debe corresponder a la edad del paciente
  SELECT * INTO v_esp FROM especialidad WHERE id = NEW.especialidad_id;
  SELECT fn_edad(p.fecha_nacimiento, NEW.fecha) INTO v_edad FROM paciente p WHERE p.id = NEW.paciente_id;
  IF (v_esp.edad_minima IS NOT NULL AND v_edad < v_esp.edad_minima)
     OR (v_esp.edad_maxima IS NOT NULL AND v_edad > v_esp.edad_maxima) THEN
    RAISE EXCEPTION 'La especialidad % no corresponde a la edad del paciente (% años) (RN15).',
                    v_esp.nombre, v_edad;
  END IF;
  -- RN05 y RN08: dentro del horario y respetando la duración de la ficha
  v_dia := EXTRACT(ISODOW FROM NEW.fecha);
  IF NOT EXISTS (
       SELECT 1 FROM horario_atencion h
       WHERE h.medico_id = NEW.medico_id AND h.estado AND h.dia_semana = v_dia
         AND NEW.hora_inicio >= h.hora_inicio AND NEW.hora_fin <= h.hora_fin
         AND NEW.hora_fin - NEW.hora_inicio = make_interval(mins => h.duracion_cita_min)
         AND MOD(EXTRACT(EPOCH FROM (NEW.hora_inicio - h.hora_inicio))::int,
                 h.duracion_cita_min * 60) = 0) THEN
    RAISE EXCEPTION 'Cita fuera del horario de atención o con duración inválida (RN05, RN08).';
  END IF;
  IF EXISTS (SELECT 1 FROM bloqueo_agenda b WHERE b.fecha = NEW.fecha
             AND (b.medico_id IS NULL OR b.medico_id = NEW.medico_id)) THEN
    RAISE EXCEPTION 'La fecha está bloqueada en la agenda.';
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_cita
  BEFORE INSERT OR UPDATE OF medico_id, especialidad_id, fecha, hora_inicio, hora_fin ON cita
  FOR EACH ROW WHEN (NEW.estado <> 'Cancelada')
  EXECUTE FUNCTION fn_validar_cita();

-- Controla las transiciones de estado válidas (RF10)
CREATE OR REPLACE FUNCTION fn_validar_transicion() RETURNS trigger AS $$
BEGIN
  IF NEW.estado = OLD.estado THEN RETURN NEW; END IF;
  IF NOT ((OLD.estado = 'Programada'
           AND NEW.estado IN ('Confirmada','Atendida','Cancelada','Inasistencia'))
       OR (OLD.estado = 'Confirmada'
           AND NEW.estado IN ('Atendida','Cancelada','Inasistencia'))) THEN
    RAISE EXCEPTION 'Transición de estado no permitida: % -> %', OLD.estado, NEW.estado;
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_transicion
  BEFORE UPDATE OF estado ON cita
  FOR EACH ROW EXECUTE FUNCTION fn_validar_transicion();

-- Interpreta cada resultado según el rango de referencia de la edad del paciente (RN16)
CREATE OR REPLACE FUNCTION fn_interpretar_resultado() RETURNS trigger AS $$
DECLARE
  v_edad INT;
  v_min  NUMERIC(8,2);
  v_max  NUMERIC(8,2);
BEGIN
  SELECT fn_edad(p.fecha_nacimiento, e.fecha_muestra) INTO v_edad
  FROM examen_laboratorio e JOIN paciente p ON p.id = e.paciente_id
  WHERE e.id = NEW.examen_id;

  IF v_edad IS NULL THEN
    RAISE EXCEPTION 'El examen aún no tiene fecha de muestra; la recepción debe completarla primero.';
  END IF;

  SELECT r.valor_minimo, r.valor_maximo INTO v_min, v_max
  FROM rango_referencia r
  WHERE r.parametro_id = NEW.parametro_id AND v_edad BETWEEN r.edad_minima AND r.edad_maxima;

  IF v_min IS NULL THEN
    RAISE EXCEPTION 'No existe rango de referencia del parámetro para % años.', v_edad;
  END IF;
  NEW.valor_minimo_ref := v_min;
  NEW.valor_maximo_ref := v_max;
  NEW.interpretacion := CASE WHEN NEW.valor < v_min THEN 'Bajo'
                             WHEN NEW.valor > v_max THEN 'Alto'
                             ELSE 'Normal' END;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_interpretar_resultado
  BEFORE INSERT OR UPDATE OF valor, parametro_id ON resultado_laboratorio
  FOR EACH ROW EXECUTE FUNCTION fn_interpretar_resultado();

-- Aplica la cobertura del seguro solo si está vigente en la fecha de la cita (RN19)
CREATE OR REPLACE FUNCTION fn_aplicar_cobertura() RETURNS trigger AS $$
DECLARE
  v_cita cita%ROWTYPE;
  v_seg  seguro_paciente%ROWTYPE;
BEGIN
  SELECT * INTO v_cita FROM cita WHERE id = NEW.cita_id;
  IF NEW.seguro_id IS NULL THEN
    NEW.porcentaje_cobertura := 0;
    RETURN NEW;
  END IF;
  SELECT * INTO v_seg FROM seguro_paciente WHERE id = NEW.seguro_id;
  IF v_seg.paciente_id <> v_cita.paciente_id THEN
    RAISE EXCEPTION 'El seguro no pertenece al paciente de la cita.';
  END IF;
  IF NOT v_seg.estado OR v_cita.fecha NOT BETWEEN v_seg.fecha_inicio AND v_seg.fecha_fin THEN
    RAISE EXCEPTION 'El seguro no está vigente en la fecha de la cita (RN19).';
  END IF;
  NEW.porcentaje_cobertura := v_seg.porcentaje_cobertura;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_aplicar_cobertura
  BEFORE INSERT OR UPDATE OF seguro_id, cita_id ON pago
  FOR EACH ROW EXECUTE FUNCTION fn_aplicar_cobertura();

-- Actualiza la fecha de modificación de la historia clínica
CREATE OR REPLACE FUNCTION fn_tocar_historia() RETURNS trigger AS $$
BEGIN
  UPDATE historia_clinica SET fecha_actualizacion = NOW() WHERE id = NEW.historia_clinica_id;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_tocar_historia
  AFTER INSERT ON antecedente
  FOR EACH ROW EXECUTE FUNCTION fn_tocar_historia();

-- Limita a 3 las solicitudes de recuperación de contraseña por usuario en la última hora (RN22)
CREATE OR REPLACE FUNCTION fn_limitar_recuperaciones() RETURNS trigger AS $$
BEGIN
  IF (SELECT COUNT(*) FROM recuperacion_clave
      WHERE usuario_id = NEW.usuario_id
        AND fecha_solicitud > NOW() - INTERVAL '1 hour') >= 3 THEN
    RAISE EXCEPTION 'Se superó el límite de 3 solicitudes de recuperación por hora (RN22).';
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_limitar_recuperaciones
  BEFORE INSERT ON recuperacion_clave
  FOR EACH ROW EXECUTE FUNCTION fn_limitar_recuperaciones();
