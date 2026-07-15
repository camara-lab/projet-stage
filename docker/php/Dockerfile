FROM php:8.3-fpm-bookworm

# -----------------------------------------------------------
# Arguments (UID/GID pour éviter les problèmes de permissions)
# -----------------------------------------------------------
ARG UID=1000
ARG GID=1000
ARG USER=symfony

# -----------------------------------------------------------
# Dépendances système
# -----------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    default-mysql-client \
    acl \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# Extensions PHP requises par Symfony 7 + projet
# -----------------------------------------------------------
RUN docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
        opcache \
        bcmath \
        gd \
        mbstring \
        exif

# -----------------------------------------------------------
# Xdebug (dev only - désactivable via ini)
# -----------------------------------------------------------
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# -----------------------------------------------------------
# Composer (version officielle)
# -----------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# -----------------------------------------------------------
# Symfony CLI (pratique pour le dev)
# -----------------------------------------------------------
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony || true

# -----------------------------------------------------------
# Utilisateur non-root (permissions propres sur le volume)
# -----------------------------------------------------------
RUN groupadd -g ${GID} ${USER} \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash ${USER}

# Dossier de travail
RUN mkdir -p /var/www/symfony \
    && chown -R ${USER}:${USER} /var/www/symfony

USER ${USER}
WORKDIR /var/www/symfony

EXPOSE 9000
CMD ["php-fpm"]
