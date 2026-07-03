# 🌐 ISK Client Setup & Monitoring Deployment Guide

This guide details the end-to-end, production-grade configuration required to monitor client **`ISK`**'s infrastructure from the central **Hyderabad NOC Server** using **Headscale VPN** and an **agentless Zabbix Proxy** deployment.

---

## 🗺️ 1. Network Topology & Architecture

Below is the visual network topology showing how data flows securely from the client's internal Windows Server to the NOC dashboard via the encrypted peer-to-peer WireGuard tunnel.

![ISK Network Topology](assets/isk_network_architecture.svg)

---

## 🔌 2. Central ISP Router Configuration (Hyderabad NOC Site)

Since the central site uses a standard ISP router instead of a dedicated hardware firewall, we must configure **Port Forwarding (NAT / Virtual Server)** to expose Headscale registration endpoints safely.

### **Step 1: Access Router Gateway**
1. Open a browser on the local NOC network and navigate to the router gateway IP (typically `http://192.168.1.1` or `http://192.168.1.254`).
2. Log in using the administrative credentials.

### **Step 2: Add Port Forwarding (NAT / Virtual Server) Rules**
Navigate to the **Port Forwarding / Virtual Server / NAT** tab and create the following two rules:

| Rule Name | External Port | Internal Port | Protocol | Internal Target IP (NOC VM) |
| :--- | :--- | :--- | :--- | :--- |
| **Headscale Web Control** | `8080` | `8080` | **TCP** | `192.168.1.178` |
| **Tailscale WireGuard P2P** | `41641` | `41641` | **UDP** | `192.168.1.178` |

> [!IMPORTANT]
> Do **NOT** port forward `10051 (TCP)` for Zabbix Server or `5432` for PostgreSQL to the public internet. These ports must remain strictly private, reachable only within the secure Headscale VPN tunnel network.

---

## 📡 3. Zabbix Proxy VM Sizing & Installation (Client Office Site)

The local Zabbix Proxy acts as an on-premise monitoring gateway. It collects metrics from the local network and relays them securely to the NOC.

### **A. Hardware Virtual Machine (VM) Sizing**
Provide a local VM in the client's LAN with the following specifications:
*   **Operating System**: Ubuntu Server 22.04 LTS (x86_64)
*   **Processor (vCPU)**: 1 core (2 cores recommended)
*   **Memory (RAM)**: 1 GB (2 GB recommended)
*   **Storage (SSD)**: 20 GB (SSD for database writes)
*   **Network**: Dedicated local IP address (e.g., `192.168.10.50`)

---

### **B. Step-by-Step CLI Installation Commands**

Log in to the client's Proxy VM via SSH and execute the following commands in order:

#### **Step 1: Connect Tailscale to the NOC Headscale Server**
```bash
# 1. Install Tailscale client
curl -fsSL https://tailscale.com/install.sh | sh

# 2. Authenticate directly to the NOC public static IP
sudo tailscale up --login-server http://122.170.96.200:8080 --auth-key <YOUR_HEADSCALE_AUTH_KEY>

# 3. Verify connection and note down the assigned VPN IP (e.g. 100.64.0.3)
tailscale ip -4
```

#### **Step 2: Add Zabbix Official Repository (Version 7.0 LTS)**
```bash
# 1. Download repository config package
wget https://repo.zabbix.com/zabbix/7.0/ubuntu/pool/main/z/zabbix-release/zabbix-release_7.0-1+ubuntu22.04_all.deb

# 2. Install repository config
sudo dpkg -i zabbix-release_7.0-1+ubuntu22.04_all.deb

# 3. Update repository packages cache list
sudo apt update
```

#### **Step 3: Install Zabbix Proxy with SQLite3 Database**
```bash
sudo apt install zabbix-proxy-sqlite3 -y
```

#### **Step 4: Initialize the SQLite3 Database File**
```bash
# Create the directory for database
sudo mkdir -p /var/lib/zabbix

# Assign correct owner permissions to Zabbix daemon user
sudo chown -R zabbix:zabbix /var/lib/zabbix
```

#### **Step 5: Edit the Proxy Configuration file**
```bash
sudo nano /etc/zabbix/zabbix_proxy.conf
```
Update the following parameters inside the configuration file:
```ini
Server=100.64.0.2                       # Zabbix NOC Server VPN IP
Hostname=Proxy_ISK                      # Unique proxy ID matching Zabbix Web UI
DBName=/var/lib/zabbix/zabbix_proxy.db   # File path for SQLite3 DB
ProxyMode=0                             # 0 = Active mode (Proxy pushes data to Server)
```

#### **Step 6: Start and Enable the Proxy Daemon**
```bash
sudo systemctl enable zabbix-proxy
sudo systemctl start zabbix-proxy
sudo systemctl status zabbix-proxy
```

---

## 💻 4. Agentless Windows Server Setup (SNMP Monitoring)

To monitor the Windows Server (e.g., Domain Controller / Database Server at `192.168.10.100`) without installing agent software:

### **A. Enable and Configure SNMP on the Windows Server**
1. Log in to the Windows Server as an Administrator.
2. Open **Server Manager** ➔ Click **Add Roles and Features**.
3. Under the **Features** list, select **`SNMP Service`** and install it.
4. Open the Windows **Services** console (`services.msc`).
5. Find **SNMP Service**, right-click, and select **Properties**.
6. Navigate to the **`Security`** tab:
   *   Under **Accepted community names**, click **Add**.
   *   Set Community Name: **`isk_noc_public`** and Community Rights: **`READ ONLY`**.
   *   Select **`Accept SNMP packets from these hosts`**, click **Add**, and enter the **Client Proxy VM's Local LAN IP** (`192.168.10.50`).
7. Click **Apply** and restart the SNMP Service.

---

### **B. Register the Host in Zabbix Web UI**
1. Navigate to **Data collection** ➔ **Hosts** ➔ **Create host**.
2. Set configuration values:
   *   **Host name**: `ISK_Windows_Server`
   *   **Templates**: Link `Windows by SNMP`
   *   **Host Groups**: Select `Tenant - ISK`
   *   **Monitored by proxy**: Select `Proxy_ISK`
   *   **Interfaces**: Click **Add** ➔ Select **`SNMP`** ➔ Enter Local IP `192.168.10.100` and Port `161`.
3. Navigate to **`Macros`** tab ➔ Click **Inherited and host macros**:
   *   Add macro: `{$SNMP_COMMUNITY}` ➔ Value: `isk_noc_public`.
4. Click **Add**.

Within 60 seconds, Zabbix Proxy will poll the server on port 161 and send metrics securely to the NOC server!
