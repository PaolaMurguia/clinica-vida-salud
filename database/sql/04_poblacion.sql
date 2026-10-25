-- =====================================================================
-- Población de datos de prueba
-- Las fechas se calculan a partir del día en que se ejecuta el script:
-- "pasada" es el lunes de la semana anterior y "proxima" el lunes de la semana siguiente.
-- =====================================================================
CREATE TEMP TABLE semana AS
SELECT (date_trunc('week', CURRENT_DATE)::date - 7) AS pasada,
       (date_trunc('week', CURRENT_DATE)::date + 7) AS proxima;

-- Datos para tabla: rol (4 registros)
INSERT INTO rol (nombre, descripcion) VALUES
 ('Administrador', 'Configura y supervisa el sistema'),
 ('Recepcionista', 'Gestiona pacientes, citas, pagos y resultados'),
 ('Médico',        'Atiende pacientes y registra la historia clínica'),
 ('Paciente',      'Reserva y sigue sus citas desde el portal');

-- Datos para tabla: especialidad (6 registros; costo en bolivianos y rango de edad atendido)
INSERT INTO especialidad (nombre, descripcion, costo_consulta, edad_minima, edad_maxima) VALUES
 ('Medicina general', 'Atención integral de jóvenes y adultos', 150, 15, 59),
 ('Pediatría',        'Atención de niños menores de 15 años',    150, 0, 14),
 ('Geriatría',        'Atención del adulto mayor',               180, 60, 120),
 ('Ginecología',      'Salud de la mujer',                       200, NULL, NULL),
 ('Odontología',      'Salud bucal',                             120, NULL, NULL),
 ('Cardiología',      'Enfermedades del corazón',                250, NULL, NULL);

-- Datos para tabla: medico (6 registros)
INSERT INTO medico (ci, nro_registro_prof, nombres, apellidos, telefono, correo) VALUES
 ('4001001','RP-1001', 'Juan',   'Pérez Rojas',      '70011111', 'jperez@vidasalud.com'),
 ('4001002','RP-1002', 'Laura',  'Mendoza Vaca',     '70022222', 'lmendoza@vidasalud.com'),
 ('4001003','RP-1003', 'Carlos', 'Rojas Suárez',     '70033333', 'crojas@vidasalud.com'),
 ('4001004','RP-1004', 'Ana',    'Suárez Justiniano','70044444', 'asuarez@vidasalud.com'),
 ('4001005','RP-1005', 'Jorge',  'Vaca Chávez',      '70055555', 'jvaca@vidasalud.com'),
 ('4001006','RP-1006', 'Elena',  'Justiniano Paz',   '70066666', 'ejustiniano@vidasalud.com');

-- Datos para tabla: medico_especialidad (7 registros)
INSERT INTO medico_especialidad (medico_id, especialidad_id)
SELECT m.id, e.id
FROM medico m JOIN (VALUES ('RP-1001','medicina general'), ('RP-1002','pediatría'),
                           ('RP-1003','odontología'),      ('RP-1004','ginecología'),
                           ('RP-1005','cardiología'),      ('RP-1005','medicina general'),
                           ('RP-1006','geriatría')) AS v(rp, esp)
     ON v.rp = m.nro_registro_prof
JOIN especialidad e ON e.nombre_normalizado = v.esp;

-- Datos para tabla: horario_atencion (13 registros; día 1 = lunes)
INSERT INTO horario_atencion (medico_id, dia_semana, hora_inicio, hora_fin, duracion_cita_min)
SELECT m.id, v.dia, v.hi::time, v.hf::time, v.dur
FROM medico m JOIN (VALUES
   ('RP-1001',1,'08:00','12:00',30), ('RP-1001',3,'14:00','18:00',30), ('RP-1001',5,'08:00','12:00',30),
   ('RP-1002',2,'09:00','13:00',30), ('RP-1002',4,'09:00','13:00',30),
   ('RP-1003',1,'14:00','18:00',30), ('RP-1003',3,'08:00','12:00',30),
   ('RP-1004',2,'14:00','18:00',30), ('RP-1004',4,'14:00','17:00',30),
   ('RP-1005',1,'08:00','11:00',20), ('RP-1005',5,'14:00','18:00',30),
   ('RP-1006',3,'08:00','12:00',30), ('RP-1006',6,'08:00','12:00',30)) AS v(rp,dia,hi,hf,dur)
  ON v.rp = m.nro_registro_prof;

