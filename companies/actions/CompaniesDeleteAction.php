<?php declare(strict_types = 1);

namespace Modules\Companies\Actions;

use CController;
use CControllerResponseData;
use API;

class CompaniesDeleteAction extends CController {

    protected function init(): void {
        $this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'name' => 'required|not_empty|string'
        ];
        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $name = $this->getInput('name');
        
        $output = [];
        
        try {
            // Find host group
            $group_name = 'Tenant - ' . $name;
            $groups = API::HostGroup()->get([
                'output' => ['groupid'],
                'filter' => ['name' => $group_name]
            ]);
            
            if (!$groups) {
                throw new \Exception('Company group not found.');
            }
            $group_id = $groups[0]['groupid'];
            
            // Find user group
            $ug_name = 'Tenant - ' . $name . ' - Users';
            $ugroups = API::UserGroup()->get([
                'output' => ['usrgrpid'],
                'filter' => ['name' => $ug_name]
            ]);
            
            if ($ugroups) {
                $usrgrp_id = $ugroups[0]['usrgrpid'];
                
                // Find and delete users in this group
                $users = API::User()->get([
                    'output' => ['userid'],
                    'usrgrpids' => $usrgrp_id
                ]);
                
                if ($users) {
                    API::User()->delete(array_column($users, 'userid'));
                }
                
                // Delete user group
                API::UserGroup()->delete([$usrgrp_id]);
            }
            
            // Delete host group
            API::HostGroup()->delete([$group_id]);
            
            // Rebuild MSP Clients Dashboard
            $this->updateMspDashboard();
            
            $output['success'] = true;
        } catch (\Exception $e) {
            $output['success'] = false;
            $output['error'] = $e->getMessage();
        }
        
        $this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
    }

    private function updateMspDashboard() {
        $groups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'search' => ['name' => 'Tenant - '],
            'startSearch' => true
        ]);
        
        if (!$groups) {
            $existing = API::Dashboard()->get([
                'output' => ['dashboardid'],
                'filter' => ['name' => 'MSP Clients Dashboard']
            ]);
            if ($existing) {
                API::Dashboard()->delete([$existing[0]['dashboardid']]);
            }
            return;
        }
        
        $pages = [];
        foreach ($groups as $g) {
            $tenant_name = str_replace('Tenant - ', '', $g['name']);
            $group_id = $g['groupid'];
            
            $widgets = [
                [
                    'type' => 'hostavail',
                    'name' => $tenant_name . ' Host Availability',
                    'x' => 0,
                    'y' => 0,
                    'width' => 18,
                    'height' => 6,
                    'view_mode' => 0,
                    'fields' => [
                        ['type' => 2, 'name' => 'groupids.0', 'value' => $group_id]
                    ]
                ],
                [
                    'type' => 'problemhosts',
                    'name' => $tenant_name . ' Problem Hosts',
                    'x' => 18,
                    'y' => 0,
                    'width' => 18,
                    'height' => 6,
                    'view_mode' => 0,
                    'fields' => [
                        ['type' => 2, 'name' => 'groupids.0', 'value' => $group_id]
                    ]
                ],
                [
                    'type' => 'web',
                    'name' => $tenant_name . ' Web Status',
                    'x' => 36,
                    'y' => 0,
                    'width' => 18,
                    'height' => 6,
                    'view_mode' => 0,
                    'fields' => [
                        ['type' => 2, 'name' => 'groupids.0', 'value' => $group_id]
                    ]
                ],
                [
                    'type' => 'tophosts',
                    'name' => $tenant_name . ' Host Metrics Overview',
                    'x' => 54,
                    'y' => 0,
                    'width' => 18,
                    'height' => 6,
                    'view_mode' => 0,
                    'fields' => [
                        ['type' => 2, 'name' => 'groupids.0', 'value' => $group_id],
                        ['type' => 1, 'name' => 'columns.0.name', 'value' => 'Host'],
                        ['type' => 0, 'name' => 'columns.0.data', 'value' => 2],
                        ['type' => 1, 'name' => 'columns.0.base_color', 'value' => ''],
                        ['type' => 1, 'name' => 'columns.1.name', 'value' => 'CPU'],
                        ['type' => 0, 'name' => 'columns.1.data', 'value' => 1],
                        ['type' => 1, 'name' => 'columns.1.item', 'value' => 'CPU'],
                        ['type' => 0, 'name' => 'columns.1.display', 'value' => 1],
                        ['type' => 0, 'name' => 'columns.1.history', 'value' => 1],
                        ['type' => 1, 'name' => 'columns.1.base_color', 'value' => ''],
                        ['type' => 1, 'name' => 'columns.2.name', 'value' => 'RAM'],
                        ['type' => 0, 'name' => 'columns.2.data', 'value' => 1],
                        ['type' => 1, 'name' => 'columns.2.item', 'value' => 'RAM'],
                        ['type' => 0, 'name' => 'columns.2.display', 'value' => 1],
                        ['type' => 0, 'name' => 'columns.2.history', 'value' => 1],
                        ['type' => 1, 'name' => 'columns.2.base_color', 'value' => ''],
                        ['type' => 1, 'name' => 'columns.3.name', 'value' => 'Storage'],
                        ['type' => 0, 'name' => 'columns.3.data', 'value' => 1],
                        ['type' => 1, 'name' => 'columns.3.item', 'value' => 'Storage (C:)'],
                        ['type' => 0, 'name' => 'columns.3.display', 'value' => 1],
                        ['type' => 0, 'name' => 'columns.3.history', 'value' => 1],
                        ['type' => 1, 'name' => 'columns.3.base_color', 'value' => '']
                    ]
                ],
                [
                    'type' => 'problems',
                    'name' => $tenant_name . ' Active Alerts Log',
                    'x' => 0,
                    'y' => 6,
                    'width' => 72,
                    'height' => 14,
                    'view_mode' => 0,
                    'fields' => [
                        ['type' => 2, 'name' => 'groupids.0', 'value' => $group_id],
                        ['type' => 0, 'name' => 'show', 'value' => 3]
                    ]
                ]
            ];
            
            $pages[] = [
                'name' => 'Client ' . $tenant_name,
                'widgets' => $widgets
            ];
        }
        
        $existing = API::Dashboard()->get([
            'output' => ['dashboardid'],
            'filter' => ['name' => 'MSP Clients Dashboard']
        ]);
        
        if ($existing) {
            API::Dashboard()->update([
                'dashboardid' => $existing[0]['dashboardid'],
                'pages' => $pages
            ]);
        } else {
            API::Dashboard()->create([
                'name' => 'MSP Clients Dashboard',
                'pages' => $pages
            ]);
        }
    }
}
