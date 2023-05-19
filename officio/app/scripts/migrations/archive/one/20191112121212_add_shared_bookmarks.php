<?php

use Phinx\Migration\AbstractMigration;

class AddSharedBookmarks extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `u_links_sharing` (
        	`link_id` INT(11) NOT NULL,
        	`role_id` INT(11) NOT NULL,
        	UNIQUE INDEX `link_id_role_id` (`link_id`, `role_id`),
        	INDEX `FK_u_links_sharing_acl_roles` (`role_id`),
        	CONSTRAINT `FK_u_links_sharing_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_u_links_sharing_u_links` FOREIGN KEY (`link_id`) REFERENCES `u_links` (`link_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Share links based on roles'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `u_links_sharing`;");
    }
}
