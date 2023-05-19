<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddVevoCountries extends AbstractMigration
{
    public function up()
    {

        $this->execute("ALTER TABLE `country_master`
	        ADD COLUMN `type` ENUM('general','vevo') NULL DEFAULT 'general' AFTER `immi_code_num`;");

        $this->execute("INSERT INTO `country_master` (`countries_id`, `countries_name`, `countries_iso_code_2`, `countries_iso_code_3`, `immi_code_3`, `immi_code_4`, `immi_code_num`, `type`) VALUES 
            (500, 'Afghanistan', '', '', 'AFG', '', '', 'vevo'),
            (501, 'Albania', '', '', 'ALB', '', '', 'vevo'),
            (502, 'Algeria', '', '', 'DZA', '', '', 'vevo'),
            (503, 'American Samoa', '', '', 'ASM', '', '', 'vevo'),
            (504, 'Andorra', '', '', 'AND', '', '', 'vevo'),
            (505, 'Angola', '', '', 'AGO', '', '', 'vevo'),
            (506, 'Anguilla', '', '', 'AIA', '', '', 'vevo'),
            (507, 'Antarctica', '', '', 'ATA', '', '', 'vevo'),
            (508, 'Antigua and Barbuda', '', '', 'ATG', '', '', 'vevo'),
            (509, 'Argentina', '', '', 'ARG', '', '', 'vevo'),
            (510, 'Armenia', '', '', 'ARM', '', '', 'vevo'),
            (511, 'Aruba', '', '', 'ABW', '', '', 'vevo'),
            (512, 'Australia', '', '', 'AUS', '', '', 'vevo'),
            (513, 'Austria', '', '', 'AUT', '', '', 'vevo'),
            (514, 'Azerbaijan', '', '', 'AZE', '', '', 'vevo'),
            (515, 'Bahamas', '', '', 'BHS', '', '', 'vevo'),
            (516, 'Bahrain', '', '', 'BHR', '', '', 'vevo'),
            (517, 'Bangladesh', '', '', 'BGD', '', '', 'vevo'),
            (518, 'Barbados', '', '', 'BRB', '', '', 'vevo'),
            (519, 'Belarus', '', '', 'BLR', '', '', 'vevo'),
            (520, 'Belgium', '', '', 'BEL', '', '', 'vevo'),
            (521, 'Belize', '', '', 'BLZ', '', '', 'vevo'),
            (522, 'Benin', '', '', 'BEN', '', '', 'vevo'),
            (523, 'Bermuda', '', '', 'BMU', '', '', 'vevo'),
            (524, 'Bhutan', '', '', 'BTN', '', '', 'vevo'),
            (525, 'Bolivia', '', '', 'BOL', '', '', 'vevo'),
            (526, 'Bonaire, Saint Eustatius and Saba', '', '', 'BES', '', '', 'vevo'),
            (527, 'Bosnia and Herzegovina', '', '', 'BIH', '', '', 'vevo'),
            (528, 'Botswana', '', '', 'BWA', '', '', 'vevo'),
            (529, 'Bouvet Island', '', '', 'BVT', '', '', 'vevo'),
            (530, 'Brazil', '', '', 'BRA', '', '', 'vevo'),
            (531, 'British Indian Ocean Territory', '', '', 'IOT', '', '', 'vevo'),
            (532, 'Brunei Darussalam', '', '', 'BRN', '', '', 'vevo'),
            (533, 'Bulgaria', '', '', 'BGR', '', '', 'vevo'),
            (534, 'Burkina Faso', '', '', 'BFA', '', '', 'vevo'),
            (535, 'Burma (Myanmar)', '', '', 'MMR', '', '', 'vevo'),
            (536, 'Burundi', '', '', 'BDI', '', '', 'vevo'),
            (537, 'Cabo Verde', '', '', 'CPV', '', '', 'vevo'),
            (538, 'Cambodia', '', '', 'KHM', '', '', 'vevo'),
            (539, 'Cameroon', '', '', 'CMR', '', '', 'vevo'),
            (540, 'Canada', '', '', 'CAN', '', '', 'vevo'),
            (541, 'Cayman Islands', '', '', 'CYM', '', '', 'vevo'),
            (542, 'Central African Republic', '', '', 'CAF', '', '', 'vevo'),
            (543, 'Chad', '', '', 'TCD', '', '', 'vevo'),
            (544, 'Chile', '', '', 'CHL', '', '', 'vevo'),
            (545, 'China', '', '', 'CHN', '', '', 'vevo'),
            (546, 'Cocos (Keeling) Islands', '', '', 'CCK', '', '', 'vevo'),
            (547, 'Colombia', '', '', 'COL', '', '', 'vevo'),
            (548, 'Comoros', '', '', 'COM', '', '', 'vevo'),
            (549, 'Congo', '', '', 'COG', '', '', 'vevo'),
            (550, 'Congo, Democratic Republic of the', '', '', 'COD', '', '', 'vevo'),
            (551, 'Cook Islands', '', '', 'COK', '', '', 'vevo'),
            (552, 'Costa Rica', '', '', 'CRI', '', '', 'vevo'),
            (553, 'Cote D\'ivoire', '', '', 'CIV', '', '', 'vevo'),
            (554, 'Croatia', '', '', 'HRV', '', '', 'vevo'),
            (555, 'Cuba', '', '', 'CUB', '', '', 'vevo'),
            (556, 'Curacao', '', '', 'CUW', '', '', 'vevo'),
            (557, 'Cyprus', '', '', 'CYP', '', '', 'vevo'),
            (558, 'Czech Republic', '', '', 'CZE', '', '', 'vevo'),
            (559, 'Czechoslovakia', '', '', 'CSK', '', '', 'vevo'),
            (560, 'Denmark', '', '', 'DNK', '', '', 'vevo'),
            (561, 'Djibouti', '', '', 'DJI', '', '', 'vevo'),
            (562, 'Dominica', '', '', 'DMA', '', '', 'vevo'),
            (563, 'Dominican Republic', '', '', 'DOM', '', '', 'vevo'),
            (564, 'East Timor', '', '', 'TMP', '', '', 'vevo'),
            (565, 'Ecuador', '', '', 'ECU', '', '', 'vevo'),
            (566, 'Egypt', '', '', 'EGY', '', '', 'vevo'),
            (567, 'El Salvador', '', '', 'SLV', '', '', 'vevo'),
            (568, 'Equatorial Guinea', '', '', 'GNQ', '', '', 'vevo'),
            (569, 'Eritrea', '', '', 'ERI', '', '', 'vevo'),
            (570, 'Estonia', '', '', 'EST', '', '', 'vevo'),
            (571, 'Ethiopia', '', '', 'ETH', '', '', 'vevo'),
            (572, 'Falkland Islands (Malvinas)', '', '', 'FLK', '', '', 'vevo'),
            (573, 'Faroe Islands', '', '', 'FRO', '', '', 'vevo'),
            (574, 'Fiji', '', '', 'FJI', '', '', 'vevo'),
            (575, 'Finland', '', '', 'FIN', '', '', 'vevo'),
            (576, 'France', '', '', 'FRA', '', '', 'vevo'),
            (577, 'France, Metropolitan', '', '', 'FXX', '', '', 'vevo'),
            (578, 'French Guiana', '', '', 'GUF', '', '', 'vevo'),
            (579, 'French Polynesia', '', '', 'PYF', '', '', 'vevo'),
            (580, 'French Southern Terr', '', '', 'ATF', '', '', 'vevo'),
            (581, 'Gabon', '', '', 'GAB', '', '', 'vevo'),
            (582, 'Gambia', '', '', 'GMB', '', '', 'vevo'),
            (583, 'Georgia', '', '', 'GEO', '', '', 'vevo'),
            (584, 'Georgia/Sandwich Isl', '', '', 'SGS', '', '', 'vevo'),
            (585, 'Germany', '', '', 'D', '', '', 'vevo'),
            (586, 'Ghana', '', '', 'GHA', '', '', 'vevo'),
            (587, 'Gibraltar', '', '', 'GIB', '', '', 'vevo'),
            (588, 'Greece', '', '', 'GRC', '', '', 'vevo'),
            (589, 'Greenland', '', '', 'GRL', '', '', 'vevo'),
            (590, 'Grenada', '', '', 'GRD', '', '', 'vevo'),
            (591, 'Guadeloupe', '', '', 'GLP', '', '', 'vevo'),
            (592, 'Guam', '', '', 'GUM', '', '', 'vevo'),
            (593, 'Guatemala', '', '', 'GTM', '', '', 'vevo'),
            (594, 'Guinea', '', '', 'GIN', '', '', 'vevo'),
            (595, 'Guinea-Bissau', '', '', 'GNB', '', '', 'vevo'),
            (596, 'Guyana', '', '', 'GUY', '', '', 'vevo'),
            (597, 'Haiti', '', '', 'HTI', '', '', 'vevo'),
            (598, 'Heard & Mcdonald Isl', '', '', 'HMD', '', '', 'vevo'),
            (599, 'Honduras', '', '', 'HND', '', '', 'vevo'),
            (600, 'Hong Kong Sar', '', '', 'HKG', '', '', 'vevo'),
            (601, 'Hungary', '', '', 'HUN', '', '', 'vevo'),
            (602, 'Iceland', '', '', 'ISL', '', '', 'vevo'),
            (603, 'India', '', '', 'IND', '', '', 'vevo'),
            (604, 'Indonesia', '', '', 'IDN', '', '', 'vevo'),
            (605, 'Iran, Islamic Republic of', '', '', 'IRN', '', '', 'vevo'),
            (606, 'Iraq', '', '', 'IRQ', '', '', 'vevo'),
            (607, 'Ireland', '', '', 'IRL', '', '', 'vevo'),
            (608, 'Israel', '', '', 'ISR', '', '', 'vevo'),
            (609, 'Italy', '', '', 'ITA', '', '', 'vevo'),
            (610, 'Jamaica', '', '', 'JAM', '', '', 'vevo'),
            (611, 'Japan', '', '', 'JPN', '', '', 'vevo'),
            (612, 'Jordan', '', '', 'JOR', '', '', 'vevo'),
            (613, 'Kazakhstan', '', '', 'KAZ', '', '', 'vevo'),
            (614, 'Kenya', '', '', 'KEN', '', '', 'vevo'),
            (615, 'Kiribati', '', '', 'KIR', '', '', 'vevo'),
            (616, 'Korea, Democratic Peoples Republic of (North)', '', '', 'PRK', '', '', 'vevo'),
            (617, 'Korea, Republic of (South)', '', '', 'KOR', '', '', 'vevo'),
            (618, 'Kosovo', '', '', 'RKS', '', '', 'vevo'),
            (619, 'Kuwait', '', '', 'KWT', '', '', 'vevo'),
            (620, 'Kyrgyzstan', '', '', 'KGZ', '', '', 'vevo'),
            (621, 'Lao People\'s Democratic Republic', '', '', 'LAO', '', '', 'vevo'),
            (622, 'Latvia', '', '', 'LVA', '', '', 'vevo'),
            (623, 'Lebanon', '', '', 'LBN', '', '', 'vevo'),
            (624, 'Lesotho', '', '', 'LSO', '', '', 'vevo'),
            (625, 'Liberia', '', '', 'LBR', '', '', 'vevo'),
            (626, 'Libya', '', '', 'LBY', '', '', 'vevo'),
            (627, 'Liechtenstein', '', '', 'LIE', '', '', 'vevo'),
            (628, 'Lithuania', '', '', 'LTU', '', '', 'vevo'),
            (629, 'Luxembourg', '', '', 'LUX', '', '', 'vevo'),
            (630, 'Macau Sar', '', '', 'MAC', '', '', 'vevo'),
            (631, 'Macedonia, Former Yugoslav Republic of', '', '', 'MKD', '', '', 'vevo'),
            (632, 'Madagascar', '', '', 'MDG', '', '', 'vevo'),
            (633, 'Malawi', '', '', 'MWI', '', '', 'vevo'),
            (634, 'Malaysia', '', '', 'MYS', '', '', 'vevo'),
            (635, 'Maldives', '', '', 'MDV', '', '', 'vevo'),
            (636, 'Mali', '', '', 'MLI', '', '', 'vevo'),
            (637, 'Malta', '', '', 'MLT', '', '', 'vevo'),
            (638, 'Marshall Islands', '', '', 'MHL', '', '', 'vevo'),
            (639, 'Martinique', '', '', 'MTQ', '', '', 'vevo'),
            (640, 'Mauritania', '', '', 'MRT', '', '', 'vevo'),
            (641, 'Mauritius', '', '', 'MUS', '', '', 'vevo'),
            (642, 'Mayotte', '', '', 'MYT', '', '', 'vevo'),
            (643, 'Mexico', '', '', 'MEX', '', '', 'vevo'),
            (644, 'Micronesia, Federated States of', '', '', 'FSM', '', '', 'vevo'),
            (645, 'Moldova, Republic of', '', '', 'MDA', '', '', 'vevo'),
            (646, 'Monaco', '', '', 'MCO', '', '', 'vevo'),
            (647, 'Mongolia', '', '', 'MNG', '', '', 'vevo'),
            (648, 'Montenegro', '', '', 'MNE', '', '', 'vevo'),
            (649, 'Montserrat', '', '', 'MSR', '', '', 'vevo'),
            (650, 'Morocco', '', '', 'MAR', '', '', 'vevo'),
            (651, 'Mozambique', '', '', 'MOZ', '', '', 'vevo'),
            (652, 'Namibia', '', '', 'NAM', '', '', 'vevo'),
            (653, 'Nauru', '', '', 'NRU', '', '', 'vevo'),
            (654, 'Nepal', '', '', 'NPL', '', '', 'vevo'),
            (655, 'Netherlands', '', '', 'NLD', '', '', 'vevo'),
            (656, 'Netherlands Antilles', '', '', 'ANT', '', '', 'vevo'),
            (657, 'Neutral Zone', '', '', 'NTZ', '', '', 'vevo'),
            (658, 'New Caledonia', '', '', 'NCL', '', '', 'vevo'),
            (659, 'New Zealand', '', '', 'NZL', '', '', 'vevo'),
            (660, 'Nicaragua', '', '', 'NIC', '', '', 'vevo'),
            (661, 'Niger', '', '', 'NER', '', '', 'vevo'),
            (662, 'Nigeria', '', '', 'NGA', '', '', 'vevo'),
            (663, 'Niue', '', '', 'NIU', '', '', 'vevo'),
            (664, 'Northern Mariana Isl', '', '', 'MNP', '', '', 'vevo'),
            (665, 'Norway', '', '', 'NOR', '', '', 'vevo'),
            (666, 'Oman', '', '', 'OMN', '', '', 'vevo'),
            (667, 'Pakistan', '', '', 'PAK', '', '', 'vevo'),
            (668, 'Palau', '', '', 'PLW', '', '', 'vevo'),
            (669, 'Palestinian Authority', '', '', 'PSE', '', '', 'vevo'),
            (670, 'Panama', '', '', 'PAN', '', '', 'vevo'),
            (671, 'Papua New Guinea', '', '', 'PNG', '', '', 'vevo'),
            (672, 'Paraguay', '', '', 'PRY', '', '', 'vevo'),
            (673, 'Peru', '', '', 'PER', '', '', 'vevo'),
            (674, 'Philippines', '', '', 'PHL', '', '', 'vevo'),
            (675, 'Pitcairn', '', '', 'PCN', '', '', 'vevo'),
            (676, 'Poland', '', '', 'POL', '', '', 'vevo'),
            (677, 'Portugal', '', '', 'PRT', '', '', 'vevo'),
            (678, 'Puerto Rico', '', '', 'PRI', '', '', 'vevo'),
            (679, 'Qatar', '', '', 'QAT', '', '', 'vevo'),
            (680, 'Refugee As Per Art 1', '', '', 'XXB', '', '', 'vevo'),
            (681, 'Refugee Other', '', '', 'XXC', '', '', 'vevo'),
            (682, 'Reunion', '', '', 'REU', '', '', 'vevo'),
            (683, 'Romania', '', '', 'ROU', '', '', 'vevo'),
            (684, 'Romania Pre 1/2/2002', '', '', 'ROM', '', '', 'vevo'),
            (685, 'Russian Federation', '', '', 'RUS', '', '', 'vevo'),
            (686, 'Rwanda', '', '', 'RWA', '', '', 'vevo'),
            (687, 'Saint Helena, Ascension and Tristan Da Cunha', '', '', 'SHN', '', '', 'vevo'),
            (688, 'Saint Kitts and Nevis', '', '', 'KNA', '', '', 'vevo'),
            (689, 'Saint Lucia', '', '', 'LCA', '', '', 'vevo'),
            (690, 'Saint Vincent and The Grenadines', '', '', 'VCT', '', '', 'vevo'),
            (691, 'Samoa', '', '', 'WSM', '', '', 'vevo'),
            (692, 'San Marino', '', '', 'SMR', '', '', 'vevo'),
            (693, 'Sao Tome and Principe', '', '', 'STP', '', '', 'vevo'),
            (694, 'Saudi Arabia', '', '', 'SAU', '', '', 'vevo'),
            (695, 'Senegal', '', '', 'SEN', '', '', 'vevo'),
            (696, 'Serbia', '', '', 'SRB', '', '', 'vevo'),
            (697, 'Serbia and Montenegro', '', '', 'SCG', '', '', 'vevo'),
            (698, 'Seychelles', '', '', 'SYC', '', '', 'vevo'),
            (699, 'Sierra Leone', '', '', 'SLE', '', '', 'vevo'),
            (700, 'Singapore', '', '', 'SGP', '', '', 'vevo'),
            (701, 'Sint Maarten (Dutch Part)', '', '', 'SXM', '', '', 'vevo'),
            (702, 'Slovak Republic', '', '', 'SVK', '', '', 'vevo'),
            (703, 'Slovenia', '', '', 'SVN', '', '', 'vevo'),
            (704, 'Solomon Islands', '', '', 'SLB', '', '', 'vevo'),
            (705, 'Somalia', '', '', 'SOM', '', '', 'vevo'),
            (706, 'South Africa', '', '', 'ZAF', '', '', 'vevo'),
            (707, 'South Sudan', '', '', 'SSD', '', '', 'vevo'),
            (708, 'Spain', '', '', 'ESP', '', '', 'vevo'),
            (709, 'Sri Lanka', '', '', 'LKA', '', '', 'vevo'),
            (710, 'St Pierre and Miquelon', '', '', 'SPM', '', '', 'vevo'),
            (711, 'Stateless Person', '', '', 'XXA', '', '', 'vevo'),
            (712, 'Sudan', '', '', 'SDN', '', '', 'vevo'),
            (713, 'Suriname', '', '', 'SUR', '', '', 'vevo'),
            (714, 'Svalbard and Jan Mayen', '', '', 'SJM', '', '', 'vevo'),
            (715, 'Swaziland', '', '', 'SWZ', '', '', 'vevo'),
            (716, 'Sweden', '', '', 'SWE', '', '', 'vevo'),
            (717, 'Switzerland', '', '', 'CHE', '', '', 'vevo'),
            (718, 'Syrian Arab Republic', '', '', 'SYR', '', '', 'vevo'),
            (719, 'Taiwan', '', '', 'TWN', '', '', 'vevo'),
            (720, 'Tajikistan', '', '', 'TJK', '', '', 'vevo'),
            (721, 'Tanzania, United Republic of', '', '', 'TZA', '', '', 'vevo'),
            (722, 'Thailand', '', '', 'THA', '', '', 'vevo'),
            (723, 'Timor-leste', '', '', 'TLS', '', '', 'vevo'),
            (724, 'Togo', '', '', 'TGO', '', '', 'vevo'),
            (725, 'Tokelau', '', '', 'TKL', '', '', 'vevo'),
            (726, 'Tonga', '', '', 'TON', '', '', 'vevo'),
            (727, 'Trinidad and Tobago', '', '', 'TTO', '', '', 'vevo'),
            (728, 'Tunisia', '', '', 'TUN', '', '', 'vevo'),
            (729, 'Turkey', '', '', 'TUR', '', '', 'vevo'),
            (730, 'Turkmenistan', '', '', 'TKM', '', '', 'vevo'),
            (731, 'Turks and Caicos Islands', '', '', 'TCA', '', '', 'vevo'),
            (732, 'Tuvalu', '', '', 'TUV', '', '', 'vevo'),
            (733, 'U.S Minor Islands', '', '', 'UMI', '', '', 'vevo'),
            (734, 'Uganda', '', '', 'UGA', '', '', 'vevo'),
            (735, 'Ukraine', '', '', 'UKR', '', '', 'vevo'),
            (736, 'United Arab Emirates', '', '', 'ARE', '', '', 'vevo'),
            (737, 'United Kingdom - British Citizen', '', '', 'GBR', '', '', 'vevo'),
            (738, 'United Kingdom - British National (Overseas)', '', '', 'GBN', '', '', 'vevo'),
            (739, 'United Kingdom - British Overseas Citizen', '', '', 'GBO', '', '', 'vevo'),
            (740, 'United Kingdom - British Overseas Territories Citizen', '', '', 'GBD', '', '', 'vevo'),
            (741, 'United Kingdom - British Protected Person', '', '', 'GBP', '', '', 'vevo'),
            (742, 'United Kingdom - British Subject', '', '', 'GBS', '', '', 'vevo'),
            (743, 'United Nations Agency', '', '', 'UNA', '', '', 'vevo'),
            (744, 'United Nations Organisation', '', '', 'UNO', '', '', 'vevo'),
            (745, 'United States', '', '', 'USA', '', '', 'vevo'),
            (746, 'UNMIK Travel Doc', '', '', 'UNK', '', '', 'vevo'),
            (747, 'Unspecified Nationality', '', '', 'XXX', '', '', 'vevo'),
            (748, 'Uruguay', '', '', 'URY', '', '', 'vevo'),
            (749, 'Uzbekistan', '', '', 'UZB', '', '', 'vevo'),
            (750, 'Vanuatu', '', '', 'VUT', '', '', 'vevo'),
            (751, 'Vatican City State (Holy See)', '', '', 'VAT', '', '', 'vevo'),
            (752, 'Venezuela', '', '', 'VEN', '', '', 'vevo'),
            (753, 'Viet Nam', '', '', 'VNM', '', '', 'vevo'),
            (754, 'Virgin Islands (British)', '', '', 'VGB', '', '', 'vevo'),
            (755, 'Virgin Islands (U.S)', '', '', 'VIR', '', '', 'vevo'),
            (756, 'Wallis and Futuna Isl', '', '', 'WLF', '', '', 'vevo'),
            (757, 'Western Sahara', '', '', 'ESH', '', '', 'vevo'),
            (758, 'Yemen', '', '', 'YEM', '', '', 'vevo'),
            (759, 'Yugoslavia', '', '', 'YUG', '', '', 'vevo'),
            (760, 'Zaire', '', '', 'ZAR', '', '', 'vevo'),
            (761, 'Zambia', '', '', 'ZMB', '', '', 'vevo'),
            (762, 'Zimbabwe', '', '', 'ZWE', '', '', 'vevo');");

        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_custom_height`, `field_type_use_for`) VALUES
            (44, 'country_vevo', 'Country VEVO', 'Y', 'Y', 'N', 'all');");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo', 'reference', 'authorized_agents', 'country_vevo') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('applicant_form_fields'), array('applicant_field_id'))
            ->where('applicant_field_unique_id = ?', 'country_of_passport');

        $applicantFieldIds = $db->fetchCol($select);

        $arrValues = array(
            'cop' => ''
        );

        foreach ($arrValues as $key => $value) {
            $db->update(
                'applicant_form_data',
                array('value' => $value),
                $db->quoteInto('value = ?', $key) . ' AND ' . $db->quoteInto('applicant_field_id IN (?)', $applicantFieldIds, 'INT')
            );
        }

        $this->execute("UPDATE `applicant_form_fields` SET `type` = 'country_vevo' WHERE `applicant_field_unique_id` = 'country_of_passport';");


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `country_master` WHERE `type` = 'vevo';");

        $this->execute("ALTER TABLE `country_master` DROP COLUMN `type`;");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo', 'reference', 'authorized_agents') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=44;");

        $this->execute("UPDATE `applicant_form_fields` SET `type` = 'text' WHERE `applicant_field_unique_id` = 'country_of_passport';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}