-- Datos para tabla: paciente (10 registros)
INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion,
                      contacto_emerg_nombre, contacto_emerg_parent, contacto_emerg_tel, acepta_privacidad) VALUES
 ('1234567','María',   'López Vargas',    '1990-05-14','F','71111111','maria.lopez@correo.com','Av. Banzer 4to anillo','Jorge López','Hermano','71111112',TRUE),
 ('7654321','Carlos',  'Gómez Áñez',      '1985-11-02','M','72222222',NULL,'Barrio Equipetrol',NULL,NULL,NULL,TRUE),
 ('3456789','Rosa',    'Suárez Rivero',   '2015-03-20','F','73333333',NULL,'Plan 3000','Marta Rivero','Madre','73333334',TRUE),
 ('4567890','Luis',    'Fernández Cuéllar','1952-08-09','M','74444444','luis.fernandez@correo.com','Av. Grigotá 3er anillo','Ana Fernández','Hija','74444445',TRUE),
 ('5678901','Sofía',   'Ribera Montaño',  '2019-12-01','F','75555555',NULL,'Villa 1ro de Mayo','Paola Montaño','Madre','75555556',TRUE),
 ('6789012','Pedro',   'Antelo Saucedo',  '1978-02-27','M','76666666','pedro.antelo@correo.com','Av. Alemana',NULL,NULL,NULL,TRUE),
 ('7890123','Carmen',  'Durán Egüez',     '1960-06-15','F','77777777',NULL,'Barrio Hamacas','Luis Durán','Esposo','77777778',TRUE),
 ('8901234','Diego',   'Paz Mercado',     '2001-09-30','M','78888888','diego.paz@correo.com','Av. Busch',NULL,NULL,NULL,TRUE),
 ('9012345','Valeria', 'Chávez Melgar',   '1995-01-18','F','79999999','valeria.chavez@correo.com','Urb. Sevilla',NULL,NULL,NULL,TRUE),
 ('1122334','Jorge',   'Arteaga Roca',    '1949-04-03','M','70101010',NULL,'Barrio Urbarí','Rosa Arteaga','Hija','70101011',TRUE);

-- Datos para tabla: usuario (8 registros). Contraseña de prueba: Clinica#2026 (hash bcrypt con prefijo $2y$, igual que password_hash de PHP)
INSERT INTO usuario (nombre_usuario, correo, password_hash, rol_id, medico_id, paciente_id, puede_marcar_atendida)
SELECT v.u, v.c, '$2y$' || substr(crypt('Clinica#2026', gen_salt('bf', 10)), 5),
       r.id, m.id, p.id, v.atiende
FROM (VALUES
   ('admin',          'admin@vidasalud.com',          'Administrador', NULL,      NULL,      FALSE),
   ('recep1',         'recep1@vidasalud.com',         'Recepcionista', NULL,      NULL,      FALSE),
   ('recep2',         'recep2@vidasalud.com',         'Recepcionista', NULL,      NULL,      FALSE),
   ('dr.perez',       'jperez@vidasalud.com',         'Médico',        'RP-1001', NULL,      TRUE),
   ('dra.mendoza',    'lmendoza@vidasalud.com',       'Médico',        'RP-1002', NULL,      FALSE),
   ('dra.justiniano', 'ejustiniano@vidasalud.com',    'Médico',        'RP-1006', NULL,      TRUE),
   ('maria.lopez',    'maria.lopez@correo.com',       'Paciente',      NULL,      '1234567', FALSE),
   ('luis.fernandez', 'luis.fernandez@correo.com',    'Paciente',      NULL,      '4567890', FALSE)
 ) AS v(u, c, rol, rp, ci, atiende)
JOIN rol r           ON r.nombre = v.rol
LEFT JOIN medico m   ON m.nro_registro_prof = v.rp
LEFT JOIN paciente p ON p.ci = v.ci;

-- Contraseña temporal: la recepcionista recep2 debe cambiarla en su próximo ingreso (RN23)
UPDATE usuario SET debe_cambiar_clave = TRUE WHERE nombre_usuario = 'recep2';

-- Datos para tabla: recuperacion_clave (2 registros): uno ya usado y otro vencido (solo se guarda el hash SHA-256)
INSERT INTO recuperacion_clave (usuario_id, token_hash, fecha_solicitud, fecha_expira, fecha_uso, ip)
SELECT u.id, encode(digest(v.tk, 'sha256'), 'hex'), NOW() - v.hace, NOW() - v.hace + INTERVAL '60 minutes',
       CASE WHEN v.usado THEN NOW() - v.hace + INTERVAL '10 minutes' END, '192.168.1.40'
