# Usamos la versión estable de PHP 8.4 en Alpine para máxima ligereza
FROM php:8.4-cli-alpine

# Recibimos los argumentos del usuario host
ARG USER_ID=1000
ARG GROUP_ID=1000

# Instalamos dependencias del sistema, incluyendo shadow para manejar usuarios de forma segura
RUN apk add --no-cache \
    shadow \
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

# Creamos el usuario que matchee con tu usuario de Linux host
RUN groupmod -g ${GROUP_ID} www-data || true \
    && usermod -u ${USER_ID} -g ${GROUP_ID} www-data || true

# Traemos Composer oficialmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Aseguramos que la carpeta pertenezca al usuario del contenedor
RUN chown -R ${USER_ID}:${GROUP_ID} /var/www

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
