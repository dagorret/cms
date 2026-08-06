#!/bin/sh
# Se ejecuta en cada arranque del contenedor cms-php, DESPUES de que el bind
# mount (.:/var/www definido en docker-compose.yml) ya está montado. Un
# `chown`/`chmod` hecho solo en el Dockerfile no sirve de nada en runtime
# porque el volumen del host pisa el contenido de la imagen: por eso este
# fixup vive en el entrypoint y no (solo) en el build.
#
# No ejecutamos Node en runtime. MathJax se integra en un chunk independiente
# durante `npm run build` y el navegador lo descarga solo cuando corresponde.
set -e

if [ -d /var/www/node_modules ]; then
    chmod -R u+rX /var/www/node_modules 2>/dev/null \
        || echo "⚠️  No se pudo asegurar acceso de lectura a node_modules." >&2
fi

exec "$@"
