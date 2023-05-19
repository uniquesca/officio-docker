<?php

namespace Files\Service\Factory;

use Exception;
use Files\Service\Cloud;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Encryption;
use ReflectionClass;

class CloudFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Encryption::class => $container->get(Encryption::class),
            HelperPluginManager::class => $container->get('ViewHelperManager')
        ];
    }

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $services = $this->retrieveServiceList($container);
        $class = new ReflectionClass(Cloud::class);

        /** @var Cloud $cloud */
        $cloud = $class->newInstanceArgs($services);
        $cloud->initAdditionalServices($this->retrieveAdditionalServiceList($container));

        if (!isset($options['parent'])) {
            throw new Exception('Parent has to be passed via options when creating sub-service.');
        }
        $cloud->setParent($options['parent']);

        if (isset($options['isImagesS3'])) {
            $cloud->setImagesS3($options['isImagesS3']);
        }

        $cloud->init();

        return $cloud;
    }

}