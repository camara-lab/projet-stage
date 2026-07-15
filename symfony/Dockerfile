# ─────────────────────────────────────────────────────────────────────────────
# BusGo — Symfony PHP-FPM (Production)
# PHP 8.3 Alpine : image minimale, pas d'outils de dev, opcache activé
# ─────────────────────────────────────────────────────────────────────────────

FROM php:8.3-fpm-alpine AS base

# Extensions système nécessaires à Symfony + Doctrine + DomPDF
RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libxml2-dev \
        git \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        zip \
        gd \
        opcache \
        mbstring \
        bcmath \
        xml \
        dom \
    && apk del --no-cache icu-dev libzip-dev oniguruma-dev freetype-dev \
                          libjpeg-turbo-dev libpng-dev libxml2-dev

# Opcache — configuration production agressive
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=0'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Limites PHP raisonnables
RUN { \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=10M'; \
    echo 'memory_limit=256M'; \
    echo 'max_execution_time=60'; \
} > /usr/local/etc/php/conf.d/app.ini

# Composer 2
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ─────────────────────────────────────────────────────────────────────────────
# Stage production
# ─────────────────────────────────────────────────────────────────────────────

FROM base AS production

WORKDIR /var/www/symfony

# 1. Installer les dépendances Composer en premier pour bénéficier du cache
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# 2. Copier le code source
COPY . .

# 3. Chiffrer le cache autoloader + préparer le répertoire var/
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var/

# 4. Préparer le répertoire JWT (les clés sont montées via volume au runtime)
RUN mkdir -p config/jwt

# 5. Permissions finales
RUN chown -R www-data:www-data /var/www/symfony

USER www-data

EXPOSE 9000

# Le cache est chauffé au démarrage du conteneur (après que .env.prod soit injecté)
CMD ["sh", "-c", "php bin/console cache:warmup --env=prod && php-fpm --nodaemonize"]
