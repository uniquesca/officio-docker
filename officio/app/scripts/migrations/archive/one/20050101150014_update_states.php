<?php

use Phinx\Migration\AbstractMigration;

class UpdateStates extends AbstractMigration
{
    public function up()
    {
/*
 CHECK:
 SELECT *, COUNT(*) AS total_count
 FROM client_form_dependents AS d
 GROUP BY d.member_id, d.relationship, d.line
 HAVING total_count > 1;
 */
        // Took 43s on local server
        $this->execute("UPDATE `client_form_dependents` SET `relationship`='child', `line`='4' WHERE  `member_id`=1765 AND `relationship`='spouse' AND `line`=0 AND `fName`='Laurel Philgiana' AND `lName`='RENEGBANGA YASSEKOTHA' AND `sex`='M' AND `DOB`='2003-04-24' AND `passport_num` IS NULL AND `passport_date` IS NULL AND `canadian`='Y' AND `country_of_birth`='' AND `country_of_citizenship`='' AND `city_of_residence` IS NULL AND `country_of_residence`='' LIMIT 1;");
        $this->execute("UPDATE `client_form_dependents` SET `relationship`='child', `line`='5' WHERE  `member_id`=1765 AND `relationship`='spouse' AND `line`=0 AND `fName`='Gloria FrΘdΘrique' AND `lName`='RENEGBANGA KAPASSIOMO' AND `sex`='M' AND `DOB`='2006-02-25' AND `passport_num` IS NULL AND `passport_date` IS NULL AND `canadian`='Y' AND `country_of_birth`='' AND `country_of_citizenship`='' AND `city_of_residence` IS NULL AND `country_of_residence`='' LIMIT 1;");
        $this->execute("UPDATE `client_form_dependents` SET `relationship`='child', `line`='1' WHERE  `member_id`=1870 AND `relationship`='spouse' AND `line`=0 AND `fName`='Nargish' AND `lName`='KHAN' AND `sex`='' AND `DOB`='1977-08-01' AND `passport_num`='' AND `passport_date` IS NULL AND `canadian`='' AND `country_of_birth`='' AND `country_of_citizenship`='' AND `city_of_residence` IS NULL AND `country_of_residence`='' LIMIT 1;");
        $this->execute('ALTER TABLE `client_form_dependents` ADD PRIMARY KEY (`member_id`, `relationship`, `line`);');
        $this->execute('ALTER TABLE `client_form_dependents` ADD COLUMN `migrating` VARCHAR(255) NULL DEFAULT NULL AFTER `country_of_residence`;');
        $this->execute('ALTER TABLE `client_form_dependents` ADD COLUMN `nationality` VARCHAR(255) NULL DEFAULT NULL AFTER `migrating`;');
        $this->execute('ALTER TABLE `client_form_dependents` ADD COLUMN `medical_expiration_date` DATE NULL DEFAULT NULL AFTER `nationality`;');

        $this->execute('CREATE TABLE `states` (
        	`state_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        	`country_id` INT(11) NOT NULL,
        	`state_name` VARCHAR(250) NOT NULL,
          `state_order` INT(11) NOT NULL default 0,
          PRIMARY KEY  (`state_id`),
        	INDEX `FK_states_country_master` (`country_id`),
        	CONSTRAINT `FK_states_country_master` FOREIGN KEY (`country_id`) REFERENCES `country_master` (`countries_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');

        $this->execute("INSERT INTO `states` (`country_id`, `state_name`, `state_order`) VALUES
        /* Canadian States */
          (38, 'Alberta', 0),
          (38, 'British Columbia', 1),
          (38, 'Manitoba', 2),
          (38, 'New Brunswick', 3),
          (38, 'Newfoundland and Labrador', 4),
          (38, 'Northwest Territories', 5),
          (38, 'Nova Scotia', 6),
          (38, 'Nunavut', 7),
          (38, 'Ontario', 8),
          (38, 'Prince Edward Island', 9),
          (38, 'Quebec', 10),
          (38, 'Saskatchewan', 11),
          (38, 'Yukon', 12),
        
        /* Australian States */
          (13, 'Queensland', 0),
          (13, 'New South Wales', 1),
          (13, 'Victoria', 2),
          (13, 'Australian Capital Territory', 3),
          (13, 'South Australia', 4),
          (13, 'Western Australia', 5),
          (13, 'Northern Territory', 6),
          (13, 'Tasmania', 7);");

        $this->execute('ALTER TABLE `faq_sections`
        	ADD COLUMN `parent_section_id` INT UNSIGNED NULL DEFAULT NULL AFTER `faq_section_id`,
        	ADD CONSTRAINT `FK_faq_sections_faq_sections` FOREIGN KEY (`parent_section_id`) REFERENCES `faq_sections` (`faq_section_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;');

        $this->execute('ALTER TABLE `faq_sections` CHANGE COLUMN `parent_section_id` `parent_section_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `faq_section_id`;');

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`, `rule_order`) VALUES (192, 5, 'help', 'F.A.Q.', 'faq-public-view', 0, 20);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`) VALUES (192, 'help', 'public');");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('guest', 192);");
    }

    public function down()
    {
    }
}