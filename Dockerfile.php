# Usamos la versión estable de PHP 8.4 en Alpine para máxima ligereza
FROM php:8.4-cli-alpine

# Instalamos dependencias del sistema, incluyendo sqlite-dev para el soporte de la BD
RUN apk add --no-cache \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    libxml2-dev \
    sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        zip \
        intl \
        bcmath \
        xml \
        dom \
        pdo_sqlite

# Traemos Composer oficialmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
