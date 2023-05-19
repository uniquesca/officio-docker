<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class SwitchTransferabilityFieldToNumber extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            UPDATE client_form_fields 
            SET type = 5 
            WHERE company_field_id = 'busConceptSTEI';
        "
        );

        $this->execute(
            "
            DELETE cfd 
            FROM client_form_default cfd
            INNER JOIN client_form_fields cff ON cff.field_id = cfd.field_id
            WHERE cff.company_field_id = 'busConceptSTEI';
        "
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
    }
}