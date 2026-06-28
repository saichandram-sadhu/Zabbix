#!/usr/bin/env python3
import sys
import json
import urllib.request
import urllib.error
import argparse

ZABBIX_URL = "https://localhost/zabbix/api_jsonrpc.php"
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
        import ssl
        context = ssl._create_unverified_context()
        with urllib.request.urlopen(req, context=context) as response:
            res = json.loads(response.read().decode('utf-8'))
            if 'error' in res:
                print(f"Error in API call {method}: {res['error']['data']} (Message: {res['error']['message']})", file=sys.stderr)
                sys.exit(1)
            return res.get('result')
    except urllib.error.URLError as e:
        print(f"Failed to connect to Zabbix API at {ZABBIX_URL}: {e.reason}", file=sys.stderr)
        sys.exit(1)

def get_auth_token(username, password):
    res = api_call("user.login", {"username": username, "password": password})
    return res

def update_msp_dashboard(auth_token):
    print("Automatically rebuilding 'MSP Clients Dashboard'...")
    
    # 1. Fetch all active tenant host groups
    groups = api_call("hostgroup.get", {
        "output": ["groupid", "name"],
        "search": {"name": "Tenant - "},
        "startSearch": True
    }, auth_token)
    
    if not groups:
        # If no tenants exist, remove the dashboard
        existing = api_call("dashboard.get", {
            "output": ["dashboardid"],
            "filter": {"name": "MSP Clients Dashboard"}
        }, auth_token)
        if existing:
            api_call("dashboard.delete", [existing[0]['dashboardid']], auth_token)
            print("-> No tenants remaining. Deleted 'MSP Clients Dashboard'.")
        return

    # 2. Build premium multi-page dashboard structure
    dashboard_pages = []
    for g in groups:
        tenant_name = g['name'].replace("Tenant - ", "")
        group_id = g['groupid']
        
        widgets = [
            # Widget 1: Host Availability (Top Left)
            {
                "type": "hostavail",
                "name": f"{tenant_name} Host Availability",
                "x": 0,
                "y": 0,
                "width": 18,
                "height": 6,
                "view_mode": 0,
                "fields": [
                    {"type": 2, "name": "groupids.0", "value": group_id}
                ]
            },
            # Widget 2: Problem Hosts (Top Middle-Left)
            {
                "type": "problemhosts",
                "name": f"{tenant_name} Problem Hosts",
                "x": 18,
                "y": 0,
                "width": 18,
                "height": 6,
                "view_mode": 0,
                "fields": [
                    {"type": 2, "name": "groupids.0", "value": group_id}
                ]
            },
            # Widget 3: Web Monitoring (Top Middle-Right)
            {
                "type": "web",
                "name": f"{tenant_name} Web Status",
                "x": 36,
                "y": 0,
                "width": 18,
                "height": 6,
                "view_mode": 0,
                "fields": [
                    {"type": 2, "name": "groupids.0", "value": group_id}
                ]
            },
            # Widget 4: Top Hosts table - CPU, RAM & Storage (Top Right)
            {
                "type": "tophosts",
                "name": f"{tenant_name} Host Metrics Overview",
                "x": 54,
                "y": 0,
                "width": 18,
                "height": 6,
                "view_mode": 0,
                "fields": [
                    {"type": 2, "name": "groupids.0", "value": group_id},
                    {"type": 1, "name": "columns.0.name", "value": "Host"},
                    {"type": 0, "name": "columns.0.data", "value": 2},
                    {"type": 1, "name": "columns.0.base_color", "value": ""},
                    {"type": 1, "name": "columns.1.name", "value": "CPU"},
                    {"type": 0, "name": "columns.1.data", "value": 1},
                    {"type": 1, "name": "columns.1.item", "value": "CPU"},
                    {"type": 0, "name": "columns.1.display", "value": 1},
                    {"type": 0, "name": "columns.1.history", "value": 1},
                    {"type": 1, "name": "columns.1.base_color", "value": ""},
                    {"type": 1, "name": "columns.2.name", "value": "RAM"},
                    {"type": 0, "name": "columns.2.data", "value": 1},
                    {"type": 1, "name": "columns.2.item", "value": "RAM"},
                    {"type": 0, "name": "columns.2.display", "value": 1},
                    {"type": 0, "name": "columns.2.history", "value": 1},
                    {"type": 1, "name": "columns.2.base_color", "value": ""},
                    {"type": 1, "name": "columns.3.name", "value": "Storage"},
                    {"type": 0, "name": "columns.3.data", "value": 1},
                    {"type": 1, "name": "columns.3.item", "value": "Storage (C:)"},
                    {"type": 0, "name": "columns.3.display", "value": 1},
                    {"type": 0, "name": "columns.3.history", "value": 1},
                    {"type": 1, "name": "columns.3.base_color", "value": ""}
                ]
            },
            # Widget 5: Current Problems List (Bottom Full Width)
            {
                "type": "problems",
                "name": f"{tenant_name} Active Alerts Log",
                "x": 0,
                "y": 6,
                "width": 72,
                "height": 14,
                "view_mode": 0,
                "fields": [
                    {"type": 2, "name": "groupids.0", "value": group_id},
                    {"type": 0, "name": "show", "value": 2}
                ]
            }
        ]
        
        dashboard_pages.append({
            "name": f"Client {tenant_name}",
            "widgets": widgets
        })

    # 3. Create or update the dashboard
    existing = api_call("dashboard.get", {
        "output": ["dashboardid"],
        "filter": {"name": "MSP Clients Dashboard"}
    }, auth_token)

    if existing:
        dashboard_id = existing[0]['dashboardid']
        api_call("dashboard.update", {
            "dashboardid": dashboard_id,
            "pages": dashboard_pages
        }, auth_token)
        print("-> 'MSP Clients Dashboard' updated successfully.")
    else:
        api_call("dashboard.create", {
            "name": "MSP Clients Dashboard",
            "pages": dashboard_pages
        }, auth_token)
        print("-> 'MSP Clients Dashboard' created successfully.")

