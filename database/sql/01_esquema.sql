-- =====================================================================
-- Base de datos: clinica_vida_salud           (PostgreSQL 15 o superior)
-- Sistema web de gestión de citas médicas - Clínica Vida Salud
-- =====================================================================
CREATE EXTENSION IF NOT EXISTS btree_gist;   -- restricciones EXCLUDE con rangos
CREATE EXTENSION IF NOT EXISTS pgcrypto;     -- hash bcrypt en la población inicial
CREATE EXTENSION IF NOT EXISTS unaccent;     -- búsquedas sin distinguir tildes

CREATE SEQUENCE seq_paciente_codigo;
CREATE SEQUENCE seq_medico_codigo;
CREATE SEQUENCE seq_cita_codigo;
CREATE SEQUENCE seq_pago_comprobante;
CREATE SEQUENCE seq_examen_codigo;

-- ---------------------------------------------------------------------
-- Seguridad y acceso
-- ---------------------------------------------------------------------
CREATE TABLE rol (
  id          SERIAL PRIMARY KEY,
  nombre      VARCHAR(30) NOT NULL UNIQUE,
  descripcion VARCHAR(120)
);

-- ---------------------------------------------------------------------
-- Catálogos y agenda médica
-- ---------------------------------------------------------------------
CREATE TABLE especialidad (
  id                 SERIAL PRIMARY KEY,
  nombre             VARCHAR(60)  NOT NULL,
  nombre_normalizado VARCHAR(60)  NOT NULL UNIQUE,      -- lo completa un trigger
  descripcion        VARCHAR(150),
  costo_consulta     NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (costo_consulta >= 0),
  edad_minima        SMALLINT CHECK (edad_minima BETWEEN 0 AND 120),
  edad_maxima        SMALLINT CHECK (edad_maxima BETWEEN 0 AND 120),
  estado             BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT ck_especialidad_edades
    CHECK (edad_minima IS NULL OR edad_maxima IS NULL OR edad_minima <= edad_maxima)
);

CREATE TABLE medico (
  id                SERIAL PRIMARY KEY,
  codigo            VARCHAR(12) NOT NULL UNIQUE
                    DEFAULT ('M-' || lpad(nextval('seq_medico_codigo')::text, 4, '0')),
  ci                VARCHAR(20) NOT NULL UNIQUE,
  nro_registro_prof VARCHAR(30) NOT NULL UNIQUE,
  nombres           VARCHAR(80) NOT NULL,
  apellidos         VARCHAR(80) NOT NULL,
  telefono          VARCHAR(20),
  correo            VARCHAR(120),
  estado            BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE medico_especialidad (
  medico_id       INT NOT NULL REFERENCES medico(id),
  especialidad_id INT NOT NULL REFERENCES especialidad(id),
  PRIMARY KEY (medico_id, especialidad_id)
);

CREATE TABLE horario_atencion (
  id                SERIAL PRIMARY KEY,
  medico_id         INT NOT NULL REFERENCES medico(id),
  dia_semana        SMALLINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 7),  -- 1 = lunes
  hora_inicio       TIME NOT NULL,
  hora_fin          TIME NOT NULL,
  duracion_cita_min SMALLINT NOT NULL DEFAULT 30 CHECK (duracion_cita_min BETWEEN 10 AND 120),
  estado            BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT ck_horario_horas CHECK (hora_fin > hora_inicio),
  -- el rango debe dividirse en fichas completas
  CONSTRAINT ck_horario_fichas
    CHECK ((EXTRACT(EPOCH FROM (hora_fin - hora_inicio))::int / 60) % duracion_cita_min = 0),
  -- un médico no puede tener dos horarios superpuestos el mismo día
  CONSTRAINT ex_horario_no_solapado EXCLUDE USING gist (
    medico_id WITH =, dia_semana WITH =,
    tsrange(DATE '2000-01-01' + hora_inicio, DATE '2000-01-01' + hora_fin) WITH &&
  ) WHERE (estado)
);

CREATE TABLE bloqueo_agenda (
  id        SERIAL PRIMARY KEY,
  medico_id INT REFERENCES medico(id),                  -- NULL = toda la clínica
  fecha     DATE NOT NULL,
  motivo    VARCHAR(120) NOT NULL,
  CONSTRAINT uq_bloqueo UNIQUE NULLS NOT DISTINCT (medico_id, fecha)
);

