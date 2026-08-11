#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

SITE="/var/www/a.dagorret.com.ar"
BACKUP_DIR="/home/motorola/backup"
RETENTION_MINUTES=14400   # 10 dias

STAMP="$(date +%F_%H-%M-%S)"
OUT="$BACKUP_DIR/faro-$STAMP.tgz"

mkdir -p "$BACKUP_DIR"

TMP="$(mktemp -d "$BACKUP_DIR/.faro-backup.XXXXXX")"

cleanup() {
    rm -rf "$TMP"
}
trap cleanup EXIT

# Evitar dos backups simultaneos.
exec 9>"$BACKUP_DIR/.backup.lock"

if ! flock -n 9; then
    echo "ERROR: ya hay otro backup de Faro en ejecucion." >&2
    exit 1
fi

echo "==> Iniciando backup FaroCMS: $STAMP"

#
# 1. SQLite
#
# Faro usa WAL. Copiamos database.sqlite y, si existen,
# los archivos WAL/SHM correspondientes.
#

mkdir -p "$TMP/database"

cp -a \
    "$SITE/database/database.sqlite" \
    "$TMP/database/database.sqlite"

if [[ -f "$SITE/database/database.sqlite-wal" ]]; then
    cp -a \
        "$SITE/database/database.sqlite-wal" \
        "$TMP/database/database.sqlite-wal"
fi

if [[ -f "$SITE/database/database.sqlite-shm" ]]; then
    cp -a \
        "$SITE/database/database.sqlite-shm" \
        "$TMP/database/database.sqlite-shm"
fi

#
# 2. Medios originales
#
# SQLite + storage/app/public forman una unidad de recuperacion.
#

mkdir -p "$TMP/storage/app"

cp -a \
    "$SITE/storage/app/public" \
    "$TMP/storage/app/public"

#
# 3. Sitio estatico publicado
#
# dist es tambien una segunda fuente de recuperacion:
# contiene HTML renderizado y copias de medios publicados.
#

cp -a \
    "$SITE/dist" \
    "$TMP/dist"

#
# 4. Configuracion necesaria para una recuperacion completa
#

cp -a \
    "$SITE/.env" \
    "$TMP/.env"

#
# 5. Verificar la copia de SQLite
#
# Al estar WAL/SHM junto al database.sqlite, SQLite puede
# interpretar correctamente el snapshot copiado.
#

CHECK="$(
    sqlite3 "$TMP/database/database.sqlite" \
        'PRAGMA integrity_check;'
)"

printf '%s\n' "$CHECK" > "$TMP/sqlite-integrity.txt"

if [[ "$CHECK" != "ok" ]]; then
    echo "ERROR: integrity_check de SQLite fallo:" >&2
    echo "$CHECK" >&2
    exit 1
fi

#
# 6. Metadata Git opcional.
#
# No hacemos fallar el backup si el repositorio pertenece
# a otro usuario.
#

if git -C "$SITE" rev-parse HEAD >/dev/null 2>&1; then

    git -C "$SITE" rev-parse HEAD \
        > "$TMP/git-commit.txt"

    git -C "$SITE" log -1 --oneline \
        > "$TMP/git-log.txt"

    git -C "$SITE" status --short \
        > "$TMP/git-status.txt"

else

    echo "Metadata Git omitida por permisos/ownership." \
        > "$TMP/git-info.txt"

fi

#
# 7. Manifiesto de hashes
#

(
    cd "$TMP"

    find . \
        -type f \
        ! -name SHA256SUMS \
        -print0 \
        | sort -z \
        | xargs -0 sha256sum \
        > SHA256SUMS
)

#
# 8. Crear archivo final
#

tar \
    -C "$TMP" \
    -czf "$OUT" \
    .

chmod 600 "$OUT"

#
# 9. Comprobar que el tar puede leerse
#

tar -tzf "$OUT" >/dev/null

#
# 10. Retencion de 10 dias
#

find "$BACKUP_DIR" \
    -type f \
    -name 'faro-*.tgz' \
    -mmin +"$RETENTION_MINUTES" \
    -delete

echo "==> Backup completado"
echo "    $OUT"

du -h "$OUT"