FROM (VALUES ('dr.perez',    'enlace-demo-1', INTERVAL '3 days', TRUE),
             ('maria.lopez', 'enlace-demo-2', INTERVAL '5 days', FALSE)) AS v(usr, tk, hace, usado)
JOIN usuario u ON u.nombre_usuario = v.usr;

-- Datos para tabla: seguro_paciente (3 registros; el de Carlos Gómez está vencido para probar la regla RN19)
INSERT INTO seguro_paciente (paciente_id, aseguradora, nro_poliza, tipo_cobertura, porcentaje_cobertura, fecha_inicio, fecha_fin)
SELECT p.id, v.aseg, v.pol, v.tipo, v.cob, CURRENT_DATE + v.ini, CURRENT_DATE + v.fin
FROM (VALUES ('1234567','Aseguradora Andina S.A.', 'AA-2026-0154', 'Ambulatoria', 70, -180, 185),
             ('4567890','Previsión Salud S.A.',    'PS-1987-2210', 'Integral',    50, -300, 65),
             ('7654321','Aseguradora Andina S.A.', 'AA-2025-0871', 'Ambulatoria', 60, -400, -35)) AS v(ci, aseg, pol, tipo, cob, ini, fin)
JOIN paciente p ON p.ci = v.ci;

-- Datos para tablas: historia_clinica (5 registros) y antecedente (8 registros)
INSERT INTO historia_clinica (paciente_id, tipo_sangre, observaciones)
SELECT p.id, v.ts, v.obs
FROM (VALUES ('1234567','O+', NULL),
             ('3456789','A+', 'Controles de crecimiento al día'),
             ('4567890','B+', 'Paciente hipertenso en control'),
             ('7890123','O+', NULL),
             ('1122334','AB+','Usa audífono')) AS v(ci, ts, obs)
JOIN paciente p ON p.ci = v.ci;

INSERT INTO antecedente (historia_clinica_id, tipo, descripcion, origen, usuario_id)
SELECT h.id, v.tipo, v.descr, v.origen, u.id
FROM (VALUES ('1234567','Alergia',            'Penicilina',                    'Paciente','maria.lopez'),
             ('3456789','Enfermedad cronica', 'Asma leve',                     'Médico',  'dra.mendoza'),
             ('4567890','Enfermedad cronica', 'Hipertensión arterial',         'Médico',  'dra.justiniano'),
             ('4567890','Medicamento',        'Losartán 50 mg cada 24 horas',  'Médico',  'dra.justiniano'),
             ('7890123','Enfermedad cronica', 'Diabetes tipo 2',               'Médico',  'dra.justiniano'),
             ('7890123','Medicamento',        'Metformina 850 mg',             'Médico',  'dra.justiniano'),
             ('1122334','Cirugia',            'Colecistectomía (2015)',        'Médico',  'dra.justiniano'),
             ('1122334','Alergia',            'Ibuprofeno',                    'Médico',  'dra.justiniano')) AS v(ci, tipo, descr, origen, usr)
JOIN paciente p         ON p.ci = v.ci
JOIN historia_clinica h ON h.paciente_id = p.id
JOIN usuario u          ON u.nombre_usuario = v.usr;

-- Datos para tabla: cita (14 registros): 8 citas pasadas en todos los estados y 6 futuras. Se desactiva solo durante la carga la validación de fechas.
ALTER TABLE cita DISABLE TRIGGER trg_validar_cita;
INSERT INTO cita (paciente_id, medico_id, especialidad_id, usuario_registro_id, fecha,
                  hora_inicio, hora_fin, motivo, estado, motivo_cancelacion)
SELECT p.id, m.id, e.id, u.id, (SELECT pasada FROM semana) + v.d, v.hi::time, v.hf::time,
       v.mot, v.est, v.mc
