<?php

namespace Clients;

use Clients\Service\Clients;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Officio\Common\InitializableListener;
use Officio\Service\SystemTriggersListener;
use Officio\Templates\SystemTemplates;

class Module implements ConfigProviderInterface, SystemTriggersListener, InitializableListener
{

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getSystemTriggerListeners()
    {
        return [Clients::class];
    }

    /**
     * @inheritdoc
     */
    public function getListeners(string $class)
    {
        $listeners = [
            SystemTemplates::class => [
                SystemTemplates::EVENT_GET_AVAILABLE_FIELDS => [Clients::class]
            ]
        ];
        return $listeners[$class] ?? [];
    }

}
