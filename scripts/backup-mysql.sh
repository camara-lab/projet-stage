#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# BusGo — Sauvegarde MySQL automatique
#
# Usage    : ./scripts/backup-mysql.sh
# Cron     : 0 2 * * * /opt/busgo/scripts/backup-mysql.sh >> /var/log/busgo-backup.log 2>&1
# Rétention: 7 jours (les sauvegardes plus anciennes sont supprimées)
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────
BACKUP_DIR="${BACKUP_DIR:-/var/backups/busgo}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/busgo_${TIMESTAMP}.sql.gz"

# Variables lues depuis .env.prod si disponible
if [ -f "$(dirname "$0")/../symfony/.env.prod" ]; then
    # shellcheck disable=SC1091
    set -a && source "$(dirname "$0")/../symfony/.env.prod" && set +a
fi

# Paramètres MySQL avec valeurs par défaut
DB_HOST="${DB_HOST:-database}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${MYSQL_DATABASE:-busgo_prod}"
DB_USER="${MYSQL_USER:-busgo_user}"
DB_PASS="${MYSQL_PASSWORD:-}"

# ── Vérifications préalables ──────────────────────────────────────────────
if [ -z "$DB_PASS" ]; then
    echo "[$(date)] ERREUR : MYSQL_PASSWORD non défini" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"

# ── Sauvegarde ────────────────────────────────────────────────────────────
echo "[$(date)] Démarrage de la sauvegarde → ${BACKUP_FILE}"

docker compose -f "$(dirname "$0")/../docker-compose.prod.yml" exec -T database \
    mysqldump \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USER" \
        --password="$DB_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        "$DB_NAME" \
    | gzip -9 > "$BACKUP_FILE"

BACKUP_SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)
echo "[$(date)] Sauvegarde terminée — taille : ${BACKUP_SIZE}"

# ── Nettoyage (rétention) ─────────────────────────────────────────────────
DELETED=$(find "$BACKUP_DIR" -name "busgo_*.sql.gz" -mtime +"$RETENTION_DAYS" -print -delete | wc -l)
if [ "$DELETED" -gt 0 ]; then
    echo "[$(date)] ${DELETED} sauvegarde(s) supprimée(s) (rétention ${RETENTION_DAYS}j)"
fi

echo "[$(date)] OK — sauvegarde disponible : ${BACKUP_FILE}"
