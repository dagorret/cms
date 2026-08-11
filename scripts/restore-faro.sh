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

if [[ ! -f "$ARCHIVE" ]]; then
    echo "ERROR: no existe el backup:" >&2
    echo "       $ARCHIVE" >&2
    exit 1
fi

TMP="$(mktemp -d)"

MAINTENANCE=0
RESTORE_STARTED=0
RESTORE_OK=0

cleanup() {

    rm -rf "$TMP"

    if [[ "$RESTORE_STARTED" -eq 1 && "$RESTORE_OK" -eq 0 ]]; then

        echo
        echo "ERROR: el restore fallo."
        echo "Se conservan los directorios *.pre-restore para recuperacion manual."
        echo

    fi

    if [[ "$MAINTENANCE" -eq 1 ]]; then
        echo "==> Saliendo de mantenimiento"

        (
            cd "$SITE"
            ./php artisan up
        ) || true
    fi
}

trap cleanup EXIT


# ------------------------------------------------------------
# 1. Verificar archivo
# ------------------------------------------------------------

echo "==> Verificando archivo comprimido"

tar -tzf "$ARCHIVE" >/dev/null


# ------------------------------------------------------------
# 2. Extraer a staging
# ------------------------------------------------------------

echo "==> Extrayendo a staging"

tar -xzf "$ARCHIVE" -C "$TMP"


# ------------------------------------------------------------
# 3. Componentes obligatorios
#
# database + media + dist se restauran juntos.
# ------------------------------------------------------------

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


# ------------------------------------------------------------
# 4. Verificar hashes ANTES de abrir SQLite
# ------------------------------------------------------------

if [[ -f "$TMP/SHA256SUMS" ]]; then

    echo "==> Verificando hashes"

    (
        cd "$TMP"
        sha256sum -c SHA256SUMS
    )

fi


# ------------------------------------------------------------
# 5. Verificar SQLite
#
# Si el backup incluye WAL, debe estar junto a database.sqlite
# durante esta validacion.
# ------------------------------------------------------------

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


# ------------------------------------------------------------
# 6. Confirmacion humana
# ------------------------------------------------------------

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


# ------------------------------------------------------------
# 7. Backup automatico inmediatamente antes del restore
# ------------------------------------------------------------

echo "==> Creando backup de seguridad previo al restore"

"$BACKUP_SCRIPT"


# ------------------------------------------------------------
# 8. Modo mantenimiento
# ------------------------------------------------------------

echo "==> Faro en modo mantenimiento"

(
    cd "$SITE"
    ./php artisan down
)

MAINTENANCE=1


# ------------------------------------------------------------
# 9. Preparar copias nuevas
#
# Todavia NO tocamos los datos activos.
# ------------------------------------------------------------

echo "==> Preparando archivos restaurados"

rm -rf \
    "$SITE/storage/app/public.restore-new" \
    "$SITE/dist.restore-new"

rm -f \
    "$SITE/database/database.sqlite.restore-new" \
    "$SITE/database/database.sqlite-wal.restore-new"

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


# ------------------------------------------------------------
# 10. Verificar la SQLite preparada
# ------------------------------------------------------------

CHECK="$(
    sqlite3 "$SITE/database/database.sqlite.restore-new" \
        'PRAGMA integrity_check;'
)"

if [[ "$CHECK" != "ok" ]]; then
    echo "ERROR: SQLite preparada no paso integrity_check." >&2
    echo "$CHECK" >&2
    exit 1
fi


# ------------------------------------------------------------
# 11. Comienza el reemplazo real
# ------------------------------------------------------------

RESTORE_STARTED=1

echo "==> Guardando estado actual como pre-restore"

rm -rf \
    "$SITE/storage/app/public.pre-restore" \
    "$SITE/dist.pre-restore"

rm -f \
    "$SITE/database/database.sqlite.pre-restore" \
    "$SITE/database/database.sqlite-wal.pre-restore"

mv \
    "$SITE/storage/app/public" \
    "$SITE/storage/app/public.pre-restore"

mv \
    "$SITE/dist" \
    "$SITE/dist.pre-restore"

mv \
    "$SITE/database/database.sqlite" \
    "$SITE/database/database.sqlite.pre-restore"

if [[ -f "$SITE/database/database.sqlite-wal" ]]; then
    mv \
        "$SITE/database/database.sqlite-wal" \
        "$SITE/database/database.sqlite-wal.pre-restore"
fi

# SHM es transitorio. No se restaura.
rm -f "$SITE/database/database.sqlite-shm"


# ------------------------------------------------------------
# 12. Activar snapshot restaurado
# ------------------------------------------------------------

echo "==> Activando snapshot restaurado"

mv \
    "$SITE/storage/app/public.restore-new" \
    "$SITE/storage/app/public"

mv \
    "$SITE/dist.restore-new" \
    "$SITE/dist"

mv \
    "$SITE/database/database.sqlite.restore-new" \
    "$SITE/database/database.sqlite"

if [[ -f "$SITE/database/database.sqlite-wal.restore-new" ]]; then
    mv \
        "$SITE/database/database.sqlite-wal.restore-new" \
        "$SITE/database/database.sqlite-wal"
fi

cp -a \
    "$TMP/.env" \
    "$SITE/.env"


# ------------------------------------------------------------
# 13. Verificacion definitiva
# ------------------------------------------------------------

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


# ------------------------------------------------------------
# 14. Restore confirmado
# ------------------------------------------------------------

RESTORE_OK=1

echo "==> Restore completado correctamente"


# ------------------------------------------------------------
# 15. Salir de mantenimiento
# ------------------------------------------------------------

(
    cd "$SITE"
    ./php artisan up
)

MAINTENANCE=0


# ------------------------------------------------------------
# 16. Limpiar copias pre-restore
#
# Ya tenemos tambien el .tgz creado justo antes del restore.
# ------------------------------------------------------------

rm -rf \
    "$SITE/storage/app/public.pre-restore" \
    "$SITE/dist.pre-restore"

rm -f \
    "$SITE/database/database.sqlite.pre-restore" \
    "$SITE/database/database.sqlite-wal.pre-restore"

echo "==> Faro restaurado y operativo"