-- ---------------------------------------------------------------------
-- Pacientes
-- ---------------------------------------------------------------------
CREATE TABLE paciente (
  id                    SERIAL PRIMARY KEY,
  codigo                VARCHAR(12) NOT NULL UNIQUE
                        DEFAULT ('P-' || lpad(nextval('seq_paciente_codigo')::text, 4, '0')),
  ci                    VARCHAR(20) UNIQUE,               -- único cuando se registra
  nombres               VARCHAR(80) NOT NULL,
  apellidos             VARCHAR(80) NOT NULL,
  fecha_nacimiento      DATE NOT NULL CHECK (fecha_nacimiento <= CURRENT_DATE),
  sexo                  CHAR(1) CHECK (sexo IN ('M','F')),
  telefono              VARCHAR(20) NOT NULL,
  correo                VARCHAR(120),
  direccion             VARCHAR(150),
  contacto_emerg_nombre VARCHAR(100),
  contacto_emerg_parent VARCHAR(40),
  contacto_emerg_tel    VARCHAR(20),
  notif_correo          BOOLEAN NOT NULL DEFAULT TRUE,     -- preferencias de notificación
  notif_alerta          BOOLEAN NOT NULL DEFAULT TRUE,     -- alertas dentro del sistema
  acepta_privacidad     BOOLEAN NOT NULL DEFAULT FALSE,    -- consentimiento de datos
  estado                BOOLEAN NOT NULL DEFAULT TRUE,
  fecha_registro        TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE usuario (
  id                    SERIAL PRIMARY KEY,
  nombre_usuario        VARCHAR(40)  NOT NULL UNIQUE,
  correo                VARCHAR(120) NOT NULL UNIQUE,
  password_hash         VARCHAR(255) NOT NULL,             -- password_hash() de PHP (bcrypt)
  debe_cambiar_clave    BOOLEAN  NOT NULL DEFAULT FALSE,   -- contraseña temporal: se exige cambiarla al ingresar
  rol_id                INT NOT NULL REFERENCES rol(id),
  medico_id             INT UNIQUE REFERENCES medico(id),
  paciente_id           INT UNIQUE REFERENCES paciente(id),
  puede_marcar_atendida BOOLEAN  NOT NULL DEFAULT FALSE,
  intentos_fallidos     SMALLINT NOT NULL DEFAULT 0,
  nivel_bloqueo         SMALLINT NOT NULL DEFAULT 0,       -- 0 ninguno, 1 = 5 min, 2 = 15 min, 3 = 60 min
  bloqueado_hasta       TIMESTAMP,
  ultimo_acceso         TIMESTAMP,
  estado                BOOLEAN  NOT NULL DEFAULT TRUE,
  fecha_creacion        TIMESTAMP NOT NULL DEFAULT NOW(),
  CONSTRAINT ck_usuario_vinculo CHECK (medico_id IS NULL OR paciente_id IS NULL)
);

-- Enlaces de recuperación de contraseña (CU27): solo se guarda el hash del enlace
CREATE TABLE recuperacion_clave (
  id              SERIAL PRIMARY KEY,
  usuario_id      INT NOT NULL REFERENCES usuario(id),
  token_hash      CHAR(64) NOT NULL UNIQUE,                -- SHA-256 del enlace; el enlace nunca se guarda
  fecha_solicitud TIMESTAMP NOT NULL DEFAULT NOW(),
  fecha_expira    TIMESTAMP NOT NULL,                      -- 60 minutos después de la solicitud
  fecha_uso       TIMESTAMP,                               -- nulo mientras no se use (un solo uso)
  ip              VARCHAR(45),
  CONSTRAINT ck_recuperacion_vigencia CHECK (fecha_expira > fecha_solicitud),
  CONSTRAINT ck_recuperacion_uso CHECK (fecha_uso IS NULL OR fecha_uso >= fecha_solicitud)
);
CREATE INDEX idx_recuperacion_usuario ON recuperacion_clave (usuario_id, fecha_solicitud);

CREATE TABLE seguro_paciente (
  id                   SERIAL PRIMARY KEY,
  paciente_id          INT NOT NULL REFERENCES paciente(id),
  aseguradora          VARCHAR(80) NOT NULL,
  nro_poliza           VARCHAR(40) NOT NULL,
  tipo_cobertura       VARCHAR(40) NOT NULL,
  porcentaje_cobertura NUMERIC(5,2) NOT NULL CHECK (porcentaje_cobertura BETWEEN 0 AND 100),
  fecha_inicio         DATE NOT NULL,
  fecha_fin            DATE NOT NULL,
  estado               BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT ck_seguro_vigencia CHECK (fecha_fin >= fecha_inicio),
  CONSTRAINT uq_seguro_poliza UNIQUE (aseguradora, nro_poliza)
);
-- un paciente solo puede tener un seguro activo a la vez
CREATE UNIQUE INDEX ux_seguro_activo ON seguro_paciente (paciente_id) WHERE estado;

-- ---------------------------------------------------------------------
-- Citas
-- ---------------------------------------------------------------------
CREATE TABLE cita (
  id                  SERIAL PRIMARY KEY,
  codigo              VARCHAR(12) NOT NULL UNIQUE
                      DEFAULT ('CM-' || lpad(nextval('seq_cita_codigo')::text, 4, '0')),
  paciente_id         INT NOT NULL REFERENCES paciente(id),
  medico_id           INT NOT NULL REFERENCES medico(id),
  especialidad_id     INT NOT NULL REFERENCES especialidad(id),
  usuario_registro_id INT NOT NULL REFERENCES usuario(id),
  fecha               DATE NOT NULL,
  hora_inicio         TIME NOT NULL,
  hora_fin            TIME NOT NULL,
  modalidad           VARCHAR(10) NOT NULL DEFAULT 'Presencial'
                      CHECK (modalidad IN ('Presencial','Virtual')),
  canal               VARCHAR(10) NOT NULL DEFAULT 'Recepcion'
                      CHECK (canal IN ('Recepcion','Web')),
  motivo              VARCHAR(200),
  estado              VARCHAR(15) NOT NULL DEFAULT 'Programada'
                      CHECK (estado IN ('Programada','Confirmada','Atendida',
                                        'Cancelada','Inasistencia')),
  motivo_cancelacion  VARCHAR(200),
  fecha_creacion      TIMESTAMP NOT NULL DEFAULT NOW(),
  CONSTRAINT ck_cita_horas CHECK (hora_fin > hora_inicio),
  -- RN07 y RN09: un médico no puede tener citas superpuestas; las canceladas no cuentan
  CONSTRAINT ex_cita_no_solapada EXCLUDE USING gist (
    medico_id WITH =,
    tsrange(fecha + hora_inicio, fecha + hora_fin, '[)') WITH &&
  ) WHERE (estado <> 'Cancelada')
);

CREATE TABLE historial_estado_cita (
  id              SERIAL PRIMARY KEY,
  cita_id         INT NOT NULL REFERENCES cita(id),
  usuario_id      INT NOT NULL REFERENCES usuario(id),
  estado_anterior VARCHAR(15),
  estado_nuevo    VARCHAR(15) NOT NULL,
  fecha_cambio    TIMESTAMP NOT NULL DEFAULT NOW(),
  observacion     VARCHAR(200)
);

CREATE TABLE pago (
  id                   SERIAL PRIMARY KEY,
  nro_comprobante      VARCHAR(12) NOT NULL UNIQUE
                       DEFAULT ('PG-' || lpad(nextval('seq_pago_comprobante')::text, 5, '0')),
  cita_id              INT NOT NULL UNIQUE REFERENCES cita(id),
  seguro_id            INT REFERENCES seguro_paciente(id),
  usuario_id           INT NOT NULL REFERENCES usuario(id),
  monto_consulta       NUMERIC(10,2) NOT NULL CHECK (monto_consulta >= 0),
  porcentaje_cobertura NUMERIC(5,2)  NOT NULL DEFAULT 0
                       CHECK (porcentaje_cobertura BETWEEN 0 AND 100),
  monto_paciente       NUMERIC(10,2) GENERATED ALWAYS AS
                       (round(monto_consulta * (100 - porcentaje_cobertura) / 100, 2)) STORED,
  metodo_pago          VARCHAR(10) CHECK (metodo_pago IN ('Efectivo','QR')),
  estado               VARCHAR(10) NOT NULL DEFAULT 'Pendiente'
                       CHECK (estado IN ('Pendiente','Pagado')),
  fecha_pago           TIMESTAMP,
  fecha_registro       TIMESTAMP NOT NULL DEFAULT NOW(),
  CONSTRAINT ck_pago_completo
    CHECK (estado <> 'Pagado' OR (metodo_pago IS NOT NULL AND fecha_pago IS NOT NULL))
);

-- ---------------------------------------------------------------------
-- Atención clínica
-- ---------------------------------------------------------------------
CREATE TABLE historia_clinica (
  id                  SERIAL PRIMARY KEY,
  paciente_id         INT NOT NULL UNIQUE REFERENCES paciente(id),
  tipo_sangre         VARCHAR(3) CHECK (tipo_sangre IN ('O+','O-','A+','A-','B+','B-','AB+','AB-')),
  observaciones       VARCHAR(500),
  fecha_creacion      TIMESTAMP NOT NULL DEFAULT NOW(),
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE antecedente (
  id                  SERIAL PRIMARY KEY,
  historia_clinica_id INT NOT NULL REFERENCES historia_clinica(id),
  tipo                VARCHAR(20) NOT NULL
                      CHECK (tipo IN ('Alergia','Enfermedad cronica','Medicamento',
                                      'Cirugia','Familiar','Otro')),
  descripcion         VARCHAR(200) NOT NULL,
  origen              VARCHAR(10)  NOT NULL CHECK (origen IN ('Paciente','Médico')),
  usuario_id          INT NOT NULL REFERENCES usuario(id),
  fecha_registro      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE registro_atencion (
  id               SERIAL PRIMARY KEY,
  cita_id          INT NOT NULL UNIQUE REFERENCES cita(id),
  diagnostico      VARCHAR(300) NOT NULL,
  tratamiento      VARCHAR(500),
  indicaciones     VARCHAR(500),
  peso_kg          NUMERIC(5,2) CHECK (peso_kg > 0),
  talla_cm         NUMERIC(5,1) CHECK (talla_cm > 0),
  presion_arterial VARCHAR(7),                              -- por ejemplo 120/80
  temperatura_c    NUMERIC(4,1) CHECK (temperatura_c BETWEEN 30 AND 45),
  dictado_voz      BOOLEAN NOT NULL DEFAULT FALSE,
  fecha_registro   TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE parametro_laboratorio (
  id     SERIAL PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL UNIQUE,
  unidad VARCHAR(20) NOT NULL,
  estado BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE rango_referencia (
  id           SERIAL PRIMARY KEY,
  parametro_id INT NOT NULL REFERENCES parametro_laboratorio(id),
  edad_minima  SMALLINT NOT NULL CHECK (edad_minima BETWEEN 0 AND 120),
  edad_maxima  SMALLINT NOT NULL CHECK (edad_maxima BETWEEN 0 AND 120),
  valor_minimo NUMERIC(8,2) NOT NULL,
  valor_maximo NUMERIC(8,2) NOT NULL,
  CONSTRAINT ck_rango_edades  CHECK (edad_minima <= edad_maxima),
  CONSTRAINT ck_rango_valores CHECK (valor_minimo < valor_maximo),
  -- los rangos de edad de un mismo parámetro no pueden superponerse
  CONSTRAINT ex_rango_no_solapado EXCLUDE USING gist (
    parametro_id WITH =, int4range(edad_minima, edad_maxima, '[]') WITH &&
  )
);

CREATE TABLE examen_laboratorio (
  id                  SERIAL PRIMARY KEY,
  codigo              VARCHAR(12) NOT NULL UNIQUE
                      DEFAULT ('EX-' || lpad(nextval('seq_examen_codigo')::text, 5, '0')),
  paciente_id         INT NOT NULL REFERENCES paciente(id),
  medico_id           INT NOT NULL REFERENCES medico(id),   -- médico solicitante
  cita_id             INT REFERENCES cita(id),
  tipo_examen         VARCHAR(60) NOT NULL,
  fecha_solicitud     TIMESTAMP NOT NULL DEFAULT NOW(),
  fecha_muestra       DATE,                                -- la completa la recepción al registrar
  observacion         VARCHAR(200),
  estado              VARCHAR(10) NOT NULL DEFAULT 'Solicitado'
                      CHECK (estado IN ('Solicitado','Registrado','Revisado')),
  usuario_registro_id INT REFERENCES usuario(id),          -- recepcionista que registró los valores
  fecha_registro      TIMESTAMP,
  CONSTRAINT ck_examen_registrado
    CHECK (estado = 'Solicitado' OR (fecha_muestra IS NOT NULL AND usuario_registro_id IS NOT NULL))
);

CREATE TABLE resultado_laboratorio (
  id               SERIAL PRIMARY KEY,
  examen_id        INT NOT NULL REFERENCES examen_laboratorio(id),
  parametro_id     INT NOT NULL REFERENCES parametro_laboratorio(id),
  valor            NUMERIC(8,2) NOT NULL,
  valor_minimo_ref NUMERIC(8,2) NOT NULL,                 -- copia del rango aplicado (lo fija un trigger)
  valor_maximo_ref NUMERIC(8,2) NOT NULL,
  interpretacion   VARCHAR(6)   NOT NULL CHECK (interpretacion IN ('Bajo','Normal','Alto')),
  CONSTRAINT uq_resultado UNIQUE (examen_id, parametro_id)
);

-- ---------------------------------------------------------------------
-- Notificaciones y bitácora
-- ---------------------------------------------------------------------
CREATE TABLE notificacion (
  id               SERIAL PRIMARY KEY,
  usuario_id       INT NOT NULL REFERENCES usuario(id),      -- destinatario
  cita_id          INT REFERENCES cita(id),
  canal            VARCHAR(7)  NOT NULL CHECK (canal IN ('Correo','Sistema')),
  tipo             VARCHAR(15) NOT NULL
                   CHECK (tipo IN ('Recordatorio','Confirmacion','Reprogramacion',
                                   'Cancelacion','Resultado','Agenda')),
  mensaje          VARCHAR(300) NOT NULL,
  fecha_programada TIMESTAMP NOT NULL,
  fecha_envio      TIMESTAMP,
  estado           VARCHAR(10) NOT NULL DEFAULT 'Pendiente'
                   CHECK (estado IN ('Pendiente','Enviado','Fallido','Anulado')),
  intentos         SMALLINT NOT NULL DEFAULT 0
);
-- evita enviar dos veces el mismo recordatorio de una cita por el mismo canal
CREATE UNIQUE INDEX ux_recordatorio ON notificacion (cita_id, usuario_id, canal, fecha_programada)
  WHERE tipo = 'Recordatorio' AND estado <> 'Anulado';

CREATE TABLE bitacora (
  id             BIGSERIAL PRIMARY KEY,
  usuario_id     INT REFERENCES usuario(id),                -- NULL si el usuario no se identificó
  tipo           VARCHAR(10) NOT NULL CHECK (tipo IN ('Acceso','Accion')),
  accion         VARCHAR(40) NOT NULL,
  modulo         VARCHAR(40),
  registro_id    INT,
  descripcion    VARCHAR(300),
  ip             VARCHAR(45),
  navegador      VARCHAR(200),
  fecha_hora     TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------
-- Índices
-- ---------------------------------------------------------------------
CREATE INDEX idx_cita_medico_fecha  ON cita (medico_id, fecha);
CREATE INDEX idx_cita_paciente      ON cita (paciente_id);
CREATE INDEX idx_cita_estado        ON cita (estado);
CREATE INDEX idx_paciente_nombre    ON paciente (lower(apellidos), lower(nombres));
CREATE INDEX idx_examen_paciente    ON examen_laboratorio (paciente_id, fecha_muestra);
CREATE INDEX idx_notif_pendiente    ON notificacion (estado, fecha_programada);
CREATE INDEX idx_bitacora_fecha     ON bitacora (fecha_hora);
CREATE INDEX idx_bitacora_usuario   ON bitacora (usuario_id);
