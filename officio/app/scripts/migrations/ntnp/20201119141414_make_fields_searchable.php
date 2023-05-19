<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Migration\AbstractMigration;

class makeFieldsSearchable extends AbstractMigration
{
    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='Y' WHERE  `field_type_text_id` IN ('active_users', 'auto_calculated', 'multiple_text_fields', 'categories', 'list_of_occupations');");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='N' WHERE  `field_type_text_id` IN ('active_users', 'auto_calculated', 'multiple_text_fields', 'categories', 'list_of_occupations');");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}