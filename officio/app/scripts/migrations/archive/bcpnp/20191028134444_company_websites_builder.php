<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class CompanyWebsitesBuilder extends AbstractMigration
{
   public function up()
   {
      $this->execute(
         "CREATE TABLE `company_websites_pages` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR (100),
                `html` LONGTEXT,
                `css` LONGTEXT,
                `available` TINYINT(1),
                PRIMARY KEY (id)
            )
            COMMENT='Company websites pages'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB");

      $this->execute(
         "CREATE TABLE `company_websites_builder` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT(20) NOT NULL,
                `template_id` BIGINT(20) NOT NULL,
                `header` LONGTEXT,
                `footer` LONGTEXT,
                `css` LONGTEXT,
                `entrance_name` VARCHAR (100) NOT NULL,
                `homepage` INT(11) UNSIGNED,
                `about` INT(11) UNSIGNED,
                `canada` INT(11) UNSIGNED,
                `immigration` INT(11) UNSIGNED,
                `assessment` INT(11) UNSIGNED,
                `contact` INT(11) UNSIGNED,
                `visible` TINYINT(1),
                PRIMARY KEY (id),
                INDEX `FK_company_websites_builder_company` (`company_id`),
                INDEX `FK_company_websites_builder_template_name` (`template_id`),
                INDEX `FK_company_websites_builder_pages_homepage` (`homepage`),
                INDEX `FK_company_websites_builder_pages_about` (`about`),
                INDEX `FK_company_websites_builder_pages_canada` (`canada`),
                INDEX `FK_company_websites_builder_pages_immigration` (`immigration`),
                INDEX `FK_company_websites_builder_pages_assessment` (`assessment`),
                INDEX `FK_company_websites_builder_pages_contact` (`contact`),
                CONSTRAINT `FK_company_websites_builder_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_company_websites_builder_pages_homepage` FOREIGN KEY (`homepage`) REFERENCES `company_websites_pages` (`id`),
                CONSTRAINT `FK_company_websites_builder_pages_about` FOREIGN KEY (`about`) REFERENCES `company_websites_pages` (`id`),
                CONSTRAINT `FK_company_websites_builder_pages_canada` FOREIGN KEY (`canada`) REFERENCES `company_websites_pages` (`id`),
                CONSTRAINT `FK_company_websites_builder_pages_immigration` FOREIGN KEY (`immigration`) REFERENCES `company_websites_pages` (`id`),
                CONSTRAINT `FK_company_websites_builder_pages_assessment` FOREIGN KEY (`assessment`) REFERENCES `company_websites_pages` (`id`),
                CONSTRAINT `FK_company_websites_builder_pages_contact` FOREIGN KEY (`contact`) REFERENCES `company_websites_pages` (`id`)
            )
            COMMENT='Company websites builder'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB");


      /** @var $cache StorageInterface */
      $cache = Zend_Registry::get('serviceManager')->get('cache');
      if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }

   }

   public function down()
   {
      $this->execute("DROP TABLE `company_websites_builder`;");
      $this->execute("DROP TABLE `company_websites_pages`;");

      /** @var $cache StorageInterface */
      $cache = Zend_Registry::get('serviceManager')->get('cache');
      if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
   }

}
