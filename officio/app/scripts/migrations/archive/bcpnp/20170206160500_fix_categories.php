<?php

use Phinx\Migration\AbstractMigration;

/*
 * Class FixCategories
 * @var Zend_Db_Adapter_Pdo_Mysql $adapter
 */

class FixCategories extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'business::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Business Immigration Application';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'entry-level::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'entry-level::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'express-intl-grad::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'express-intl-grad::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'express-intl-postgrad::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'express-intl-postgrad::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'express-skilled::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'express-skilled::registration';
      
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'health-care::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'health-care::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'intl-grad::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'intl-grad::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'intl-postgrad::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'intl-postgrad::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'northeast::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'northeast::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'skilled::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'skilled::registration';
            
            UPDATE client_form_data cfd
            INNER JOIN client_form_fields cff ON cfd.field_id = cff.field_id
            INNER JOIN clients c ON cfd.member_id = c.member_id
            INNER JOIN client_types ct ON ct.client_type_id = c.client_type_id
            INNER JOIN company_default_options cdo_exstng ON cdo_exstng.default_option_id = cfd.value
            INNER JOIN company_default_options cdo ON cdo.default_option_name = 'express-health-care::application'
            SET cfd.value = cdo.default_option_id
            WHERE cff.company_field_id = 'visa_subclass' AND ct.client_type_name = 'Skills Immigration Application' AND cdo_exstng.default_option_name = 'express-health-care::registration';
        "
        );
    }

    public function down()
    {
    }
}