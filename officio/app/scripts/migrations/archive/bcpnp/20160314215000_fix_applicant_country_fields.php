<?php

use Phinx\Migration\AbstractMigration;

class FixApplicantCountryFields extends AbstractMigration
{
    public function up()
    {
        $this->execute('INSERT INTO `country_master` (`countries_name`, `countries_iso_code_2`, `countries_iso_code_3`) VALUES (\'Palestine\', \'PS\', \'PSE\');');
        $this->execute(
            "
            UPDATE applicant_form_fields SET type='country'
            WHERE applicant_field_unique_id IN (
              'country_of_birth',
              'country_of_residence',
              'country_of_citizenship',
              'country_of_passport'
            );

            UPDATE applicant_form_data afd, applicant_form_fields aff
            SET afd.value = 'Vatican City State (Holy See)'
            WHERE afd.applicant_field_id = aff.applicant_field_id AND aff.applicant_field_unique_id IN (
                'country_of_birth',
                'country_of_residence',
                'country_of_citizenship',
                'country_of_passport',
                'country',
                'representative_country'
            ) AND (afd.value IN ('Vatican', 'Holy See (Vatican City State)'));

            UPDATE applicant_form_data afd, applicant_form_fields aff
            SET afd.value = 'Palestine'
            WHERE afd.applicant_field_id = aff.applicant_field_id AND aff.applicant_field_unique_id IN (
                'country_of_birth',
                'country_of_residence',
                'country_of_citizenship',
                'country_of_passport',
                'country',
                'representative_country'
            ) AND afd.value = 'Palestina';

            UPDATE applicant_form_data afd, applicant_form_fields aff
            SET afd.value = 'St. Helena'
            WHERE afd.applicant_field_id = aff.applicant_field_id AND aff.applicant_field_unique_id IN (
                'country_of_birth',
                'country_of_residence',
                'country_of_citizenship',
                'country_of_passport',
                'country',
                'representative_country'
            ) AND afd.value = 'Saint Helena, Ascension and Tristan da Cunha';

            UPDATE applicant_form_data afd, applicant_form_fields aff
            SET afd.value = 'St. Pierre and Miquelon'
            WHERE afd.applicant_field_id = aff.applicant_field_id AND aff.applicant_field_unique_id IN (
                'country_of_birth',
                'country_of_residence',
                'country_of_citizenship',
                'country_of_passport',
                'country',
                'representative_country'
            ) AND afd.value = 'Saint Pierre and Miquelon';

            UPDATE applicant_form_data afd, applicant_form_fields aff
            SET afd.value = 'East Timor'
            WHERE afd.applicant_field_id = aff.applicant_field_id AND aff.applicant_field_unique_id IN (
                'country_of_birth',
                'country_of_residence',
                'country_of_citizenship',
                'country_of_passport',
                'country',
                'representative_country'
            ) AND afd.value = 'Timor-Leste';
        "
        );
    }


}
