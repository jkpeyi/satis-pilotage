#!/usr/bin/env bash
# Daily encrypted MySQL dump, 30-day retention. Run from the host's cron, e.g.:
#   30 2 * * * /opt/satis-pilotage/docker/backup.sh >> /var/log/satis-backup.log 2>&1
# Requires BACKUP_DIR and a passphrase file readable only by root (BACKUP_PASSPHRASE_FILE).
set -euo pipefail
cd "$(dirname "$0")/.."

BACKUP_DIR="${BACKUP_DIR:-/var/backups/satis-pilotage}"
BACKUP_PASSPHRASE_FILE="${BACKUP_PASSPHRASE_FILE:-/root/.satis-backup-passphrase}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

mkdir -p "$BACKUP_DIR"
file="$BACKUP_DIR/satis-$(date +%Y%m%d-%H%M%S).sql.gz.enc"

docker compose exec -T mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
    | gzip \
    | openssl enc -aes-256-cbc -pbkdf2 -salt -pass "file:$BACKUP_PASSPHRASE_FILE" -out "$file"

find "$BACKUP_DIR" -name 'satis-*.sql.gz.enc' -mtime +"$RETENTION_DAYS" -delete
echo "Sauvegarde créée : $file"

# Restore:
#   openssl enc -d -aes-256-cbc -pbkdf2 -pass file:$BACKUP_PASSPHRASE_FILE -in FILE | gunzip \
#     | docker compose exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
