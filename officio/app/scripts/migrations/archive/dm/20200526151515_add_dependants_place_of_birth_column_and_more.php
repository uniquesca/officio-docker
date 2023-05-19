<?php

use Phinx\Migration\AbstractMigration;

class AddDependantsPlaceOfBirthColumnAndMore extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `place_of_birth` VARCHAR(255) NULL DEFAULT NULL AFTER `country_of_birth`;");
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `spouse_name` VARCHAR(255) NULL DEFAULT NULL AFTER `lName`;");

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('dbAdapter');

        $select = $db->select()
            ->from(array('f' => 'applicant_form_fields'), 'applicant_field_id')
            ->where('f.applicant_field_unique_id = ?', 'relationship_status');

        $arrFieldIds = $db->fetchCol($select);

        foreach ($arrFieldIds as $fieldId) {
            $select = $db->select()
                ->from(array('d' => 'applicant_form_default'), array('applicant_form_default_id', 'value'))
                ->where('d.applicant_field_id = ?', $fieldId, 'INT');

            $arrOptions = $db->fetchAll($select);

            $singleId    = 0;
            $marriedId   = 0;
            $engagedId   = 0;
            $widowedId   = 0;
            $separatedId = 0;
            $divorcedId  = 0;
            $commonLawId = 0;
            foreach ($arrOptions as $arrOptionInfo) {
                switch ($arrOptionInfo['value']) {
                    case 'Never Married':
                        $singleId = $arrOptionInfo['applicant_form_default_id'];
                        break;

                    case 'Married':
                        $marriedId = $arrOptionInfo['applicant_form_default_id'];
                        break;

                    case 'Engaged':
                        $engagedId = $arrOptionInfo['applicant_form_default_id'];
                        break;

                    case 'Widowed':
                        $widowedId = $arrOptionInfo['applicant_form_default_id'];
                        break;

                    case 'Separated':
                        $separatedId = $arrOptionInfo['applicant_form_default_id'];
                        break;

                    case 'Divorced':
                        $divorcedId = $arrOptionInfo['applicant_form_default_id'];
                        break;

                    case 'De-Facto/Common Law':
                        $commonLawId = $arrOptionInfo['applicant_form_default_id'];
                        break;

                    default:
                        break;
                }
            }

            if (!empty($singleId)) {
                // Rename to Single
                $db->update(
                    'applicant_form_default',
                    array('value' => 'Single'),
                    $db->quoteInto('applicant_form_default_id = ?', $singleId, 'INT')
                );


                // Fix text values
                $db->update(
                    'applicant_form_data',
                    array('value' => $singleId),
                    $db->quoteInto('applicant_field_id = ?', $fieldId, 'INT') . ' AND ' . $db->quoteInto('value IN (?)', array('Never married', 'single'))
                );

                if (!empty($marriedId)) {
                    $db->update(
                        'applicant_form_data',
                        array('value' => $marriedId),
                        $db->quoteInto('applicant_field_id = ?', $fieldId, 'INT') . ' AND ' . $db->quoteInto('value IN (?)', array('married'))
                    );
                }

                if (!empty($divorcedId)) {
                    $db->update(
                        'applicant_form_data',
                        array('value' => $divorcedId),
                        $db->quoteInto('applicant_field_id = ?', $fieldId, 'INT') . ' AND ' . $db->quoteInto('value IN (?)', array('divorced'))
                    );
                }

                if (!empty($engagedId)) {
                    $db->update(
                        'applicant_form_data',
                        array('value' => $engagedId),
                        $db->quoteInto('applicant_field_id = ?', $fieldId, 'INT') . ' AND ' . $db->quoteInto('value IN (?)', array('engaged'))
                    );
                }

                if (!empty($separatedId)) {
                    $db->update(
                        'applicant_form_data',
                        array('value' => $separatedId),
                        $db->quoteInto('applicant_field_id = ?', $fieldId, 'INT') . ' AND ' . $db->quoteInto('value IN (?)', array('separated'))
                    );
                }

                if (!empty($widowedId)) {
                    $db->update(
                        'applicant_form_data',
                        array('value' => $widowedId),
                        $db->quoteInto('applicant_field_id = ?', $fieldId, 'INT') . ' AND ' . $db->quoteInto('value IN (?)', array('Widow', 'widowed'))
                    );
                }


                // De-facto/Common law >>> Single
                if (!empty($commonLawId)) {
                    $db->update(
                        'applicant_form_data',
                        array('value' => $singleId),
                        $db->quoteInto('applicant_field_id = ?', $fieldId, 'INT') . ' AND ' . $db->quoteInto('value = ?', $commonLawId)
                    );

                    $db->delete(
                        'applicant_form_default',
                        $db->quoteInto('applicant_form_default_id = ?', $commonLawId, 'INT')
                    );
                }
            }
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_dependents` DROP COLUMN `place_of_birth`,  DROP COLUMN `spouse_name`;");
    }
}