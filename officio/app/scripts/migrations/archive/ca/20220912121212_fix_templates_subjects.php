<?php

use Officio\Migration\AbstractMigration;

class FixTemplatesSubjects extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE templates SET `subject` = REPLACE(`subject`, 'Reciept', 'Receipt') WHERE `subject` LIKE '%Reciept%';");
        $this->execute("UPDATE templates SET `subject` = REPLACE(`subject`, 'reciept', 'receipt') WHERE `subject` LIKE '%reciept%';");
        $this->execute("UPDATE templates SET `name` = REPLACE(`name`, 'Reciept', 'Receipt') WHERE `name` LIKE '%Reciept%';");
        $this->execute("UPDATE templates SET `name` = REPLACE(`name`, 'reciept', 'receipt') WHERE `name` LIKE '%reciept%';");
    }

    public function down()
    {
    }
}
