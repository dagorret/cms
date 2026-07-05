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
    nodejs \
    npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        zip \
        intl \
        bcmath \
        xml \
        dom \
        pdo_sqlite

# 🔧 Node/KaTeX: nos aseguramos de que el binario y los paquetes globales de
# Node queden legibles/ejecutables para cualquier usuario del contenedor
# (www-data corre con UID/GID variable segun el host), evitando que
# Process::run() falle en silencio al invocar `node` desde Filament.
RUN chmod -R o+rX /usr/lib/node_modules /usr/bin/node /usr/bin/npm 2>/dev/null || true

# Creamos el usuario que matchee con tu usuario de Linux host
RUN groupmod -g ${GROUP_ID} www-data || true \
    && usermod -u ${USER_ID} -g ${GROUP_ID} www-data || true

# Traemos Composer oficialmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 🛠️ AJUSTE DE PHP.INI DEFINITIVO para subida y compresión de imágenes
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 128M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www

# Aseguramos que la carpeta pertenezca al usuario del contenedor
RUN chown -R ${USER_ID}:${GROUP_ID} /var/www

# ⚠️ IMPORTANTE: docker-compose.yml monta ".:/var/www" como bind mount, asi
# que este chown de build-time queda pisado por los permisos del host en
# cuanto arranca el contenedor. El fixup real (render-math.js ejecutable,
# node_modules legible) se repite en cada arranque via el entrypoint.
COPY docker/entrypoint.php.sh /usr/local/bin/entrypoint.php.sh
RUN chmod +x /usr/local/bin/entrypoint.php.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.php.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
