<?php

namespace Officio\Service\Factory;

use Laminas\Db\TableGateway\TableGateway;
use Laminas\Session\SaveHandler\DbTableGateway;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Session\SaveHandler\DbTableGatewayOptions;
use Officio\Common\DbAdapterWrapper;

class SessionSaveHandlerFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var DbAdapterWrapper $db */
        $db           = $container->get('db2');
        $tableGateway = new TableGateway('sessions', $db);
        return new DbTableGateway($tableGateway, new DbTableGatewayOptions());
    }

}