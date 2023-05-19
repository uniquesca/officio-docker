<?php

use Officio\Migration\AbstractMigration;

class ConvertVisaPostsToVac extends AbstractMigration
{
    public function up()
    {
        $fieldId = 'immigration_office';

        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'manage-company-edit';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found for public access.');
        }

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'superadmin',
                    'resource_id'        => 'manage-vac',
                    'resource_privilege' => '',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $arrIncorrectValues = $this->fetchAll("SELECT d.* FROM `client_form_data` AS d LEFT JOIN client_form_default AS def ON def.form_default_id = d.value WHERE def.value IS NULL AND d.field_id IN (SELECT field_id FROM `client_form_fields` WHERE company_field_id = '$fieldId')");

        if (!empty($arrIncorrectValues)) {
            $arrFieldsIds = [];
            foreach ($arrIncorrectValues as $arrIncorrectValueRecord) {
                $arrFieldsIds[] = $arrIncorrectValueRecord['field_id'];
            }

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('client_form_default')
                ->where(['field_id IN' => array_unique($arrFieldsIds)])
                ->orderAsc('field_id')
                ->orderAsc('order')
                ->execute();

            $arrCorrectDefaultValues = $statement->fetchAll('assoc');

            $maxFieldOrderMapping     = [];
            $arrGroupedCorrectOptions = [];
            foreach ($arrCorrectDefaultValues as $arrCorrectDefaultValueRecord) {
                $this->getQueryBuilder()
                    ->update('client_form_data')
                    ->set('value', $arrCorrectDefaultValueRecord['form_default_id'])
                    ->where([
                        'field_id' => $arrCorrectDefaultValueRecord['field_id'],
                        'value'    => $arrCorrectDefaultValueRecord['value']
                    ])
                    ->execute();

                $arrGroupedCorrectOptions[$arrCorrectDefaultValueRecord['field_id']][] = $arrCorrectDefaultValueRecord['value'];

                if (!isset($maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']])) {
                    $maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']] = 0;
                }
                $maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']] = max($maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']], $arrCorrectDefaultValueRecord['order']);
            }

            foreach ($arrIncorrectValues as $arrIncorrectValueRecord) {
                if (!in_array($arrIncorrectValueRecord['value'], $arrGroupedCorrectOptions[$arrIncorrectValueRecord['field_id']])) {
                    $arrGroupedCorrectOptions[$arrIncorrectValueRecord['field_id']][] = $arrIncorrectValueRecord['value'];

                    $maxFieldOrderMapping[$arrIncorrectValueRecord['field_id']] += 1;

                    $arrNewOptionInsert = [
                        'field_id' => $arrIncorrectValueRecord['field_id'],
                        'value'    => $arrIncorrectValueRecord['value'],
                        'order'    => $maxFieldOrderMapping[$arrIncorrectValueRecord['field_id']],
                    ];

                    $statement = $this->getQueryBuilder()
                        ->insert(array_keys($arrNewOptionInsert))
                        ->into('client_form_default')
                        ->values($arrNewOptionInsert)
                        ->execute();

                    // Save the mapping
                    $createdOptionId = $statement->lastInsertId('client_form_default');

                    $this->getQueryBuilder()
                        ->update('client_form_data')
                        ->set('value', $createdOptionId)
                        ->where([
                            'field_id' => $arrIncorrectValueRecord['field_id'],
                            'value'    => $arrIncorrectValueRecord['value']
                        ])
                        ->execute();
                }
            }
        }

        $statement = $this->getQueryBuilder()
            ->select(array('field_id'))
            ->from('client_form_fields')
            ->where(['company_field_id' => $fieldId])
            ->orderAsc('field_id')
            ->execute();

        $arrVACFieldsIds = array_column($statement->fetchAll('assoc'), 'field_id');

        if (!empty($arrVACFieldsIds)) {
            $this->execute("UPDATE `client_form_fields` SET `label`='VAC/Visa Office', `type`=26 WHERE company_field_id = '$fieldId'");
        }

        $statement = $this->getQueryBuilder()
            ->select(array('company_id'))
            ->from('company')
            ->orderAsc('company_id')
            ->execute();

        $arrCompanyIds = array_column($statement->fetchAll('assoc'), 'company_id');

        $this->execute("CREATE TABLE `client_vac` (
            `client_vac_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `client_vac_parent_id` INT(10) UNSIGNED NULL DEFAULT NULL,
            `company_id` BIGINT(19) NULL DEFAULT NULL,
            `client_vac_country` CHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `client_vac_city` CHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `client_vac_link` CHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `client_vac_order` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
            `client_vac_deleted` ENUM('Y','N') NOT NULL DEFAULT 'N' COLLATE 'utf8_general_ci',
            PRIMARY KEY (`client_vac_id`) USING BTREE,
            INDEX `FK_client_vac_parent_id` (`client_vac_parent_id`) USING BTREE,
            INDEX `FK_client_vac_company_id` (`company_id`) USING BTREE,
            CONSTRAINT `FK_client_vac_parent_id` FOREIGN KEY (`client_vac_parent_id`) REFERENCES `client_vac` (`client_vac_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_vac_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='VAC list for each company.'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $arrDefaultVACs = [];

        // Add custom records for the default company (so will be copied to all others
        $arrDefaultVisaPosts = $this->fetchAll("SELECT * FROM client_form_default WHERE field_id IN (SELECT field_id FROM client_form_fields WHERE company_field_id = '$fieldId' AND company_id = 0)");
        foreach ($arrDefaultVisaPosts as $arrDefaultVisaPostRecord) {
            $booFound = false;
            foreach ($arrDefaultVACs as $arrDefaultVACInfo) {
                if ($arrDefaultVACInfo['city'] == $arrDefaultVisaPostRecord['value']) {
                    $booFound = true;
                    break;
                }
            }

            if (!$booFound) {
                $arrDefaultVACs[] = [
                    'city'    => $arrDefaultVisaPostRecord['value'],
                    'country' => null,
                    'link'    => null,
                ];
            }
        }

        $i                        = 0;
        $arrDefaultMapping        = [];
        $arrNewCategoriesToCreate = [];
        foreach ($arrDefaultVACs as $key => $arrDefaultVACInfo) {
            foreach ($arrCompanyIds as $companyId) {
                $arrNewVACInsert = [
                    'company_id'         => $companyId,
                    'client_vac_country' => $arrDefaultVACInfo['country'],
                    'client_vac_city'    => $arrDefaultVACInfo['city'],
                    'client_vac_link'    => $arrDefaultVACInfo['link'],
                    'client_vac_order'   => $i,
                    'client_vac_deleted' => 'N',
                ];

                if (empty($companyId)) {
                    $statement = $this->getQueryBuilder()
                        ->insert(array_keys($arrNewVACInsert))
                        ->into('client_vac')
                        ->values($arrNewVACInsert)
                        ->execute();

                    // Save the mapping
                    $createdVACId = $statement->lastInsertId('client_vac');

                    $arrDefaultMapping[$key] = $createdVACId;
                } else {
                    $arrNewVACInsert['client_vac_parent_id'] = $arrDefaultMapping[$key];

                    $arrNewCategoriesToCreate[] = $arrNewVACInsert;
                }
            }
            $i++;
        }

        if (!empty($arrNewCategoriesToCreate)) {
            $this->table('client_vac')
                ->insert($arrNewCategoriesToCreate)
                ->save();

            echo 'Created VAC records for all companies' . PHP_EOL;
        }

        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('client_vac')
            ->orderAsc('company_id')
            ->orderAsc('client_vac_order')
            ->execute();

        $arrSavedVACRecords = $statement->fetchAll('assoc');

        $maxOrderMapping           = [];
        $arrSavedVACRecordsGrouped = [];
        foreach ($arrSavedVACRecords as $arrSavedVACRecordInfo) {
            $arrSavedVACRecordsGrouped[$arrSavedVACRecordInfo['company_id']][mb_strtolower($arrSavedVACRecordInfo['client_vac_city'])] = $arrSavedVACRecordInfo['client_vac_id'];

            if (!isset($maxOrderMapping[$arrSavedVACRecordInfo['company_id']])) {
                $maxOrderMapping[$arrSavedVACRecordInfo['company_id']] = 0;
            }
            $maxOrderMapping[$arrSavedVACRecordInfo['company_id']] = max($maxOrderMapping[$arrSavedVACRecordInfo['company_id']], $arrSavedVACRecordInfo['client_vac_order']);
        }

        echo 'Start previously saved records updating' . PHP_EOL;

        $arrDefaultValues = [];
        if (!empty($arrVACFieldsIds)) {
            $statement = $this->getQueryBuilder()
                ->select(['d.*', 'f.company_id'])
                ->from(array('d' => 'client_form_default'))
                ->innerJoin(array('f' => 'client_form_fields'), ['f.field_id = d.field_id'])
                ->where(['d.field_id IN' => array_unique($arrVACFieldsIds)])
                ->orderAsc('f.field_id')
                ->orderAsc('d.order')
                ->execute();

            $arrDefaultValues = $statement->fetchAll('assoc');
        }

        foreach ($arrDefaultValues as $arrDefaultValueInfo) {
            if (isset($arrSavedVACRecordsGrouped[$arrDefaultValueInfo['company_id']][mb_strtolower($arrDefaultValueInfo['value'])])) {
                $createdVACId = $arrSavedVACRecordsGrouped[$arrDefaultValueInfo['company_id']][mb_strtolower($arrDefaultValueInfo['value'])];
            } else {
                $maxOrderMapping[$arrDefaultValueInfo['company_id']] += 1;

                $arrNewVACInsert = [
                    'company_id'         => $arrDefaultValueInfo['company_id'],
                    'client_vac_country' => null,
                    'client_vac_city'    => $arrDefaultValueInfo['value'],
                    'client_vac_link'    => null,
                    'client_vac_order'   => $maxOrderMapping[$arrDefaultValueInfo['company_id']],
                    'client_vac_deleted' => 'N',
                ];

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrNewVACInsert))
                    ->into('client_vac')
                    ->values($arrNewVACInsert)
                    ->execute();

                // Save the mapping
                $createdVACId = $statement->lastInsertId('client_vac');

                $arrSavedVACRecordsGrouped[$arrDefaultValueInfo['company_id']][mb_strtolower($arrDefaultValueInfo['value'])] = $createdVACId;
            }

            $this->getQueryBuilder()
                ->update('client_form_data')
                ->set('value', $createdVACId)
                ->where([
                    'field_id' => $arrDefaultValueInfo['field_id'],
                    'value'    => $arrDefaultValueInfo['form_default_id']
                ])
                ->execute();
        }

        echo 'Updated previously saved records' . PHP_EOL;

        if (!empty($arrVACFieldsIds)) {
            $this->getQueryBuilder()
                ->delete('client_form_default')
                ->where(['field_id IN' => array_unique($arrVACFieldsIds)])
                ->execute();
        }
    }

    public function down()
    {
        $fieldId = 'immigration_office';
        $this->execute("UPDATE `client_form_fields` SET `label`='Visa Posts', `type`=3 WHERE company_field_id = '$fieldId'");
        $this->execute("DROP TABLE `client_vac`;");
    }
}