FROM (VALUES
   (1,'1234567','RP-1001','medicina general',0,'09:00','09:30','Control general',       'Atendida',     NULL),
   (2,'3456789','RP-1002','pediatría',       1,'09:00','09:30','Tos persistente',       'Atendida',     NULL),
   (3,'4567890','RP-1006','geriatría',       2,'08:00','08:30','Control de presión',    'Atendida',     NULL),
   (4,'7654321','RP-1003','odontología',     0,'14:00','14:30','Dolor de muela',        'Cancelada',    'El paciente viajó'),
   (5,'6789012','RP-1001','medicina general',0,'09:30','10:00','Dolor de espalda',      'Inasistencia', NULL),
   (6,'9012345','RP-1004','ginecología',     1,'14:00','14:30','Control anual',         'Atendida',     NULL),
   (7,'7890123','RP-1006','geriatría',       2,'08:30','09:00','Control de glucosa',    'Atendida',     NULL),
   (8,'8901234','RP-1005','cardiología',     4,'14:00','14:30','Palpitaciones',         'Atendida',     NULL)
 ) AS v(n, ci, rp, esp, d, hi, hf, mot, est, mc)
JOIN paciente p     ON p.ci = v.ci
JOIN medico m       ON m.nro_registro_prof = v.rp
JOIN especialidad e ON e.nombre_normalizado = v.esp
JOIN usuario u      ON u.nombre_usuario = 'recep1'
ORDER BY v.n;
ALTER TABLE cita ENABLE TRIGGER trg_validar_cita;

-- Citas futuras (pasan por todas las validaciones)
INSERT INTO cita (paciente_id, medico_id, especialidad_id, usuario_registro_id, fecha,
                  hora_inicio, hora_fin, motivo, modalidad, canal)
SELECT p.id, m.id, e.id, u.id, (SELECT proxima FROM semana) + v.d, v.hi::time, v.hf::time,
       v.mot, v.modal, v.canal
FROM (VALUES
   (1,'1234567','RP-1001','medicina general',0,'08:00','08:30','Revisión de análisis','Presencial','Web',      'maria.lopez'),
   (2,'5678901','RP-1002','pediatría',       1,'09:00','09:30','Fiebre',              'Presencial','Recepcion','recep1'),
   (3,'1122334','RP-1006','geriatría',       2,'09:00','09:30','Control general',     'Presencial','Recepcion','recep1'),
   (4,'7654321','RP-1005','medicina general',0,'08:00','08:20','Chequeo',             'Presencial','Recepcion','recep2'),
   (5,'8901234','RP-1003','odontología',     2,'08:00','08:30','Limpieza dental',     'Presencial','Recepcion','recep1'),
   (6,'9012345','RP-1004','ginecología',     3,'14:00','14:30','Resultados',          'Virtual',   'Recepcion','recep2')
 ) AS v(n, ci, rp, esp, d, hi, hf, mot, modal, canal, usr)
JOIN paciente p     ON p.ci = v.ci
JOIN medico m       ON m.nro_registro_prof = v.rp
JOIN especialidad e ON e.nombre_normalizado = v.esp
JOIN usuario u      ON u.nombre_usuario = v.usr
ORDER BY v.n;

-- Confirmación de una cita futura (transición válida Programada -> Confirmada)
UPDATE cita SET estado = 'Confirmada' WHERE codigo = 'CM-0010';

-- Datos para tabla: historial_estado_cita (23 registros)
INSERT INTO historial_estado_cita (cita_id, usuario_id, estado_anterior, estado_nuevo, fecha_cambio)
SELECT c.id, c.usuario_registro_id, NULL, 'Programada', c.fecha_creacion FROM cita c;

INSERT INTO historial_estado_cita (cita_id, usuario_id, estado_anterior, estado_nuevo, fecha_cambio, observacion)
SELECT c.id, u.id, 'Programada', c.estado, c.fecha + c.hora_fin, c.motivo_cancelacion
FROM cita c JOIN usuario u ON u.nombre_usuario = 'recep1'
WHERE c.estado IN ('Atendida','Cancelada','Inasistencia','Confirmada');

-- Datos para tabla: registro_atencion (6 registros)
INSERT INTO registro_atencion (cita_id, diagnostico, tratamiento, indicaciones, peso_kg, talla_cm,
                               presion_arterial, temperatura_c)
SELECT c.id, v.diag, v.trat, v.ind, v.peso, v.talla, v.pa, v.temp
FROM (VALUES
   ('CM-0001','Paciente sano','Ninguno','Control anual y análisis de sangre',62.5,160.0,'110/70',36.5),
   ('CM-0002','Bronquitis aguda','Salbutamol inhalado','Hemograma de control en una semana',36.0,142.0,NULL,37.8),
   ('CM-0003','Hipertensión controlada','Continuar losartán','Perfil metabólico y dieta baja en sal',78.0,168.0,'135/85',36.4),
   ('CM-0006','Control ginecológico normal','Ninguno','Control en 12 meses',58.0,162.0,'105/70',36.6),
   ('CM-0007','Diabetes tipo 2 compensada','Continuar metformina','Glucosa en ayunas en un mes',70.5,155.0,'125/80',36.5),
   ('CM-0008','Arritmia sinusal benigna','Ninguno','Evitar cafeína; electrocardiograma de control',74.0,178.0,'120/80',36.6)
 ) AS v(cod, diag, trat, ind, peso, talla, pa, temp)
