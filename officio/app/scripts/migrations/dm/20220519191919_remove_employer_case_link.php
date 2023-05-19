<?php

use Phinx\Migration\AbstractMigration;

class RemoveEmployerCaseLink extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $this->execute("ALTER TABLE `clients` ADD COLUMN `employer_sponsorship_case_id` BIGINT(20) NULL DEFAULT NULL AFTER `applicant_type_id`;");
        $this->execute("ALTER TABLE `clients` ADD CONSTRAINT `FK_clients_members_2` FOREIGN KEY (`employer_sponsorship_case_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        // Get all cases
        $arrAllCasesIds   = [];
        $arrAllSavedCases = $this->fetchAll("SELECT m.member_id FROM members AS m WHERE m.userType = 3");
        foreach ($arrAllSavedCases as $arrAllCaseInfo) {
            $arrAllCasesIds[$arrAllCaseInfo['member_id']] = 1;
        }

        // Load saved links, update the new column
        $arrSavedLinks = $this->fetchAll("SELECT d.member_id, d.value FROM client_form_data AS d WHERE d.field_id IN (SELECT field_id FROM client_form_fields AS f WHERE f.type = 29)");
        foreach ($arrSavedLinks as $arrSavedLinkInfo) {
            if (is_numeric($arrSavedLinkInfo['value']) && isset($arrAllCasesIds[$arrSavedLinkInfo['value']])) {
                $builder = $this->getQueryBuilder();
                $builder->update('clients')
                    ->set(['employer_sponsorship_case_id' => $arrSavedLinkInfo['value']])
                    ->where(['member_id' => $arrSavedLinkInfo['member_id']])
                    ->execute();
            }
        }

        $this->execute("DELETE FROM client_form_fields WHERE `type` = 29");
        $this->execute("DELETE FROM field_types WHERE `field_type_id` = 29");
    }

    public function down()
    {
        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_text_aliases`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_max_length`, `field_type_with_options`, `field_type_with_default_value`, `field_type_with_custom_height`, `field_type_use_for`) VALUES (29, 'employer_case_link', NULL, 'Employer Sponsorship Case', 'N', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("ALTER TABLE `clients` DROP FOREIGN KEY `FK_clients_members_2`;");
        $this->execute("ALTER TABLE `clients` DROP COLUMN `employer_sponsorship_case_id`;");
    }
}