<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class IntroduceSessionsStorage extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            CREATE TABLE `sessions` (
                `id` char(32),
                `name` char(32),
                `modified` int,
                `lifetime` int,
                `data` text,
                 PRIMARY KEY (`id`, `name`)
            )
            COMMENT='Officio user sessions'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB;
        "
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE session;");
    }
}