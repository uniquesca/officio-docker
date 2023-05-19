<?php

use Phinx\Migration\AbstractMigration;

class FixSiCategories extends AbstractMigration
{

    public function up()
    {
        $this->execute(
            "
            CREATE TABLE tmp (
              `code` VARCHAR(255) NOT NULL,
              `text` VARCHAR(255) NOT NULL
            );

            INSERT INTO tmp (`text`, `code`) VALUES
              ('Express Entry BC – Skilled Worker', 'express-skilled'),
              ('Express Entry BC – International Graduate', 'express-intl-grad'),
              ('Express Entry BC – International Post-Graduate', 'express-intl-postgrad'),
              ('Skills Immigration – Skilled Worker', 'skilled'),
              ('Skills Immigration – Health Care Professional', 'health-care'),
              ('Skills Immigration – International Graduate', 'intl-grad'),
              ('Skills Immigration – International Post-Graduate', 'intl-postgrad'),
              ('Skills Immigration – Entry-Level and Semi-Skilled Worker (including Northeast)', 'entry-level'),
              ('Express Entry BC – Health Care Professional', 'express-health-care');
            
            DELETE cfd
            FROM client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            WHERE cff.company_field_id = 'siCategoryName';
            
            INSERT INTO client_form_data
                SELECT cfd.member_id, cff1.field_id, t.text
                FROM client_form_data cfd
                  INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
                  INNER JOIN company_default_options cdo ON cdo.default_option_id = cfd.value
                  INNER JOIN client_form_fields cff1 ON cff1.company_field_id = 'siCategoryName' AND cff.company_id = cff1.company_id
                  INNER JOIN tmp t ON t.code = cdo.default_option_name
                WHERE cff.company_field_id = 'visa_subclass';
            
            DROP TABLE tmp;
        "
        );
    }

    public function down()
    {
    }

}
