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
