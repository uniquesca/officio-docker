<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Superadmin\Controller\ResourcesController;

/**
 * This is the factory for ResourcesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ResourcesControllerFactory extends BaseControllerFactory
{
    protected $controllerClass = ResourcesController::class;
}
