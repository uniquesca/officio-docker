<?php

use Phinx\Migration\AbstractMigration;

class SiInvitationExpiration extends AbstractMigration
{
    public function up()
    {
        // Queue fields cleaning
        $this->execute(
            "
            DELETE aff, afde, afda
            FROM applicant_form_fields aff
              LEFT JOIN applicant_form_default afde ON aff.applicant_field_id = afde.applicant_field_id
              LEFT JOIN applicant_form_data afda ON aff.applicant_field_id = afda.applicant_field_id
            WHERE applicant_field_unique_id = 'entered_queue_on' AND type <> 'office_change_date_time';
        "
        );

        // Creating queue
        $this->execute(
            "
          INSERT INTO `divisions` (`company_id`, `name`, `order`)
            SELECT `company_id`, 'SI Invited', `order`
            FROM `divisions`
            WHERE `name` = 'SI Registrations Intake'

            UNION

            SELECT `company_id`, 'SI Expired ITA', `order`
            FROM `divisions`
            WHERE `name` = 'SI Registrations Intake';
        "
        );

        // Creating case statuses
        $this->execute(
            "
          INSERT INTO `client_form_default` (`field_id`, `value`, `order`)
            SELECT `field_id`, 'SI Expired ITA', 1
            FROM `client_form_fields`
            WHERE `company_field_id` = 'file_status'
        "
        );

        // Creating mail template
        $this->execute(
            "
          INSERT INTO `templates` (`member_id`, `folder_id`, `order`, `templates_for`, `templates_type`, `name`, `subject`, `from`, `cc`, `bcc`, `message`, `create_date`, `default`)
              SELECT `m`.`member_id`, `uf`.`folder_id`, 200, 'General', 'Email', 'SI Expired ITA', 'BC PNP SI ITA Expiry', 'noreply@gov.bc.ca', '', '', '<div>
                <div>
                <div>&nbsp;</div>

                <div>
                <div style=\"text-align:center\">
                <table align=\"center\" border=\"0\" cellpadding=\"10\" cellspacing=\"1\" style=\"border-collapse:collapse; font-family:sans-serif,arial,verdana,trebuchet ms; font-size:13px; line-height:20.7999992370605px; width:650px\">
                  <tbody>
                    <tr>
                      <td>
                      <div style=\"text-align:left\">
                      <div><span style=\"font-size:12px\"><span style=\"font-family:arial,helvetica,sans-serif\">Date:&nbsp;&lt;%today_date%&gt;</span></span></div>

                      <div><span style=\"font-size:12px\"><span style=\"font-family:arial,helvetica,sans-serif\">Dear&nbsp;&lt;%first_name%&gt;&nbsp;&lt;%last_name%&gt;:</span></span></div>

                      <div>
                      <p><span style=\"font-size:12px\"><span style=\"font-family:arial,helvetica,sans-serif\">This email confirms withdrawal of registration <strong>&lt;%file_number%&gt;</strong> to the British&nbsp;Columbia Provincial Nominee Program (BC PNP) for&nbsp;<strong>&lt;%first_name%&gt;&nbsp;&lt;%last_name%&gt;</strong>.</span></span></p>

                      <p><span style=\"font-size:12px\"><span style=\"font-family:arial,helvetica,sans-serif\">You can register again at any time using your existing BC PNP Online profile. </span></span></p>

                      <p><span style=\"font-size:12px\"><span style=\"font-family:arial,helvetica,sans-serif\">Please note that should you submit a new registration, you must meet the minimum program and category requirements. A registration into the Skills Immigration Registration System is not an application to the BC PNP or a guarantee that you will be invited to apply.</span></span></p>
                      </div>

                      <div>&nbsp;</div>

                      <div>
                      <div><span style=\"color:#333333\"><span style=\"font-family:arial,sans-serif\"><span style=\"font-size:9.0pt\"><a href=\"http://www.welcomebc.ca/Immigrate/About-the-BC-PNP.aspx\"><strong><em><span style=\"color:#0782C1\">BC Provincial Nominee Program</span></em></strong></a><br />
                      Economic Immigration Programs Branch</span></span></span></div>
                      <span style=\"color:#333333\"><span style=\"font-family:arial,sans-serif\"><span style=\"font-size:9.0pt\">Ministry of Jobs, Tourism and Skills Training</span></span></span></div>
                      </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
                </div>
                </div>
                </div>
                </div>', '2015-12-16', 'N'
              FROM `members` AS `m`
                INNER JOIN `members_types` AS `mt` ON `m`.`userType` = `mt`.`member_type_id`
                INNER JOIN `company` AS `c` ON `m`.`company_id` = `c`.`company_id`
                LEFT OUTER JOIN `u_folders` AS `uf` ON `uf`.`company_id` = `c`.`company_id` AND `uf`.`folder_name` = 'Shared Templates'
              WHERE `mt`.`member_type_name` = 'admin' AND `m`.`fName` = 'Admin' AND `m`.`lName` = 'Admin' AND `c`.`companyName` = 'BC PNP';"
        );

        // Add Automatic Task
        $this->execute(
            "
            INSERT INTO `automatic_reminders` (`company_id`, `template_id`, `assigned_to`, `assign_to_role_id`, `assign_to_member_id`, `type`, `trigger`, `number`, `days`, `ba`, `prof`, `file_status`, `reminder`, `message`, `active_clients_only`, `notify_client`, `create_date`)
              SELECT `c`.`company_id`, `t`.`template_id`, 3, 0, 0, 'FILESTATUS', 0, 0, 'CALENDAR', 'AFTER', 0, `cfd`.`form_default_id`, 'SI Expired ITA', 'Happens when SI Registration case status changed to \"SI Expired\".', 'Y', 'Y', '2015-12-16'
              FROM `company` AS `c`
                LEFT OUTER JOIN `templates` AS `t` ON `t`.`name` = 'SI Expired ITA'
                INNER JOIN `client_form_fields` AS `cff` ON `cff`.`company_id` = `c`.`company_id` AND `cff`.`company_field_id` = 'file_status'
                INNER JOIN `client_form_default` AS `cfd` ON `cfd`.`field_id` = `cff`.`field_id` AND `cfd`.`value` = 'SI Expired ITA'
              WHERE `c`.`companyName` = 'BC PNP';
        "
        );

        $arSettings    = $this->fetchRow(
            "
            SELECT aff.applicant_field_id, d.division_id
            FROM applicant_form_fields aff
              INNER JOIN company c ON c.company_id = aff.company_id
              INNER JOIN members_types mt ON mt.member_type_id = aff.member_type_id
              LEFT OUTER JOIN divisions d ON d.name = 'SI Expired ITA' AND c.company_id = d.company_id
            WHERE applicant_field_unique_id = 'office' AND c.companyName = 'BC PNP' AND mt.member_type_name = 'individual';
        "
        );
        $arSettingsStr = "{\"member_type\":\"individual\",\"field_id\":\"" . $arSettings['applicant_field_id'] . "\",\"option\":\"" . $arSettings['division_id'] . "\"}";
        $this->execute(
            "
          INSERT INTO `automatic_reminder_actions` (`automatic_reminder_id`, `automatic_reminder_action_type_id`, `automatic_reminder_action_settings`, `automatic_reminder_action_create_date`)
            SELECT ar.automatic_reminder_id, 1, '$arSettingsStr', CURDATE()
            FROM automatic_reminders ar
            WHERE reminder = 'SI Expired ITA';
            (6, 1, '', '2016-03-04');
        "
        );
    }

    public function down()
    {
        // Dropping automatic reminder
        $this->execute(
            "
          DELETE ara, ar
          FROM automatic_reminder_actions ara
           INNER JOIN automatic_reminders ar ON ar.automatic_reminder_id = ara.automatic_reminder_id
          WHERE  ar.reminder = 'SI Expired ITA';"
        );

        // Dropping template
        $this->execute("DELETE FROM `templates` WHERE  `name` = 'SI Expired ITA';");

        // Dropping case statuses
        $this->execute("DELETE FROM `client_form_default` WHERE  `value` IN ('SI Expired ITA');");

        // Dropping queues
        $this->execute("DELETE FROM `divisions` WHERE  `name`='SI Expired ITA';");
    }
}
