<?php

use Officio\Migration\AbstractMigration;

class AddDependantsPlaceOfBirthColumnAndMore extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `place_of_birth` VARCHAR(255) NULL DEFAULT NULL AFTER `country_of_birth`;");
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `spouse_name` VARCHAR(255) NULL DEFAULT NULL AFTER `lName`;");

        // TODO PHP7 Rewrite this migration using Phinx DB adapter

        $builder = $this->getQueryBuilder();

        $statement = $builder
            ->select(['applicant_field_id'])
            ->from(['applicant_form_fields'])
            ->where(
                [
                    'applicant_field_unique_id' => 'relationship_status'
                ]
            )
            ->execute();

        $arrFieldIds = array_column($statement->fetchAll(), 0);

        foreach ($arrFieldIds as $fieldId) {
            $statement = $builder
                ->select(array('applicant_form_default_id', 'value'))
                ->from(array('d' => 'applicant_form_default'))
                ->where(
                    [
                        'd.applicant_field_id' => (int)$fieldId
                    ]
                )
                ->execute();

            $arrOptions = $statement->fetchAll();

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
                $builder
                    ->update('applicant_form_default')
                    ->set( array('value' => 'Single'))->where(
                        [
                            'applicant_form_default_id', (int)$singleId
                        ]
                    )
                    ->execute();


                // Fix text values
                $builder
                    ->update('applicant_form_data')
                    ->set(array('value' => $singleId))
                    ->where(function ($exp) use ($fieldId) {
                        return $exp
                            ->eq('applicant_field_id', (int)$fieldId)
                            ->in('value', ['Never married', 'single']);
                    })
                    ->execute();

                if (!empty($marriedId)) {
                    $builder
                        ->update('applicant_form_data')
                        ->set(array('value' => $marriedId))
                        ->where(function ($exp) use ($fieldId) {
                            return $exp
                                ->eq('applicant_field_id', (int)$fieldId)
                                ->in('value', ['married']);
                        })
                        ->execute();
                }

                if (!empty($divorcedId)) {
                    $builder
                        ->update('applicant_form_data')
                        ->set(array('value' => $divorcedId))
                        ->where(function ($exp) use ($fieldId) {
                            return $exp
                                ->eq('applicant_field_id', (int)$fieldId)
                                ->in('value', ['divorced']);
                        })
                        ->execute();
                }

                if (!empty($engagedId)) {
                    $builder
                        ->update('applicant_form_data')
                        ->set(array('value' => $engagedId))
                        ->where(function ($exp) use ($fieldId) {
                            return $exp
                                ->eq('applicant_field_id', (int)$fieldId)
                                ->in('value', ['engaged']);
                        })
                        ->execute();
                }

                if (!empty($separatedId)) {
                    $builder
                        ->update('applicant_form_data')
                        ->set(array('value' => $separatedId))
                        ->where(function ($exp) use ($fieldId) {
                            return $exp
                                ->eq('applicant_field_id', (int)$fieldId)
                                ->in('value', ['separated']);
                        })
                        ->execute();
                }

                if (!empty($widowedId)) {
                    $builder
                        ->update('applicant_form_data')
                        ->set(array('value' => $widowedId))
                        ->where(function ($exp) use ($fieldId) {
                            return $exp
                                ->eq('applicant_field_id', (int)$fieldId)
                                ->in('value', ['Widow', 'widowed']);
                        })
                        ->execute();
                }


                // De-facto/Common law >>> Single
                if (!empty($commonLawId)) {
                    $builder
                        ->update('applicant_form_data')
                        ->set(array('value' => $singleId))
                        ->where(
                            [
                                'applicant_field_id' => (int)$fieldId,
                                'value' => $commonLawId
                            ]
                        )
                        ->execute();

                    $builder
                        ->delete('applicant_form_default')
                        ->where(
                            [
                                'applicant_form_default_id', (int)$commonLawId
                            ]
                        )
                        ->execute();
                }
            }
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_dependents` DROP COLUMN `place_of_birth`,  DROP COLUMN `spouse_name`;");
    }
}