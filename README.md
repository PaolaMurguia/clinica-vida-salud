# Clínica Vida Salud – Sistema de gestión de citas médicas

Examen Parcial II – Sistemas 1. Aplicación web en **Laravel 12** con **PostgreSQL**, Blade y Bootstrap 5.
Este documento cubre el **ciclo 1** (casos de uso CU01 a CU13, CU26 y CU27).

## Requisitos

| Herramienta | Versión | Notas |
|---|---|---|
| PHP | 8.2 o superior | Con las extensiones `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `fileinfo` y `zip` habilitadas (en XAMPP: quitar el `;` de `extension=pdo_pgsql` y `extension=pgsql` en `php.ini`). |
| Composer | 2.x | |
| Node.js | 22 | Incluye `npm`. |
| PostgreSQL | 15 o superior | Las migraciones crean las extensiones `btree_gist`, `pgcrypto` y `unaccent`: use el usuario `postgres` u otro con permiso para crearlas. |

Para comprobar PHP: `php -v` y `php -m` (deben aparecer `pdo_pgsql` y `pgsql`).

## Instalación paso a paso

1. **Clonar el repositorio** y entrar a la carpeta:
   ```bash
   git clone https://github.com/PaolaMurguia/clinica-vida-salud.git
   cd clinica-vida-salud
   git checkout desarrollo
   ```
   En **Windows**, si la carpeta de destino tiene una ruta larga, `git clone` puede fallar con *Filename too long*. Antes de clonar, active las rutas largas (una sola vez) o clone en una carpeta corta como `C:\proyectos`:
   ```bash
   git config --global core.longpaths true
   ```
2. **Dependencias de PHP:**
   ```bash
   composer install
   ```
3. **Dependencias de JavaScript y compilación de estilos y scripts** (Bootstrap 5 + Sass con Vite):
   ```bash
   npm install
   npm run build
   ```
4. **Archivo de entorno:** copiar `.env.example` a `.env` y generar la clave de la aplicación.
   ```bash
   cp .env.example .env          # en Windows (cmd): copy .env.example .env
   php artisan key:generate
   ```
   Luego, en `.env`, completar el usuario y la contraseña de PostgreSQL:
   ```
   DB_USERNAME=postgres
   DB_PASSWORD=su_contraseña
   ```
   El resto ya está configurado: base `clinica_vida_salud` en `127.0.0.1:5432`, idioma español, zona horaria `America/La_Paz` y correo en modo `log` (los correos de recuperación de contraseña quedan en `storage/logs/laravel.log`).
5. **Crear las bases de datos** (la segunda la usan solo las pruebas automáticas):
   ```bash
   psql -U postgres -c "CREATE DATABASE clinica_vida_salud ENCODING 'UTF8' TEMPLATE template0"
   psql -U postgres -c "CREATE DATABASE clinica_vida_salud_test ENCODING 'UTF8' TEMPLATE template0"
   ```
   (También se pueden crear desde pgAdmin con codificación UTF8.)
6. **Crear las tablas y cargar los datos de prueba.** Las migraciones ejecutan `database/sql/01_esquema.sql`, `02_funciones.sql` y `03_procedimientos.sql`, y el seeder ejecuta `04_poblacion.sql`:
   ```bash
   php artisan migrate:fresh --seed
   ```
7. **Iniciar el servidor** y abrir <http://localhost:8000>:
   ```bash
   php artisan serve
   ```

### Pruebas automáticas

```bash
php artisan test
```

Las pruebas usan **solo** la base `clinica_vida_salud_test` (configurada en `phpunit.xml`) y nunca tocan `clinica_vida_salud`. Si la configuración apunta a otra base, se detienen con un aviso (si aparece, ejecutar `php artisan config:clear`).

## Usuarios de prueba

Todos usan la contraseña **`Clinica#2026`**.

| Rol | Usuario | Observación |
|---|---|---|
| Administrador | `admin` | |
| Recepcionista | `recep1` | |
| Recepcionista | `recep2` | Contraseña temporal: al ingresar debe cambiarla (CU26). |
| Médico | `dr.perez` | Medicina general (puede marcar citas atendidas). |
| Médico | `dra.mendoza` | Pediatría. |
| Médico | `dra.justiniano` | Geriatría (puede marcar citas atendidas). |
| Paciente | `1234567` | María López Vargas (el usuario de un paciente es su CI). |
| Paciente | `4567890` | Luis Fernández Cuéllar. |

También se puede ingresar con el correo de la cuenta, por ejemplo `admin@vidasalud.com`.

## Casos de uso implementados (ciclo 1)

| CU | Nombre | Módulo | Roles |
|---|---|---|---|
| CU01 | Iniciar sesión (bloqueo progresivo de 5, 15 y 60 minutos) | Acceso | Todos |
| CU02 | Cerrar sesión | Acceso | Todos |
| CU03 | Gestionar usuarios y roles | Administración | Administrador |
| CU04 | Consultar bitácora (filtros y exportación a CSV) | Administración | Administrador |
| CU05 | Gestionar especialidades | Administración | Administrador |
| CU06 | Gestionar médicos | Administración | Administrador |
| CU07 | Definir horarios de atención | Administración | Administrador |
| CU08 | Gestionar pacientes | Pacientes | Recepcionista, Administrador |
| CU09 | Buscar paciente | Pacientes | Recepcionista, Administrador |
| CU10 | Registrarse como paciente | Pacientes | Visitante |
| CU11 | Gestionar perfil del paciente | Pacientes | Paciente |
| CU12 | Registrar seguro del paciente | Pacientes | Recepcionista |
| CU13 | Consultar disponibilidad | Disponibilidad | Recepcionista, Paciente |
| CU26 | Cambiar contraseña | Acceso | Todos |
| CU27 | Recuperar contraseña (enlace de un solo uso por correo) | Acceso | Todos |

