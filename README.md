# Zabbix NOC & Multi-Tenant Monitoring System 📊

Developed and optimized for production environments. Designed for secure, zero-port-exposure monitoring of remote networks.

> **Project NOC Architect / Author:** `Saichandram Sadhu` 🚀

---

## 🎯 Animated Network Workflow (How Data Flows)
Below is the live animation of how remote Zabbix Agents securely route data to your Central Zabbix NOC Server via the Zabbix Proxy and Tailscale Encrypted Mesh.

<div align="center">
  <img src="network_flow.svg" width="100%" max-width="800px" alt="Zabbix NOC Network Flow Animation" />
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
