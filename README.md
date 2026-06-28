# Zabbix NOC & Multi-Tenant Monitoring System 📊

Welcome to the automated Zabbix 7.0 monitoring infrastructure repository. Developed and optimized for production environments.

> **Project Architect / Author:** `Saichandram Sadhu` 🚀

---

## 🛠️ 1-Click Quick Start (Dockerized Environment)
This project is fully dockerized. To start a clean, pre-configured Zabbix monitoring setup along with the custom **Companies Management (MSP Multi-Tenant)** module loaded automatically:

```bash
# 1. Clone the repository
git clone https://github.com/saichandram-sadhu/Zabbix.git
cd Zabbix

# 2. Start the stack
docker compose up -d
```
Access the Zabbix Web UI at: `http://localhost` (or `https://localhost` with default ssl).

---

## 🔗 Zabbix Proxy Network Architecture & Routing
When monitoring remote networks or customer environments (like VMware systems in dynamic home/office networks), direct connection is blocked by ISPs using **CGNAT (Carrier-Grade NAT)**. We bypass this limitation securely using a **Tailscale Mesh Overlay Tunnel**.

### How Active Proxy Routing Works:
```mermaid
graph TD
    subgraph Server Network (Zabbix NOC)
        ZServer[Zabbix Server VM<br>Tailscale IP: 100.124.123.38]
    end

    subgraph Client Network (Remote Site)
        ZProxy[Zabbix Proxy VM<br>Tailscale IP: 100.71.60.63]
        Agent1[Windows Host Agent]
        Agent2[Linux Host Agent]
        
        Agent1 -->|Local Port 10051| ZProxy
        Agent2 -->|Local Port 10051| ZProxy
    end

    ZProxy ===|Secure Tailscale Overlay Tunnel| ZServer
```

---

## 📂 Features & Integration Methods

<details>
<summary><b>1. Zabbix Active vs. Passive Proxy Modes</b> (Click to expand)</summary>

* **Active Proxy (Used Here)**:
  - The proxy connects directly to the Zabbix Server's port `10051`.
  - It pulls configuration details, buffers monitored metrics locally, and pushes the data back to the server.
  - **Best for**: Environments behind NAT / CGNAT.
* **Passive Proxy**:
  - The Zabbix Server initiates connections to the proxy's port `10051` to fetch metrics.
  - **Best for**: Direct access networks where the proxy has a public IP/Routing.
</details>

<details>
<summary><b>2. Bypassing CGNAT via Tailscale</b> (Click to expand)</summary>

1. **Install Tailscale** on both Zabbix Server VM and Proxy VM:
   ```bash
   curl -fsSL https://tailscale.com/install.sh | sh
   sudo tailscale up --accept-dns=false
   ```
2. **Authenticate** both machines to the same Tailscale network.
3. Configure the Zabbix Proxy container/service with:
   - `ZBX_SERVER_HOST = 100.124.123.38` (Your Server's Tailscale Virtual IP)
   - `ZBX_HOSTNAME = Proxy_Clientbeta`
4. The proxy will bypass all router firewalls and CGNAT dynamically!
</details>

<details>
<summary><b>3. Custom MSP Companies Management Module</b> (Click to expand)</summary>

Inside the `companies/` folder, you will find our custom Zabbix PHP UI Module:
- **Interactive KPIs**: Quick count cards that link directly to Zabbix's user, host, and group configurations.
- **Dynamic Host Status Grid**: Clickable hosts showing availability metrics (`ZBX` / `SNMP`) with responsive transitions and pointer events.
- **Live Logs and Incident Panel**: Lists active problems and links directly to native Zabbix event detail views.
</details>

---

## 🛡️ Production Hardening Standards Applied
This environment has been hardened following strict security protocols:
1. **Host-Level Firewall**: Enforced via `UFW` (only TCP ports `22`, `443`, and `10051` allowed).
2. **Brute Force Defense**: `Fail2ban` jails enabled on SSH and Apache HTTP auth.
3. **Apache Hardening**: Server signature tokens hidden, custom Security Headers (`HSTS`, `CSP`, `X-Frame-Options SAMEORIGIN`) enabled.
4. **PHP & Database Configuration**: Disabled PHP execution exposure (`expose_php = Off`), enforced HTTP-only secure cookie flags, and confined database servers (`PostgreSQL` / `MySQL`) to listen only on localhost.
5. **Agent Security**: Disabled remote execution parameters by setting `DenyKey=system.run[*]` inside the agent configuration.
