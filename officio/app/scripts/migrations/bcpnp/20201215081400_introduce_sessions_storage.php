<?php

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
            ENGINE=InnoDB;
        "
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE session;");
    }
}