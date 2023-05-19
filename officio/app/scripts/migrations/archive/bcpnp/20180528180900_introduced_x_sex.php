<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroducedXSex extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO applicant_form_default (`applicant_field_id`, `value`, `order`)
            SELECT applicant_field_id, 'X', 2
            FROM applicant_form_fields
            WHERE applicant_field_unique_id = 'sex';
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