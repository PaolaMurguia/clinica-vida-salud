# Mensajes para Claude Code, en orden

Copia cada mensaje cuando el anterior esté terminado y revisado. Al inicio de cada sesión nueva, Claude Code lee CLAUDE.md solo.

## 0. Entender el proyecto (usa el modo plan: Shift+Tab dos veces)
Lee CLAUDE.md, docs/casos_de_uso_ciclo1.md y los archivos de database/sql. Todavía no escribas código. Resume en diez líneas lo que entendiste, dime qué dudas o contradicciones encuentras entre los documentos y propón un plan por pasos para el ciclo 1. Espera mi aprobación.

## 1. Proyecto base
Crea el proyecto Laravel 12 en la carpeta actual sin borrar CLAUDE.md, docs ni database/sql. Configura el .env para PostgreSQL (base clinica_vida_salud, usuario y clave que te daré), verifica que pdo_pgsql funcione, instala Bootstrap 5 con Vite, crea el layout base en español con menú según el rol y una página de inicio. Crea .env.example y haz el primer commit.

## 2. Base de datos completa
Crea las migraciones que ejecutan exactamente los archivos 01_esquema, 02_funciones y 03_procedimientos de database/sql (extensiones incluidas) y los seeders que ejecutan 04_poblacion. Crea los 22 modelos Eloquent con sus relaciones, con los nombres y métodos de docs/casos_de_uso_ciclo1.md (sin timestamps, porque las tablas no los tienen). Ejecuta php artisan migrate:fresh --seed y comprueba con consultas que existen las 22 tablas, los triggers y los datos. Haz commit.

## 3. Acceso a la cuenta: CU01, CU02, CU26, CU27
Implementa iniciar sesión (con bloqueo temporal progresivo y regeneración de la sesión), cerrar sesión, cambiar contraseña (obligatorio cuando es temporal) y recuperar contraseña con enlace de un solo uso por correo (guarda solo el hash, vence a los 60 minutos, máximo 3 solicitudes por hora, mismo mensaje exista o no la cuenta). Crea el middleware de rol y el servicio de bitácora. Sigue paso a paso los flujos y las excepciones de cada caso de uso. Escribe pruebas. Haz un commit por caso de uso.

## 4. Administración: CU03, CU04, CU05, CU06, CU07
Implementa gestionar usuarios y roles (incluye restablecer contraseña temporal y desbloquear), consultar la bitácora con filtros, gestionar especialidades (costo y rango de edad), gestionar médicos (con sus especialidades) y definir horarios de atención (con las franjas que se generan). Respeta las excepciones de cada caso de uso, por ejemplo no desactivar al único administrador. Pruebas y un commit por caso de uso.

## 5. Pacientes: CU08, CU09, CU10, CU11, CU12
Implementa gestionar pacientes (la recepcionista), buscar paciente (sin distinguir tildes), registrarse como paciente desde el portal (vincula la cuenta con la ficha existente solo si coinciden fecha de nacimiento y apellidos), gestionar el perfil del paciente con sus antecedentes declarados y registrar el seguro del paciente. Pruebas y un commit por caso de uso.

## 6. Disponibilidad: CU13
Implementa consultar disponibilidad: especialidades según la edad del paciente, médicos de la especialidad y franjas libres de una fecha (usa la función fn_franjas_disponibles). El paciente no debe ver datos de otros pacientes. Pruebas y commit.

## 7. Pruebas y revisión contra la docente
Escribe las pruebas automáticas de las pruebas mínimas del enunciado que corresponden al ciclo 1: registrar un paciente, rechazar un CI duplicado, acceder a una página interna sin sesión y rechazar datos inválidos en el servidor. Después revisa todo el código contra la lista de requisitos técnicos del enunciado (consultas parametrizadas, contraseñas con hash, sesiones, manejo de errores sin detalles internos, validación en cliente y servidor, comentarios) y contra los nombres de docs/casos_de_uso_ciclo1.md. Dame la lista de diferencias que encuentres antes de corregir.

## 8. Cierre
Escribe el README con los pasos para instalar y ejecutar el proyecto desde cero (XAMPP o php artisan serve, PostgreSQL, migrate:fresh --seed, usuarios de prueba). Verifica que el repositorio no incluya claves. Prepara el push a GitHub.
