# Diferencias con el documento de casos de uso

Registro de decisiones en las que la implementación agrega algo que `docs/casos_de_uso_ciclo1.md` no nombra o interpreta un paso. Cada entrada indica quién la aprobó.

## Decisiones por caso de uso

### CU27 – Validación del enlace al abrirlo (aprobada por la autora)
- **Documento:** paso 6, «el usuario abre el enlace y el sistema valida que exista, no esté usado ni vencido». `CtrlRecuperacion` solo tiene `solicitarEnlace()` y `restablecer()`.
- **Implementación:** la ruta del formulario (`GET /recuperar-clave/{token}`) llama directamente a `Recuperacion::validar()`, que sí está en el documento, y muestra `V.recuperarClave`. Si el enlace venció, ya se usó o fue alterado, la vista muestra el aviso y el enlace para solicitar otro. **No se agregan métodos a `CtrlRecuperacion`.**
- **Seguridad:** `restablecer()` vuelve a ejecutar `Recuperacion::validar()` al enviar el formulario, porque el enlace pudo vencer o usarse entre la apertura y el envío.

### CU01 – Destino del paciente
- **Documento:** paso 9, «redirige al menú, o al portal si es paciente». El documento no nombra la vista del portal.
- **Implementación:** vista `portal.blade.php` (aprobada por la autora), publicada con `Route::view` (B1) y solo para el rol Paciente.

## Nombres que no están en el documento (aprobados por la autora)

| Elemento | Nombre | Motivo |
|---|---|---|
| Plantilla Blade | `plantilla.blade.php` | Estructura común (barra superior, mensajes, pie). Las vistas siguen planas. |
| Página «/» | sin vista: redirige a `V.login` | Se eliminó `inicio.blade.php`. |
| Portal del paciente | `portal.blade.php` | CU01, paso 9. |
| Form Requests | `<método>Request` por módulo (`Acceso/AutenticarRequest`, …) | Obligatorios (RNF07); el documento no los nombra. |
| Middlewares | `VerificarRol`, `VerificarClaveTemporal`, `SinCache` | RNF03, RN23 y CU02 (botón «Atrás»). |
| Servicio | `ServicioBitacora` | Decisión B8 de `CLAUDE.md`. |
| Política RNF05 | `Password::defaults()` de Laravel | Sin clase con nombre nuevo. |
| Opciones del menú por rol | `config/menu.php` | Una sola lista para la barra, `V.menu` y el portal. |

## Modelos (paso 2)

- **Métodos que no están en el documento:** relaciones de Eloquent (`rol()`, `especialidades()`, `paciente()`, …) y `getAuthPassword()` / `getAuthPasswordName()` en `Usuario`, que Laravel necesita para autenticar con la columna `password_hash`. Se consideran equivalentes a las llamadas estándar de Eloquent (B13).
- **Qué reciben y qué devuelven algunos métodos (el documento solo da los nombres):**
  - `Bloqueo::estaBloqueada()` devuelve el bloqueo con su motivo, o `null`, porque CU13 debe mostrar el motivo.
  - `Rol::obtener()` acepta un id, un nombre o nada (en ese caso devuelve todos).
  - `Usuario::actualizarEstado($accion)` recibe la acción: `activar`, `desactivar` o `desbloquear` (B2).
  - `Usuario::registrarIntentoFallido()` devuelve los minutos de bloqueo aplicados, o `null`.
  - `Recuperacion::crear()` devuelve el token en claro para enviarlo por correo; en la base solo queda su hash.

## Base de datos y pruebas

- **Usuario de los pacientes:** `04_poblacion.sql` crea `maria.lopez` y `luis.fernandez`, pero la decisión A7 exige el CI. El archivo no se modifica: `PoblacionSeeder` actualiza `nombre_usuario` con el CI después de ejecutarlo.
- **Secuencias:** la primera migración borra las 5 secuencias de los códigos antes de ejecutar `01_esquema.sql`, porque `migrate:fresh` solo borra tablas.
- **`tests/Feature/ModelosTest.php`:** queda fuera de los módulos porque prueba los 22 modelos y la población, no un caso de uso.
