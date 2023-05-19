<?php

use Phinx\Migration\AbstractMigration;

class AddDivisionsGroups extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `divisions_groups` (
          `division_group_id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
          `company_id` BIGINT(20) DEFAULT NULL,
          `division_group_salutation` CHAR(255) DEFAULT NULL,
          `division_group_first_name` CHAR(255) DEFAULT NULL,
          `division_group_last_name` CHAR(255) DEFAULT NULL,
          `division_group_position` CHAR(255) DEFAULT NULL,
          `division_group_company` CHAR(255) DEFAULT NULL,
          `division_group_address1` CHAR(255) DEFAULT NULL,
          `division_group_address2` CHAR(255) DEFAULT NULL,
          `division_group_city` CHAR(255) DEFAULT NULL,
          `division_group_state` CHAR(255) DEFAULT NULL,
          `division_group_country` CHAR(255) DEFAULT NULL,
          `division_group_postal_code` CHAR(255) DEFAULT NULL,
          `division_group_phone_main` CHAR(255) DEFAULT NULL,
          `division_group_phone_secondary` CHAR(255) DEFAULT NULL,
          `division_group_email_primary` CHAR(255) DEFAULT NULL,
          `division_group_email_other` CHAR(255) DEFAULT NULL,
          `division_group_fax` CHAR(255) DEFAULT NULL,
          `division_group_notes` TEXT NULL,
          `division_group_is_system` ENUM('Y', 'N') DEFAULT 'N',
          `division_group_status` ENUM('active','inactive','suspended') DEFAULT 'active',
          PRIMARY KEY (`division_group_id`),
          KEY `FK_divisions_company` (`company_id`),
          CONSTRAINT `FK_divisions_groups_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) COMMENT='List of offices/divisions groups.' ENGINE=InnoDB DEFAULT CHARSET=UTF8;"
        );

        $this->execute("ALTER TABLE `divisions` ADD COLUMN `division_group_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `division_id`");
        $this->execute("ALTER TABLE `divisions` ADD CONSTRAINT `FK_divisions_divisions_groups` FOREIGN KEY (`division_group_id`) REFERENCES `divisions_groups` (`division_group_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->execute("ALTER TABLE `divisions` ADD COLUMN `access_owner_can_edit` ENUM('Y','N') NULL DEFAULT 'N' AFTER `name`");
        $this->execute("ALTER TABLE `divisions` ADD COLUMN `access_permanent` ENUM('Y','N') NULL DEFAULT 'N' AFTER `access_owner_can_edit`");

        $this->execute("ALTER TABLE `acl_roles` ADD COLUMN `division_group_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `company_id`");
        $this->execute("ALTER TABLE `acl_roles` ADD CONSTRAINT `FK_roles_divisions_groups` FOREIGN KEY (`division_group_id`) REFERENCES `divisions_groups` (`division_group_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `members` ADD COLUMN `division_group_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `company_id`");
        $this->execute("ALTER TABLE `members` ADD CONSTRAINT `FK_members_divisions_groups` FOREIGN KEY (`division_group_id`) REFERENCES `divisions_groups` (`division_group_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1040, 'superadmin', 'manage-divisions-groups', '', 1)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1, 'api', 'index', 'register-agent', 1)");

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from('company', 'company_id');

        $arrCompanies = $db->fetchCol($select);

        foreach ($arrCompanies as $companyId) {
            $db->insert(
                'divisions_groups',
                array(
                    'company_id'               => $companyId,
                    'division_group_company'   => 'Main',
                    'division_group_is_system' => 'Y'
                )
            );

            $groupId = $db->lastInsertId('divisions_groups');

            $db->update(
                'divisions',
                array('division_group_id' => $groupId),
                $db->quoteInto('company_id = ?', $companyId, 'INT')
            );

            $db->update(
                'acl_roles',
                array('division_group_id' => $groupId),
                $db->quoteInto('company_id = ?', $companyId, 'INT')
            );

            $db->update(
                'members',
                array('division_group_id' => $groupId),
                $db->quoteInto('company_id = ?', $companyId, 'INT')
            );
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1 AND `module_id`='api' AND `resource_id`='index' AND `resource_privilege`='register-agent'");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1040 AND `module_id`='superadmin' AND `resource_id`='manage-divisions-groups' AND `resource_privilege`=''");

        $this->execute("ALTER TABLE `members` DROP FOREIGN KEY `FK_members_divisions_groups`;");
        $this->execute("ALTER TABLE `members` DROP COLUMN `division_group_id`;");

        $this->execute("ALTER TABLE `acl_roles` DROP FOREIGN KEY `FK_roles_divisions_groups`;");
        $this->execute("ALTER TABLE `acl_roles` DROP COLUMN `division_group_id`;");

        $this->execute("ALTER TABLE `divisions` DROP FOREIGN KEY `FK_divisions_divisions_groups`;");
        $this->execute("ALTER TABLE `divisions` DROP COLUMN `division_group_id`;");
        $this->execute("ALTER TABLE `divisions` DROP COLUMN `access_owner_can_edit`;");
        $this->execute("ALTER TABLE `divisions` DROP COLUMN `access_permanent`;");

        $this->execute("DROP TABLE IF EXISTS `divisions_groups`;");
    }
}
