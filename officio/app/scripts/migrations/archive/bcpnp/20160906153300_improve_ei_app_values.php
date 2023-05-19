<?php

use Officio\Service\Bcpnp;
use Phinx\Migration\AbstractMigration;

class ImproveEiAppValues extends AbstractMigration
{

    public function up()
    {
        /** @var Bcpnp $bcpnp */
        $bcpnp  = Zend_Registry::get('serviceManager')->get(Bcpnp::class);
        $output = $this->getOutput();

        /** @var Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('c' => 'clients'), array('c.member_id', 'c.client_type_id', 'fa.FormAssignedId', 'ct.company_id', 'c.fileNumber'))
            ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id')
            ->join(array('co' => 'company'), 'co.company_id = ct.company_id')
            ->join(array('fa' => 'FormAssigned'), 'fa.ClientMemberId = c.member_id')
            ->join(array('m' => 'members_relations'), 'm.child_member_id = c.member_id')
            ->where('ct.client_type_name = ?', 'Business Immigration Application')
            ->where('co.companyName = ?', 'BC PNP');

        $clients = $db->fetchAll($select);

        $conversions = array(
            'syncA_App_BusPropType'       => array(
                'newValue'      => array(
                    '1' => 'startup',
                    '2' => 'purchase-or-partnership',
                ),
                'caseFieldName' => 'Officio_BusPropType',
            ),
            'syncA_App_BusMunicipality'   => array(
                'newValue'      => array(
                    '100'  => '100 Mile House',
                    'ABBY' => 'Abbotsford',
                    'ABAY' => 'Alert Bay',
                    'AN'   => 'Anmore',
                    'ARM'  => 'Armstrong',
                    'ASH'  => 'Ashcroft',
                    'BAR'  => 'Barriere',
                    'BEL'  => 'Belcarra',
                    'BOW'  => 'Bowen Island',
                    'BURN' => 'Burnaby',
                    'BURL' => 'Burns Lake',
                    'CC'   => 'Cache Creek',
                    'CR'   => 'Campbell River',
                    'CF'   => 'Canal Flats',
                    'CST'  => 'Castlegar',
                    'CS'   => 'Central Saanich',
                    'CHS'  => 'Chase',
                    'CHT'  => 'Chetwynd',
                    'CHL'  => 'Chilliwack',
                    'CLR'  => 'Clearwater',
                    'CLN'  => 'Clinton',
                    'CLD'  => 'Coldstream',
                    'COL'  => 'Colwood',
                    'COM'  => 'Comox',
                    'COQ'  => 'Coquitlam',
                    'COR'  => 'Courtenay',
                    'CRN'  => 'Cranbrook',
                    'CRS'  => 'Creston',
                    'CMB'  => 'Cumberland',
                    'DAW'  => 'Dawson Creek',
                    'DEL'  => 'Delta',
                    'DUN'  => 'Duncan',
                    'ELK'  => 'Elkford',
                    'END'  => 'Enderby',
                    'ESQ'  => 'Esquimalt',
                    'FRN'  => 'Fernie',
                    'FSJA' => 'Fort St. James',
                    'FSJO' => 'Fort St. John',
                    'FRL'  => 'Fraser Lake',
                    'FRT'  => 'Fruitvale',
                    'GIB'  => 'Gibsons',
                    'GR'   => 'Gold River',
                    'GLD'  => 'Golden',
                    'GF'   => 'Grand Forks',
                    'GRAN' => 'Granisle',
                    'GREN' => 'Greenwood',
                    'HHS'  => 'Harrison Hot Springs',
                    'HZL'  => 'Hazelton',
                    'HL'   => 'Highlands',
                    'HP'   => 'Hope',
                    'HST'  => 'Houston',
                    'HH'   => 'Hudson\'s Hope',
                    'INV'  => 'Invermere',
                    'JG'   => 'Jumbo Glacier',
                    'KAM'  => 'Kamloops',
                    'KAS'  => 'Kaslo',
                    'KEL'  => 'Kelowna',
                    'KEN'  => 'Kent',
                    'KER'  => 'Keremeos',
                    'KIM'  => 'Kimberley',
                    'KIT'  => 'Kitimat',
                    'LS'   => 'Ladysmith',
                    'LCO'  => 'Lake Country',
                    'LCW'  => 'Lake Cowichan',
                    'LNGF' => 'Langford',
                    'LNGY' => 'Langley',
                    'LV'   => 'Lantzville',
                    'LIL'  => 'Lillooet',
                    'LB'   => 'Lions Bay',
                    'LL'   => 'Logan Lake',
                    'LM'   => 'Lumby',
                    'LY'   => 'Lytton',
                    'MAC'  => 'Mackenzie',
                    'MR'   => 'Maple Ridge',
                    'MAS'  => 'Masset',
                    'MCB'  => 'McBride',
                    'MER'  => 'Merritt',
                    'MET'  => 'Metchosin',
                    'MID'  => 'Midway',
                    'MIS'  => 'Mission',
                    'MON'  => 'Montrose',
                    'NAK'  => 'Nakusp',
                    'NAN'  => 'Nanaimo',
                    'NEL'  => 'Nelson',
                    'ND'   => 'New Denver',
                    'NH'   => 'New Hazelton',
                    'NW'   => 'New Westminster',
                    'NC'   => 'North Cowichan',
                    'NS'   => 'North Saanich',
                    'NV'   => 'North Vancouver',
                    'NR'   => 'Northern Rockies',
                    'OB'   => 'Oak Bay',
                    'OL'   => 'Oliver',
                    'OS'   => 'Osoyoos',
                    'PV'   => 'Parksville',
                    'PCH'  => 'Peachland',
                    'PEM'  => 'Pemberton',
                    'PEN'  => 'Penticton',
                    'PM'   => 'Pitt Meadows',
                    'PAB'  => 'Port Alberni',
                    'PAL'  => 'Port Alice',
                    'PCL'  => 'Port Clements',
                    'PCQ'  => 'Port Coquitlam',
                    'PE'   => 'Port Edward',
                    'PH'   => 'Port Hardy',
                    'PMC'  => 'Port McNeill',
                    'PMO'  => 'Port Moody',
                    'PC'   => 'Pouce Coupe',
                    'POR'  => 'Powell River',
                    'PG'   => 'Prince George',
                    'PRR'  => 'Prince Rupert',
                    'PRN'  => 'Princeton',
                    'QB'   => 'Qualicum Beach',
                    'QC'   => 'Queen Charlotte',
                    'QN'   => 'Quesnel',
                    'RHS'  => 'Radium Hot Springs',
                    'REV'  => 'Revelstoke',
                    'RM'   => 'Richmond',
                    'RS'   => 'Rossland',
                    'SAN'  => 'Saanich',
                    'SAL'  => 'Salmo',
                    'SLA'  => 'Salmon Arm',
                    'SAY'  => 'Sayward',
                    'SEC'  => 'Sechelt',
                    'SIGD' => 'Sechelt Indian Government District',
                    'SC'   => 'Sicamous',
                    'SD'   => 'Sidney',
                    'SLV'  => 'Silverton',
                    'SLO'  => 'Slocan',
                    'SM'   => 'Smithers',
                    'SO'   => 'Sooke',
                    'SPL'  => 'Spallumcheen',
                    'SPW'  => 'Sparwood',
                    'SQ'   => 'Squamish',
                    'ST'   => 'Stewart',
                    'SL'   => 'Summerland',
                    'SPK'  => 'Sun Peaks',
                    'SRY'  => 'Surrey',
                    'TA'   => 'Tahsis',
                    'TL'   => 'Taylor',
                    'TEL'  => 'Telkwa',
                    'TER'  => 'Terrace',
                    'TO'   => 'Tofino',
                    'TRA'  => 'Trail',
                    'TRI'  => 'Tumbler Ridge',
                    'UC'   => 'Ucluelet',
                    'VL'   => 'Valemount',
                    'VAN'  => 'Vancouver',
                    'VND'  => 'Vanderhoof',
                    'VER'  => 'Vernon',
                    'VIC'  => 'Victoria',
                    'VR'   => 'View Royal',
                    'WRF'  => 'Warfield',
                    'WE'   => 'Wells',
                    'WK'   => 'West Kelowna',
                    'WV'   => 'West Vancouver',
                    'WH'   => 'Whistler',
                    'WRK'  => 'White Rock',
                    'WL'   => 'Williams Lake',
                    'ZL'   => 'Zeballos',
                ),
                'caseFieldName' => 'Officio_BusMunicipality',
            ),
            'syncA_App_Bus_franchise'     => array(
                'newValue'      => array(
                    'No',
                    'Yes'
                ),
                'caseFieldName' => 'Officio_ei_app_bus_is_franchise',
            ),
            'syncA_App_Bus_farm'          => array(
                'newValue'      => array(
                    'No',
                    'Yes'
                ),
                'caseFieldName' => 'Officio_ei_app_bus_is_farm',
            ),
            'syncA_App_Bus_local_partner' => array(
                'newValue'      => array(
                    'No',
                    'Yes'
                ),
                'caseFieldName' => 'Officio_ei_app_bus_is_local_partner',
            ),
            'syncA_App_Bus_co_partner'    => array(
                'newValue'      => array(
                    'No',
                    'Yes'
                ),
                'caseFieldName' => 'Officio_ei_app_bus_is_co_pnp_partner',
            ),
            'syncA_App_Bus_key_staff'     => array(
                'newValue'      => array(
                    'No',
                    'Yes'
                ),
                'caseFieldName' => 'Officio_ei_app_bus_key_staff',
            )
        );
        foreach ($clients as $client) {
            list($result, $strError) = $bcpnp->changeCaseJsonFields($client, $conversions);
            if (!$result) {
                $output->writeln('<error>Client ' . $client['fileNumber'] . ' could not be converted. Reason: ' . $strError . '.</error>');
            } else {
                $output->writeln('Client ' . $client['fileNumber'] . ' successfully converted.');
            }
        }
    }

    public function down()
    {
    }

}