JOIN cita c ON c.codigo = v.cod;

-- Datos para tablas: parametro_laboratorio (4 registros) y rango_referencia (10 registros)
INSERT INTO parametro_laboratorio (nombre, unidad) VALUES
 ('Hemoglobina', 'g/dL'), ('Glucosa en ayunas', 'mg/dL'),
 ('Colesterol total', 'mg/dL'), ('Leucocitos', 'x10^3/uL');

INSERT INTO rango_referencia (parametro_id, edad_minima, edad_maxima, valor_minimo, valor_maximo)
SELECT pl.id, v.emin, v.emax, v.vmin, v.vmax
FROM (VALUES ('Hemoglobina',        0, 11,  11.0,  14.5), ('Hemoglobina',       12, 17, 12.0, 16.0),
             ('Hemoglobina',       18, 59,  12.0,  17.0), ('Hemoglobina',       60,120, 11.5, 16.5),
             ('Glucosa en ayunas',  0, 17,  60.0, 100.0), ('Glucosa en ayunas', 18,120, 70.0,100.0),
             ('Colesterol total',   0, 17,   0.0, 170.0), ('Colesterol total',  18,120,  0.0,200.0),
             ('Leucocitos',         0, 11,   5.0,  14.5), ('Leucocitos',        12,120,  4.5, 11.0)
 ) AS v(par, emin, emax, vmin, vmax)
JOIN parametro_laboratorio pl ON pl.nombre = v.par;

-- Datos para tablas: examen_laboratorio (5 registros) y resultado_laboratorio (8 registros); la interpretación la calcula el trigger según la edad
INSERT INTO examen_laboratorio (paciente_id, medico_id, cita_id, tipo_examen, fecha_muestra, estado, usuario_registro_id, fecha_registro)
SELECT p.id, m.id, c.id, v.tipo, (SELECT pasada FROM semana) + v.d, v.est, u.id, (SELECT pasada FROM semana) + v.d + TIME '16:00'
FROM (VALUES (1,'3456789','RP-1002','CM-0002','Hemograma',           2,'Revisado'),
             (2,'4567890','RP-1006','CM-0003','Perfil metabólico',   3,'Revisado'),
             (3,'1234567','RP-1001','CM-0001','Hemograma',           1,'Revisado'),
             (4,'3456789','RP-1002', NULL,    'Hemograma de control', 7,'Registrado')
 ) AS v(n, ci, rp, cod, tipo, d, est)
JOIN paciente p     ON p.ci = v.ci
JOIN medico m       ON m.nro_registro_prof = v.rp
LEFT JOIN cita c    ON c.codigo = v.cod
JOIN usuario u      ON u.nombre_usuario = 'recep1'
ORDER BY v.n;

-- Examen solicitado por el médico que aún no tiene resultados (la recepción lo completará)
INSERT INTO examen_laboratorio (paciente_id, medico_id, cita_id, tipo_examen, estado)
SELECT p.id, m.id, c.id, 'Control de glucosa y colesterol', 'Solicitado'
FROM paciente p, medico m, cita c
WHERE p.ci = '4567890' AND m.nro_registro_prof = 'RP-1006' AND c.codigo = 'CM-0003';

INSERT INTO resultado_laboratorio (examen_id, parametro_id, valor, valor_minimo_ref, valor_maximo_ref, interpretacion)
SELECT ex.id, pl.id, v.valor, 0, 0, 'Normal'          -- el trigger reemplaza rango e interpretación
FROM (VALUES ('EX-00001','Hemoglobina',         9.8), ('EX-00001','Leucocitos',         12.0),
             ('EX-00002','Glucosa en ayunas', 128.0), ('EX-00002','Colesterol total',  215.0),
             ('EX-00002','Hemoglobina',        13.2), ('EX-00003','Hemoglobina',        12.6),
             ('EX-00003','Glucosa en ayunas',  92.0), ('EX-00004','Hemoglobina',        11.4)
 ) AS v(cod, par, valor)
