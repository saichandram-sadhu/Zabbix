<?php declare(strict_types = 1);

namespace Modules\Companies;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;
use CWebUser;

class Module extends CModule {
    public function init(): void {
        if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
            APP::Component()->get('menu.main')
                ->add((new CMenuItem(_('Companies')))
                ->setAction('companies.list')
                ->setIcon('zi-users'));

            // NOC Topology nested under Services menu (uses existing Services icon)
            APP::Component()->get('menu.main')
                ->findOrAdd(_('Services'))
                    ->getSubMenu()
                        ->add(
                            (new CMenuItem(_('NOC Topology')))
                                ->setAction('companies.topology')
                        );
        }
    }
}
