#!/bin/bash
# Zabbix & Headscale VPN Backup Command Center (Interactive CLI Tool)
# Allows interactive creation and restoration of backups across Local, Physical Hardware, and Cloud destinations.

set -e

# Colors for UI
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Ensure script is run as root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Error: Please run this script as root (use sudo).${NC}"
  exit 1
fi

LOCAL_BACKUP_DIR="/home/saichandram/zabbix_backups"
HEADSCALE_DIR="/home/saichandram/headscale"
DB_NAME="zabbix"
DB_USER="zabbix"
DB_PASS="StrongPassword@123"
DB_HOST="localhost"

mkdir -p "$LOCAL_BACKUP_DIR"

show_header() {
  clear
  echo -e "${CYAN}====================================================${NC}"
  echo -e "${CYAN}🛡️  ZABBIX SERVER & HEADSCALE VPN BACKUP MANAGER     ${NC}"
  echo -e "${CYAN}====================================================${NC}"
}

wait_key() {
  echo -e "\nPress any key to return to main menu..."
  read -n 1 -s -r
}

# --- BACKUP LOGIC ---
perform_backup() {
  show_header
  echo -e "${YELLOW}--- CREATE NEW BACKUP ---${NC}\n"
  
  TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
  TEMP_DIR="/tmp/noc_backup_temp_$TIMESTAMP"
  LOCAL_FILE="$LOCAL_BACKUP_DIR/noc_backup_$TIMESTAMP.tar.gz"
  
  mkdir -p "$TEMP_DIR/headscale"
  
  echo -e "${BLUE}[1/4] Dumping Zabbix PostgreSQL database...${NC}"
  export PGPASSWORD="$DB_PASS"
  pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -F c -f "$TEMP_DIR/zabbix_db.dump"
  unset PGPASSWORD
  
  echo -e "${BLUE}[2/4] Copying Headscale configuration and security keys...${NC}"
  if [ -d "$HEADSCALE_DIR/config" ]; then
    cp -r "$HEADSCALE_DIR/config" "$TEMP_DIR/headscale/"
    # Exclude volatile db files from configs
    rm -f "$TEMP_DIR/headscale/config/db.sqlite" "$TEMP_DIR/headscale/config/db.sqlite-journal"
  fi
  if [ -d "$HEADSCALE_DIR/headplane" ]; then
    cp -r "$HEADSCALE_DIR/headplane" "$TEMP_DIR/headscale/"
  fi
  if [ -f "$HEADSCALE_DIR/docker-compose.yml" ]; then
    cp "$HEADSCALE_DIR/docker-compose.yml" "$TEMP_DIR/headscale/"
  fi
  
  echo -e "${BLUE}[3/4] Compressing archive...${NC}"
  tar -czf "$LOCAL_FILE" -C "$TEMP_DIR" .
  rm -rf "$TEMP_DIR"
  echo -e "${GREEN}Local backup created successfully: $LOCAL_FILE${NC}\n"
  
  # Destination Choice
  echo -e "${YELLOW}Where would you like to copy/save this backup?${NC}"
  echo -e "1) Local Disk Only (Keep in $LOCAL_BACKUP_DIR)"
  echo -e "2) Physical Hardware / Network Share (e.g. USB Drive, NAS Mount)"
  echo -e "3) Cloud Storage (Google Drive, AWS S3, Dropbox via rclone)"
  read -p "Select Destination (1-3): " dest_choice
  
  case $dest_choice in
    1)
      echo -e "${GREEN}Backup remains safely stored in local disk directory.${NC}"
      ;;
    2)
      echo -e "\n${CYAN}--- Physical / External Drive Copy ---${NC}"
      echo -e "Enter the mount path to your USB Drive or NAS directory (e.g., /mnt/usb or /mnt/nas):"
      read -p "Path: " phys_path
      if [ -d "$phys_path" ]; then
        cp "$LOCAL_FILE" "$phys_path/"
        echo -e "${GREEN}Successfully copied to external hardware storage: $phys_path/$(basename $LOCAL_FILE)${NC}"
      else
        echo -e "${RED}Error: Path '$phys_path' is not a valid directory! Keeping local backup only.${NC}"
      fi
      ;;
    3)
      echo -e "\n${CYAN}--- Cloud Upload via Rclone ---${NC}"
      if ! command -v rclone &> /dev/null; then
        echo -e "${RED}Error: rclone is not installed. Run 'apt install rclone' and configure it first.${NC}"
      else
        echo -e "Available Cloud Remotes:"
        rclone listremotes || true
        read -p "Enter the remote name (e.g., gdrive or s3): " remote_name
        read -p "Enter cloud folder name (optional, e.g., backups): " cloud_folder
        
        echo -e "${BLUE}Uploading backup to cloud remote '${remote_name}'...${NC}"
        if [ -n "$cloud_folder" ]; then
          rclone copy "$LOCAL_FILE" "${remote_name}:${cloud_folder}"
        else
          rclone copy "$LOCAL_FILE" "${remote_name}:"
        fi
        echo -e "${GREEN}Cloud upload completed successfully!${NC}"
      fi
      ;;
    *)
      echo -e "${RED}Invalid option. Keeping local backup only.${NC}"
      ;;
  esac
  wait_key
}

