<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddRequiredForSubmissionFieldOption extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `client_form_fields`
	        ADD COLUMN `required_for_submission` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `required`;"
        );

        $this->execute(
            "ALTER TABLE `applicant_form_fields`
	        ADD COLUMN `required_for_submission` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `required`;"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `applicant_form_fields` DROP COLUMN `required_for_submission`");

        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `required_for_submission`");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}