<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddDependantsPhotoColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `photo` VARCHAR(255) NULL DEFAULT NULL AFTER `medical_expiration_date`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (10, 'applicants', 'profile', 'get-dependent-image');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_dependents` DROP COLUMN `photo`;");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='get-dependent-image';");


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}