## Despliegue en Render

La aplicación se despliega como **Web Service con Docker** (`Dockerfile` en la raíz) y una base **PostgreSQL** de Render.

- **Imagen:** etapa Node 22 que compila los assets con Vite, y etapa PHP 8.2 + Apache con `pdo_pgsql` y dependencias de Composer sin las de desarrollo (`--no-dev`).
- **Arranque** (`docker/entrypoint.sh`):
  1. Apache escucha en el puerto de la variable `PORT` que define Render.
  2. Con `APP_INIT_DB=true` ejecuta `php artisan migrate:fresh --seed --force`; si no, solo `php artisan migrate --force`.
  3. Genera `config:cache`, `route:cache` y `view:cache`.
  4. Arranca el servidor.
- **https:** el TLS lo termina Render. La aplicación confía en su proxy (`trustProxies`) y en producción genera todas las URL con https.

### Pasos

1. En Render, crear una base **PostgreSQL** (versión 15 o superior). Las migraciones crean las extensiones `btree_gist`, `pgcrypto` y `unaccent`, que Render permite crear al dueño de la base.
2. Crear un **Web Service** desde el repositorio de GitHub: *Language/Runtime* = **Docker**, rama = `master` (o `desarrollo` para pruebas), en la misma región que la base.
3. Generar la clave de la aplicación en una copia local y copiarla en `APP_KEY`:
   ```bash
   php artisan key:generate --show
   ```
4. Cargar las variables de entorno de la tabla siguiente y desplegar.
5. **Después del primer despliegue exitoso, cambiar `APP_INIT_DB` a `false`.** Si queda en `true`, cada despliegue borra la base y vuelve a cargar los datos de prueba.

### Variables de entorno

| Variable | Valor | Notas |
|---|---|---|
| `APP_NAME` | `Clínica Vida Salud` | |
| `APP_ENV` | `production` | Activa https forzado. |
| `APP_DEBUG` | `false` | Sin detalles internos en los errores (RNF08). |
| `APP_KEY` | `base64:…` | La que muestra `php artisan key:generate --show`. **No subirla al repositorio.** |
| `APP_URL` | `https://<su-servicio>.onrender.com` | |
| `APP_TIMEZONE` | `America/La_Paz` | También la usa la conexión a PostgreSQL. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `es` | |
| `DB_CONNECTION` | `pgsql` | |
| `DB_URL` | *Internal Database URL* de la base en Render | Formato `postgres://usuario:clave@host:5432/base`; reemplaza `DB_HOST`, `DB_DATABASE`, etc. |
| `APP_INIT_DB` | `true` en el primer despliegue, luego `false` | Ver el paso 5. |
| `LOG_CHANNEL` | `stderr` | Los logs aparecen en la pestaña *Logs* de Render. |
| `LOG_LEVEL` | `debug` | Necesario para ver los correos en modo `log` (se registran con nivel debug). |
| `MAIL_MAILER` | `log` | Sin servidor de correo: el enlace de recuperación de CU27 se lee en *Logs* (buscar `recuperar-clave`). |
| `MAIL_FROM_ADDRESS` | `no-responder@vidasalud.com` | |
| `SESSION_DRIVER` | `file` | |
| `SESSION_SECURE_COOKIE` | `true` | La cookie de sesión solo viaja por https. |
| `CACHE_STORE` | `file` | |
| `QUEUE_CONNECTION` | `sync` | |

`PORT` no se configura a mano: la define Render.

**Obligatorias:** `APP_KEY` y `DB_URL` (y `APP_INIT_DB=true` en el primer despliegue). Las demás ya tienen en la imagen los valores de la tabla, así que solo hace falta cargarlas para cambiarlos. Si falta `APP_KEY` o `DB_URL`, el contenedor se detiene con un mensaje `[entrypoint] ERROR` en *Logs*.

**Si aparece un error 500:**
1. Revise la pestaña *Logs* de Render: el arranque muestra una línea `[entrypoint] APP_ENV=… SESSION_DRIVER=… CACHE_STORE=…` y, con `LOG_CHANNEL=stderr`, el detalle de cada error.
2. Compruebe que `SESSION_DRIVER` y `CACHE_STORE` valgan `file`: el esquema no tiene tablas de sesiones ni de caché.
3. Solo para diagnosticar, puede poner `APP_DEBUG=true` y volver a `false` al terminar (RNF08).

**Aviso:** con `MAIL_MAILER=log` los enlaces de recuperación quedan en los logs del servicio. Sirve para la demostración; en un uso real se configura un servidor SMTP con las variables `MAIL_*`.

## Documentación del proyecto

- `docs/casos_de_uso_ciclo1.md`: casos de uso, reglas del negocio y requisitos no funcionales (fuente de verdad de los nombres).
- `docs/trazabilidad_ciclo1.md`: relación CU → vista → controlador → métodos → modelos → ruta → prueba.
- `docs/diferencias_con_documento.md`: decisiones y diferencias con el documento de casos de uso.
- `database/sql/`: esquema, funciones, procedimientos, población y consultas de referencia.

## Estructura principal

```
app/Http/Controllers/{Acceso,Administracion,Pacientes,Disponibilidad}   controladores por módulo
app/Http/Requests/{Acceso,Administracion,Pacientes,Disponibilidad}      validación en el servidor (Form Requests)
app/Models                                                              22 modelos Eloquent
app/Services/ServicioBitacora.php                                       auditoría centralizada
resources/views                                                         vistas Blade (planas)
resources/js/validaciones.js                                            validación en el navegador
tests/Feature/{Acceso,Administracion,Pacientes,Disponibilidad}          pruebas por módulo
```
