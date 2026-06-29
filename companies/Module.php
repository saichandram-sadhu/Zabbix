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

            // NOC Topology nested under Services menu (no separate icon needed)
            APP::Component()->get('menu.main')
                ->findOrAdd(_('Services'))
                    ->getSubMenu()
                        ->insertAfter(_('Services'),
                            (new CMenuItem(_('NOC Topology')))
                                ->setAction('companies.topology')
                        );
        }
    }
}
