<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Superadmin\Controller\ErrorController;

/**
 * This is the factory for ErrorController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ErrorControllerFactory extends BaseControllerFactory
{
    protected $controllerClass = ErrorController::class;
}

