#!/usr/bin/env python3
import sys
import json
import urllib.request
import urllib.error

ZABBIX_URL = "http://localhost/zabbix/api_jsonrpc.php"
DEFAULT_ADMIN_USER = "Admin"
DEFAULT_ADMIN_PASS = "zabbix"

def api_call(method, params, auth_token=None):
    payload = {
        "jsonrpc": "2.0",
        "method": method,
        "params": params,
        "id": 1
    }
    if auth_token:
        payload["auth"] = auth_token
        
    data = json.dumps(payload).encode('utf-8')
    req = urllib.request.Request(
        ZABBIX_URL, 
        data=data, 
        headers={'Content-Type': 'application/json'}
    )
    try:
        with urllib.request.urlopen(req) as response:
            res = json.loads(response.read().decode('utf-8'))
            if 'error' in res:
                print(f"Error in API call {method}: {res['error']['data']} (Message: {res['error']['message']})", file=sys.stderr)
                sys.exit(1)
            return res.get('result')
    except urllib.error.URLError as e:
        print(f"Failed to connect to Zabbix API at {ZABBIX_URL}: {e.reason}", file=sys.stderr)
        sys.exit(1)

def main():
    # 1. Login to Zabbix API
    print("Logging into Zabbix API...")
    auth_token = api_call("user.login", {"username": DEFAULT_ADMIN_USER, "password": DEFAULT_ADMIN_PASS})
    if not auth_token:
        print("Failed to authenticate with Zabbix.", file=sys.stderr)
        sys.exit(1)
    print("Authenticated successfully.")

    # 2. Get all tenant host groups (starting with 'Tenant - ')
    print("Fetching active tenants from Host Groups...")
    groups = api_call("hostgroup.get", {
        "output": ["groupid", "name"],
        "search": {"name": "Tenant - "},
        "startSearch": True
    }, auth_token)

    if not groups:
        print("No active tenants found to build the dashboard.", file=sys.stderr)
        sys.exit(1)

    print(f"Found {len(groups)} tenant(s). Creating dashboard pages...")

    # 3. Build dashboard pages (tabs) dynamically
    dashboard_pages = []
    
    for idx, g in enumerate(groups):
        tenant_name = g['name'].replace("Tenant - ", "")
        group_id = g['groupid']
        
        print(f"-> Building tab for: '{tenant_name}' (HostGroup ID: {group_id})")
        
        # Define widgets for this tenant page
        widgets = [
            # Host Availability Widget (Left Side)
            {
                "type": "hostavail",
                "name": f"{tenant_name} Host Availability",
                "x": 0,
                "y": 0,
                "width": 24,
                "height": 10,
                "view_mode": 0,
                "fields": [
                    {
                        "type": 2, # Host Group type
                        "name": "groupids.0",
                        "value": group_id
                    }
                ]
            },
            # Problems Widget (Right Side)
            {
                "type": "problems",
                "name": f"{tenant_name} Current Problems",
                "x": 24,
                "y": 0,
                "width": 48,
                "height": 15,
                "view_mode": 0,
                "fields": [
                    {
                        "type": 2, # Host Group type
                        "name": "groupids.0",
                        "value": group_id
                    }
                ]
            }
        ]
        
        dashboard_pages.append({
            "name": f"Client {tenant_name}",
            "widgets": widgets
        })

    # 4. Check if "MSP Clients Dashboard" already exists
    existing = api_call("dashboard.get", {
        "output": ["dashboardid"],
        "filter": {"name": "MSP Clients Dashboard"}
    }, auth_token)

    if existing:
        dashboard_id = existing[0]['dashboardid']
        print(f"Updating existing 'MSP Clients Dashboard' (ID: {dashboard_id})...")
        api_call("dashboard.update", {
            "dashboardid": dashboard_id,
            "pages": dashboard_pages
        }, auth_token)
    else:
        print("Creating a brand new 'MSP Clients Dashboard'...")
        api_call("dashboard.create", {
            "name": "MSP Clients Dashboard",
            "pages": dashboard_pages
        }, auth_token)

    print("\nSuccess! The multi-tenant 'MSP Clients Dashboard' has been generated/updated.")
    print("You can now refresh your Zabbix web interface to view it under the main Dashboards menu.")

if __name__ == "__main__":
    main()
