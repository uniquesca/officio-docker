<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class CopyTotalScoreForCoregistrants extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO client_form_data (member_id, field_id, `value`)
            SELECT cfd.member_id, cff1.field_id, cfd.`value`
            FROM client_form_data cfd
            INNER JOIN client_form_fields cff ON cff.field_id = cfd.field_id
            LEFT OUTER JOIN client_form_fields cff1 ON cff1.company_field_id = 'scoreTotalEIBefore' AND cff1.company_id = cff.company_id
            WHERE cff.company_field_id = 'scoreTotalEI';
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
    }
}