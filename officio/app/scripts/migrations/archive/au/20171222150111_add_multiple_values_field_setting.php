<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddMultipleValuesFieldSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `multiple_values` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `blocked`;");
        $this->execute("ALTER TABLE `applicant_form_fields` ADD COLUMN `multiple_values` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `blocked`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `applicant_form_fields` DROP COLUMN `multiple_values`");
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `multiple_values`");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}