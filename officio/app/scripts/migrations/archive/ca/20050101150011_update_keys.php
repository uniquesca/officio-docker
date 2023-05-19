<?php

use Officio\Migration\AbstractMigration;

class UpdateKeys extends AbstractMigration
{
    public function up()
    {
        // Took 76s on local server
        $this->execute('DELETE FROM client_form_group_access WHERE role_id NOT IN (SELECT r.role_id FROM acl_roles as r);');
        $this->execute('ALTER TABLE `client_form_group_access`
            CHANGE COLUMN `role_id` `role_id` INT(11) NULL DEFAULT NULL AFTER `access_id`,
            ADD CONSTRAINT `FK_client_form_group_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_details WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_details`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AUTO_INCREMENT FIRST,
            ADD CONSTRAINT `FK_company_details_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE company_invoice SET company_id = NULL WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_invoice`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `prospect_id`,
            ADD CONSTRAINT `FK_company_invoice_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_packages WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_packages`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL FIRST,
            ADD CONSTRAINT `FK_company_packages_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_prospects_notes WHERE prospect_id NOT IN (SELECT p.prospect_id FROM company_prospects AS p);');
        $this->execute('ALTER TABLE `company_prospects_notes` ADD CONSTRAINT `FK_company_prospects_notes_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_prospects_templates WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_prospects_templates` ADD CONSTRAINT `FK_company_prospects_templates_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_ta WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_ta`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_ta_id`,
            ADD CONSTRAINT `FK_company_ta_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
    }

    public function down()
    {
    }
}