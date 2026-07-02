#!/bin/bash
# Zabbix Server & Headscale VPN Backup Script
# Automatically dumps DB, compresses keys, and creates a unified backup archive.

set -e

# --- CONFIGURATION ---
BACKUP_DIR="/home/saichandram/zabbix_backups"
HEADSCALE_DIR="/home/saichandram/headscale"
DB_NAME="zabbix"
DB_USER="zabbix"
DB_PASS="StrongPassword@123"
DB_HOST="localhost"

# Format backup name with date and time
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TEMP_DIR="/tmp/zabbix_backup_$TIMESTAMP"
BACKUP_FILE="$BACKUP_DIR/noc_backup_$TIMESTAMP.tar.gz"

echo "=== [1/5] Creating directories ==="
mkdir -p "$BACKUP_DIR"
mkdir -p "$TEMP_DIR"

echo "=== [2/5] Exporting Zabbix PostgreSQL Database ==="
export PGPASSWORD="$DB_PASS"
pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -F c -f "$TEMP_DIR/zabbix_db.dump"
unset PGPASSWORD

echo "=== [3/5] Backing up Headscale configuration and security keys ==="
mkdir -p "$TEMP_DIR/headscale"
# Copy only configuration files and security keys (excluding logs and large db file)
if [ -d "$HEADSCALE_DIR/config" ]; then
    cp -r "$HEADSCALE_DIR/config" "$TEMP_DIR/headscale/"
    # Remove large/temporary databases or logs if they exist inside the config backup folder
    rm -f "$TEMP_DIR/headscale/config/db.sqlite" "$TEMP_DIR/headscale/config/db.sqlite-journal"
fi
if [ -d "$HEADSCALE_DIR/headplane" ]; then
    cp -r "$HEADSCALE_DIR/headplane" "$TEMP_DIR/headscale/"
fi
# Copy docker-compose file
if [ -f "$HEADSCALE_DIR/docker-compose.yml" ]; then
    cp "$HEADSCALE_DIR/docker-compose.yml" "$TEMP_DIR/headscale/"
fi

echo "=== [4/5] Compressing all backup components into unified archive ==="
tar -czf "$BACKUP_FILE" -C "$TEMP_DIR" .
rm -rf "$TEMP_DIR"

echo "=== [5/5] Backup created successfully ==="
echo "Local Backup File: $BACKUP_FILE"
ls -lh "$BACKUP_FILE"

# --- CLOUD BACKUP SYNC OPTION (OPTIONAL) ---
# If you want to automatically upload this backup to the cloud (Google Drive, Dropbox, AWS S3, etc.),
# uncomment the lines below and configure rclone:
#
# if command -v rclone &> /dev/null; then
#     echo "=== [Sync] Uploading backup to Cloud storage via rclone ==="
#     rclone copy "$BACKUP_FILE" "mycloudremote:zabbix-backups"
#     echo "=== [Sync] Upload complete ==="
# else
#     echo "=== [Sync Skip] rclone is not installed. Keeping local backup only ==="
# fi