# --- RESTORE LOGIC ---
perform_restore() {
  show_header
  echo -e "${YELLOW}--- RESTORE FROM BACKUP ---${NC}\n"
  
  echo -e "Select Backup Source Location:"
  echo -e "1) Local Disk Storage"
  echo -e "2) Physical Hardware / Network Share (e.g., USB, NAS)"
  echo -e "3) Cloud Storage (Download from Cloud)"
  read -p "Select Source (1-3): " src_choice
  
  RESTORE_FILE=""
  
  case $src_choice in
    1)
      echo -e "\nAvailable Local Backups in $LOCAL_BACKUP_DIR:"
      local backups=($(ls $LOCAL_BACKUP_DIR/*.tar.gz 2>/dev/null || true))
      if [ ${#backups[@]} -eq 0 ]; then
        echo -e "${RED}No backups found in $LOCAL_BACKUP_DIR.${NC}"
        wait_key
        return
      fi
      for i in "${!backups[@]}"; do
        echo -e "$((i+1))) $(basename "${backups[$i]}")"
      done
      read -p "Select backup number to restore: " bk_num
      bk_idx=$((bk_num-1))
      if [ -n "${backups[$bk_idx]}" ]; then
        RESTORE_FILE="${backups[$bk_idx]}"
      else
        echo -e "${RED}Invalid backup selection.${NC}"
        wait_key
        return
      fi
      ;;
    2)
      echo -e "\nEnter the full file path to the backup file (e.g., /mnt/usb/noc_backup_xxx.tar.gz):"
      read -p "Path: " custom_path
      if [ -f "$custom_path" ]; then
        RESTORE_FILE="$custom_path"
      else
        echo -e "${RED}Error: File not found at '$custom_path'.${NC}"
        wait_key
        return
      fi
      ;;
    3)
      if ! command -v rclone &> /dev/null; then
        echo -e "${RED}Error: rclone is not installed.${NC}"
        wait_key
        return
      fi
      echo -e "\nAvailable Cloud Remotes:"
      rclone listremotes || true
      read -p "Enter the remote name: " remote_name
      read -p "Enter remote folder/bucket path (optional): " remote_path
      
      echo -e "\nFiles available on cloud remote:"
      rclone lsf "${remote_name}:${remote_path}" || true
      read -p "Enter exact backup filename to download: " cloud_file
      
      DOWNLOAD_PATH="$LOCAL_BACKUP_DIR/$cloud_file"
      echo -e "${BLUE}Downloading '$cloud_file' from cloud remote...${NC}"
      rclone copy "${remote_name}:${remote_path}/${cloud_file}" "$LOCAL_BACKUP_DIR/"
      RESTORE_FILE="$DOWNLOAD_PATH"
      ;;
    *)
      echo -e "${RED}Invalid source selection.${NC}"
      wait_key
      return
      ;;
  esac
  
  if [ -n "$RESTORE_FILE" ] && [ -f "$RESTORE_FILE" ]; then
    echo -e "\n${RED}⚠️  WARNING: This will overwrite Zabbix DB and Headscale config!${NC}"
    read -p "Are you absolutely sure you want to proceed? (y/N): " confirm_restore
    if [[ ! $confirm_restore =~ ^[Yy]$ ]]; then
      echo -e "${BLUE}Restore operation cancelled.${NC}"
      wait_key
      return
    fi
    
    TEMP_EXTRACT="/tmp/noc_restore_extract"
    rm -rf "$TEMP_EXTRACT"
    mkdir -p "$TEMP_EXTRACT"
    
    echo -e "${BLUE}[1/6] Extracting backup archive...${NC}"
    tar -xzf "$RESTORE_FILE" -C "$TEMP_EXTRACT"
    
    echo -e "${BLUE}[2/6] Stopping monitoring & VPN services...${NC}"
    systemctl stop zabbix-server || true
    if [ -d "$HEADSCALE_DIR" ]; then
      cd "$HEADSCALE_DIR"
      docker compose down || true
    fi
    
    echo -e "${BLUE}[3/6] Dropping and Re-creating PostgreSQL database...${NC}"
    export PGPASSWORD="220831"
    sudo -u postgres psql -h "$DB_HOST" -c "DROP DATABASE IF EXISTS $DB_NAME;"
    sudo -u postgres psql -h "$DB_HOST" -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"
    unset PGPASSWORD
    
    echo -e "${BLUE}[4/6] Restoring Database Dump...${NC}"
    export PGPASSWORD="$DB_PASS"
    pg_restore -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -v "$TEMP_EXTRACT/zabbix_db.dump"
    unset PGPASSWORD
    
    echo -e "${BLUE}[5/6] Restoring Headscale keys and configuration files...${NC}"
    if [ -d "$TEMP_EXTRACT/headscale" ]; then
      mkdir -p "$HEADSCALE_DIR"
      cp -r "$TEMP_EXTRACT/headscale/"* "$HEADSCALE_DIR/"
    fi
    
    echo -e "${BLUE}[6/6] Restarting all services...${NC}"
    if [ -d "$HEADSCALE_DIR" ]; then
      cd "$HEADSCALE_DIR"
      docker compose up -d
    fi
    systemctl start zabbix-server
    systemctl restart apache2
    
    rm -rf "$TEMP_EXTRACT"
    echo -e "${GREEN}Restore completed successfully! System is fully operational.${NC}"
  fi
  wait_key
}

# --- CLOUD CONFIGURATION ---
configure_cloud() {
  show_header
  echo -e "${YELLOW}--- CONFIGURE CLOUD STORAGE (RCLONE) ---${NC}\n"
  echo -e "This wizard will launch rclone config. Follow the prompts to add remotes."
  echo -e "For Google Drive, AWS S3, OneDrive, or Dropbox, follow the interactive setup.\n"
  
  if ! command -v rclone &> /dev/null; then
    echo -e "${BLUE}Installing rclone first...${NC}"
    apt update && apt install rclone -y
  fi
  
  rclone config
  wait_key
}

# --- MAIN LOOP ---
while true; do
  show_header
  echo -e "Select an operation to perform:"
  echo -e "1) ${GREEN}Create Backup${NC} (Backup DB + VPN Keys to Local/Physical/Cloud)"
  echo -e "2) ${RED}Restore Backup${NC} (Restore DB + VPN Keys from Local/Physical/Cloud)"
  echo -e "3) ${YELLOW}Configure Cloud Connection${NC} (Google Drive, S3, Dropbox, etc.)"
  echo -e "4) Exit Manager"
  echo -e "===================================================="
  read -p "Enter Choice (1-4): " main_choice
  
  case $main_choice in
    1) perform_backup ;;
    2) perform_restore ;;
    3) configure_cloud ;;
    4)
      clear
      echo -e "${GREEN}Exiting Backup Manager. Keep monitoring!${NC}"
      exit 0
      ;;
    *)
      echo -e "${RED}Invalid selection. Please choose 1 to 4.${NC}"
      sleep 1
      ;;
  esac
done
