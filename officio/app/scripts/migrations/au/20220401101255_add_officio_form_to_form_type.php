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


        $statement = $this->getQueryBuilder()
            ->select(['max(v.form_id) as id'])
            ->from(array('v' => 'form_version'))
            ->execute();

        $arrFormId = $statement->fetchAll('assoc');
        $formId = $arrFormId[0]['id'] ?: 0;
        
        $this->table('form_version')->insert(
            [
                [
                    'form_id'       => $formId+1,
                    'form_type'     => 'officio-form',
                    'version_date'  => date('Y-m-d H:i:s'),
                    'file_path'     => '',
                    'file_name'     => 'New Questionnaire',
                    'uploaded_date' => date('Y-m-d H:i:s'),
                    'uploaded_by'   => 1,
                    'size'          => '0kb',
                    'note1'         => '',
                    'note2'         => ''
                ]
            ]
        )->save();

        $fieldId = $this->getAdapter()->getConnection()->lastInsertId();
    }

    public function down()
    {
        $this->execute("ALTER TABLE `form_version` MODIFY COLUMN form_type enum('', 'bar')");

        $this->table('form_assigned')
            ->removeColumn('form_settings')
            ->save();

        $this->execute("DELETE FROM `form_version` WHERE form_type = 'officio-form'");
    }
}
