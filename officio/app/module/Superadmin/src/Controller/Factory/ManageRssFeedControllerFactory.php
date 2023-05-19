<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Superadmin\Controller\ManageRssFeedController;

/**
 * This is the factory for ManageRssFeedController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageRssFeedControllerFactory extends BaseControllerFactory
{
    protected $controllerClass = ManageRssFeedController::class;
}
