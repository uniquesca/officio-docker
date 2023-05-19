<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddTypeColumnToInvoiceTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_invoice` ADD COLUMN `type` VARCHAR(255) NOT NULL DEFAULT 'invoice' AFTER `received`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_invoice` DROP COLUMN `type`;");
    }
}