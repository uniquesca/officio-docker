<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroduceNewProfileFields extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            CALL `createIAField` ('birth_city', 'text', 'City of Birth', 'N', 'N', 'Applicant Info', null);
        "
        );

        $this->execute(
            "
            CALL `createIAField` ('other_first_names', 'text', 'Other Given Names', 'N', 'N', 'Applicant Info', null);
        "
        );

        $this->execute(
            "
            CALL `createIAField` ('other_last_names', 'text', 'Other Family Names', 'N', 'N', 'Applicant Info', null);
        "
        );

        $this->execute(
            "
            CALL `createIAField` ('bcpnp_knowledge_source', 'text', 'How knows about BC PNP', 'N', 'N', 'Applicant Info', null);
        "
        );

        $this->execute(
            "
            CALL `createIAField` ('bcpnp_knowledge_source_other', 'text', 'How knows about BC PNP (other)', 'N', 'N', 'Applicant Info', null);
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