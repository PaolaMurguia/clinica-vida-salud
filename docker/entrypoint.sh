#!/bin/sh
# =====================================================================
# Arranque del contenedor en Render
#  1. Apache escucha en el puerto de la variable PORT (Render la define).
#  2. Base de datos: APP_INIT_DB=true  -> migrate:fresh --seed (borra y recarga los datos de prueba)
#                    en otro caso       -> migrate (solo aplica migraciones pendientes)
#  3. Cachés de configuración, rutas y vistas.
#  4. Arranca Apache en primer plano.
# =====================================================================
set -e

cd /var/www/html

# 1. Puerto de Render (8080 si se ejecuta fuera de Render)
PORT="${PORT:-8080}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
echo "[entrypoint] Apache escuchará en el puerto ${PORT}"

# 2. Base de datos (con reintentos por si PostgreSQL todavía no acepta conexiones)
if [ "${APP_INIT_DB}" = "true" ]; then
    COMANDO="migrate:fresh --seed --force"
    echo "[entrypoint] APP_INIT_DB=true: se recrea la base con los datos de prueba"
else
    COMANDO="migrate --force"
fi

INTENTO=1
until php artisan $COMANDO; do
    if [ "$INTENTO" -ge 5 ]; then
        echo "[entrypoint] No se pudo preparar la base de datos después de $INTENTO intentos" >&2
        exit 1
    fi
    echo "[entrypoint] Base de datos no disponible; reintento $INTENTO de 5 en 5 segundos..." >&2
    INTENTO=$((INTENTO + 1))
    sleep 5
done

# 3. Cachés (se generan aquí porque dependen de las variables de entorno de Render)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Apache (www-data) debe poder escribir sesiones, cachés y vistas compiladas
chown -R www-data:www-data storage bootstrap/cache

# 4. Servidor
exec apache2-foreground
