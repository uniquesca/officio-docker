<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Superadmin\Controller\ManageOwnCompanyController;

/**
 * This is the factory for ManageOwnCompanyController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageOwnCompanyControllerFactory extends BaseControllerFactory
{
    protected $controllerClass = ManageOwnCompanyController::class;
}
