<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddAdjustedEiTotalScore extends AbstractMigration
{
    public function up()
    {
        // Add new fields
        $this->execute(
            "           
            CALL `createCaseGroup` ('Total Score', 3, 'Business Immigration Registration');
        "
        );

        $this->execute(
            "           
            CALL `createCaseField` ('scoreTotalEIBefore', 35, 'Total score', 0, 'N', 'Y', 'Total Score', 'Business Immigration Registration', null);
        "
        );

        $this->execute(
            "           
            CALL `createCaseField` ('scoreCoRegistrantsEI', 42, 'Co-registrant scores', 0, 'N', 'Y', 'Total Score', 'Business Immigration Registration', null);
        "
        );

        $this->execute(
            "           
            CALL `createCaseField` ('scoreTotalEIAfter', 5, 'Total Score (adjusted, leave empty if not needed)', 0, 'N', 'N', 'Total Score', 'Business Immigration Registration', null);
        "
        );

        $this->execute(
            "
            INSERT INTO `client_form_default` (`field_id`, `value`, `order`)
            SELECT cff.field_id, 'scoreTotalEI', 0
            FROM `client_form_fields` cff
            INNER JOIN company c ON cff.company_id = c.company_id
            WHERE company_field_id = 'scoreTotalEIBefore' AND `companyName` = 'BC PNP';
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute(
            "
            DELETE cfd, cffa, cfo, cfde
                FROM client_form_fields cff
                  LEFT JOIN  client_form_data cfd ON cff.field_id = cfd.field_id
                  LEFT JOIN client_form_field_access cffa ON cff.field_id = cffa.field_id
                  LEFT JOIN client_form_order cfo ON cfo.field_id = cff.field_id
                  LEFT JOIN client_form_default cfde ON cfde.field_id = cff.field_id
                WHERE cff.company_field_id IN (
                    'scoreTotalEIBefore',
                    'scoreTotalEIAfter',
                    'scoreCoRegistrantsEI'
                );
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}