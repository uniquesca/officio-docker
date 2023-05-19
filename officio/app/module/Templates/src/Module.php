<?php

namespace Templates;

use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Officio\Service\SystemTriggersListener;
use Templates\Service\Templates;

class Module implements ConfigProviderInterface, SystemTriggersListener
{

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getSystemTriggerListeners() {
        return [Templates::class];
    }

}
