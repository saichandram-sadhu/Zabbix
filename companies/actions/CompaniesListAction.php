<?php declare(strict_types = 1);

namespace Modules\Companies\Actions;

use CController;
use CControllerResponseData;
use API;
use CWebUser;

class CompaniesListAction extends CController {

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $groups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'search' => ['name' => 'Tenant - '],
            'startSearch' => true
        ]);
        
        // Fetch dashboard ID
        $dashboards = API::Dashboard()->get([
            'output' => ['dashboardid'],
            'filter' => ['name' => 'MSP Clients Dashboard']
        ]);
        $dashboard_id = $dashboards ? $dashboards[0]['dashboardid'] : null;

        $companies = [];
        foreach ($groups as $g) {
            $name = str_replace('Tenant - ', '', $g['name']);
            
            // 1. Get associated user group details
            $ugroups = API::UserGroup()->get([
                'output' => ['usrgrpid'],
                'filter' => ['name' => 'Tenant - ' . $name . ' - Users']
            ]);
            
            $user_list = [];
            $usrgrp_id = null;
            if ($ugroups) {
                $usrgrp_id = $ugroups[0]['usrgrpid'];
                $users = API::User()->get([
                    'output' => ['userid', 'username'],
                    'usrgrpids' => $usrgrp_id
                ]);
                foreach ($users as $u) {
                    $user_list[] = [
                        'userid' => $u['userid'],
                        'username' => $u['username']
                    ];
                }
            }
            
            // 2. Fetch connected hosts for this tenant
            $hosts = API::Host()->get([
                'output' => ['hostid', 'name', 'status'],
                'groupids' => $g['groupid'],
                'selectInterfaces' => ['interfaceid', 'ip', 'port', 'main', 'type', 'available', 'error']
            ]);
            
            $hosts_mapped = [];
            foreach ($hosts as $h) {
                $agent_available = 0;
                $agent_error = '';
                $snmp_available = 0;
                $snmp_error = '';
                
                if (!empty($h['interfaces'])) {
                    foreach ($h['interfaces'] as $interface) {
                        if ($interface['type'] == 1) { // Agent
                            $agent_available = (int)$interface['available'];
                            $agent_error = $interface['error'];
                        } elseif ($interface['type'] == 2) { // SNMP
                            $snmp_available = (int)$interface['available'];
                            $snmp_error = $interface['error'];
                        }
                    }
                }
                
                $hosts_mapped[] = [
                    'hostid' => $h['hostid'],
                    'name' => $h['name'],
                    'status' => (int)$h['status'],
                    'available' => $agent_available,
                    'snmp_available' => $snmp_available,
                    'error' => $agent_error,
                    'snmp_error' => $snmp_error
                ];
            }
            
            // 3. Fetch recent active problems (live logs) for this tenant
            $problems = API::Problem()->get([
                'output' => ['eventid', 'objectid', 'name', 'severity', 'clock'],
                'groupids' => $g['groupid'],
                'recent' => true,
                'sortfield' => 'eventid',
                'sortorder' => 'DESC',
                'limit' => 15
            ]);
            
            // Resolve host names for the problems (since Problems link to Triggers)
            $problems_mapped = [];
            if ($problems) {
                $triggerids = [];
                foreach ($problems as $p) {
                    $triggerids[] = $p['objectid'];
                }
                
                $triggers = API::Trigger()->get([
                    'output' => ['triggerid'],
                    'triggerids' => array_unique($triggerids),
                    'selectHosts' => ['hostid', 'name']
                ]);
                
                $trigger_hosts = [];
                foreach ($triggers as $t) {
                    if (!empty($t['hosts'])) {
                        $trigger_hosts[$t['triggerid']] = $t['hosts'][0]['name'];
                    }
                }
                
                foreach ($problems as $p) {
                    $problems_mapped[] = [
                        'eventid' => $p['eventid'],
                        'triggerid' => $p['objectid'],
                        'name' => $p['name'],
                        'severity' => (int)$p['severity'],
                        'clock' => (int)$p['clock'],
                        'host' => isset($trigger_hosts[$p['objectid']]) ? $trigger_hosts[$p['objectid']] : 'System'
                    ];
                }
            }
            
            $companies[] = [
                'groupid' => $g['groupid'],
                'name' => $name,
                'usrgrpid' => $usrgrp_id,
                'users' => $user_list,
                'hosts' => $hosts_mapped,
                'problems' => $problems_mapped
            ];
        }
        
        $data = [
            'companies' => $companies,
            'dashboard_id' => $dashboard_id
        ];
        
        $this->setResponse(new CControllerResponseData($data));
    }
}
