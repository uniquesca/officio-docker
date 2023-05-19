<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class UpdateQnrFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires_fields` ADD COLUMN `q_field_show_in_qnr` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `q_field_show_in_prospect_profile`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_questionnaires_fields` DROP COLUMN `q_field_show_in_qnr`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}