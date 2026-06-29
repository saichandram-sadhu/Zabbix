<?php declare(strict_types = 1);

namespace Modules\Companies\Actions;

use CController;
use CControllerResponseData;
use API;
use CWebUser;

class CompaniesTopologyAction extends CController {

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
        // 1. Fetch Zabbix Server info
        $server_name = "Zabbix NOC Server";
        
        // 2. Fetch all Zabbix Proxies
        $proxies = API::Proxy()->get([
            'output' => ['proxyid', 'name', 'operating_mode', 'lastaccess'],
            'selectHosts' => ['hostid']
        ]);

        // 3. Fetch all Hosts
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name', 'status', 'proxyid', 'description'],
            'selectInterfaces' => ['interfaceid', 'ip', 'port', 'main', 'type', 'available', 'error'],
            'selectParentTemplates' => ['templateid', 'name']
        ]);

        // 4. Fetch Active Problems to highlight issues on the topology map
        $problems = API::Problem()->get([
            'output' => ['eventid', 'objectid', 'name', 'severity', 'clock'],
            'recent' => true
        ]);

        // Map trigger/problem counts to hosts
        $host_problems = [];
        if ($problems) {
            $triggerids = [];
            foreach ($problems as $p) {
                $triggerids[] = $p['objectid'];
            }
            $triggers = API::Trigger()->get([
                'output' => ['triggerid'],
                'triggerids' => array_unique($triggerids),
                'selectHosts' => ['hostid']
            ]);
            
            $trigger_to_hosts = [];
            foreach ($triggers as $t) {
                if (!empty($t['hosts'])) {
                    foreach ($t['hosts'] as $th) {
                        $trigger_to_hosts[$t['triggerid']][] = $th['hostid'];
                    }
                }
            }

            foreach ($problems as $p) {
                if (isset($trigger_to_hosts[$p['objectid']])) {
                    foreach ($trigger_to_hosts[$p['objectid']] as $hid) {
                        if (!isset($host_problems[$hid])) {
                            $host_problems[$hid] = [];
                        }
                        $host_problems[$hid][] = [
                            'name' => $p['name'],
                            'severity' => (int)$p['severity']
                        ];
                    }
                }
            }
        }

        $data = [
            'server_name' => $server_name,
            'proxies' => $proxies,
            'hosts' => $hosts,
            'host_problems' => $host_problems
        ];

        $this->setResponse(new CControllerResponseData($data));
    }
}
