<?php

use Officio\Migration\AbstractMigration;

class IntroduceCaseFieldParentDefaultOptionId extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_default`
            ADD COLUMN `parent_form_default_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `form_default_id`,
            ADD CONSTRAINT `FK_client_form_default_client_form_default` FOREIGN KEY (`parent_form_default_id`) REFERENCES `client_form_default` (`form_default_id`) ON UPDATE CASCADE ON DELETE CASCADE;
        ");

        $arrDefaultFields      = $this->fetchAll("SELECT * FROM client_form_fields WHERE company_id  = 0 AND `type` IN (SELECT field_type_id FROM field_types WHERE field_type_text_id IN ('combo', 'radio', 'multiple_combo'))");
        $arrAllCompaniesFields = $this->fetchAll("SELECT * FROM client_form_fields WHERE company_id != 0 AND `type` IN (SELECT field_type_id FROM field_types WHERE field_type_text_id IN ('combo', 'radio', 'multiple_combo'))");

        $arrGroupedOptions = [];
        $arrAllOptions     = $this->fetchAll("SELECT * FROM client_form_default");
        foreach ($arrAllOptions as $arrOptionInfo) {
            $arrGroupedOptions[$arrOptionInfo['field_id']][] = $arrOptionInfo;
        }

        foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
            foreach ($arrAllCompaniesFields as $arrCompanyFieldInfo) {
                if ($arrDefaultFieldInfo['field_id'] === $arrCompanyFieldInfo['parent_field_id'] && isset($arrGroupedOptions[$arrDefaultFieldInfo['field_id']])) {
                    foreach ($arrGroupedOptions[$arrDefaultFieldInfo['field_id']] as $arrDefaultOptionInfo) {
                        $booDefaultOptionFound = false;
                        if (isset($arrGroupedOptions[$arrCompanyFieldInfo['field_id']])) {
                            foreach ($arrGroupedOptions[$arrCompanyFieldInfo['field_id']] as $arrCompanyOptionInfo) {
                                if ($arrCompanyOptionInfo['value'] == $arrDefaultOptionInfo['value']) {
                                    $this->getQueryBuilder()
                                        ->update('client_form_default')
                                        ->set('parent_form_default_id', $arrDefaultOptionInfo['form_default_id'])
                                        ->where(['form_default_id' => $arrCompanyOptionInfo['form_default_id']])
                                        ->execute();

                                    $booDefaultOptionFound = true;
                                    break;
                                }
                            }
                        }

                        if (!$booDefaultOptionFound) {
                            $arrInsert = [
                                'parent_form_default_id' => $arrDefaultOptionInfo['form_default_id'],
                                'field_id'               => $arrCompanyFieldInfo['field_id'],
                                'value'                  => $arrDefaultOptionInfo['value'],
                                'order'                  => $arrDefaultOptionInfo['order']
                            ];

                            $this->getQueryBuilder()
                                ->insert(array_keys($arrInsert))
                                ->into('client_form_default')
                                ->values($arrInsert)
                                ->execute();
                        }
                    }
                }
            }
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_default` DROP FOREIGN KEY `FK_client_form_default_client_form_default`;");
        $this->execute("ALTER TABLE `client_form_default` DROP COLUMN `parent_form_default_id`;");
    }
}