<?php

use Phinx\Migration\AbstractMigration;

class AddDecemberPromotion extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `pricing_categories` (`pricing_category_id`, `name`, `expiry_date`, `key_string`) VALUES (3, 'Promotion 2', '2017-12-29 00:00:00', 'Dec2017');");

        $this->execute("INSERT INTO `pricing_category_details` VALUES (NULL, 3, 1, 5, 0.5, 699, 69, 1275, 69, 799, 0, 1, 2);");
        $this->execute("INSERT INTO `pricing_category_details` VALUES (NULL, 3, 2, 5, 0.5, 699, 69, 1799, 99, 1199, 1, 1, 10);");
        $this->execute("INSERT INTO `pricing_category_details` VALUES (NULL, 3, 3, 5, 0.5, 999, 99, 2380, 95, 950, 1, 1, 50);");
    }

    public function down()
    {
        $this->execute("DELETE FROM `pricing_categories` WHERE  `pricing_category_id` = 3;");
    }
}