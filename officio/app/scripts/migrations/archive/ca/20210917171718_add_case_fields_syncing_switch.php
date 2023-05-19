<?php

use Officio\Migration\AbstractMigration;

class AddCaseFieldsSyncingSwitch extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `sync_with_default` ENUM('Yes','No','Label') NOT NULL DEFAULT 'Yes' AFTER `skip_access_requirements`;");
        $this->execute("UPDATE `client_form_fields` SET sync_with_default='No' WHERE company_id = 0;");

        // CA v1 only
        $this->execute("UPDATE `client_form_fields` SET `type`=1 WHERE company_field_id = 'b-file-number' AND company_id = 1;");
        $this->execute("UPDATE `client_form_fields` SET `type`=11 WHERE company_field_id = 'miss_docs_description' AND company_id IN (1, 2);");
        $this->execute("UPDATE `client_form_fields` SET `required`='Y' WHERE company_field_id = 'sales_and_marketing' AND company_id = 41;");
        $this->execute("UPDATE `client_form_fields` SET `required`='Y' WHERE company_field_id = 'processing' AND company_id = 41;");
        $this->execute("UPDATE `client_form_fields` SET `required`='Y' WHERE company_field_id = 'accounting' AND company_id = 41;");
        $this->execute("UPDATE `client_form_fields` SET `maxlength`=25 WHERE company_field_id IN ('file_number_gov_other')");
        $this->execute("UPDATE `client_form_fields` SET `maxlength`=100 WHERE company_field_id IN ('employer')");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Montreal', 52  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Ottawa', 53  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Toronto', 54  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Abu Dhabi', 55  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Bonn', 56  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Beirut', 57  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Dubai', 58  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Rabat', 59  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Dakar', 60  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Kuala Lumpur', 61  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Mississauga', 62  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Sydney', 63  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Tehran', 64  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");
        $this->execute("INSERT INTO `client_form_default` (`field_id`, `value`, `order`) SELECT field_id, 'Lagos', 65  FROM client_form_fields WHERE company_id = 0 AND company_field_id = 'visa_office';");


        $arrAllCompaniesCaseFields                 = $this->fetchAll('SELECT * FROM client_form_fields');
        $arrAllCompaniesCaseFieldsGroupedByCompany = array();
        foreach ($arrAllCompaniesCaseFields as $arrCompanyCaseFieldInfo) {
            $arrAllCompaniesCaseFieldsGroupedByCompany[$arrCompanyCaseFieldInfo['company_id']][] = $arrCompanyCaseFieldInfo;
        }


        $arrAllCompaniesCaseFieldsOptions                 = $this->fetchAll('SELECT * FROM client_form_default');
        $arrAllCompaniesCaseFieldsOptionsGroupedByCompany = array();
        foreach ($arrAllCompaniesCaseFieldsOptions as $arrCompanyCaseFieldInfo) {
            $arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrCompanyCaseFieldInfo['field_id']][] = strtolower($arrCompanyCaseFieldInfo['value']);
        }

        $arrDefaultCaseFields = $arrAllCompaniesCaseFieldsGroupedByCompany[0];
        unset($arrAllCompaniesCaseFieldsGroupedByCompany[0]);

        $arrSetToNo                 = array();
        $arrSetToLabel              = array();
        $arrKeysToCheck             = array('type', 'maxlength', 'encrypted', 'required', 'required_for_submission', 'disabled', 'multiple_values', 'skip_access_requirements', 'custom_height', 'min_value', 'max_value');
        // $arrCompanyFieldsDifference = array();
        // $arrCompanyFieldsDifferenceLabels = array();
        foreach ($arrAllCompaniesCaseFieldsGroupedByCompany as $companyId => $arrCompanyCaseFields) {
            foreach ($arrCompanyCaseFields as $arrCompanyCaseFieldInfo) {
                if ($arrCompanyCaseFieldInfo['company_field_id'] == 'file_status') {
                    continue;
                }

                $booFoundField             = false;
                $booLabelIsDifferent       = false;
                $booAtLeastOneKeyDifferent = false;
                foreach ($arrDefaultCaseFields as $arrDefaultCaseFieldInfo) {
                    if ($arrCompanyCaseFieldInfo['company_field_id'] == $arrDefaultCaseFieldInfo['company_field_id']) {
                        $booFoundField = true;
                        foreach ($arrKeysToCheck as $keyToCheck) {
                            if ($arrDefaultCaseFieldInfo[$keyToCheck] != $arrCompanyCaseFieldInfo[$keyToCheck]) {
                                if ($keyToCheck == 'maxlength' && empty($arrDefaultCaseFieldInfo[$keyToCheck]) && empty($arrCompanyCaseFieldInfo[$keyToCheck])) {
                                    // null or 0 is set, ignore
                                } else {
                                    $booAtLeastOneKeyDifferent = true;

                                    // $arrCompanyFieldsDifference[$companyId][$arrCompanyCaseFieldInfo['company_field_id']][] = $keyToCheck . '(def: ' . $arrDefaultCaseFieldInfo[$keyToCheck] . ', set: ' . $arrCompanyCaseFieldInfo[$keyToCheck] . ')';
                                }
                            }
                        }

                        if (!$booAtLeastOneKeyDifferent && in_array($arrDefaultCaseFieldInfo['type'], array(3, 6))) {
                            // Check options for combo/radio fields
                            $arrThisFieldOptions    = isset($arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrCompanyCaseFieldInfo['field_id']]) ? $arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrCompanyCaseFieldInfo['field_id']] : array();
                            $arrDefaultFieldOptions = $arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrDefaultCaseFieldInfo['field_id']];
                            $arrTheSame             = array_intersect($arrThisFieldOptions, $arrDefaultFieldOptions);

                            if (count($arrTheSame) == count($arrThisFieldOptions) && count($arrTheSame) == count($arrDefaultFieldOptions)) {
                                // options are the same
                            } else {
                                $arrMissingDefaultOptions = array_diff($arrThisFieldOptions, $arrTheSame);
                                if (!empty($arrMissingDefaultOptions)) {
                                    // there is a difference
                                    $booAtLeastOneKeyDifferent = true;

                                    // $arrCompanyFieldsDifference[$companyId][$arrCompanyCaseFieldInfo['company_field_id']][] = 'options' . PHP_EOL . '(def: ' . implode(', ', array_diff($arrDefaultFieldOptions, $arrTheSame)) . ' ' . PHP_EOL . ' set: ' . implode(', ', $arrMissingDefaultOptions) . ')';
                                }
                            }
                        }

                        if (!$booAtLeastOneKeyDifferent) {
                            $keyToCheck = 'label';
                            if ($arrDefaultCaseFieldInfo[$keyToCheck] != $arrCompanyCaseFieldInfo[$keyToCheck]) {
                                $booLabelIsDifferent = true;

                                // $arrCompanyFieldsDifferenceLabels[$companyId][] = 'label (def: ' . $arrDefaultCaseFieldInfo[$keyToCheck] . ' ' . 'set: ' . $arrCompanyCaseFieldInfo[$keyToCheck] . ')';
                            }
                        }
                        break;
                    }
                }

                if (!$booFoundField || $booAtLeastOneKeyDifferent) {
                    $arrSetToNo[] = $arrCompanyCaseFieldInfo['field_id'];
                } elseif ($booLabelIsDifferent) {
                    $arrSetToLabel[] = $arrCompanyCaseFieldInfo['field_id'];
                }
            }
        }


        if (!empty($arrSetToNo)) {
            $query = sprintf(
                "UPDATE `client_form_fields` SET `sync_with_default`='No' WHERE `field_id` IN (%s);",
                implode(',', $arrSetToNo)
            );

            $this->execute($query);
        }

        if (!empty($arrSetToLabel)) {
            $query = sprintf(
                "UPDATE `client_form_fields` SET `sync_with_default`='Label' WHERE `field_id` IN (%s);",
                implode(',', $arrSetToLabel)
            );

            $this->execute($query);
        }

        // /** @var Log $log */
        // $log = self::getService('log');
        // $log->debugToFile($arrCompanyFieldsDifference);
        // $log->debugToFile(str_repeat('*', 80));
        // $log->debugToFile($arrCompanyFieldsDifferenceLabels);
        // $log->debugToFile(str_repeat('*', 80));
        // $log->debugToFile($arrSetToNo);
        // $log->debugToFile(str_repeat('*', 80));
        // $log->debugToFile($arrSetToLabel);
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `sync_with_default`;");
    }
}