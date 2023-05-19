<?php

use Phinx\Migration\AbstractMigration;
use Officio\Service\Log;

class UpdateHtmlConTemplate extends AbstractMigration
{
    public function up()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $template = '<div style="font-size: 17px; border: 1px solid #ccc; padding: 10px 10px 40px 10px; margin: 5px 5px 30px 5px; background-color: white;">
                <div style="float: right; margin: 10px 0;"><input type="text" name="con_number_{page_number}" size="30" value="{con_number}" /></div>
                <table border="0" cellpadding="3" width="100%">
                    <tr>
                        <td width="75%" style="font-weight: bold;">COMMONWEALTH OF DOMINICA CITIZENSHIP ACT (Ch.1:10)</td>
                        <td width="25%" align="right" style="font-weight: bold;">SECTION 8</td>
                    </tr>
                </table>
                <h1 align="center" style="font-size: 22px; margin: 20px">CERTIFICATE OF NATURALISATION</h1>
                <p style="text-indent: 30px; padding-bottom: 15px;">
                    WHEREAS <i><b><input type="text" name="main_applicant_name_{page_number}" size="30" value="{main_applicant_name}"/></b></i> has applied to the Minister responsible for citizenship in the Government of Dominica for a certificate of naturalisation, alleging with respect to <input type="text" name="self_name_{page_number}" size="30" value="{self_name}"/> the particulars set out below, and has satisfied the Minister that
                    the conditions laid down in the Constitution and in the Citizenship Act Chapter 1:10 for the grant of a certificate of naturalisation are fulfilled:
                </p>
                <p style="text-indent: 30px; padding-bottom: 15px;">
                    Now, THEREFORE, the Minister, in exercise of the powers conferred upon him by the said Constitution and Act, grants to the said <i><b><input type="text" name="main_applicant_name_2_{page_number}" size="30" value="{main_applicant_name_2}"/></b></i> this Certificate of Naturalisation, and declares that upon taking the oath or affirmation of allegiance in the manner required by the Act shall be a citizen of Dominica as from the
                    date of this certificate.
                </p>
                <p style="text-indent: 30px; padding-bottom: 75px;">In witness whereof I have hereto subscribed my name this ____ day of _________, ______.</p>
            
                <table border="0" cellpadding="3" width="100%">
                    <tr>
                        <td width="65%"></td>
                        <td width="35%" style="font-style: italic; font-size: smaller;">.....................................................................<br/>MINISTER FOR JUSTICE, IMMIGRATION <br/>AND NATIONAL SECURITY</td>
                    </tr>
                </table>
            
                <p align="center" style="font-weight: bold; padding: 40px 0 10px;">Particulars relating to applicant</p>
            
                <table border="0" cellpadding="2" width="100%">
                    <tr>
                        <td width="37%" rowspan="7" style="text-align: center">{photo}<input type="hidden" name="photo_path_{page_number}" value="{photo_path}"/></td>
                        <td width="30%" height="30px"><label for="main_applicant_name_3_{page_number}">FULL NAME:</label></td>
                        <td width="33%" height="30px"><textarea id="main_applicant_name_3_{page_number}" name="main_applicant_name_3_{page_number}" cols="37" rows="2">{main_applicant_name_3}</textarea></td>
                    </tr>
                    <tr>
                        <td width="30%" height="70px"><label for="address_{page_number}">ADDRESS:</label></td>
                        <td width="33%" height="70px"><textarea id="address_{page_number}" name="address_{page_number}" cols="37" rows="5">{address}</textarea></td>
                    </tr>
                    <tr>
                        <td width="30%" height="30px"><label for="occupation_{page_number}">PROFESSION/<br/>OCCUPATION:</label></td>
                        <td width="33%" height="30px"><textarea id="occupation_{page_number}" name="occupation_{page_number}" cols="37" rows="2">{occupation}</textarea></td>
                    </tr>
                    <tr>
                        <td width="30%"><label for="place_of_birth_{page_number}">PLACE OF BIRTH:</label></td>
                        <td width="33%"><input type="text" id="place_of_birth_{page_number}" name="place_of_birth_{page_number}" value="{place_of_birth}" size="37"/></td>
                    </tr>
                    <tr>
                        <td width="30%"><label for="date_of_birth_{page_number}">DATE OF BIRTH:</label></td>
                        <td width="33%"><input type="text" id="date_of_birth_{page_number}" name="date_of_birth_{page_number}" value="{date_of_birth}" size="37"/></td>
                    </tr>
                    <tr>
                        <td width="30%"><label for="marital_status_{page_number}">SINGLE/MARRIED, etc.:</label></td>
                        <td width="33%"><input type="text" id="marital_status_{page_number}" name="marital_status_{page_number}" value="{marital_status}" size="37"/></td>
                    </tr>
                    <tr>
                        <td width="30%" height="30px"><label for="name_of_spouse_{page_number}">NAME OF SPOUSE:</label></td>
                        <td width="33%" height="30px"><textarea id="name_of_spouse_{page_number}" name="name_of_spouse_{page_number}" cols="37" rows="2">{name_of_spouse}</textarea></td>
                    </tr>
                </table>
            </div>';

