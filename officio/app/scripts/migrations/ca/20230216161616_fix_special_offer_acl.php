<?php

use Officio\Migration\AbstractMigration;

class FixSpecialOfferAcl extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_modules` (`module_id`, `module_name`) VALUES ('special-offer', 'Special Offer');");
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='special-offer' WHERE  `rule_id`=1 AND `module_id`='specialoffer' AND `resource_id`='index' AND `resource_privilege`='';");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_modules` WHERE `module_id`='special-offer';");
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='specialoffer' WHERE  `rule_id`=1 AND `module_id`='special-offer' AND `resource_id`='index' AND `resource_privilege`='';");
    }
}