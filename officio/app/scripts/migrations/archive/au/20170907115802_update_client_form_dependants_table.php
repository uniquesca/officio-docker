<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class UpdateClientFormDependantsTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` DROP PRIMARY KEY;");
        $this->execute(
            "ALTER TABLE `client_form_dependents`
	        ADD COLUMN `dependent_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
	        ADD PRIMARY KEY (`dependent_id`);
	"
        );


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        // $this->execute("ALTER TABLE `client_form_dependents` DROP PRIMARY KEY");
        $this->execute("ALTER TABLE `client_form_dependents` DROP COLUMN `dependent_id`;");
        $this->execute("ALTER TABLE `client_form_dependents` ADD PRIMARY KEY (`member_id`, `relationship`, `line`);");


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}