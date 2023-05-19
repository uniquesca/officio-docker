<?php

use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class MakeConTemplateGreatAgain extends AbstractMigration
{
    public function up()
    {
        try {

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $template = '<table border="0" cellpadding="3" align="left" width="100%">
                 <tr>
                  <td width="75%" style="font-weight: bold;">COMMONWEALTH OF DOMINICA CITIZENSHIP ACT (Ch.1:10)</td>
                  <td width="25%" align="right" style="font-weight: bold;">SECTION 8</td>
                 </tr>
                </table>
                <h1 align="center">CERTIFICATE OF NATURALISATION</h1>
                <p style="text-indent: 30px;">WHEREAS <i><b>{fName} {lName}</b></i> has applied to the Minister responsible for citizenship in the Government of Dominica for a certificate of naturalisation, alleging with respect to <input type="text" name="self_{page_number}" size="7" /> the particulars set out below, and has satisfied the Minister that the conditions laid down in the Constitution and in the Citizenship Act Chapter 1:10 for the grant of a certificate of naturalisation are fulfilled:</p>
                <p style="text-indent: 30px;">Now, THEREFORE, the Minister, in exercise of the powers conferred upon him by the said Constitution and Act, grants to the said <i><b>{fName} {lName}</b></i> this Certificate of Naturalisation, and declares that upon taking the oath or affirmation of allegiance in the manner required by the Act shall be a citizen of Dominica as from the date of this certificate.</p>
                <p style="text-indent: 30px;">In witness whereof I have hereto subscribed my name this ____ day of _________, ______.</p>
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
                <table border="0" cellpadding="2" align="left" width="83%" style="font-size: 10px;">
                 <tr>
                  <td width="37%" height="30px"></td>
                  <td width="30%" height="30px">FULL NAME:</td>
                  <td width="33%" height="30px"><textarea name="name_{page_number}" cols="37" rows="2">{fName} {lName}</textarea></td>
                 </tr>
                 <tr>
                  <td width="37%" height="70px"></td>
                  <td width="30%" height="70px">ADDRESS:</td>
                  <td width="33%" height="70px"><textarea name="address_{page_number}" cols="37" rows="5">{address}</textarea></td>
                 </tr>
                 <tr>
                  <td width="37%" height="30px"></td>
                  <td width="30%" height="30px">PROFESSION/<br />OCCUPATION:</td>
                  <td width="33%" height="30px"><textarea name="profession_{page_number}" cols="37" rows="2">{occupation}</textarea></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">PLACE OF BIRTH:</td>
                  <td width="33%"><input type="text" name="place_of_birth_{page_number}" value="{place_of_birth}" size="37"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">DATE OF BIRTH:</td>
                  <td width="33%"><input type="text" name="date_of_birth_{page_number}" value="{date_of_birth}" size="37"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">SINGLE/MARRIED, etc.:</td>
                  <td width="33%"><input type="text" name="marital_status_{page_number}" value="{marital_status}" size="37"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">NAME OF SPOUSE:</td>
                  <td width="33%"><input type="text" name="name_of_spouse_{page_number}" value="{name_of_spouse}" size="37"/></td>
                 </tr>
                </table>';

            $db->update(
                'system_templates',
                array('template' => $template),
                $db->quoteInto('title = ?', 'Certificate of Naturalisation')
            );

            $db->commit();
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

            $db->beginTransaction();

            $template = '
                <table border="0" cellpadding="3" align="left" width="100%">
                 <tr>
                  <td width="75%" style="font-weight: bold;">COMMONWEALTH OF DOMINICA CITIZENSHIP ACT (Ch.1:10)</td>
                  <td width="25%" align="right" style="font-weight: bold;">SECTION 8</td>
                 </tr>
                </table>
                <h1 align="center">CERTIFICATE OF NATURALISATION</h1>
                <p style="text-indent: 30px;">WHEREAS <i><b>{fName} {lName}</b></i> has applied to the Minister responsible for citizenship in the Government of Dominica for a certificate of naturalisation, alleging with respect to <input type="text" name="self_{page_number}" size="7" /> the particulars set out below, and has satisfied the Minister that the conditions laid down in the Constitution and in the Citizenship Act Chapter 1:10 for the grant of a certificate of naturalisation are fulfilled:</p>
                <p style="text-indent: 30px;">Now, THEREFORE, the Minister, in exercise of the powers conferred upon him by the said Constitution and Act, grants to the said <i><b>{fName} {lName}</b></i> this Certificate of Naturalisation, and declares that upon taking the oath or affirmation of allegiance in the manner required by the Act shall be a citizen of Dominica as from the date of this certificate.</p>
                <p style="text-indent: 30px;">In witness whereof I have hereto subscribed my name this ____ day of _________, ______.</p>
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
                <table border="0" cellpadding="2" align="left" width="83%" style="font-size: 10px;">
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">FULL NAME:</td>
                  <td width="33%"><input type="text" name="name_{page_number}" value="{fName} {lName}" size="37" /></td>
                 </tr>
                 <tr>
                  <td width="37%" height="70px"></td>
                  <td width="30%" height="70px">ADDRESS:</td>
                  <td width="33%" height="70px"><textarea name="address_{page_number}" cols="37" rows="5">{address}</textarea></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">PROFESSION/<br />OCCUPATION:</td>
                  <td width="33%"><input type="text" name="profession_{page_number}" value="{occupation}" size="37"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">PLACE OF BIRTH:</td>
                  <td width="33%"><input type="text" name="place_of_birth_{page_number}" value="{place_of_birth}" size="37"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">DATE OF BIRTH:</td>
                  <td width="33%"><input type="text" name="date_of_birth_{page_number}" value="{date_of_birth}" size="37"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">SINGLE/MARRIED, etc.:</td>
                  <td width="33%"><input type="text" name="marital_status_{page_number}" value="{marital_status}" size="37"/></td>
                 </tr>
                 <tr>
                  <td width="37%"></td>
                  <td width="30%">NAME OF SPOUSE:</td>
                  <td width="33%"><input type="text" name="name_of_spouse_{page_number}" value="{name_of_spouse}" size="37"/></td>
                 </tr>
                </table>';

            $db->update(
                'system_templates',
                array('template' => $template),
                $db->quoteInto('title = ?', 'Certificate of Naturalisation')
            );

            $db->commit();

        } catch (\Exception $e) {
           /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }
}