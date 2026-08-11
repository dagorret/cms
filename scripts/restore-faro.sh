#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

SITE="/var/www/a.dagorret.com.ar"
BACKUP_SCRIPT="/home/motorola/backup-faro.sh"

if [[ $# -ne 1 ]]; then
    echo "Uso:"
    echo "  $0 /home/motorola/backup/faro-YYYY-MM-DD_HH-MM-SS.tgz"
    exit 1
fi

ARCHIVE="$1"
STAMP="$(date +%F_%H-%M-%S)"

if [[ ! -f "$ARCHIVE" ]]; then
    echo "ERROR: no existe el backup:" >&2
    echo "       $ARCHIVE" >&2
    exit 1
fi

TMP="$(mktemp -d)"

MAINTENANCE=0

cleanup() {

    rm -rf "$TMP"

    if [[ "$MAINTENANCE" -eq 1 ]]; then
        echo "==> Saliendo de mantenimiento"
        "$SITE/php" "$SITE/artisan" up || true
    fi
}

trap cleanup EXIT

echo "==> Verificando archivo"
tar -tzf "$ARCHIVE" >/dev/null

echo "==> Extrayendo a staging"
tar -xzf "$ARCHIVE" -C "$TMP"

#
# Un restore valido exige las tres unidades fundamentales.
#

REQUIRED=(
    "$TMP/database/database.sqlite"
    "$TMP/storage/app/public"
    "$TMP/dist"
    "$TMP/.env"
)

for ITEM in "${REQUIRED[@]}"; do

    if [[ ! -e "$ITEM" ]]; then
        echo "ERROR: backup incompleto." >&2
        echo "Falta: $ITEM" >&2
        exit 1
    fi

done

#
# Verificar SQLite ANTES de tocar produccion.
#

echo "==> Verificando SQLite del backup"

CHECK="$(
    sqlite3 "$TMP/database/database.sqlite" \
        'PRAGMA integrity_check;'
)"

if [[ "$CHECK" != "ok" ]]; then
    echo "ERROR: SQLite del backup no es integra:" >&2
    echo "$CHECK" >&2
    exit 1
fi

#
# Si hay SHA256SUMS, comprobarlo.
#

if [[ -f "$TMP/SHA256SUMS" ]]; then

    echo "==> Verificando hashes"

    (
        cd "$TMP"
        sha256sum -c SHA256SUMS
    )

fi

echo
echo "Backup:"
echo "  $ARCHIVE"
echo
echo "Se restauraran JUNTOS:"
echo
echo "  database/database.sqlite"
echo "  storage/app/public/"
echo "  dist/"
echo "  .env"
echo
echo "Esto reemplazara el estado actual de Faro."
echo

read -r -p "Escriba RESTAURAR para continuar: " CONFIRM

if [[ "$CONFIRM" != "RESTAURAR" ]]; then
    echo "Restore cancelado."
    exit 0
fi

#
# Backup automatico del estado que estamos a punto de reemplazar.
#

echo "==> Creando backup de seguridad previo al restore"

"$BACKUP_SCRIPT"

#
# Bloquear escrituras administrativas durante el cambio.
# El sitio publico en dist sigue siendo estatico.
#

echo "==> Faro en modo mantenimiento"

"$SITE/php" "$SITE/artisan" down

MAINTENANCE=1

#
# Preparar las nuevas copias dentro del mismo filesystem.
#

echo "==> Preparando restore"

rm -rf \
    "$SITE/storage/app/public.restore-new" \
    "$SITE/dist.restore-new"

cp -a \
    "$TMP/storage/app/public" \
    "$SITE/storage/app/public.restore-new"

cp -a \
    "$TMP/dist" \
    "$SITE/dist.restore-new"

cp -a \
    "$TMP/database/database.sqlite" \
    "$SITE/database/database.sqlite.restore-new"

if [[ -f "$TMP/database/database.sqlite-wal" ]]; then

    cp -a \
        "$TMP/database/database.sqlite-wal" \
        "$SITE/database/database.sqlite-wal.restore-new"

fi

if [[ -f "$TMP/database/database.sqlite-shm" ]]; then

    cp -a \
        "$TMP/database/database.sqlite-shm" \
        "$SITE/database/database.sqlite-shm.restore-new"

fi

#
# Sustituir media y dist.
#
# mv dentro del mismo filesystem minimiza la ventana
# de estados parciales.
#

rm -rf \
    "$SITE/storage/app/public.pre-restore" \
    "$SITE/dist.pre-restore"

mv \
    "$SITE/storage/app/public" \
    "$SITE/storage/app/public.pre-restore"

mv \
    "$SITE/storage/app/public.restore-new" \
    "$SITE/storage/app/public"

mv \
    "$SITE/dist" \
    "$SITE/dist.pre-restore"

mv \
    "$SITE/dist.restore-new" \
    "$SITE/dist"

#
# Sustituir SQLite.
#

rm -f \
    "$SITE/database/database.sqlite-wal" \
    "$SITE/database/database.sqlite-shm"

mv \
    "$SITE/database/database.sqlite.restore-new" \
    "$SITE/database/database.sqlite"

if [[ -f "$SITE/database/database.sqlite-wal.restore-new" ]]; then

    mv \
        "$SITE/database/database.sqlite-wal.restore-new" \
        "$SITE/database/database.sqlite-wal"

fi

if [[ -f "$SITE/database/database.sqlite-shm.restore-new" ]]; then

    mv \
        "$SITE/database/database.sqlite-shm.restore-new" \
        "$SITE/database/database.sqlite-shm"

fi

#
# Configuracion del mismo snapshot.
#

cp -a \
    "$TMP/.env" \
    "$SITE/.env"

#
# Verificacion posterior.
#

echo "==> Verificando SQLite restaurada"

CHECK="$(
    sqlite3 "$SITE/database/database.sqlite" \
        'PRAGMA integrity_check;'
)"

if [[ "$CHECK" != "ok" ]]; then
    echo "ERROR: SQLite restaurada no paso integrity_check." >&2
    echo "$CHECK" >&2
    exit 1
fi

#
# Si todo salio bien, las copias pre-restore ya no son necesarias:
# el backup previo completo esta guardado en /home/motorola/backup.
#

rm -rf \
    "$SITE/storage/app/public.pre-restore" \
    "$SITE/dist.pre-restore"

echo "==> Restore completado correctamente"

"$SITE/php" "$SITE/artisan" up
MAINTENANCE=0
