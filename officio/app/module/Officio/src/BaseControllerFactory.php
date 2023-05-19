<?php

namespace Officio;

use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\ServiceManager;
use Officio\Common\BaseFactory;
use Officio\Common\Service\Settings;

/**
 * BaseControllerFactory - The default controller factorty class that is used as a parent for all controller factories
 * @package Officio
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class BaseControllerFactory extends BaseFactory {

    /**
     * Returns list of services, required by controller.
     * Order has to be the same as in controller's constructor.
     * @param ContainerInterface $container
     * @return array
     */
    protected function retrieveServiceList(ContainerInterface $container) {
        return [
            $container->get('config'),
            $container->get(ServiceManager::class),
            $container->get('db2'),
            $container->get('auth'),
            $container->get('acl'),
            $container->get('cache'),
            $container->get('log'),
            $container->get('translator'),
            $container->get(Settings::class),
            $container->get(Members::class),
        ];
    }

    /**
     * Define all additional service to be injected into the controller
     * @param ContainerInterface $container
     * @return array
     */
    protected function retrieveAdditionalServiceList(ContainerInterface $container) {
        return [];
    }

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $instance = parent::__invoke($container, $requestedName, $options);
        if ($instance instanceof BaseController) {
            $instance->initAdditionalServices($this->retrieveAdditionalServiceList($container));
            $instance->init();
        }
        return $instance;
    }

}