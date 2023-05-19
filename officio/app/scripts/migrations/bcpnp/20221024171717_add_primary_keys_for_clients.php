<?php

use Officio\Migration\AbstractMigration;

class AddPrimaryKeysForClients extends AbstractMigration
{
    public function up()
    {
        $this->getAdapter()->commitTransaction();

        // Remove duplicates (with the same member_id/field_id/value
        $this->execute("CREATE TABLE temp_client_form_data LIKE client_form_data;");
        $this->execute("INSERT INTO temp_client_form_data SELECT DISTINCT * FROM client_form_data;");
        $this->execute("DROP TABLE client_form_data;");
        $this->execute("RENAME TABLE temp_client_form_data TO client_form_data;");

        // Remove specific duplicate values
        $allQueries = <<<EOD
DELETE FROM `client_form_data` WHERE `member_id`=284042 AND `field_id`=74584 AND `value`='[""]';
DELETE FROM `client_form_data` WHERE `member_id`=285285 AND `field_id`=74584 AND `value`='["Unit 6 8388 128th Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=285305 AND `field_id`=74584 AND `value`='["Lorimer Rd"]';
DELETE FROM `client_form_data` WHERE `member_id`=285310 AND `field_id`=74584 AND `value`='["4789 Kingsway Suite 250"]';
DELETE FROM `client_form_data` WHERE `member_id`=285648 AND `field_id`=74584 AND `value`='["221-17 Fawcett Road"]';
DELETE FROM `client_form_data` WHERE `member_id`=286572 AND `field_id`=74584 AND `value`='["300 - 4611 Canada Way"]';
DELETE FROM `client_form_data` WHERE `member_id`=286971 AND `field_id`=74584 AND `value`='["595 Burrard Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=287074 AND `field_id`=74584 AND `value`='["West 5th Ave"]';
DELETE FROM `client_form_data` WHERE `member_id`=287403 AND `field_id`=74584 AND `value`='["#501-1166 Alberni Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=288207 AND `field_id`=74584 AND `value`='["UNIT 200 - 120 LONSDALE AVE"]';
DELETE FROM `client_form_data` WHERE `member_id`=290553 AND `field_id`=74584 AND `value`='["600-2889 East 12th Avenue"]';
DELETE FROM `client_form_data` WHERE `member_id`=290665 AND `field_id`=74584 AND `value`='["123 West 7th Avenue"]';
DELETE FROM `client_form_data` WHERE `member_id`=290756 AND `field_id`=74584 AND `value`='["1100-333 Seymour Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=291255 AND `field_id`=74584 AND `value`='["8-3871 North Fraser Way"]';
DELETE FROM `client_form_data` WHERE `member_id`=291893 AND `field_id`=74584 AND `value`='["600-565 Great Northern Way"]';
DELETE FROM `client_form_data` WHERE `member_id`=293735 AND `field_id`=74584 AND `value`='["700 - 675 West Hastings Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=294126 AND `field_id`=74584 AND `value`='["12033 Riverside Way Unit 200"]';
DELETE FROM `client_form_data` WHERE `member_id`=295890 AND `field_id`=74584 AND `value`='["107 4369 Main Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=295975 AND `field_id`=74584 AND `value`='["2299 W 41 Ave."]';
DELETE FROM `client_form_data` WHERE `member_id`=296028 AND `field_id`=74584 AND `value`='["11191 Coppersmith Pl."]';
DELETE FROM `client_form_data` WHERE `member_id`=296819 AND `field_id`=74584 AND `value` LIKE '% 1183 Odlum Drive"]';
DELETE FROM `client_form_data` WHERE `member_id`=297319 AND `field_id`=74584 AND `value`='["Vanier Place"]';
DELETE FROM `client_form_data` WHERE `member_id`=299594 AND `field_id`=74584 AND `value`='["300 - 1055 W Hastings Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=300000 AND `field_id`=74584 AND `value`='["1523 W 3rd Ave"]';
DELETE FROM `client_form_data` WHERE `member_id`=300070 AND `field_id`=74584 AND `value`='["1758 W 8th Avenue"]';
DELETE FROM `client_form_data` WHERE `member_id`=300071 AND `field_id`=74584 AND `value`='["200 - 1 Alexander Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=300072 AND `field_id`=74584 AND `value`='["401-130 Brew Street"]';
DELETE FROM `client_form_data` WHERE `member_id`=300125 AND `field_id`=74584 AND `value`='["12180 86th Ave."]';
DELETE FROM `client_form_data` WHERE `member_id`=300125 AND `field_id`=74814 AND `value`='["E"]';
DELETE FROM `client_form_data` WHERE `member_id`=300125 AND `field_id`=74814 AND `value`='["Suite E"]';
DELETE FROM `client_form_data` WHERE `member_id`=300133 AND `field_id`=74584 AND `value`='["8800 Glenlyon Pkwy BC"]';
DELETE FROM `client_form_data` WHERE `member_id`=302064 AND `field_id`=74584 AND `value`='["188 Agnes St"]';
DELETE FROM `client_form_data` WHERE `member_id`=302378 AND `field_id`=74584 AND `value`='["138 E 13th ST"]';
DELETE FROM `client_form_data` WHERE `member_id`=302378 AND `field_id`=74814 AND `value`='["400"]';
DELETE FROM `client_form_data` WHERE `member_id`=302378 AND `field_id`=74814 AND `value`='["Suite 400"]';
DELETE FROM `client_form_data` WHERE `member_id`=302406 AND `field_id`=74584 AND `value`='["Robert H.Lee Alumni Center - 6163 University Blvd"]';
DELETE FROM `client_form_data` WHERE `member_id`=302407 AND `field_id`=74584 AND `value`='["Quadra street"]';
DELETE FROM `client_form_data` WHERE `member_id`=303052 AND `field_id`=74584 AND `value`='["960 Quayside Dr."]';
EOD;

        $this->execute($allQueries);

        // Add the primary key and foreign keys
        $this->execute("ALTER TABLE `client_form_data`
            CHANGE COLUMN `member_id` `member_id` BIGINT(19) NOT NULL FIRST,
            CHANGE COLUMN `field_id` `field_id` INT(10) UNSIGNED NOT NULL AFTER `member_id`;");
        $this->execute("ALTER TABLE `client_form_data` ADD PRIMARY KEY (`member_id`, `field_id`);");
        $this->execute("ALTER TABLE `client_form_data`
            ADD CONSTRAINT `FK_client_form_data_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            ADD CONSTRAINT `FK_client_form_data_client_form_fields` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
    }
}
