<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;
use Officio\Service\Log;

class AddGenerateCon extends AbstractMigration
{
    public function up()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id'   => 10,
                    'module_id'        => 'applicants',
                    'rule_description' => 'Generate CON',
                    'rule_check_id'    => 'generate-con',
                    'superadmin_only'  => 0,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 23,
                )
            );

            $ruleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $ruleId,
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'generate-con',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $ruleId,
                    'package_detail_description' => 'Generate CON',
                    'visible'                    => 1,
                )
            );

            $template = '
                <table border="0" cellpadding="3" align="left" width="100%">
                 <tr>
                  <td width="75%" style="font-weight: bold;">COMMONWEALTH OF DOMINICA CITIZENSHIP ACT (Ch.1:10)</td>
                  <td width="25%" align="right" style="font-weight: bold;">SECTION 8</td>
                 </tr>
                </table>
                <h1 align="center">CERTIFICATE OF NATURALISATION</h1>
                <p style="text-indent: 30px;">WHEREAS <i><b>{fName} {lName}</b></i> has applied to the Minister responsible for citizenship in the Government of Dominica for a certificate of naturalisation, alleging with respect to herself the particulars set out below, and has satisfied the Minister that the conditions laid down in the Constitution and in the Citizenship Act Chapter 1:10 for the grant of a certificate of naturalisation are fulfilled:</p>
                <p style="text-indent: 30px;">Now, THEREFORE, the Minister, in exercise of the powers conferred upon him by the said Constitution and Act, grants to the said <i><b>{fName} {lName}</b></i> this Certificate of Naturalisation, and declares that upon taking the oath or affirmation of allegiance in the manner required by the Act shall be a citizen of Dominica as from the date of this certificate.</p>
                <p style="text-indent: 30px;">In witness whereof I have hereto subscribed my name this {today_day} day of {today_month}, {today_year}.</p>
                <p></p>
                <p></p>
                <p></p>
                <table border="0" cellpadding="3" align="left" width="100%">
                 <tr>
                  <td width="65%"></td>
                  <td width="35%" style="font-size: 8px; font-style: italic;">…………………………………………………..<br />MINISTER FOR JUSTICE, IMMIGRATION <br />AND NATIONAL SECURITY</td>
                 </tr>
                </table>
                <p></p>
                <p align="center" style="font-weight: bold; font-size: 10px;">Particulars relating to applicant</p>
                <table border="0" cellpadding="3" align="left" width="85%" style="font-size: 10px;">
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">FULL NAME:</td>
                  <td width="33%"><input type="text" name="name_{page_number}" value="{fName} {lName}" size="25" /></td>
                 </tr>
                 <tr>
                  <td width="37%" height="35px"></td>
                  <td width="30%" height="35px">ADDRESS:</td>
                  <td width="33%" height="35px"><textarea name="address_{page_number}" cols="25">{address}</textarea></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">PROFESSION/<br />OCCUPATION:</td>
                  <td width="33%"><input type="text" name="profession_{page_number}" value="{occupation}" size="25"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">PLACE OF BIRTH:</td>
                  <td width="33%"><input type="text" name="place_of_birth_{page_number}" value="{place_of_birth}" size="25"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">DATE OF BIRTH:</td>
                  <td width="33%"><input type="text" name="date_of_birth_{page_number}" value="{date_of_birth}" size="25"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">SINGLE/MARRIED, etc.:</td>
                  <td width="33%"><input type="text" name="marital_status_{page_number}" value="{marital_status}" size="25"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">NAME OF SPOUSE:</td>
                  <td width="33%"><input type="text" name="name_of_spouse_{page_number}" value="{name_of_spouse}" size="25"/></td>
                 </tr>
                </table>';

            $db->insert(
                'system_templates',
                array(
                    'type'        => 'system',
                    'title'       => 'Certificate of Naturalisation',
                    'subject'     => 'Certificate of Naturalisation',
                    'from'        => '',
                    'to'          => '',
                    'cc'          => '',
                    'bcc'         => '',
                    'template'    => $template,
                    'create_date' => date('Y-m-d')
                )
            );

            $db->commit();

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id = ?', 'generate-con')
            );

            $db->delete(
                'system_templates',
                $db->quoteInto('title = ?', 'Certificate of Naturalisation')
            );

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}