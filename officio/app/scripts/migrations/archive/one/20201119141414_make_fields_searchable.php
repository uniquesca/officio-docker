<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class makeFieldsSearchable extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='Y' WHERE  `field_type_text_id` IN ('active_users', 'auto_calculated', 'multiple_text_fields', 'categories', 'list_of_occupations');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='N' WHERE  `field_type_text_id` IN ('active_users', 'auto_calculated', 'multiple_text_fields', 'categories', 'list_of_occupations');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}