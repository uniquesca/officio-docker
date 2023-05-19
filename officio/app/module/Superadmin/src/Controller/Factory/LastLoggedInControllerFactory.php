<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Superadmin\Controller\LastLoggedInController;

/**
 * This is the factory for LastLoggedInController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class LastLoggedInControllerFactory extends BaseControllerFactory
{
    protected $controllerClass = LastLoggedInController::class;
}
