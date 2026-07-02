#!/bin/bash
# Zabbix Server & Headscale VPN Restore Script
# Automatically restores the PostgreSQL database and Headscale security keys from a single backup tar.gz archive.

set -e

# Check if a backup file argument is passed
if [ -z "$1" ]; then
    echo "Usage: sudo $0 <path_to_backup_file.tar.gz>"
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Confirm with user
read -p "WARNING: This will drop the current Zabbix database and replace all Headscale configurations. Are you sure? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Restore cancelled."
    exit 1
fi

# --- CONFIGURATION ---
HEADSCALE_DIR="/home/saichandram/headscale"
DB_NAME="zabbix"
DB_USER="zabbix"
DB_PASS="StrongPassword@123"
DB_HOST="localhost"
TEMP_DIR="/tmp/zabbix_restore_extract"

echo "=== [1/7] Extracting backup file ==="
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"
tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"

echo "=== [2/7] Stopping Zabbix Server & Docker Containers ==="
systemctl stop zabbix-server || true
if [ -d "$HEADSCALE_DIR" ]; then
    cd "$HEADSCALE_DIR"
    docker compose down || true
fi

echo "=== [3/7] Re-creating PostgreSQL Database ==="
# Connect as postgres admin user to drop and recreate the database
export PGPASSWORD="220831" # Using system postgres admin sudo privileges
sudo -u postgres psql -h "$DB_HOST" -c "DROP DATABASE IF EXISTS $DB_NAME;"
sudo -u postgres psql -h "$DB_HOST" -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"
unset PGPASSWORD

echo "=== [4/7] Restoring Zabbix Database Dump ==="
export PGPASSWORD="$DB_PASS"
pg_restore -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -v "$TEMP_DIR/zabbix_db.dump"
unset PGPASSWORD

echo "=== [5/7] Restoring Headscale Configurations & Master Keys ==="
if [ -d "$TEMP_DIR/headscale" ]; then
    mkdir -p "$HEADSCALE_DIR"
    cp -r "$TEMP_DIR/headscale/"* "$HEADSCALE_DIR/"
    echo "Headscale configurations restored."
fi

echo "=== [6/7] Restarting Services ==="
if [ -d "$HEADSCALE_DIR" ]; then
    cd "$HEADSCALE_DIR"
    docker compose up -d
fi
systemctl start zabbix-server
systemctl restart apache2

echo "=== [7/7] Cleaning up temporary files ==="
rm -rf "$TEMP_DIR"

echo "=== Restore completed successfully! ==="
systemctl status zabbix-server --no-pager | grep "Active:"
docker ps
