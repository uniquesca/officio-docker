<?php

use Officio\Migration\AbstractMigration;

class ConvertVisaPostsToVac extends AbstractMigration
{
    public function up()
    {
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'manage-company-edit';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found for public access.');
        }

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'superadmin',
                    'resource_id'        => 'manage-vac',
                    'resource_privilege' => '',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $arrIncorrectValues = $this->fetchAll("SELECT d.* FROM `client_form_data` AS d LEFT JOIN client_form_default AS def ON def.form_default_id = d.value WHERE def.value IS NULL AND d.field_id IN (SELECT field_id FROM `client_form_fields` WHERE company_field_id = 'visa_office')");

        if (!empty($arrIncorrectValues)) {
            $arrFieldsIds = [];
            foreach ($arrIncorrectValues as $arrIncorrectValueRecord) {
                $arrFieldsIds[] = $arrIncorrectValueRecord['field_id'];
            }

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('client_form_default')
                ->where(['field_id IN' => array_unique($arrFieldsIds)])
                ->orderAsc('field_id')
                ->orderAsc('order')
                ->execute();

            $arrCorrectDefaultValues = $statement->fetchAll('assoc');

            $maxFieldOrderMapping     = [];
            $arrGroupedCorrectOptions = [];
            foreach ($arrCorrectDefaultValues as $arrCorrectDefaultValueRecord) {
                $this->getQueryBuilder()
                    ->update('client_form_data')
                    ->set('value', $arrCorrectDefaultValueRecord['form_default_id'])
                    ->where([
                        'field_id' => $arrCorrectDefaultValueRecord['field_id'],
                        'value'    => $arrCorrectDefaultValueRecord['value']
                    ])
                    ->execute();

                $arrGroupedCorrectOptions[$arrCorrectDefaultValueRecord['field_id']][] = $arrCorrectDefaultValueRecord['value'];

                if (!isset($maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']])) {
                    $maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']] = 0;
                }
                $maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']] = max($maxFieldOrderMapping[$arrCorrectDefaultValueRecord['field_id']], $arrCorrectDefaultValueRecord['order']);
            }

            foreach ($arrIncorrectValues as $arrIncorrectValueRecord) {
                if (!in_array($arrIncorrectValueRecord['value'], $arrGroupedCorrectOptions[$arrIncorrectValueRecord['field_id']])) {
                    $arrGroupedCorrectOptions[$arrIncorrectValueRecord['field_id']][] = $arrIncorrectValueRecord['value'];

                    $maxFieldOrderMapping[$arrIncorrectValueRecord['field_id']] += 1;

                    $arrNewOptionInsert = [
                        'field_id' => $arrIncorrectValueRecord['field_id'],
                        'value'    => $arrIncorrectValueRecord['value'],
                        'order'    => $maxFieldOrderMapping[$arrIncorrectValueRecord['field_id']],
                    ];

                    $statement = $this->getQueryBuilder()
                        ->insert(array_keys($arrNewOptionInsert))
                        ->into('client_form_default')
                        ->values($arrNewOptionInsert)
                        ->execute();

                    // Save the mapping
                    $createdOptionId = $statement->lastInsertId('client_form_default');

                    $this->getQueryBuilder()
                        ->update('client_form_data')
                        ->set('value', $createdOptionId)
                        ->where([
                            'field_id' => $arrIncorrectValueRecord['field_id'],
                            'value'    => $arrIncorrectValueRecord['value']
                        ])
                        ->execute();
                }
            }
        }

        $statement = $this->getQueryBuilder()
            ->select(array('field_id'))
            ->from('client_form_fields')
            ->where(['company_field_id' => 'visa_office'])
            ->orderAsc('field_id')
            ->execute();

        $arrVACFieldsIds = array_column($statement->fetchAll('assoc'), 'field_id');

        $this->getQueryBuilder()
            ->update('client_form_default')
            ->set('value', 'Abidjan')
            ->where([
                'field_id IN ' => $arrVACFieldsIds,
                'value'        => 'Abidjian'
            ])
            ->execute();

        $this->getQueryBuilder()
            ->update('client_form_default')
            ->set('value', 'Buenos Aires')
            ->where([
                'field_id IN ' => $arrVACFieldsIds,
                'value'        => 'Buenos Airies'
            ])
            ->execute();

        $this->getQueryBuilder()
            ->update('client_form_default')
            ->set('value', 'Guatemala City')
            ->where([
                'field_id IN ' => $arrVACFieldsIds,
                'value'        => 'Guatemala'
            ])
            ->execute();

        $this->getQueryBuilder()
            ->update('client_form_default')
            ->set('value', 'Mexico City')
            ->where([
                'field_id IN ' => $arrVACFieldsIds,
                'value'        => 'Mexico'
            ])
            ->execute();

        // Kyiv, not Kiev https://en.wikipedia.org/wiki/KyivNotKiev
        $this->getQueryBuilder()
            ->update('client_form_default')
            ->set('value', 'Kyiv')
            ->where([
                'field_id IN ' => $arrVACFieldsIds,
                'value'        => 'Kiev'
            ])
            ->execute();


        echo 'Fixed incorrect data' . PHP_EOL;

        $this->execute("UPDATE `client_form_fields` SET `label`='VAC/Visa Office', `type`=26 WHERE company_field_id = 'visa_office'");

        $statement = $this->getQueryBuilder()
            ->select(array('company_id'))
            ->from('company')
            ->orderAsc('company_id')
            ->execute();

        $arrCompanyIds = array_column($statement->fetchAll('assoc'), 'company_id');

        $this->execute("CREATE TABLE `client_vac` (
            `client_vac_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `client_vac_parent_id` INT(10) UNSIGNED NULL DEFAULT NULL,
            `company_id` BIGINT(19) NULL DEFAULT NULL,
            `client_vac_country` CHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `client_vac_city` CHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `client_vac_link` CHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `client_vac_order` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
            `client_vac_deleted` ENUM('Y','N') NOT NULL DEFAULT 'N' COLLATE 'utf8_general_ci',
            PRIMARY KEY (`client_vac_id`) USING BTREE,
            INDEX `FK_client_vac_parent_id` (`client_vac_parent_id`) USING BTREE,
            INDEX `FK_client_vac_company_id` (`company_id`) USING BTREE,
            CONSTRAINT `FK_client_vac_parent_id` FOREIGN KEY (`client_vac_parent_id`) REFERENCES `client_vac` (`client_vac_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_vac_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='VAC list for each company.'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $arrDefaultVACs = [
            0   => [
                'city'    => 'Abidjan',
                'country' => 'Ivory Coast',
                'link'    => 'https://visa.vfsglobal.com/civ/en/can',
            ],
            1   => [
                'city'    => 'Abu Dhabi',
                'country' => 'United Arab Emirates',
                'link'    => 'https://visa.vfsglobal.com/are/en/can',
            ],
            2   => [
                'city'    => 'Abuja',
                'country' => 'Nigeria',
                'link'    => 'https://visa.vfsglobal.com/nga/en/can',
            ],
            3   => [
                'city'    => 'Accra',
                'country' => 'Ghana',
                'link'    => 'https://visa.vfsglobal.com/gha/en/can',
            ],
            4   => [
                'city'    => 'Addis Ababa',
                'country' => 'Ethiopia',
                'link'    => 'https://visa.vfsglobal.com/eth/en/can',
            ],
            5   => [
                'city'    => 'Ahmedabad',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            6   => [
                'city'    => 'Al Khobar',
                'country' => 'Saudi Arabia',
                'link'    => 'https://visa.vfsglobal.com/sau/en/can',
            ],
            7   => [
                'city'    => 'Algiers',
                'country' => 'Algeria',
                'link'    => 'https://visa.vfsglobal.com/dza/en/can',
            ],
            8   => [
                'city'    => 'Almaty',
                'country' => 'Kazakhstan',
                'link'    => 'https://visa.vfsglobal.com/kaz/en/can',
            ],
            9   => [
                'city'    => 'Amman',
                'country' => 'Jordan',
                'link'    => 'https://visa.vfsglobal.com/jor/en/can',
            ],
            10  => [
                'city'    => 'Ankara',
                'country' => 'Turkey',
                'link'    => 'https://visa.vfsglobal.com/tur/en/can',
            ],
            11  => [
                'city'    => 'Antananarivo',
                'country' => 'Madagascar',
                'link'    => 'https://visa.vfsglobal.com/mdg/en/can',
            ],
            12  => [
                'city'    => 'Asuncion',
                'country' => 'Paraguay',
                'link'    => 'https://www.vfsglobal.ca/canada/paraguay/english/index.html',
            ],
            13  => [
                'city'    => 'Athens',
                'country' => 'Greece',
                'link'    => 'https://visa.vfsglobal.com/grc/en/can',
            ],
            14  => [
                'city'    => 'Auckland',
                'country' => 'New Zealand',
                'link'    => 'http://www.vfsglobal.ca/canada/newzealand/',
            ],
            15  => [
                'city'    => 'Baku',
                'country' => 'Azerbaijan',
                'link'    => 'https://visa.vfsglobal.com/aze/en/can',
            ],
            16  => [
                'city'    => 'Bali',
                'country' => 'Indonesia',
                'link'    => 'http://www.vfsglobal.ca/canada/indonesia/english/index.html',
            ],
            17  => [
                'city'    => 'Bamako',
                'country' => 'Mali',
                'link'    => 'https://visa.vfsglobal.com/mli/en/can',
            ],
            18  => [
                'city'    => 'Bangalore',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            19  => [
                'city'    => 'Bangkok',
                'country' => 'Thailand',
                'link'    => 'https://www.vfsglobal.ca/canada/thailand/',
            ],
            20  => [
                'city'    => 'Beijing',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            21  => [
                'city'    => 'Beirut',
                'country' => 'Lebanon',
                'link'    => 'https://visa.vfsglobal.com/lbn/en/can',
            ],
            22  => [
                'city'    => 'Belgrade',
                'country' => 'Serbia',
                'link'    => 'https://visa.vfsglobal.com/srb/en/can',
            ],
            23  => [
                'city'    => 'Berlin',
                'country' => 'Germany',
                'link'    => 'https://visa.vfsglobal.com/deu/en/can',
            ],
            24  => [
                'city'    => 'Bishkek',
                'country' => 'Kyrgyzstan',
                'link'    => 'https://visa.vfsglobal.com/kgz/en/can',
            ],
            25  => [
                'city'    => 'Bogota',
                'country' => 'Colombia',
                'link'    => 'https://www.vfsglobal.ca/Canada/Colombia/English/index.html',
            ],
            26  => [
                'city'    => 'Brasilia',
                'country' => 'Brazil',
                'link'    => 'http://www.vfsglobal.ca/canada/brazil/english/index.html',
            ],
            27  => [
                'city'    => 'Bridgetown',
                'country' => 'Barbados',
                'link'    => 'https://www.vfsglobal.ca/Canada/Barbados/',
            ],
            28  => [
                'city'    => 'Bucharest',
                'country' => 'Romania',
                'link'    => 'https://visa.vfsglobal.com/rou/en/can',
            ],
            29  => [
                'city'    => 'Buenos Aires',
                'country' => 'Argentina',
                'link'    => 'http://www.vfsglobal.ca/canada/argentina/english/index.html',
            ],
            30  => [
                'city'    => 'Cairo',
                'country' => 'Egypt',
                'link'    => 'https://visa.vfsglobal.com/egy/en/can',
            ],
            31  => [
                'city'    => 'Cali',
                'country' => 'Colombia',
                'link'    => 'https://www.vfsglobal.ca/Canada/Colombia/English/index.html',
            ],
            32  => [
                'city'    => 'Cape Town',
                'country' => 'South Africa',
                'link'    => 'https://visa.vfsglobal.com/zaf/en/can',
            ],
            33  => [
                'city'    => 'Caracas',
                'country' => 'Venezuela',
                'link'    => 'https://www.vfsglobal.ca/Canada/Venezuela/English/index.html',
            ],
            34  => [
                'city'    => 'Castries',
                'country' => 'Saint Lucia',
                'link'    => 'http://www.vfsglobal.ca/Canada/Saint-Lucia',
            ],
            35  => [
                'city'    => 'Cebu',
                'country' => 'Philippines',
                'link'    => 'https://www.vfsglobal.ca/canada/Philippines/',
            ],
            36  => [
                'city'    => 'Chandigarh',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            37  => [
                'city'    => 'Chengdu',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            38  => [
                'city'    => 'Chennai',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            39  => [
                'city'    => 'Chisinau',
                'country' => 'Moldova',
                'link'    => 'https://visa.vfsglobal.com/mda/en/can',
            ],
            40  => [
                'city'    => 'Chittagong',
                'country' => 'Bangladesh',
                'link'    => 'http://www.vfsglobal.ca/Canada/bangladesh/index.html',
            ],
            41  => [
                'city'    => 'Chongqing',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            42  => [
                'city'    => 'Colombo',
                'country' => 'Sri Lanka',
                'link'    => 'http://www.vfsglobal.ca/canada/srilanka/',
            ],
            43  => [
                'city'    => 'Conakry',
                'country' => 'Guinea',
                'link'    => 'https://visa.vfsglobal.com/gin/en/can',
            ],
            44  => [
                'city'    => 'Dakar',
                'country' => 'Senegal',
                'link'    => 'https://visa.vfsglobal.com/sen/en/can',
            ],
            45  => [
                'city'    => 'Dar es Salaam',
                'country' => 'Tanzania',
                'link'    => 'https://visa.vfsglobal.com/tza/en/can',
            ],
            46  => [
                'city'    => 'Dhaka',
                'country' => 'Bangladesh',
                'link'    => 'http://www.vfsglobal.ca/Canada/bangladesh/index.html',
            ],
            47  => [
                'city'    => 'Doha',
                'country' => 'Qatar',
                'link'    => 'https://visa.vfsglobal.com/qat/en/can',
            ],
            48  => [
                'city'    => 'Dubai',
                'country' => 'United Arab Emirates',
                'link'    => 'https://visa.vfsglobal.com/are/en/can',
            ],
            49  => [
                'city'    => 'Dublin',
                'country' => 'Ireland',
                'link'    => 'https://visa.vfsglobal.com/irl/en/can',
            ],
            50  => [
                'city'    => 'Dushanbe',
                'country' => 'Tajikistan',
                'link'    => 'https://visa.vfsglobal.com/tjk/en/can',
            ],
            51  => [
                'city'    => 'Düsseldorf',
                'country' => 'Germany',
                'link'    => 'https://visa.vfsglobal.com/deu/en/can',
            ],
            52  => [
                'city'    => 'Erbil',
                'country' => 'Iraq',
                'link'    => 'https://visa.vfsglobal.com/irq/en/can',
            ],
            53  => [
                'city'    => 'Georgetown',
                'country' => 'Guyana',
                'link'    => 'https://www.vfsglobal.ca/Canada/Guyana/',
            ],
            54  => [
                'city'    => 'Guangzhou',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            55  => [
                'city'    => 'Guatemala City',
                'country' => 'Guatemala',
                'link'    => 'https://www.vfsglobal.ca/canada/Guatemala/english/index.html',
            ],
            56  => [
                'city'    => 'Hangzhou',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            57  => [
                'city'    => 'Hanoi',
                'country' => 'Vietnam',
                'link'    => 'https://www.vfsglobal.ca/canada/vietnam/index.html',
            ],
            58  => [
                'city'    => 'Harare',
                'country' => 'Zimbabwe',
                'link'    => 'https://visa.vfsglobal.com/zwe/en/can',
            ],
            59  => [
                'city'    => 'Helsinki',
                'country' => 'Finland',
                'link'    => 'https://visa.vfsglobal.com/fin/en/can',
            ],
            60  => [
                'city'    => 'Ho Chi Minh',
                'country' => 'Vietnam',
                'link'    => 'https://www.vfsglobal.ca/canada/vietnam/index.html',
            ],
            61  => [
                'city'    => 'Hong Kong',
                'country' => 'Hong Kong SAR',
                'link'    => 'http://www.vfsglobal.ca/canada/Hongkong/english/index.html',
            ],
            62  => [
                'city'    => 'Hyderabad',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            63  => [
                'city'    => 'Islamabad',
                'country' => 'Pakistan',
                'link'    => 'https://www.vfsglobal.ca/Canada/Pakistan/',
            ],
            64  => [
                'city'    => 'Istanbul',
                'country' => 'Turkey',
                'link'    => 'https://visa.vfsglobal.com/tur/en/can',
            ],
            65  => [
                'city'    => 'Jakarta',
                'country' => 'Indonesia',
                'link'    => 'http://www.vfsglobal.ca/canada/indonesia/english/index.html',
            ],
            66  => [
                'city'    => 'Jalandhar',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            67  => [
                'city'    => 'Jeddah',
                'country' => 'Saudi Arabia',
                'link'    => 'https://visa.vfsglobal.com/sau/en/can',
            ],
            68  => [
                'city'    => 'Jinan',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            69  => [
                'city'    => 'Kampala',
                'country' => 'Uganda',
                'link'    => 'https://visa.vfsglobal.com/uga/en/can',
            ],
            70  => [
                'city'    => 'Karachi',
                'country' => 'Pakistan',
                'link'    => 'https://www.vfsglobal.ca/Canada/Pakistan/',
            ],
            71  => [
                'city'    => 'Kathmandu',
                'country' => 'Nepal',
                'link'    => 'http://www.vfsglobal.ca/canada/nepal/',
            ],
            72  => [
                'city'    => 'Khartoum',
                'country' => 'Sudan',
                'link'    => 'https://visa.vfsglobal.com/sdn/en/can/',
            ],
            73  => [
                'city'    => 'Kigali',
                'country' => 'Rwanda',
                'link'    => 'https://visa.vfsglobal.com/rwa/en/can',
            ],
            74  => [
                'city'    => 'Kingston',
                'country' => 'Jamaica',
                'link'    => 'https://www.vfsglobal.ca/Canada/Jamaica/',
            ],
            75  => [
                'city'    => 'Kingstown',
                'country' => 'Saint Vincent and the Grenadines',
                'link'    => 'http://www.vfsglobal.ca/Canada/Saint-Vincent-and-the-Grenadines',
            ],
            76  => [
                'city'    => 'Kinshasa',
                'country' => 'Democratic Rep. of Congo',
                'link'    => 'https://visa.vfsglobal.com/cod/en/can',
            ],
            77  => [
                'city'    => 'Kolkata',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            78  => [
                'city'    => 'Kuala Lumpur',
                'country' => 'Malaysia',
                'link'    => 'https://www.vfsglobal.ca/canada/malaysia/english/',
            ],
            79  => [
                'city'    => 'Kunming',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            80  => [
                'city'    => 'Kuwait City',
                'country' => 'Kuwait',
                'link'    => 'https://visa.vfsglobal.com/kwt/en/can',
            ],
            81  => [
                'city'    => 'Kyiv',
                'country' => 'Ukraine',
                'link'    => 'https://visa.vfsglobal.com/ukr/en/can',
            ],
            82  => [
                'city'    => 'La Paz',
                'country' => 'Bolivia',
                'link'    => 'http://www.vfsglobal.ca/Canada/Bolivia/English/',
            ],
            83  => [
                'city'    => 'Lagos',
                'country' => 'Nigeria',
                'link'    => 'https://visa.vfsglobal.com/nga/en/can',
            ],
            84  => [
                'city'    => 'Lahore',
                'country' => 'Pakistan',
                'link'    => 'https://www.vfsglobal.ca/Canada/Pakistan/',
            ],
            85  => [
                'city'    => 'Lima',
                'country' => 'Peru',
                'link'    => 'http://www.vfsglobal.ca/canada/peru/english/index.html',
            ],
            86  => [
                'city'    => 'London',
                'country' => 'United Kingdom',
                'link'    => 'https://visa.vfsglobal.com/gbr/en/can',
            ],
            87  => [
                'city'    => 'Los Angeles',
                'country' => 'United States',
                'link'    => 'https://www.vfsglobal.ca/Canada/USA/',
            ],
            88  => [
                'city'    => 'Lviv',
                'country' => 'Ukraine',
                'link'    => 'https://visa.vfsglobal.com/ukr/en/can',
            ],
            89  => [
                'city'    => 'Lyon',
                'country' => 'France',
                'link'    => 'https://visa.vfsglobal.com/fra/en/can',
            ],
            90  => [
                'city'    => 'Madrid',
                'country' => 'Spain',
                'link'    => 'https://visa.vfsglobal.com/esp/en/can',
            ],
            91  => [
                'city'    => 'Managua',
                'country' => 'Nicaragua',
                'link'    => 'http://www.vfsglobal.ca/canada/Nicaragua/english/index.html',
            ],
            92  => [
                'city'    => 'Manama',
                'country' => 'Bahrain',
                'link'    => 'https://visa.vfsglobal.com/bhr/en/can',
            ],
            93  => [
                'city'    => 'Manila',
                'country' => 'Philippines',
                'link'    => 'https://www.vfsglobal.ca/canada/Philippines/',
            ],
            94  => [
                'city'    => 'Medellin',
                'country' => 'Colombia',
                'link'    => 'https://www.vfsglobal.ca/Canada/Colombia/English/index.html',
            ],
            95  => [
                'city'    => 'Melbourne',
                'country' => 'Australia',
                'link'    => 'http://www.vfsglobal.ca/canada/australia/',
            ],
            96  => [
                'city'    => 'Mexico City',
                'country' => 'Mexico',
                'link'    => 'https://www.vfsglobal.ca/canada/Mexico/English/index.html',
            ],
            97  => [
                'city'    => 'Montego Bay',
                'country' => 'Jamaica',
                'link'    => 'https://www.vfsglobal.ca/Canada/Jamaica/',
            ],
            98  => [
                'city'    => 'Montevideo',
                'country' => 'Uruguay',
                'link'    => 'http://www.vfsglobal.ca/canada/uruguay/english/index.html',
            ],
            99  => [
                'city'    => 'Moscow',
                'country' => 'Russia',
                'link'    => 'https://visa.vfsglobal.com/rus/en/can',
            ],
            100 => [
                'city'    => 'Mumbai',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            101 => [
                'city'    => 'Muscat',
                'country' => 'Oman',
                'link'    => 'https://visa.vfsglobal.com/omn/en/can',
            ],
            102 => [
                'city'    => 'Nairobi',
                'country' => 'Kenya',
                'link'    => 'https://visa.vfsglobal.com/ken/en/can',
            ],
            103 => [
                'city'    => 'Nanjing',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            104 => [
                'city'    => 'New Delhi',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            105 => [
                'city'    => 'New York',
                'country' => 'United States',
                'link'    => 'https://www.vfsglobal.ca/Canada/USA/',
            ],
            106 => [
                'city'    => 'Niamey',
                'country' => 'Niger',
                'link'    => 'https://visa.vfsglobal.com/ner/en/can',
            ],
            107 => [
                'city'    => 'Novosibirsk',
                'country' => 'Russia',
                'link'    => 'https://visa.vfsglobal.com/rus/en/can',
            ],
            108 => [
                'city'    => 'Nur-Sultan',
                'country' => 'Kazakhstan',
                'link'    => 'https://visa.vfsglobal.com/kaz/en/can',
            ],
            109 => [
                'city'    => 'Osaka',
                'country' => 'Japan',
                'link'    => 'http://www.vfsglobal.ca/canada/japan/',
            ],
            110 => [
                'city'    => 'Ouagadougou',
                'country' => 'Burkina Faso',
                'link'    => 'https://visa.vfsglobal.com/bfa/en/can',
            ],
            111 => [
                'city'    => 'Panama City',
                'country' => 'Panama',
                'link'    => 'http://www.vfsglobal.ca/canada/panama/english/index.html',
            ],
            112 => [
                'city'    => 'Paris',
                'country' => 'France',
                'link'    => 'https://visa.vfsglobal.com/fra/en/can',
            ],
            113 => [
                'city'    => 'Perth',
                'country' => 'Australia',
                'link'    => 'http://www.vfsglobal.ca/canada/australia/',
            ],
            114 => [
                'city'    => 'Phnom Penh',
                'country' => 'Cambodia',
                'link'    => 'http://www.vfsglobal.ca/canada/cambodia/',
            ],
            115 => [
                'city'    => 'Port Louis',
                'country' => 'Mauritius',
                'link'    => 'https://visa.vfsglobal.com/mus/en/can',
            ],
            116 => [
                'city'    => 'Port of Spain',
                'country' => 'Trinidad and Tobago',
                'link'    => 'https://www.vfsglobal.ca/Canada/Trinidad-and-Tobago/',
            ],
            117 => [
                'city'    => 'Port-au-Prince',
                'country' => 'Haiti',
                'link'    => 'https://www.vfsglobal.ca/Canada/Haiti/',
            ],
            118 => [
                'city'    => 'Porto Alegre',
                'country' => 'Brazil',
                'link'    => 'http://www.vfsglobal.ca/canada/brazil/english/index.html',
            ],
            119 => [
                'city'    => 'Pretoria',
                'country' => 'South Africa',
                'link'    => 'https://visa.vfsglobal.com/zaf/en/can',
            ],
            120 => [
                'city'    => 'Pristina',
                'country' => 'Kosovo',
                'link'    => 'https://visa.vfsglobal.com/xkx/en/can',
            ],
            121 => [
                'city'    => 'Pune',
                'country' => 'India',
                'link'    => 'https://www.vfsglobal.ca/canada/india/',
            ],
            122 => [
                'city'    => 'Quito',
                'country' => 'Ecuador',
                'link'    => 'http://www.vfsglobal.ca/canada/ecuador/english/index.html',
            ],
            123 => [
                'city'    => 'Rabat',
                'country' => 'Morocco',
                'link'    => 'https://visa.vfsglobal.com/mar/en/can',
            ],
            124 => [
                'city'    => 'Rangoon (Yangon)',
                'country' => 'Burma (Myanmar)',
                'link'    => 'http://www.vfsglobal.ca/canada/myanmar/',
            ],
            125 => [
                'city'    => 'Recife',
                'country' => 'Brazil',
                'link'    => 'http://www.vfsglobal.ca/canada/brazil/english/index.html',
            ],
            126 => [
                'city'    => 'Rio de Janeiro',
                'country' => 'Brazil',
                'link'    => 'http://www.vfsglobal.ca/canada/brazil/english/index.html',
            ],
            127 => [
                'city'    => 'Riyadh',
                'country' => 'Saudi Arabia',
                'link'    => 'https://visa.vfsglobal.com/sau/en/can',
            ],
            128 => [
                'city'    => 'Rome',
                'country' => 'Italy',
                'link'    => 'https://visa.vfsglobal.com/ita/en/can',
            ],
            129 => [
                'city'    => 'Rostov-on-Don',
                'country' => 'Russia',
                'link'    => 'https://visa.vfsglobal.com/rus/en/can',
            ],
            130 => [
                'city'    => 'Saint Petersburg',
                'country' => 'Russia',
                'link'    => 'https://visa.vfsglobal.com/rus/en/can',
            ],
            131 => [
                'city'    => 'San Jose',
                'country' => 'Costa Rica',
                'link'    => 'http://www.vfsglobal.ca/canada/costarica/english/index.html',
            ],
            132 => [
                'city'    => 'San Salvador',
                'country' => 'El Salvador',
                'link'    => 'http://www.vfsglobal.ca/canada/elsalvador/english/index.html',
            ],
            133 => [
                'city'    => 'Santiago',
                'country' => 'Chile',
                'link'    => 'https://www.vfsglobal.ca/Canada/Chile/',
            ],
            134 => [
                'city'    => 'Santo Domingo',
                'country' => 'Dominican Republic',
                'link'    => 'https://www.vfsglobal.ca/Canada/DominicanRepublic/index.html',
            ],
            135 => [
                'city'    => 'Sao Paulo',
                'country' => 'Brazil',
                'link'    => 'http://www.vfsglobal.ca/canada/brazil/english/index.html',
            ],
            136 => [
                'city'    => 'Sarajevo',
                'country' => 'Bosnia and Herzegovina',
                'link'    => 'https://visa.vfsglobal.com/bih/en/can',
            ],
            137 => [
                'city'    => 'Seoul',
                'country' => 'South Korea',
                'link'    => 'http://www.vfsglobal.ca/canada/korea/english/index.html',
            ],
            138 => [
                'city'    => 'Shanghai',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            139 => [
                'city'    => 'Shenyang',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            140 => [
                'city'    => 'Singapore',
                'country' => 'Singapore',
                'link'    => 'http://www.vfsglobal.ca/canada/singapore/',
            ],
            141 => [
                'city'    => 'Skopje',
                'country' => 'Macedonia',
                'link'    => 'https://visa.vfsglobal.com/mkd/en/can',
            ],
            142 => [
                'city'    => 'Stockholm',
                'country' => 'Sweden',
                'link'    => 'https://visa.vfsglobal.com/swe/en/can',
            ],
            143 => [
                'city'    => 'Suva',
                'country' => 'Fiji',
                'link'    => 'http://www.vfsglobal.ca/canada/fiji/',
            ],
            144 => [
                'city'    => 'Sydney',
                'country' => 'Australia',
                'link'    => 'http://www.vfsglobal.ca/canada/australia/',
            ],
            145 => [
                'city'    => 'Sylhet',
                'country' => 'Bangladesh',
                'link'    => 'http://www.vfsglobal.ca/Canada/bangladesh/index.html',
            ],
            146 => [
                'city'    => 'Taipei',
                'country' => 'Taiwan',
                'link'    => 'http://www.vfsglobal.ca/canada/taiwan/english/index.html',
            ],
            147 => [
                'city'    => 'Tbilisi',
                'country' => 'Georgia',
                'link'    => 'https://visa.vfsglobal.com/geo/en/can',
            ],
            148 => [
                'city'    => 'Tegucigalpa',
                'country' => 'Honduras',
                'link'    => 'http://www.vfsglobal.ca/canada/honduras/english/index.html',
            ],
            149 => [
                'city'    => 'Tel Aviv',
                'country' => 'Israel',
                'link'    => 'https://visa.vfsglobal.com/isr/en/can',
            ],
            150 => [
                'city'    => 'The Hague',
                'country' => 'The Netherlands',
                'link'    => 'https://visa.vfsglobal.com/nld/en/can',
            ],
            151 => [
                'city'    => 'Tirana',
                'country' => 'Albania',
                'link'    => 'https://visa.vfsglobal.com/alb/en/can',
            ],
            152 => [
                'city'    => 'Tokyo',
                'country' => 'Japan',
                'link'    => 'http://www.vfsglobal.ca/canada/japan/',
            ],
            153 => [
                'city'    => 'Tunis',
                'country' => 'Tunisia',
                'link'    => 'https://visa.vfsglobal.com/tun/en/can',
            ],
            154 => [
                'city'    => 'Ulaanbaatar',
                'country' => 'Mongolia',
                'link'    => 'http://www.vfsglobal.ca/canada/mongolia/english/index.html',
            ],
            155 => [
                'city'    => 'Vienna',
                'country' => 'Austria',
                'link'    => 'https://visa.vfsglobal.com/aut/en/can',
            ],
            156 => [
                'city'    => 'Vladivostok',
                'country' => 'Russia',
                'link'    => 'https://visa.vfsglobal.com/rus/en/can',
            ],
            157 => [
                'city'    => 'Warsaw',
                'country' => 'Poland',
                'link'    => 'https://visa.vfsglobal.com/pol/en/can',
            ],
            158 => [
                'city'    => 'Warsaw Temporary VAC',
                'country' => 'Poland',
                'link'    => 'https://visa.vfsglobal.com/pol/en/can',
            ],
            159 => [
                'city'    => 'Wuhan',
                'country' => 'China',
                'link'    => 'https://www.vfsglobal.ca/canada/china/english/index.html',
            ],
            160 => [
                'city'    => 'Yaoundé',
                'country' => 'Cameroon',
                'link'    => 'https://visa.vfsglobal.com/cmr/en/can',
            ],
            161 => [
                'city'    => 'Yekaterinburg',
                'country' => 'Russia',
                'link'    => 'https://visa.vfsglobal.com/rus/en/can',
            ],
            162 => [
                'city'    => 'Yerevan',
                'country' => 'Armenia',
                'link'    => 'https://visa.vfsglobal.com/arm/en/can',
            ],
        ];

        // Add custom records for the default company (so will be copied to all others
        $arrDefaultVisaPosts = $this->fetchAll("SELECT * FROM client_form_default WHERE field_id IN (SELECT field_id FROM client_form_fields WHERE company_field_id = 'visa_office' AND company_id = 0)");
        foreach ($arrDefaultVisaPosts as $arrDefaultVisaPostRecord) {
            $booFound = false;
            foreach ($arrDefaultVACs as $arrDefaultVACInfo) {
                if ($arrDefaultVACInfo['city'] == $arrDefaultVisaPostRecord['value']) {
                    $booFound = true;
                    break;
                }
            }

            if (!$booFound) {
                $arrDefaultVACs[] = [
                    'city'    => $arrDefaultVisaPostRecord['value'],
                    'country' => null,
                    'link'    => null,
                ];
            }
        }

        $i                        = 0;
        $arrDefaultMapping        = [];
        $arrNewCategoriesToCreate = [];
        foreach ($arrDefaultVACs as $key => $arrDefaultVACInfo) {
            foreach ($arrCompanyIds as $companyId) {
                $arrNewVACInsert = [
                    'company_id'         => $companyId,
                    'client_vac_country' => $arrDefaultVACInfo['country'],
                    'client_vac_city'    => $arrDefaultVACInfo['city'],
                    'client_vac_link'    => $arrDefaultVACInfo['link'],
                    'client_vac_order'   => $i,
                    'client_vac_deleted' => 'N',
                ];

                if (empty($companyId)) {
                    $statement = $this->getQueryBuilder()
                        ->insert(array_keys($arrNewVACInsert))
                        ->into('client_vac')
                        ->values($arrNewVACInsert)
                        ->execute();

                    // Save the mapping
                    $createdVACId = $statement->lastInsertId('client_vac');

                    $arrDefaultMapping[$key] = $createdVACId;
                } else {
                    $arrNewVACInsert['client_vac_parent_id'] = $arrDefaultMapping[$key];

                    $arrNewCategoriesToCreate[] = $arrNewVACInsert;
                }
            }
            $i++;
        }

        $this->table('client_vac')
            ->insert($arrNewCategoriesToCreate)
            ->save();

        echo 'Created VAC records for all companies' . PHP_EOL;

        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('client_vac')
            ->orderAsc('company_id')
            ->orderAsc('client_vac_order')
            ->execute();

        $arrSavedVACRecords = $statement->fetchAll('assoc');

        $maxOrderMapping           = [];
        $arrSavedVACRecordsGrouped = [];
        foreach ($arrSavedVACRecords as $arrSavedVACRecordInfo) {
            $arrSavedVACRecordsGrouped[$arrSavedVACRecordInfo['company_id']][mb_strtolower($arrSavedVACRecordInfo['client_vac_city'])] = $arrSavedVACRecordInfo['client_vac_id'];

            if (!isset($maxOrderMapping[$arrSavedVACRecordInfo['company_id']])) {
                $maxOrderMapping[$arrSavedVACRecordInfo['company_id']] = 0;
            }
            $maxOrderMapping[$arrSavedVACRecordInfo['company_id']] = max($maxOrderMapping[$arrSavedVACRecordInfo['company_id']], $arrSavedVACRecordInfo['client_vac_order']);
        }

        echo 'Start previously saved records updating' . PHP_EOL;

        $statement = $this->getQueryBuilder()
            ->select(['d.*', 'f.company_id'])
            ->from(array('d' => 'client_form_default'))
            ->innerJoin(array('f' => 'client_form_fields'), ['f.field_id = d.field_id'])
            ->where(['d.field_id IN' => array_unique($arrVACFieldsIds)])
            ->orderAsc('f.field_id')
            ->orderAsc('d.order')
            ->execute();

        $arrDefaultValues = $statement->fetchAll('assoc');

        foreach ($arrDefaultValues as $arrDefaultValueInfo) {
            if (isset($arrSavedVACRecordsGrouped[$arrDefaultValueInfo['company_id']][mb_strtolower($arrDefaultValueInfo['value'])])) {
                $createdVACId = $arrSavedVACRecordsGrouped[$arrDefaultValueInfo['company_id']][mb_strtolower($arrDefaultValueInfo['value'])];
            } else {
                $maxOrderMapping[$arrDefaultValueInfo['company_id']] += 1;

                $arrNewVACInsert = [
                    'company_id'         => $arrDefaultValueInfo['company_id'],
                    'client_vac_country' => null,
                    'client_vac_city'    => $arrDefaultValueInfo['value'],
                    'client_vac_link'    => null,
                    'client_vac_order'   => $maxOrderMapping[$arrDefaultValueInfo['company_id']],
                    'client_vac_deleted' => 'N',
                ];

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrNewVACInsert))
                    ->into('client_vac')
                    ->values($arrNewVACInsert)
                    ->execute();

                // Save the mapping
                $createdVACId = $statement->lastInsertId('client_vac');

                $arrSavedVACRecordsGrouped[$arrDefaultValueInfo['company_id']][mb_strtolower($arrDefaultValueInfo['value'])] = $createdVACId;
            }

            $this->getQueryBuilder()
                ->update('client_form_data')
                ->set('value', $createdVACId)
                ->where([
                    'field_id' => $arrDefaultValueInfo['field_id'],
                    'value'    => $arrDefaultValueInfo['form_default_id']
                ])
                ->execute();
        }

        echo 'Updated previously saved records' . PHP_EOL;

        $this->getQueryBuilder()
            ->delete('client_form_default')
            ->where(['field_id IN' => array_unique($arrVACFieldsIds)])
            ->execute();
    }

    public function down()
    {
        $this->execute("UPDATE `client_form_fields` SET `label`='Visa Posts', `type`=3 WHERE company_field_id = 'visa_office'");
        $this->execute("DROP TABLE `client_vac`;");
    }
}
