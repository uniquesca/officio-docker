<?php

use Phinx\Migration\AbstractMigration;

class ImproveCategories extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO company_default_options (company_id, default_option_type, default_option_name, default_option_abbreviation, default_option_order)
            SELECT cdo.company_id, cdo.default_option_type, CONCAT(cdo.default_option_name, '::application'), 'BCSA', cdo.default_option_order
            FROM company_default_options cdo
            INNER JOIN company c ON cdo.company_id = c.company_id
            WHERE 
              c.companyName = 'BC PNP' AND 
              cdo.default_option_type = 'categories' AND 
              cdo.default_option_abbreviation = 'BCS';
              
            INSERT INTO company_default_options (company_id, default_option_type, default_option_name, default_option_abbreviation, default_option_order)
            SELECT cdo.company_id, cdo.default_option_type, CONCAT(cdo.default_option_name, '::application'), 'BCEA', cdo.default_option_order
            FROM company_default_options cdo
            INNER JOIN company c ON cdo.company_id = c.company_id
            WHERE 
              c.companyName = 'BC PNP' AND 
              cdo.default_option_type = 'categories' AND 
              cdo.default_option_abbreviation = 'BCE';
              
            UPDATE company_default_options
            SET default_option_name = CONCAT(default_option_name, '::registration'), default_option_abbreviation = 'BCSR'
            WHERE 
              default_option_type = 'categories' AND 
              default_option_abbreviation = 'BCS';
              
            UPDATE company_default_options
            SET default_option_name = CONCAT(default_option_name, '::registration'), default_option_abbreviation = 'BCER'
            WHERE 
              default_option_type = 'categories' AND 
              default_option_abbreviation = 'BCE';
        "
        );
    }

    public function down()
    {
        $this->execute(
            "
            DELETE FROM company_default_options WHERE default_option_abbreviation IN ('BCER', 'BCSR');
            
            UPDATE company_default_options
            SET default_option_name = SUBSTR(default_option_name, 1, LENGTH(default_option_name) - 13), default_option_abbreviation = 'BCS'
            WHERE 
              default_option_type = 'categories' AND 
              default_option_abbreviation = 'BCSA';
              
            UPDATE company_default_options
            SET default_option_name = SUBSTR(default_option_name, 1, LENGTH(default_option_name) - 13), default_option_abbreviation = 'BCE'
            WHERE 
              default_option_type = 'categories' AND 
              default_option_abbreviation = 'BCEA';
        "
        );
    }
}