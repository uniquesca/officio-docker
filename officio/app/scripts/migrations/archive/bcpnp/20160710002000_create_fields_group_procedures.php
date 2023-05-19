<?php

use Phinx\Migration\AbstractMigration;

class CreateFieldsGroupProcedures extends AbstractMigration
{

    public function up()
    {
        $this->execute(
            "            
            -- Create case group
            CREATE PROCEDURE createCaseGroup(groupName VARCHAR(255), colsCount INT, caseTypeName VARCHAR(255))
            BEGIN
                DECLARE groupOrder INT;
                
                SELECT MAX(`order`) INTO groupOrder
                FROM client_form_groups AS cfg
                  INNER JOIN client_types ct ON ct.client_type_id = cfg.client_type_id
                WHERE ct.client_type_name = caseTypeName;
                
                INSERT INTO `client_form_groups` (`company_id`, `client_type_id`, `title`, `order`, `cols_count`, `regTime`, `assigned`)
                  SELECT `ct`.`company_id`, `ct`.`client_type_id`, groupName, groupOrder + 1, colsCount, UNIX_TIMESTAMP(), 'A'
                  FROM `client_types` AS `ct` WHERE `ct`.`client_type_name` = caseTypeName;
                  
                INSERT INTO `client_form_group_access` (`role_id`, `group_id`, `status`)
                  SELECT `ar`.`role_id`, `cfg`.`group_id`, 'F'
                  FROM `client_form_groups` as `cfg`
                    LEFT OUTER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cfg`.`company_id`
                    INNER JOIN client_types AS ct ON ct.client_type_id = cfg.client_type_id
                  WHERE 
                    (`cfg`.`title` = groupName) AND 
                    (`ar`.`role_name` = 'Admin') AND 
                    (`ct`.`client_type_name` = caseTypeName);
            END;
        "
        );

        $this->execute(
            "
            CREATE PROCEDURE putCaseFieldIntoGroup(newFieldName VARCHAR(255), groupName VARCHAR(255), caseTypeName VARCHAR(255), mapTo VARCHAR(255))
            BEGIN
                DECLARE rowNum INT;
                DECLARE synFieldCount INT;
                
                SELECT MAX(`field_order`) INTO rowNum FROM `client_form_order`;
                INSERT INTO `client_form_order` (`group_id`, `field_id`, `use_full_row`, `field_order`)  
                  SELECT `cfg`.`group_id`, `cff`.`field_id`, 'N', rowNum + 1
                  FROM `client_form_fields` as `cff`
                    LEFT OUTER JOIN `client_form_groups` AS `cfg` ON `cff`.`company_id` = `cfg`.`company_id`
                    LEFT OUTER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                  WHERE 
                    (`cff`.`company_field_id` = newFieldName) AND 
                    (`cfg`.`title` = groupName) AND 
                    (`ct`.`client_type_name` = caseTypeName);
                  
                -- Grant fields access
                INSERT INTO `client_form_field_access` (`role_id`, `field_id`, `client_type_id`, `status`)
                  SELECT `ar`.`role_id`, `cff`.`field_id`, `ct`.`client_type_id`, 'F'
                  FROM `client_form_fields` as `cff`
                    LEFT OUTER JOIN `client_form_groups` AS `cfg` ON `cff`.`company_id` = `cfg`.`company_id`
                    LEFT OUTER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                    LEFT OUTER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cff`.`company_id`
                  WHERE
                    (`cff`.`company_field_id` = newFieldName) AND 
                    (`cfg`.`title` = groupName) AND 
                    (`ct`.`client_type_name` = caseTypeName) AND
                    (`ar`.`role_name` = 'Admin'); 
                    
                IF (mapTo <> '') THEN
                    SELECT COUNT(*) INTO synFieldCount FROM FormSynField WHERE `FieldName` = mapTo;
                    IF (synFieldCount = 0) THEN
                        INSERT INTO FormSynField (`FieldName`) VALUES (mapTo);
                    END IF;
                    
                    INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
                      SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', newFieldName
                      FROM `FormSynField` 
                      WHERE `FieldName` = mapTo;
                END IF;
            END;
        "
        );

        $this->execute(
            "
            -- Create case field
            CREATE PROCEDURE createCaseField(newFieldName VARCHAR(255), fieldType INT, fieldLabel VARCHAR(255), fieldMaxlength INT, fieldRequired VARCHAR(1), fieldDisabled VARCHAR(1), groupName VARCHAR(255), caseTypeName VARCHAR(255), mapTo VARCHAR(255))
            BEGIN               
                INSERT INTO `client_form_fields` (company_id, company_field_id, type, label, maxlength, encrypted, required, disabled, blocked)
                  SELECT `company_id`, newFieldName, fieldType, fieldLabel, fieldMaxlength, 'N', fieldRequired, fieldDisabled, 'N' FROM `company`;
                  
                CALL putCaseFieldIntoGroup (newFieldName, groupName, caseTypeName, mapTo);
            END;
        "
        );

        $this->execute(
            "
            -- Create IA group
            CREATE PROCEDURE createIAGroup(groupName VARCHAR(255), colsCount INT, collapsed VARCHAR(1))
            BEGIN 
                DECLARE groupOrder INT;
                
                SELECT MAX(`order`) INTO groupOrder FROM applicant_form_groups;
                
                INSERT INTO applicant_form_groups (applicant_block_id, company_id, title, cols_count, collapsed, `order`)
                  SELECT afb.applicant_block_id, afb.company_id, groupName, colsCount, collapsed, groupOrder + 1
                  FROM applicant_form_blocks AS afb
                    INNER JOIN members_types AS mt ON afb.member_type_id = mt.member_type_id
                  WHERE mt.member_type_name = 'individual' AND afb.contact_block = 'Y'
                  GROUP BY afb.company_id HAVING MIN(afb.applicant_block_id);
                  
                INSERT INTO members_relations (parent_member_id, child_member_id, applicant_group_id, row)
                  SELECT mr.parent_member_id, mr.child_member_id, afg1.applicant_group_id, 0
                  FROM members_relations AS mr
                    INNER JOIN members AS m ON m.member_id = mr.child_member_id
                    INNER JOIN members_types AS mt ON mt.member_type_id = m.userType
                    INNER JOIN applicant_form_groups AS afg ON afg.applicant_group_id = mr.applicant_group_id
                    LEFT OUTER JOIN applicant_form_groups AS afg1 ON afg1.company_id = m.company_id
                  WHERE mt.member_type_name = 'internal_contact' AND afg.title = 'Applicant Passport Info' AND afg1.title = groupName;

            END;
        "
        );

        $this->execute(
            "
            -- Put IA field into group
            CREATE PROCEDURE putIAFieldIntoGroup(newFieldName VARCHAR(255), groupName VARCHAR(255), mapTo VARCHAR(255))
            BEGIN 
                DECLARE rowCount INT;
                DECLARE synFieldCount INT;
                    
                SELECT MAX(`field_order`) INTO rowCount FROM `applicant_form_order`;
                INSERT INTO `applicant_form_order` (`applicant_group_id`, `applicant_field_id`, `use_full_row`, `field_order`)
                  SELECT `afg`.`applicant_group_id`, `aff`.`applicant_field_id`, 'N', rowCount + 1
                  FROM `applicant_form_fields` as `aff`
                    LEFT OUTER JOIN `applicant_form_groups` AS `afg` ON `aff`.`company_id` = `afg`.`company_id`
                  WHERE 
                    (`aff`.`applicant_field_unique_id` = newFieldName) AND 
                    (`afg`.`title` = groupName);
                  
                INSERT INTO `applicant_form_fields_access` (`role_id`, `applicant_group_id`, `applicant_field_id`, `status`)
                  SELECT `ar`.`role_id`, `afo`.`applicant_group_id`, `aff`.`applicant_field_id`, 'F'
                  FROM `applicant_form_fields` as `aff`
                    INNER JOIN `applicant_form_order` AS `afo` ON `afo`.`applicant_field_id` = `aff`.`applicant_field_id`
                    LEFT OUTER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `aff`.`company_id`
                  WHERE
                    (`aff`.`applicant_field_unique_id` = newFieldName) AND
                    (`ar`.`role_name` = 'Admin');
                    
                IF (mapTo <> '') THEN
                    SELECT COUNT(*) INTO synFieldCount FROM FormSynField WHERE `FieldName` = mapTo;
                    IF (synFieldCount = 0) THEN
                        INSERT INTO FormSynField (`FieldName`) VALUES (mapTo);
                    END IF;
                    
                    INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
                      SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', newFieldName
                      FROM `FormSynField` 
                      WHERE `FieldName` = mapTo;
                END IF;
            END;
        "
        );

        $this->execute(
            "
            -- Create field in IA group
            CREATE PROCEDURE createIAField(newFieldName VARCHAR(255), fieldType VARCHAR(15), fieldLabel VARCHAR(255), fieldRequired VARCHAR(1), fieldDisabled VARCHAR(1), groupName VARCHAR(255), mapTo VARCHAR(255))
            BEGIN
                INSERT INTO applicant_form_fields (member_type_id, company_id, applicant_field_unique_id, type, label, encrypted, required, disabled, blocked)
                  SELECT mt.member_type_id, c.`company_id`, newFieldName, fieldType, fieldLabel, 'N', fieldRequired, fieldDisabled, 'N'
                  FROM `company` AS c
                    LEFT OUTER JOIN  members_types AS mt ON mt.member_type_name = 'individual';
                    
                CALL putIAFieldIntoGroup (newFieldName, groupName, mapTo);
            END;
        "
        );
    }

    public function down()
    {
        $this->execute(
            "
            DROP PROCEDURE createIAField;
            DROP PROCEDURE createIAGroup;
            DROP PROCEDURE putIAFieldIntoGroup;
            DROP PROCEDURE createCaseField;
            DROP PROCEDURE createCaseGroup;
            DROP PROCEDURE putCaseFieldIntoGroup;
        "
        );
    }

}
