 <?php

use Officio\Migration\AbstractMigration;

class AddOfficioFormToFormType extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $this->execute("ALTER TABLE `form_version` MODIFY COLUMN form_type enum('', 'bar', 'officio-form')");

        $this->table('form_assigned')
            ->addColumn('form_settings', 'text', [
                'after' => 'form_status',
                'null'  => true
            ])
            ->save();
    }

    public function down()
    {
        $this->execute("DELETE FROM `form_version` WHERE form_type = 'officio-form'");
        $this->execute("ALTER TABLE `form_version` MODIFY COLUMN form_type enum('', 'bar')");

        $this->table('form_assigned')
            ->removeColumn('form_settings')
            ->save();
    }
}