            $db->update(
                'system_templates',
                array(
                    'template' => $template
                ),

                $db->quoteInto('title = ?', 'Certificate of Naturalisation (for HTML preview)')
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

            $template = '<div style="font-size: 17px; border: 1px solid #ccc; padding: 10px 10px 40px 10px; margin: 5px 5px 30px 5px; background-color: white;">
                <table border="0" cellpadding="3" width="100%">
                    <tr>
                        <td width="75%" style="font-weight: bold;">COMMONWEALTH OF DOMINICA CITIZENSHIP ACT (Ch.1:10)</td>
                        <td width="25%" align="right" style="font-weight: bold;">SECTION 8</td>
                    </tr>
                </table>
                <h1 align="center" style="font-size: 22px; margin: 20px">CERTIFICATE OF NATURALISATION</h1>
                <p style="text-indent: 30px; padding-bottom: 15px;">
                    WHEREAS <i><b><input type="text" name="main_applicant_name_{page_number}" size="30" value="{main_applicant_name}"/></b></i> has applied to the Minister responsible for citizenship in the Government of Dominica for a certificate of naturalisation, alleging with respect to <input type="text" name="self_name_{page_number}" size="30" value="{self_name}"/> the particulars set out below, and has satisfied the Minister that
                    the conditions laid down in the Constitution and in the Citizenship Act Chapter 1:10 for the grant of a certificate of naturalisation are fulfilled:
                </p>
                <p style="text-indent: 30px; padding-bottom: 15px;">
                    Now, THEREFORE, the Minister, in exercise of the powers conferred upon him by the said Constitution and Act, grants to the said <i><b><input type="text" name="main_applicant_name_2_{page_number}" size="30" value="{main_applicant_name_2}"/></b></i> this Certificate of Naturalisation, and declares that upon taking the oath or affirmation of allegiance in the manner required by the Act shall be a citizen of Dominica as from the
                    date of this certificate.
                </p>
                <p style="text-indent: 30px; padding-bottom: 75px;">In witness whereof I have hereto subscribed my name this ____ day of _________, ______.</p>
            
                <table border="0" cellpadding="3" width="100%">
                    <tr>
                        <td width="65%"></td>
                        <td width="35%" style="font-style: italic; font-size: smaller;">.....................................................................<br/>MINISTER FOR JUSTICE, IMMIGRATION <br/>AND NATIONAL SECURITY</td>
                    </tr>
                </table>
            
                <p align="center" style="font-weight: bold; padding: 40px 0 10px;">Particulars relating to applicant</p>
            
                <table border="0" cellpadding="2" width="100%">
                    <tr>
                        <td width="37%" rowspan="7" style="text-align: center">{photo}<input type="hidden" name="photo_path_{page_number}" value="{photo_path}"/></td>
                        <td width="30%" height="30px"><label for="main_applicant_name_3_{page_number}">FULL NAME:</label></td>
                        <td width="33%" height="30px"><textarea id="main_applicant_name_3_{page_number}" name="main_applicant_name_3_{page_number}" cols="37" rows="2">{main_applicant_name_3}</textarea></td>
                    </tr>
                    <tr>
                        <td width="30%" height="70px"><label for="address_{page_number}">ADDRESS:</label></td>
                        <td width="33%" height="70px"><textarea id="address_{page_number}" name="address_{page_number}" cols="37" rows="5">{address}</textarea></td>
                    </tr>
                    <tr>
                        <td width="30%" height="30px"><label for="occupation_{page_number}">PROFESSION/<br/>OCCUPATION:</label></td>
                        <td width="33%" height="30px"><textarea id="occupation_{page_number}" name="occupation_{page_number}" cols="37" rows="2">{occupation}</textarea></td>
                    </tr>
                    <tr>
                        <td width="30%"><label for="place_of_birth_{page_number}">PLACE OF BIRTH:</label></td>
                        <td width="33%"><input type="text" id="place_of_birth_{page_number}" name="place_of_birth_{page_number}" value="{place_of_birth}" size="37"/></td>
                    </tr>
                    <tr>
                        <td width="30%"><label for="date_of_birth_{page_number}">DATE OF BIRTH:</label></td>
                        <td width="33%"><input type="text" id="date_of_birth_{page_number}" name="date_of_birth_{page_number}" value="{date_of_birth}" size="37"/></td>
                    </tr>
                    <tr>
                        <td width="30%"><label for="marital_status_{page_number}">SINGLE/MARRIED, etc.:</label></td>
                        <td width="33%"><input type="text" id="marital_status_{page_number}" name="marital_status_{page_number}" value="{marital_status}" size="37"/></td>
                    </tr>
                    <tr>
                        <td width="30%" height="30px"><label for="name_of_spouse_{page_number}">NAME OF SPOUSE:</label></td>
                        <td width="33%" height="30px"><textarea id="name_of_spouse_{page_number}" name="name_of_spouse_{page_number}" cols="37" rows="2">{name_of_spouse}</textarea></td>
                    </tr>
                </table>
            </div>';

            $db->update(
                'system_templates',
                array(
                    'template' => $template
                ),

                $db->quoteInto('title = ?', 'Certificate of Naturalisation (for HTML preview)')
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