<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddUrlChecker extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `snapshots` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `url` CHAR(255) NULL DEFAULT NULL,
            `assigned_form_id` INT(10) UNSIGNED NULL DEFAULT NULL,
            `url_description` TEXT NULL,
            `created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` TIMESTAMP NULL DEFAULT NULL,
            `hash` CHAR(255) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `FK_snapshots_FormUpload` (`assigned_form_id`),
            CONSTRAINT `FK_snapshots_FormUpload` FOREIGN KEY (`assigned_form_id`) REFERENCES `FormUpload` (`FormId`) ON UPDATE CASCADE ON DELETE SET NULL
        )
        COMMENT='Contains URLs for PDF Version Checker in SuperAdmin'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;"
        );

        $this->execute(
            "INSERT INTO `snapshots` (`url`) VALUES
            ('https://immi.homeaffairs.gov.au/form-listing/forms/956.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/956a.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/MR5AppointmentOfRepAppointmentARform.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/MR1s312BNotice.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1139a.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1217.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47bt.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47bu.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/922.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/118.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/119.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1272.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/128.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1290.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1300t.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/132.pdf'),
            ('https://immi.homeaffairs.gov.au/entering-and-leaving-australia/business-travel-card'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1391.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1399.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1000.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1283.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1284.pdf'),
            ('https://www.visumdienst.com/download/australie%2Btemp%2Bwork%2Bshort%2Bstay%2B1400.pdf'),
            ('https://australia.basketball/wp-content/uploads/2014/11/1401n-March-2014.pdf'),
            ('http://aecg.tw/Images/zxfwqzyqygqzdyz200806P020150714600243519480.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1002.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1040.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1149.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1229.pdf'),
            ('http://www.migratedownunder.com/PDF/Form%201277%20-%20Application%20for%20Sponsorship%20under%20GSM.pdf'),
            ('https://australia.basketball/wp-content/uploads/2014/02/PDF/1378_Nomination__Form_Updated_July_2011.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1410.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/147.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/40ch.pdf'),
            ('http://www.fenfeivisa.net/Upload/40SP.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47a.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47ch.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47of.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47pa.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47pt.pdf'),
            ('http://www.moving-to-melbourne.co.uk/Documents/47sp.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/54.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/888.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1451ben.pdf'),
            ('http://www.moving-to-melbourne.co.uk/Documents/47sp.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/866.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1085.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1465.pdf'),
            ('http://www.ccschemicals.com.au/dreamcms/app/webroot/uploads/documents/164.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/47SV.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/681.pdf'),
            ('https://www.racs.org.au/wp-content/uploads/2015/12/RACS-FACT-SHEET-TPV-and-SHEV-22.12.2015.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/842.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/852.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/866.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/866.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/866.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/866.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/931.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/681.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1005.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1006.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1007.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1022.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1023.pdf'),
            ('https://archive.homeaffairs.gov.au/forms/documents/1153.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1193.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1195.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1221.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1257.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1281.pdf'),
            ('https://www.dss.gov.au/sites/default/files/documents/11_2016/referral_for_complex_case_support_0.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1359.pdf'),
            ('https://fiji.embassy.gov.au/files/suva/New%20Form%201380.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1392.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1424.pdf'),
            ('https://archive.homeaffairs.gov.au/forms/documents/1428.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1429.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1436.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/M1.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/M11RequestForFeeReduction.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/M16RequestAccessWrittenMaterial.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/M2.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/MR10WithdrawalApplication.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/MR6ChangeOfContactform.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/MR14ConsentToReleasePersonalInformation.pdf'),
            ('https://www.aat.gov.au/AAT/media/AAT/Files/MRD%20documents/Forms/R1.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1249.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1404.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/927.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/949.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1194.pdf'),
            ('https://www.travisa.com/pdffiles/au1208.pdf'),
            ('https://visapath.de/wp-content/uploads/2018/04/Form-1263-employment-verification.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1273.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1276.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1364.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1383.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1409.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1418.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/1419.pdf'),
            ('https://ferratagroup.com/doc/AU/157%20application_for_a_student_visa_150622.pdf'),
            ('https://www.kahlawyers.com/assets/templates/kahlawyers/files/157G%20(04-2012).pdf'),
            ('http://pds.magichome.co.kr/board/msky/157n.pdf'),
            ('http://www.academies.edu.au/pdf/Permission%20to%20work%20form.pdf'),
            ('https://www.kahlawyers.com/assets/templates/kahlawyers/files/48ME%20(11-2011).pdf'),
            ('https://newzealand.embassy.gov.au/files/wltn/Subclass%20771%20-%20Transit%20Visa%20April%202018.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/918.pdf'),
            ('https://immi.homeaffairs.gov.au/form-listing/forms/919.pdf');"
        );

        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->insert(
            'acl_rules',
            array(
                'rule_parent_id'   => 4,
                'module_id'        => 'superadmin',
                'rule_description' => 'PDF Version Checker',
                'rule_check_id'    => 'url-checker',
                'superadmin_only'  => 1,
                'rule_visible'     => 1,
                'rule_order'       => 1,
            )
        );

        $id = $db->lastInsertId('acl_rules');

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES
            ($id, 'superadmin', 'url-checker', '');"
        );

        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
            (1, $id, 'PDF Version Checker', 1);"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `snapshots`;");

        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_check_id` = 'url-checker';");
    }
}
