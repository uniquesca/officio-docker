<?php

use Phinx\Migration\AbstractMigration;

class LinkGroupsAndFields extends AbstractMigration
{
    public function up()
    {
        // Link companies groups to the default ones (identified by group name)
        $arrDefaultGroups      = $this->fetchAll('SELECT * FROM client_form_groups WHERE company_id = 0 AND client_type_id = 1');
        $arrAllCompaniesGroups = $this->fetchAll('SELECT * FROM client_form_groups WHERE company_id != 0');

        foreach ($arrDefaultGroups as $arrDefaultGroupInfo) {
            foreach ($arrAllCompaniesGroups as $arrCompanyGroupInfo) {
                if ($arrDefaultGroupInfo['title'] === $arrCompanyGroupInfo['title'] && empty($arrCompanyGroupInfo['parent_group_id'])) {
                    $builder = $this->getQueryBuilder();
                    $builder
                        ->update('client_form_groups')
                        ->set('parent_group_id', $arrDefaultGroupInfo['group_id'])
                        ->where(['group_id' => $arrCompanyGroupInfo['group_id']])
                        ->execute();
                }
            }
        }


        // Link companies fields to the default ones (identified by company_field_id)
        $arrDefaultFields      = $this->fetchAll('SELECT * FROM client_form_fields WHERE company_id = 0');
        $arrAllCompaniesFields = $this->fetchAll('SELECT * FROM client_form_fields WHERE company_id != 0');

        foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
            foreach ($arrAllCompaniesFields as $arrCompanyFieldInfo) {
                if ($arrDefaultFieldInfo['company_field_id'] === $arrCompanyFieldInfo['company_field_id'] && empty($arrCompanyFieldInfo['parent_field_id'])) {
                    $builder = $this->getQueryBuilder();
                    $builder
                        ->update('client_form_fields')
                        ->set('parent_field_id', $arrDefaultFieldInfo['field_id'])
                        ->where(['field_id' => $arrCompanyFieldInfo['field_id']])
                        ->execute();
                }
            }
        }
    }

    public function down()
    {
        $this->execute('UPDATE `client_form_groups` SET `parent_group_id` = NULL');
        $this->execute('UPDATE `client_form_fields` SET `parent_field_id` = NULL');
    }
}
