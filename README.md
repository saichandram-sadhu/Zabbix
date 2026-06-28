# Zabbix NOC & Multi-Tenant Monitoring System 📊

Developed and optimized for production environments. Designed for secure, zero-port-exposure monitoring of remote networks.

> **Project NOC Architect / Author:** `Saichandram Sadhu` 🚀

---

## 🎯 Animated Network Workflow (How Data Flows)
Below is the live animation of how remote Zabbix Agents securely route data to your Central Zabbix NOC Server via the Zabbix Proxy and Tailscale Encrypted Mesh.

<div align="center">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 280" width="100%" max-width="800px">
    <defs>
      <linearGradient id="grad-server" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#0275d8;stop-opacity:1" />
        <stop offset="100%" style="stop-color:#025aa5;stop-opacity:1" />
      </linearGradient>
      <linearGradient id="grad-proxy" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#10b981;stop-opacity:1" />
        <stop offset="100%" style="stop-color:#059669;stop-opacity:1" />
      </linearGradient>
      <linearGradient id="grad-client" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#64748b;stop-opacity:1" />
        <stop offset="100%" style="stop-color:#334155;stop-opacity:1" />
      </linearGradient>
    </defs>
    <style>
      @keyframes pulse {
        0% { transform: scale(1); filter: drop-shadow(0 0 2px rgba(2, 117, 216, 0.4)); }
        50% { transform: scale(1.03); filter: drop-shadow(0 0 10px rgba(2, 117, 216, 0.7)); }
        100% { transform: scale(1); filter: drop-shadow(0 0 2px rgba(2, 117, 216, 0.4)); }
      }
      @keyframes flow-active {
        0% { stroke-dashoffset: 24; }
        100% { stroke-dashoffset: 0; }
      }
      .node-server { fill: url(#grad-server); animation: pulse 3s infinite ease-in-out; transform-origin: 650px 140px; }
      .node-proxy { fill: url(#grad-proxy); animation: pulse 2.5s infinite ease-in-out; transform-origin: 400px 140px; }
      .node-client { fill: url(#grad-client); }
      .flow-line { stroke: #38bdf8; stroke-width: 3; stroke-dasharray: 8, 4; animation: flow-active 1.5s linear infinite; }
      .label-text { font-family: 'Segoe UI', -apple-system, sans-serif; font-size: 13px; font-weight: bold; fill: #f8fafc; text-anchor: middle; }
      .sub-text { font-family: 'Segoe UI', -apple-system, sans-serif; font-size: 11px; fill: #94a3b8; text-anchor: middle; }
    </style>
    
    <rect width="100%" height="100%" fill="#0f172a" rx="12"/>
    
    <!-- Flow Lines -->
    <path d="M 150 140 L 400 140" class="flow-line" />
    <path d="M 400 140 L 650 140" class="flow-line" />
    
    <!-- Client/Agent Node -->
    <circle cx="150" cy="140" r="50" class="node-client" stroke="#475569" stroke-width="2" />
    <text x="150" y="130" class="label-text">Client VMs / Hosts</text>
    <text x="150" y="150" class="sub-text">Zabbix Agents</text>
    <text x="150" y="165" class="sub-text">IP: 192.168.87.10</text>
    
    <!-- Proxy Node (Tailscale Client) -->
    <circle cx="400" cy="140" r="55" class="node-proxy" stroke="#047857" stroke-width="2" />
    <text x="400" y="130" class="label-text">Zabbix Proxy</text>
    <text x="400" y="150" class="sub-text">Docker Container</text>
    <text x="400" y="165" class="sub-text">Tailscale: 100.71.60.63</text>
    
    <!-- NOC Zabbix Server Node -->
    <circle cx="650" cy="140" r="60" class="node-server" stroke="#1d4ed8" stroke-width="2" />
    <text x="650" y="130" class="label-text">Zabbix Server</text>
    <text x="650" y="150" class="sub-text">NOC Central</text>
    <text x="650" y="165" class="sub-text">Tailscale: 100.124.123.38</text>
  </svg>
</div>

---

## 📖 A-to-Z Concept Guide: What is Zabbix & How it Works

### 1. What is Zabbix?
Zabbix is an enterprise-class open-source monitoring solution. It monitors the health, performance, and availability of network servers, virtual machines, databases, cloud resources, and network switches in real time.

### 2. What is a Zabbix Proxy and Why do we need it?
A **Zabbix Proxy** is a lightweight local monitoring daemon. Instead of Zabbix Server connecting directly to 100 remote servers, you install **one Proxy** inside the remote network:
* **Security**: Only the Proxy needs an outbound internet connection to talk to the Zabbix Server. Remote servers remain completely private on the local LAN.
* **Network Efficiency**: The Proxy collects data locally, buffers it, compresses it, and sends it periodically.
* **Resilience**: If the internet connection drops between the client and Zabbix Server, the Proxy continues monitoring, stores the metrics in its database, and uploads them when the connection is restored (Zero data loss).

### 3. Zabbix Proxy Working Modes:
* **Active Mode (Recommended)**: The Zabbix Proxy initiates connection pushes to Zabbix Server port `10051`. No router configuration or port forwarding is required on the Client's router.
* **Passive Mode**: Zabbix Server initiates connections to the Zabbix Proxy's port `10051`. This requires the Proxy's network to have a public IP and open ports.

---

## 🔒 Default Credentials & Configuration Secrets

The default configurations built into this project are:
* **Zabbix Web UI**:
  - **URL**: `http://<your-ip-or-domain>` (redirected automatically to HTTPS if configured)
  - **Username**: `Admin`
  - **Password**: `zabbix`
* **PostgreSQL Database**:
  - **Database Name**: `zabbix`
  - **Database Username**: `zabbix`
  - **Database Password**: `StrongPassword@123`

---

## 🛠️ Step-by-Step: How to Change Default Passwords (Hardening)

When deploying to production, you **MUST** change all default passwords to secure credentials.

### Step 1: Change PostgreSQL Password in `docker-compose.yml`
Open `docker-compose.yml` and replace `StrongPassword@123` with your secure custom database password in **all 3 services**:

```yaml
# In docker-compose.yml:
services:
  zabbix-db:
    environment:
      - POSTGRES_PASSWORD=YourNewSecureDBPassword  # 1. Change here

  zabbix-server:
    environment:
      - POSTGRES_PASSWORD=YourNewSecureDBPassword  # 2. Change here

  zabbix-web:
    environment:
      - POSTGRES_PASSWORD=YourNewSecureDBPassword  # 3. Change here
```

Apply the changes by running:
```bash
docker compose down && docker compose up -d
```

### Step 2: Change Zabbix Web UI Admin Password
1. Log in to the Zabbix Web Interface using `Admin` / `zabbix`.
2. Go to **`Administration`** (or **`Users`** in Zabbix 7.0) -> **`Users`**.
3. Click on the user **`Admin`**.
4. Click on **`Change password`**, type your secure new password, and click **`Update`**.

---

## 🔗 How to Connect a Remote Client (CGNAT Bypass Workflow)

If a remote client (e.g. `ClientBeta`) is located at a different network behind a restricted router (CGNAT), follow this dynamic configuration:

### 1. Tailscale Installation on Both Sides
Install Tailscale on both your Zabbix Server VM and the Client's Host VM:
```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up --accept-dns=false
```
Authenticate both devices under the same Tailscale account. You will receive private `100.x.x.x` virtual IPs.
* **Server Tailscale IP**: `100.124.123.38` (example)
* **Proxy Tailscale IP**: `100.71.60.63` (example)

### 2. Configure the Zabbix Proxy (Docker on Client side)
On the remote VM, launch the proxy container pointing to the server's Tailscale IP:
```bash
docker run --name zabbix-proxy -d \
  -e ZBX_SERVER_HOST="100.124.123.38" \
  -e ZBX_HOSTNAME="Proxy_Clientbeta" \
  --net=host \
  --restart unless-stopped \
  zabbix/zabbix-proxy-sqlite3:alpine-7.0-latest
```

### 3. Configure the Zabbix Agents on Client VMs
On each remote Windows or Linux VM running inside ClientBeta's network, install Zabbix Agent and configure the config file (`zabbix_agentd.conf`):
```ini
Server=192.168.87.20        # Local IP of the Zabbix Proxy VM
ServerActive=192.168.87.20  # Local IP of the Zabbix Proxy VM
Hostname=Windows_Server_ClientBeta  # Exactly matching Zabbix Frontend host name
```
Restart the agent service, and data will flow seamlessly through the proxy tunnel!
