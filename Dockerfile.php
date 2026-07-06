# Usamos la versión estable de PHP 8.4 en Alpine para máxima ligereza
FROM php:8.4-cli-alpine

# Recibimos los argumentos del usuario host
ARG USER_ID=1000
ARG GROUP_ID=1000

# Instalamos runtime libs y dependencias de build de forma explícita.
# Añadimos libwebp-tools para disponer del comando global cwebp en el sistema.
# La build falla si SQLite o GD no quedan realmente disponibles para Artisan.
RUN apk add --no-cache \
shadow \
nodejs \
npm \
sqlite-libs \
libjpeg-turbo \
libpng \
libwebp \
libwebp-tools \
freetype \
libzip \
icu-libs \
libxml2 \
&& apk add --no-cache --virtual .build-deps \
$PHPIZE_DEPS \
sqlite-dev \
libjpeg-turbo-dev \
libpng-dev \
libwebp-dev \
freetype-dev \
libzip-dev \
icu-dev \
libxml2-dev \
&& docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
&& docker-php-ext-install -j"$(nproc)" \
gd \
zip \
intl \
bcmath \
xml \
dom \
pdo_sqlite \
&& php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' \
&& php -r '$gd = gd_info(); exit(!empty($gd["WebP Support"]) && !empty($gd["JPEG Support"]) && !empty($gd["FreeType Support"]) ? 0 : 1);' \
&& apk del .build-deps \
&& cwebp -version

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

# 🛠 AJUSTE DE PHP.INI CALIBRADO POR CARLOS (CON MARGEN DE SEGURIDAD OOM)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
&& { \
echo "memory_limit = 700M"; \
echo "upload_max_filesize = 256M"; \
echo "post_max_size = 256M"; \
echo "max_execution_time = 180"; \
echo "max_input_time = 120"; \
} > "$PHP_INI_DIR/conf.d/99-cms-faro.ini" \
&& php -r 'exit(ini_get("memory_limit") === "700M" ? 0 : 1);'

WORKDIR /var/www

# Aseguramos que la carpeta pertenezca al usuario del contenedor
RUN chown -R ${USER_ID}:${GROUP_ID} /var/www

# ⚠ IMPORTANTE: docker-compose.yml monta ".:/var/www" como bind mount, asi
# que este chown de build-time queda pisado por los permisos del host en
# cuanto arranca el contenedor. El fixup real (render-math.js ejecutable,
# node_modules legible) se repite en cada arranque via el entrypoint.
COPY docker/entrypoint.php.sh /usr/local/bin/entrypoint.php.sh
RUN chmod +x /usr/local/bin/entrypoint.php.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.php.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
