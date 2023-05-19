<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class NewEiRegistrationScoringFields extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            CALL `createCaseField` ('ownership_bonus', 5, 'Ownership Bonus', 0, 'N', 'N', 'Score', 'Business Immigration Registration', null);
            CALL `createCaseField` ('total_investment', 5, 'Total Investment', 0, 'N', 'N', 'Score', 'Business Immigration Registration', null);
            CALL `createCaseField` ('business_location', 5, 'Business Region', 0, 'N', 'N', 'Score', 'Business Immigration Registration', null);
            
            CALL `createCaseField` ('verifiedTotalInvestmentEI', 5, 'Total Investment (8-20)', 0, 'N', 'N', 'Verified Score', 'Business Immigration Registration', null);
            CALL `createCaseField` ('verifiedOwnershipEI', 5, 'Ownership Bonus (0, 4)', 0, 'N', 'N', 'Verified Score', 'Business Immigration Registration', null);
            CALL `createCaseField` ('verifiedLocationEI', 5, 'Business Location (0-12)', 0, 'N', 'N', 'Verified Score', 'Business Immigration Registration', null);
            
            CALL `createCaseField` ('eligibleInvestmentEI', 5, 'Assessment of Eligible Investment', 0, 'N', 'N', 'Business Concept and Total Score', 'Business Immigration Registration', null);
            CALL `createCaseField` ('proposedInvestmentTotal', 5, 'Assessment of Proposed Investment', 0, 'N', 'N', 'Business Concept and Total Score', 'Business Immigration Registration', null);
            
            CALL `createCaseField` ('jobPlanEI', 5, 'Job Plan Assessment', 0, 'N', 'N', 'Business Concept and Total Score', 'Business Immigration Registration', null);
            CALL `createCaseField` ('impactOccupationsEI', 5, 'High Impact Occupations', 0, 'N', 'N', 'Business Concept and Total Score', 'Business Immigration Registration', null);
        "
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute(
            "           
            DELETE FROM divisions
            WHERE `name` IN ('EI Final Report Intake', 'EI Final Report Expired');
            
            DELETE FROM client_form_default
            WHERE `value` IN ('EI Final Report Submitted', 'EI Final Report Expired');
        "
        );
    }
}