JOIN examen_laboratorio ex    ON ex.codigo = v.cod
JOIN parametro_laboratorio pl ON pl.nombre = v.par;

-- Datos para tabla: pago (6 registros); la cobertura la aplica el trigger si el seguro está vigente
INSERT INTO pago (cita_id, seguro_id, usuario_id, monto_consulta, metodo_pago, estado, fecha_pago)
SELECT c.id, s.id, u.id, e.costo_consulta, v.met, v.est,
       CASE WHEN v.est = 'Pagado' THEN c.fecha + c.hora_fin END
FROM (VALUES ('CM-0001','QR','Pagado'), ('CM-0002','Efectivo','Pagado'),
             ('CM-0003','QR','Pagado'), ('CM-0006','Efectivo','Pagado'),
             ('CM-0007','Efectivo','Pagado'), ('CM-0008',NULL,'Pendiente')) AS v(cod, met, est)
JOIN cita c              ON c.codigo = v.cod
JOIN especialidad e      ON e.id = c.especialidad_id
LEFT JOIN seguro_paciente s ON s.paciente_id = c.paciente_id AND s.estado
                           AND c.fecha BETWEEN s.fecha_inicio AND s.fecha_fin
JOIN usuario u           ON u.nombre_usuario = 'recep1'
ORDER BY c.codigo;

-- Datos para tabla: notificacion (5 registros)
INSERT INTO notificacion (usuario_id, cita_id, canal, tipo, mensaje, fecha_programada)
SELECT u.id, c.id, v.canal, 'Recordatorio',
       'Recuerde su cita ' || c.codigo || ' el ' || to_char(c.fecha, 'DD/MM/YYYY') ||
       ' a las ' || to_char(c.hora_inicio, 'HH24:MI'),
       (c.fecha + c.hora_inicio) - INTERVAL '24 hours'
FROM cita c JOIN usuario u ON u.paciente_id = c.paciente_id
CROSS JOIN (VALUES ('Correo'), ('Sistema')) AS v(canal)
WHERE c.estado IN ('Programada','Confirmada');

INSERT INTO notificacion (usuario_id, cita_id, canal, tipo, mensaje, fecha_programada)
SELECT u.id, NULL, 'Correo', 'Agenda',
       'Tiene ' || COUNT(c.id) || ' cita(s) programada(s) para el ' || to_char(c.fecha, 'DD/MM/YYYY'),
       (c.fecha - 1) + TIME '18:00'
FROM cita c JOIN usuario u ON u.medico_id = c.medico_id
WHERE c.estado IN ('Programada','Confirmada')
GROUP BY u.id, c.fecha;

-- Datos para tabla: bloqueo_agenda (3 registros)
INSERT INTO bloqueo_agenda (medico_id, fecha, motivo)
SELECT NULL::int, make_date(EXTRACT(YEAR FROM CURRENT_DATE)::int, 11, 2), 'Día de Todos Santos'
UNION ALL
SELECT NULL, make_date(EXTRACT(YEAR FROM CURRENT_DATE)::int, 12, 25), 'Navidad'
UNION ALL
SELECT m.id, (SELECT proxima FROM semana) + 14, 'Capacitación del médico'
FROM medico m WHERE m.nro_registro_prof = 'RP-1003';

-- Datos para tabla: bitacora (6 registros)
INSERT INTO bitacora (usuario_id, tipo, accion, modulo, registro_id, descripcion, ip, navegador)
SELECT u.id, v.tipo, v.acc, v.tab, NULL, v.descr, '192.168.1.' || v.ip, 'Chrome 130 / Windows 11'
FROM (VALUES ('admin',  'Acceso','LOGIN',          NULL,      'Inicio de sesión correcto', 10),
             ('recep1', 'Acceso','LOGIN',          NULL,      'Inicio de sesión correcto', 21),
             ('recep1', 'Accion','CREAR',          'Pacientes','Registro del paciente P-0010', 21),
             ('recep1', 'Accion','CREAR',          'Citas',    'Registro de la cita CM-0011', 21),
             ('recep2', 'Acceso','LOGIN_FALLIDO',  NULL,      'Contraseña incorrecta', 22),
             ('dr.perez','Accion','DICTADO_VOZ',   'Historia clínica','Diagnóstico dictado por voz', 31)) AS v(usr, tipo, acc, tab, descr, ip)
JOIN usuario u ON u.nombre_usuario = v.usr;
