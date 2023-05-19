<?php

use Phinx\Migration\AbstractMigration;

class Pnp643NewEiSyncFields extends AbstractMigration
{
    public function up()
    {
        // Add three new fields
        $this->execute(
            "           
            CALL `createCaseField` ('eiRegEnglishLevel', 1, 'English Language Level', 0, 'N', 'N', 'Assessment Factors', 'Entrepreneur Immigration Registration', 'syncA_Reg_EnglishLevel');
        "
        );
        $this->execute(
            "           
            CALL `createCaseField` ('eiRegEducation', 1, 'Education Level', 0, 'N', 'N', 'Assessment Factors', 'Entrepreneur Immigration Registration', 'syncA_Reg_Education');
        "
        );
        $this->execute(
            "           
            CALL `createCaseField` ('eiRegAge', 1, 'Age', 0, 'N', 'N', 'Assessment Factors', 'Entrepreneur Immigration Registration', 'syncA_Reg_RegistrationAge');
        "
        );
        $this->execute(
            "           
            CALL `createCaseField` ('eiCanWorkExp', 1, 'Canadian Work Experience', 0, 'N', 'N', 'Assessment Factors', 'Entrepreneur Immigration Registration', 'syncA_Reg_CANWorkExp');
        "
        );
        $this->execute(
            "           
            CALL `createCaseField` ('eiCanBusExp', 1, 'Canadian Business Experience', 0, 'N', 'N', 'Assessment Factors', 'Entrepreneur Immigration Registration', 'syncA_Reg_CANBusExp');
        "
        );
        $this->execute(
            "           
            CALL `createCaseField` ('eiCanStudy', 1, 'Studied in Canada', 0, 'N', 'N', 'Assessment Factors', 'Entrepreneur Immigration Registration', 'syncA_Reg_CANStudies');
        "
        );
    }

    public function down()
    {
    }
}