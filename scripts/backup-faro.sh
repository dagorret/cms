#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

SITE="/var/www/a.dagorret.com.ar"
BACKUP_DIR="/home/motorola/backup"
RETENTION_MINUTES=14400   # 10 dias

STAMP="$(date +%F_%H-%M-%S)"

OUT="$BACKUP_DIR/faro-$STAMP.tgz"
PART="$BACKUP_DIR/.faro-$STAMP.tgz.part"

mkdir -p "$BACKUP_DIR"

TMP="$(mktemp -d "$BACKUP_DIR/.faro-backup.XXXXXX")"

cleanup() {
    rm -rf "$TMP"
    rm -f "$PART"
}

trap cleanup EXIT


# ------------------------------------------------------------
# Evitar dos backups simultaneos.
#
# El archivo .backup.lock puede existir permanentemente.
# flock solo evita que dos procesos hagan backup al mismo tiempo.
# ------------------------------------------------------------

exec 9>"$BACKUP_DIR/.backup.lock"

if ! flock -n 9; then
    echo "ERROR: ya hay otro backup de Faro en ejecucion." >&2
    exit 1
fi


echo "==> Iniciando backup FaroCMS: $STAMP"


# ------------------------------------------------------------
# 1. SQLite
#
# Faro usa WAL.
# Copiamos database.sqlite y, si existen en ese momento,
# database.sqlite-wal y database.sqlite-shm.
#
# Los tres pertenecen al mismo snapshot de backup.
# ------------------------------------------------------------

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


# ------------------------------------------------------------
# 2. Medios originales
#
# database + storage/app/public deben viajar juntos.
# ------------------------------------------------------------

mkdir -p "$TMP/storage/app"

cp -a \
    "$SITE/storage/app/public" \
    "$TMP/storage/app/public"


# ------------------------------------------------------------
# 3. Sitio estatico publicado
#
# dist es una segunda fuente de recuperacion:
#
# - conserva el HTML publicado;
# - conserva texto renderizado;
# - conserva copias de medios publicados;
# - permite recuperar contenido incluso ante perdida parcial
#   de BD o storage.
# ------------------------------------------------------------

cp -a \
    "$SITE/dist" \
    "$TMP/dist"


# ------------------------------------------------------------
# 4. Configuracion
# ------------------------------------------------------------

cp -a \
    "$SITE/.env" \
    "$TMP/.env"


# ------------------------------------------------------------
# 5. Verificar SQLite copiada
# ------------------------------------------------------------

CHECK="$(
    sqlite3 "$TMP/database/database.sqlite" \
        'PRAGMA integrity_check;'
)"

printf '%s\n' "$CHECK" \
    > "$TMP/sqlite-integrity.txt"

if [[ "$CHECK" != "ok" ]]; then
    echo "ERROR: integrity_check de SQLite fallo:" >&2
    echo "$CHECK" >&2
    exit 1
fi


# ------------------------------------------------------------
# 6. Metadata Git opcional
#
# No hacemos fallar el backup por ownership del repositorio.
# ------------------------------------------------------------

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


# ------------------------------------------------------------
# 7. Manifiesto SHA256
#
# Permite verificar posteriormente archivos recuperados.
# ------------------------------------------------------------

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


# ------------------------------------------------------------
# 8. Crear backup como archivo PARCIAL
#
# Mientras se genera:
#
#   .faro-....tgz.part
#
# Un archivo faro-....tgz solo existira si el backup
# termino y pudo verificarse.
# ------------------------------------------------------------

echo "==> Comprimiendo backup..."

tar \
    -C "$TMP" \
    -czf "$PART" \
    .


# ------------------------------------------------------------
# 9. Verificar el archivo comprimido
# ------------------------------------------------------------

echo "==> Verificando archivo comprimido..."

tar -tzf "$PART" >/dev/null


# ------------------------------------------------------------
# 10. Convertir el backup parcial en backup valido
#
# mv dentro del mismo filesystem es practicamente instantaneo.
# ------------------------------------------------------------

chmod 600 "$PART"

mv \
    "$PART" \
    "$OUT"


# ------------------------------------------------------------
# 11. Retencion: 10 dias
# ------------------------------------------------------------

find "$BACKUP_DIR" \
    -type f \
    -name 'faro-*.tgz' \
    -mmin +"$RETENTION_MINUTES" \
    -delete


# ------------------------------------------------------------
# 12. Limpiar .part abandonados de ejecuciones antiguas
#
# Por ejemplo, despues de un corte de energia.
# Solo eliminamos parciales con mas de 24 horas.
# ------------------------------------------------------------

find "$BACKUP_DIR" \
    -type f \
    -name '.faro-*.tgz.part' \
    -mmin +1440 \
    -delete


echo
echo "==> Backup completado"
echo "    $OUT"

du -h "$OUT"
