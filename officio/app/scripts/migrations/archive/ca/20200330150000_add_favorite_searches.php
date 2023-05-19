<?php

use Officio\Migration\AbstractMigration;

class AddFavoriteSearches extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `searches_favorites` (
            `member_id` BIGINT(20) NOT NULL,
            `search_id` INT(11) NOT NULL,
            INDEX `FK_searches_favorites_members` (`member_id`) USING BTREE,
            INDEX `FK_searches_favorites_searches` (`search_id`) USING BTREE,
            CONSTRAINT `FK_searches_favorites_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_searches_favorites_searches` FOREIGN KEY (`search_id`) REFERENCES `searches` (`search_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Favorite users searches'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");
    }

    public function down()
    {
        $this->execute("DROP TABLE `searches_favorites`;");
    }
}
