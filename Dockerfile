# =====================================================================
# Clínica Vida Salud – imagen para Render (Docker)
# Etapa 1: compila estilos y scripts con Vite (Node 22)
# Etapa 2: PHP 8.2 + Apache con pdo_pgsql y dependencias sin las de desarrollo
# =====================================================================

# ---------- Etapa 1: assets (Bootstrap 5 + Sass con Vite) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------- Etapa 2: aplicación ----------
FROM php:8.2-apache

# Extensiones de PHP: pdo_pgsql y pgsql (PostgreSQL) y opcache; unzip lo usa Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql pgsql opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# php.ini de producción: sin errores en pantalla (RNF08)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apache sirve la carpeta public/ y respeta el .htaccess de Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n    Require all granted\n</Directory>\nServerName localhost\n' \
       > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencias de PHP sin las de desarrollo (se instalan antes de copiar el código para aprovechar la caché)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

# Código de la aplicación y assets compilados en la etapa 1
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions \
       storage/framework/views storage/framework/testing bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh

# Valores por defecto de producción. En el contenedor no hay .env: sin estos valores Laravel usaría
# sesiones y caché en tablas que este esquema no tiene (error 500). Las variables de Render los reemplazan.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=es \
    APP_FALLBACK_LOCALE=es \
    APP_TIMEZONE=America/La_Paz \
    DB_CONNECTION=pgsql \
    SESSION_DRIVER=file \
    SESSION_SECURE_COOKIE=true \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=debug \
    MAIL_MAILER=log

# Render indica el puerto en la variable PORT; el entrypoint configura Apache con ese valor
EXPOSE 8080

ENTRYPOINT ["docker/entrypoint.sh"]
