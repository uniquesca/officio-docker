CREATE DATABASE `uniques_main_statistics` /*!40100 CHARACTER SET utf8 COLLATE 'utf8_general_ci' */;
DROP TABLE IF EXISTS `statistics`;
CREATE TABLE IF NOT EXISTS `statistics` (
  `statistic_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `statistic_date` DATETIME NOT NULL,
  `statistic_member_id` BIGINT(20) NOT NULL,
  `statistic_module` VARCHAR(255) DEFAULT NULL,
  `statistic_controller` VARCHAR(255) DEFAULT NULL,
  `statistic_action` VARCHAR(255) DEFAULT NULL,
  `statistic_ip` VARCHAR(39) DEFAULT NULL, /* IP V6 max 39 chars */
  `statistic_details` TEXT NULL,
  `statistic_gen_time` DOUBLE(11,4) UNSIGNED NOT NULL,
  `statistic_memory_used` INT(11) NOT NULL,
  PRIMARY KEY (`statistic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
