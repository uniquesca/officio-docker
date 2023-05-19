<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class NewEmpContactPhoneSyncField extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            CALL `createCaseField` ('si_app_emp_contact_phone', 1, 'Employer Contact Phone', 0, 'N', 'N', 'Job Offer', 'Skills Immigration Application', 'syncA_App_Emp_ContactPhone');
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