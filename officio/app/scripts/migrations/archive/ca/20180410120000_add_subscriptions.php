<?php

use Officio\Migration\AbstractMigration;

class AddSubscriptions extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `subscriptions` (
                `subscription_id` varchar(255) NOT NULL,
                `subscription_name` varchar(255) DEFAULT NULL,
                `subscription_hidden` ENUM('Y','N') NOT NULL DEFAULT 'N',
                `subscription_order` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
                KEY `subscription_id` (`subscription_id`)
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB;"
        );

        $this->execute(
            "INSERT INTO `subscriptions` (`subscription_id`, `subscription_name`, `subscription_hidden`, `subscription_order`) VALUES
            ('lite', 'OfficioLite', 'N', 1),
            ('pro', 'OfficioPro', 'N', 2),
            ('pro13', 'OfficioPro (with Pack 1 and 3)', 'N', 3),
            ('ultimate', 'OfficioUltimate', 'N', 4),
            ('ultimate_plus', 'OfficioUltimatePlus', 'Y', 5);"
        );


        $this->execute(
            "CREATE TABLE `subscriptions_packages` (
                `subscription_id` VARCHAR(255) NOT NULL,
                `package_id` INT(11) NOT NULL,
                INDEX `FK_subscriptions_packages_subscriptions` (`subscription_id`),
                INDEX `FK_subscriptions_packages_packages` (`package_id`),
                CONSTRAINT `FK_subscriptions_packages_packages` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_subscriptions_packages_subscriptions` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB"
        );

        $this->execute(
            "INSERT INTO `subscriptions_packages` (`subscription_id`, `package_id`) VALUES
                ('lite', 1),
                ('pro', 1),
                ('pro', 2),
                ('pro13', 1),
                ('pro13', 3),
                ('ultimate', 1),
                ('ultimate', 2),
                ('ultimate', 3),
                ('ultimate_plus', 1),
                ('ultimate_plus', 2),
                ('ultimate_plus', 3),
                ('ultimate_plus', 4);"
        );

        $this->execute('ALTER TABLE `pricing_category_details` ADD COLUMN `subscription_id` VARCHAR(255) NOT NULL AFTER `package_id`;');
        $this->execute("UPDATE `pricing_category_details` SET `subscription_id`='lite'          WHERE  `package_id` = 1;");
        $this->execute("UPDATE `pricing_category_details` SET `subscription_id`='pro'           WHERE  `package_id` = 2;");
        $this->execute("UPDATE `pricing_category_details` SET `subscription_id`='ultimate'      WHERE  `package_id` = 3;");
        $this->execute("UPDATE `pricing_category_details` SET `subscription_id`='ultimate_plus' WHERE  `package_id` = 4;");
        $this->execute('ALTER TABLE `pricing_category_details` ADD CONSTRAINT `FK_pricing_category_details_subscriptions` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute('ALTER TABLE `pricing_category_details` DROP FOREIGN KEY `FK_pricing_category_details_packages`;');
        $this->execute('ALTER TABLE `pricing_category_details` DROP INDEX `FK_pricing_category_details_packages`;');
        $this->execute('ALTER TABLE `pricing_category_details` DROP COLUMN `package_id`;');
        $this->execute("UPDATE `prospects` SET `package_type` = NULL WHERE `package_type` = ''");
    }

    public function down()
    {
        $this->execute('ALTER TABLE `pricing_category_details` ADD COLUMN `package_id` int(11) NOT NULL AFTER `pricing_category_id`;');
        $this->execute("UPDATE `pricing_category_details` SET `package_id` = 1  WHERE  `subscription_id`='lite';");
        $this->execute("UPDATE `pricing_category_details` SET `package_id` = 2  WHERE  `subscription_id`='pro';");
        $this->execute("UPDATE `pricing_category_details` SET `package_id` = 3  WHERE  `subscription_id`='ultimate';");
        $this->execute("UPDATE `pricing_category_details` SET `package_id` = 4  WHERE  `subscription_id`='ultimate_plus';");
        $this->execute('ALTER TABLE `pricing_category_details` ADD CONSTRAINT `FK_pricing_category_details_packages` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON DELETE CASCADE ON UPDATE CASCADE');

        $this->execute('ALTER TABLE `pricing_category_details` DROP FOREIGN KEY `FK_pricing_category_details_subscriptions`;');
        $this->execute('ALTER TABLE `pricing_category_details` DROP COLUMN `subscription_id`;');
        $this->execute('DROP TABLE `subscriptions_packages`');
        $this->execute('DROP TABLE `subscriptions`');
    }
}