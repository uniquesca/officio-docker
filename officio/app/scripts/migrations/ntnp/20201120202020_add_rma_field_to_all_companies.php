<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Migration\AbstractMigration;

class addRmaFieldToAllCompanies extends AbstractMigration
{
    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute(
            "INSERT INTO `client_form_fields` (`company_id`, `company_field_id`, `type`, `label`, `required`, `maxlength`)
             SELECT c.company_id, 'registered_migrant_agent', 27, 'RMA', 'Y', 0
             FROM company AS c
             WHERE c.company_id NOT IN (
                SELECT company_id
                FROM client_form_fields
                WHERE company_field_id = 'registered_migrant_agent'
             )"
        );

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

        $this->execute("DELETE FROM client_form_fields WHERE `company_field_id` = 'registered_migrant_agent'");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}