def create_tenant(auth_token, name, username, password):
    group_name = f"Tenant - {name}"
    user_group_name = f"Tenant - {name} - Users"
    
    # 1. Create Host Group
    print(f"Creating Host Group: '{group_name}'...")
    hg_res = api_call("hostgroup.create", {"name": group_name}, auth_token)
    group_id = hg_res['groupids'][0]
    print(f"-> Host Group created with ID: {group_id}")
    
    # 2. Create User Group
    print(f"Creating User Group: '{user_group_name}' with Read-Write permissions...")
    ug_res = api_call("usergroup.create", {
        "name": user_group_name,
        "hostgroup_rights": [
            {
                "id": group_id,
                "permission": 3 # Read-Write
            }
        ]
    }, auth_token)
    user_group_id = ug_res['usrgrpids'][0]
    print(f"-> User Group created with ID: {user_group_id}")
    
    # 3. Create Tenant Admin User
    print(f"Creating Tenant Admin User: '{username}' with Admin Role...")
    u_res = api_call("user.create", {
        "username": username,
        "passwd": password,
        "roleid": "2", # Admin role
        "usrgrps": [
            {"usrgrpid": user_group_id}
        ]
    }, auth_token)
    user_id = u_res['userids'][0]
    print(f"-> User '{username}' created successfully with ID: {user_id}")
    print(f"\nSuccess! Tenant '{name}' has been provisioned.")
    
    # 4. Automatically update the MSP dashboard
    update_msp_dashboard(auth_token)

