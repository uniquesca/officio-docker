<?php

use Officio\Migration\AbstractMigration;

class LinkCaseTypes extends AbstractMigration
{
    public function up()
    {
        // Link companies case types to the default ones (identified by case type name)
        $arrDefaultCaseTypes      = $this->fetchAll('SELECT * FROM client_types WHERE company_id = 0');
        $arrAllCompaniesCaseTypes = $this->fetchAll('SELECT * FROM client_types WHERE company_id != 0');

        foreach ($arrDefaultCaseTypes as $arrDefaultCaseTypeInfo) {
            foreach ($arrAllCompaniesCaseTypes as $arrCompanyCaseTypeInfo) {
                if ($arrDefaultCaseTypeInfo['client_type_name'] === $arrCompanyCaseTypeInfo['client_type_name'] && empty($arrCompanyCaseTypeInfo['parent_client_type_id'])) {
                    $this->getQueryBuilder()
                        ->update('client_types')
                        ->set('parent_client_type_id', $arrDefaultCaseTypeInfo['client_type_id'])
                        ->where(['client_type_id' => $arrCompanyCaseTypeInfo['client_type_id']])
                        ->execute();
                }
            }
        }
    }

    public function down()
    {
        $this->execute('UPDATE `client_types` SET `parent_client_type_id` = NULL');
    }
}
