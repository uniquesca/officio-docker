<?php

use Officio\Migration\AbstractMigration;

class AddClientReferralsFieldType extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'clients-profile-edit';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found for public access.');
        }

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'save-client-referral',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'remove-client-referrals',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'get-assigned-client-referrals',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'get-client-referrals',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $this->execute("CREATE TABLE `client_referrals` (
            `referral_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
            `member_id` BIGINT(20) NOT NULL,
            `client_id` BIGINT(20) NULL DEFAULT NULL,
            `prospect_id` BIGINT(20) NULL DEFAULT NULL,
            `referral_compensation_arrangement` VARCHAR(255) NULL DEFAULT NULL,
            `referral_is_paid` ENUM('Y','N') NOT NULL DEFAULT 'N',
            PRIMARY KEY (`referral_id`) USING BTREE,
            INDEX `FK_client_referrals_1` (`member_id`) USING BTREE,
            INDEX `FK_client_referrals_2` (`client_id`) USING BTREE,
            INDEX `FK_client_referrals_3` (`prospect_id`) USING BTREE,
            CONSTRAINT `FK_client_referrals_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_referrals_2` FOREIGN KEY (`client_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_referrals_3` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Linked clients/prospects to the client (referrals)'");
        
        
        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`) VALUES
            (47, 'client_referrals', 'Client Referrals');");

        $this->execute("ALTER TABLE `applicant_form_fields`
            CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo','reference','authorized_agents','hyperlink','client_referrals') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `applicant_form_fields`
            CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo','reference','authorized_agents','hyperlink') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");
        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=47;");

        $this->execute("DROP TABLE `client_referrals`;");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'applicants' AND resource_privilege = 'save-client-referral';");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'applicants' AND resource_privilege = 'remove-client-referrals';");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'applicants' AND resource_privilege = 'get-assigned-client-referrals';");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'applicants' AND resource_privilege = 'get-client-referrals';");
    }
}