def list_tenants(auth_token):
    print("Fetching Zabbix Host Groups starting with 'Tenant - '...")
    groups = api_call("hostgroup.get", {
        "output": ["groupid", "name"],
        "search": {"name": "Tenant - "},
        "startSearch": True
    }, auth_token)
    
    if not groups:
        print("No tenants found.")
        return
        
    print("\nActive Tenants:")
    print("-" * 50)
    for g in groups:
        name = g['name'].replace("Tenant - ", "")
        print(f"Tenant Name : {name}")
        print(f"  Host Group ID: {g['groupid']}")
        
        # Get associated user groups
        ug_name = f"Tenant - {name} - Users"
        ugroups = api_call("usergroup.get", {
            "output": ["usrgrpid", "name"],
            "filter": {"name": ug_name}
        }, auth_token)
        
        if ugroups:
            ugid = ugroups[0]['usrgrpid']
            print(f"  User Group ID: {ugid}")
            
            # Get users in this group
            users = api_call("user.get", {
                "output": ["userid", "username"],
                "usrgrpids": ugid
            }, auth_token)
            user_list = ", ".join([u['username'] for u in users]) if users else "No users"
            print(f"  Users        : {user_list}")
        else:
            print("  User Group   : Not Found")
        print("-" * 50)

def delete_tenant(auth_token, name):
    group_name = f"Tenant - {name}"
    user_group_name = f"Tenant - {name} - Users"
    
    # Get group details
    groups = api_call("hostgroup.get", {
        "output": ["groupid"],
        "filter": {"name": group_name}
    }, auth_token)
    
    if not groups:
        print(f"Tenant '{name}' (Host Group '{group_name}') not found.", file=sys.stderr)
        sys.exit(1)
        
    group_id = groups[0]['groupid']
    
    # Get user group details
    ugroups = api_call("usergroup.get", {
        "output": ["usrgrpid"],
        "filter": {"name": user_group_name}
    }, auth_token)
    
    usrgrp_id = ugroups[0]['usrgrpid'] if ugroups else None
    
    # If user group exists, find and delete all its users
    if usrgrp_id:
        users = api_call("user.get", {
            "output": ["userid", "username"],
            "usrgrpids": usrgrp_id
        }, auth_token)
        
        if users:
            user_ids = [u['userid'] for u in users]
            user_names = ", ".join([u['username'] for u in users])
            print(f"Deleting users in tenant group: {user_names}...")
            api_call("user.delete", user_ids, auth_token)
            
        print(f"Deleting User Group: '{user_group_name}'...")
        api_call("usergroup.delete", [usrgrp_id], auth_token)
        
    print(f"Deleting Host Group: '{group_name}'...")
    api_call("hostgroup.delete", [group_id], auth_token)
    
    print(f"\nSuccess! Tenant '{name}' has been completely deleted.")
    
    # Automatically update the MSP dashboard
    update_msp_dashboard(auth_token)

def main():
    parser = argparse.ArgumentParser(description="Zabbix Logical Multi-Tenancy Manager")
    parser.add_argument("--admin-user", default=DEFAULT_ADMIN_USER, help="Zabbix Admin Username")
    parser.add_argument("--admin-pass", default=DEFAULT_ADMIN_PASS, help="Zabbix Admin Password")
    
    subparsers = parser.add_subparsers(dest="command", required=True)
    
    # Create command
    create_parser = subparsers.add_parser("create", help="Create a new tenant")
    create_parser.add_argument("--name", required=True, help="Tenant/Client name (e.g. ClientA)")
    create_parser.add_argument("--user", required=True, help="Tenant administrator username")
    create_parser.add_argument("--password", required=True, help="Tenant administrator password")
    
    # List command
    subparsers.add_parser("list", help="List all active tenants")
    
    # Delete command
    delete_parser = subparsers.add_parser("delete", help="Delete a tenant and its users")
    delete_parser.add_argument("--name", required=True, help="Tenant/Client name to delete")
    
    # Rebuild dashboard command
    subparsers.add_parser("rebuild-dashboard", help="Rebuild the MSP Clients Dashboard manually")
    
    args = parser.parse_args()
    
    auth_token = get_auth_token(args.admin_user, args.admin_pass)
    
    if args.command == "create":
        create_tenant(auth_token, args.name, args.user, args.password)
    elif args.command == "list":
        list_tenants(auth_token)
    elif args.command == "delete":
        delete_tenant(auth_token, args.name)
    elif args.command == "rebuild-dashboard":
        update_msp_dashboard(auth_token)

if __name__ == "__main__":
    main()
