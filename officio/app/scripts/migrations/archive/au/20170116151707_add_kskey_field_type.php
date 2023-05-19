<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddKskeyFieldType extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_custom_height`) VALUES
            (37, 'kskeydid', 'KSKEYdId', 'Y', 'Y', 'N');"
        );

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (11, 'applicants', 'profile', 'generate-ks-key');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=37;");

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=11 AND `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='generate-ks-key';");
    }
}