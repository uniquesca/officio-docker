<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddQueueLeftSection extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (95, 'applicants', 'queue', 'load-queues-with-count');");
        $this->execute("ALTER TABLE `users` ADD COLUMN `queue_show_in_left_panel` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `time_tracker_round_up`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id` = 95 AND `module_id` = 'applicants' AND `resource_id` = 'queue' AND `resource_privilege` = 'load-queues-with-count';");
        $this->execute("ALTER TABLE `users` DROP COLUMN `queue_show_in_left_panel`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}