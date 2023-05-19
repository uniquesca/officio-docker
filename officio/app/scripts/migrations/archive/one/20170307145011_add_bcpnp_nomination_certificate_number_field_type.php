<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddBcpnpNominationCertificateNumberFieldType extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_custom_height`, `field_type_use_for`) VALUES
            (41, 'bcpnp_nomination_certificate_number', 'BC PNP Nomination Certificate Number', 'Y', 'Y', 'N', 'case');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=41;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}