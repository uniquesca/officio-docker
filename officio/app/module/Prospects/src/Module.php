<?php

namespace Prospects;

use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Officio\Common\InitializableListener;
use Officio\Service\SystemTriggersListener;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;
use Prospects\Service\Prospects;

class Module implements ConfigProviderInterface, SystemTriggersListener, InitializableListener
{

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getSystemTriggerListeners()
    {
        return [CompanyProspects::class];
    }

    /**
     * @inheritdoc
     */
    public function getListeners(string $class)
    {
        $listeners = [
            SystemTemplates::class => [
                SystemTemplates::EVENT_GET_AVAILABLE_FIELDS => [Prospects::class, CompanyProspects::class]
            ]
        ];
        return $listeners[$class] ?? [];
    }

}
