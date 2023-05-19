<?php

use Officio\Migration\AbstractMigration;

class AddCountriesCodes extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `country_master` SET `immi_code_3`='AFG', `immi_code_4`='AFGH', `immi_code_num`='2' WHERE  `countries_id`=1;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ALB', `immi_code_4`='ALBA', `immi_code_num`='3' WHERE  `countries_id`=2;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='DZA', `immi_code_4`='ALGE', `immi_code_num`='4' WHERE  `countries_id`=3;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ASM', `immi_code_4`='ASAM', `immi_code_num`='5' WHERE  `countries_id`=4;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='AND', `immi_code_4`='ANDO', `immi_code_num`='6' WHERE  `countries_id`=5;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='AGO', `immi_code_4`='ANGA', `immi_code_num`='7' WHERE  `countries_id`=6;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='AIA', `immi_code_4`='ANGU', `immi_code_num`='8' WHERE  `countries_id`=7;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ATG', `immi_code_4`='ANBA', `immi_code_num`='9' WHERE  `countries_id`=9;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ARG', `immi_code_4`='ARGE', `immi_code_num`='10' WHERE  `countries_id`=10;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ARM', `immi_code_4`='ARME', `immi_code_num`='11' WHERE  `countries_id`=11;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ABW', `immi_code_4`='ARUB', `immi_code_num`='12' WHERE  `countries_id`=12;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='AUS', `immi_code_4`='A', `immi_code_num`='13' WHERE  `countries_id`=13;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='AUT', `immi_code_4`='AUST', `immi_code_num`='14' WHERE  `countries_id`=14;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='AZE', `immi_code_4`='AZER', `immi_code_num`='15' WHERE  `countries_id`=15;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BHS', `immi_code_4`='BHMS', `immi_code_num`='16' WHERE  `countries_id`=16;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BHR', `immi_code_4`='BAHR', `immi_code_num`='17' WHERE  `countries_id`=17;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BGD', `immi_code_4`='BDES', `immi_code_num`='18' WHERE  `countries_id`=18;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BRB', `immi_code_4`='BARS', `immi_code_num`='19' WHERE  `countries_id`=19;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BLR', `immi_code_4`='BYEL', `immi_code_num`='20' WHERE  `countries_id`=20;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BEL', `immi_code_4`='BELM', `immi_code_num`='21' WHERE  `countries_id`=21;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BLZ', `immi_code_4`='BELZ', `immi_code_num`='22' WHERE  `countries_id`=22;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BEN', `immi_code_4`='BENI', `immi_code_num`='23' WHERE  `countries_id`=23;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BMU', `immi_code_4`='BERM', `immi_code_num`='24' WHERE  `countries_id`=24;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BTN', `immi_code_4`='BHUT', `immi_code_num`='25' WHERE  `countries_id`=25;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BOL', `immi_code_4`='BOLI', `immi_code_num`='26' WHERE  `countries_id`=26;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='BES', `immi_code_4`='BSES', `immi_code_num`='27' WHERE  `countries_id`=243;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BIH', `immi_code_4`='BOHE', `immi_code_num`='28' WHERE  `countries_id`=27;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BWA', `immi_code_4`='BOTS', `immi_code_num`='29' WHERE  `countries_id`=28;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='BVTI', `immi_code_num`='30' WHERE  `countries_id`=29;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BRA', `immi_code_4`='BRAZ', `immi_code_num`='31' WHERE  `countries_id`=30;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='BIOT', `immi_code_num`='32' WHERE  `countries_id`=31;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='GBD', `immi_code_4`='BOTC', `immi_code_num`='33' WHERE  `countries_id`=244;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='BDTC' WHERE  `countries_id`=263;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='BWIN' WHERE  `countries_id`=264;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BRN', `immi_code_4`='BRUI', `immi_code_num`='34' WHERE  `countries_id`=32;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BGR', `immi_code_4`='BULG', `immi_code_num`='35' WHERE  `countries_id`=33;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BFA', `immi_code_4`='UVOL', `immi_code_num`='36' WHERE  `countries_id`=34;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='MMR' WHERE  `countries_id`=265;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='BDI', `immi_code_4`='BURU', `immi_code_num`='37' WHERE  `countries_id`=35;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='CPV', `immi_code_4`='CABV', `immi_code_num`='38' WHERE  `countries_id`=245;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KHM', `immi_code_4`='CAMB', `immi_code_num`='39' WHERE  `countries_id`=36;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CMR', `immi_code_4`='CREP', `immi_code_num`='40' WHERE  `countries_id`=37;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CAN', `immi_code_4`='CANA', `immi_code_num`='41' WHERE  `countries_id`=38;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='CVER', `immi_code_num`='42' WHERE  `countries_id`=39;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CYM', `immi_code_4`='CAIS', `immi_code_num`='43' WHERE  `countries_id`=40;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CAF', `immi_code_4`='CARE', `immi_code_num`='44' WHERE  `countries_id`=41;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TCD', `immi_code_4`='CHAD', `immi_code_num`='45' WHERE  `countries_id`=42;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CHL', `immi_code_4`='CHIL', `immi_code_num`='46' WHERE  `countries_id`=43;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CHN', `immi_code_4`='PRCH', `immi_code_num`='47' WHERE  `countries_id`=44;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='CLIS' WHERE  `countries_id`=266;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='CSIS', `immi_code_num`='48' WHERE  `countries_id`=45;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='COCO', `immi_code_num`='49' WHERE  `countries_id`=46;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='COL', `immi_code_4`='COLA', `immi_code_num`='50' WHERE  `countries_id`=47;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='COM', `immi_code_4`='COMO', `immi_code_num`='51' WHERE  `countries_id`=48;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='COG', `immi_code_4`='CONG', `immi_code_num`='52' WHERE  `countries_id`=49;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='COD' WHERE  `countries_id`=241;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='COK', `immi_code_4`='COOI', `immi_code_num`='53' WHERE  `countries_id`=50;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CRI', `immi_code_4`='CRIC', `immi_code_num`='54' WHERE  `countries_id`=51;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CIV', `immi_code_4`='ICOA', `immi_code_num`='55' WHERE  `countries_id`=52;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='HRV', `immi_code_4`='CROA', `immi_code_num`='56' WHERE  `countries_id`=53;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CUB', `immi_code_4`='CUBA', `immi_code_num`='57' WHERE  `countries_id`=54;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='CUW', `immi_code_4`='CURA', `immi_code_num`='58' WHERE  `countries_id`=246;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CYP', `immi_code_4`='CYPR', `immi_code_num`='59' WHERE  `countries_id`=55;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CZE', `immi_code_4`='CZER', `immi_code_num`='60' WHERE  `countries_id`=56;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='CZEC' WHERE  `countries_id`=267;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='DNK', `immi_code_4`='DENM', `immi_code_num`='61' WHERE  `countries_id`=57;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='DJI', `immi_code_4`='JIBU', `immi_code_num`='62' WHERE  `countries_id`=58;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='DMA', `immi_code_4`='DOMI', `immi_code_num`='63' WHERE  `countries_id`=59;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='DOM', `immi_code_4`='DREP', `immi_code_num`='64' WHERE  `countries_id`=60;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TMP', `immi_code_4`='ETIM', `immi_code_num`='228' WHERE  `countries_id`=61;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ECU', `immi_code_4`='ECUA', `immi_code_num`='65' WHERE  `countries_id`=62;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='EGY', `immi_code_4`='EGYP', `immi_code_num`='66' WHERE  `countries_id`=63;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SLV', `immi_code_4`='ESAL', `immi_code_num`='67' WHERE  `countries_id`=64;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GNQ', `immi_code_4`='EQGN', `immi_code_num`='69' WHERE  `countries_id`=65;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ERI', `immi_code_4`='ERIT', `immi_code_num`='70' WHERE  `countries_id`=66;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='ENGL', `immi_code_num`='68' WHERE  `countries_id`=247;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='EST', `immi_code_4`='ESTO', `immi_code_num`='71' WHERE  `countries_id`=67;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ETH', `immi_code_4`='ETHI', `immi_code_num`='72' WHERE  `countries_id`=68;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='FLK', `immi_code_4`='FALI', `immi_code_num`='73' WHERE  `countries_id`=69;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='FAIS', `immi_code_num`='74' WHERE  `countries_id`=70;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='FJI', `immi_code_4`='FIJI', `immi_code_num`='75' WHERE  `countries_id`=71;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='FIN', `immi_code_4`='FINL', `immi_code_num`='76' WHERE  `countries_id`=72;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='FRA', `immi_code_4`='FRAE', `immi_code_num`='77' WHERE  `countries_id`=73;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GUF', `immi_code_4`='GUYE', `immi_code_num`='78' WHERE  `countries_id`=75;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PYF', `immi_code_4`='FPOL', `immi_code_num`='79' WHERE  `countries_id`=76;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MKD', `immi_code_4`='MKDA', `immi_code_num`='80' WHERE  `countries_id`=126;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GAB', `immi_code_4`='GABO', `immi_code_num`='81' WHERE  `countries_id`=78;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GMB', `immi_code_4`='GAMB', `immi_code_num`='82' WHERE  `countries_id`=79;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='GAZA', `immi_code_num`='83' WHERE  `countries_id`=248;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GEO', `immi_code_4`='GEOG', `immi_code_num`='84' WHERE  `countries_id`=80;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='D', `immi_code_4`='GERM', `immi_code_num`='86' WHERE  `countries_id`=81;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GHA', `immi_code_4`='GHAN', `immi_code_num`='87' WHERE  `countries_id`=82;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GIB', `immi_code_4`='GIBR', `immi_code_num`='88' WHERE  `countries_id`=83;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GRL', `immi_code_4`='GRED', `immi_code_num`='90' WHERE  `countries_id`=85;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GRD', `immi_code_4`='GREN', `immi_code_num`='91' WHERE  `countries_id`=86;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GLP', `immi_code_4`='GUAE', `immi_code_num`='92' WHERE  `countries_id`=87;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GUM', `immi_code_4`='GUAM', `immi_code_num`='93' WHERE  `countries_id`=88;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GTM', `immi_code_4`='GUAT', `immi_code_num`='94' WHERE  `countries_id`=89;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='GUER', `immi_code_num`='95' WHERE  `countries_id`=249;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GIN', `immi_code_4`='GUIN', `immi_code_num`='96' WHERE  `countries_id`=90;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GNB', `immi_code_4`='GUBS', `immi_code_num`='97' WHERE  `countries_id`=91;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GUY', `immi_code_4`='GUYN', `immi_code_num`='98' WHERE  `countries_id`=92;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='HTI', `immi_code_4`='HAIT', `immi_code_num`='99' WHERE  `countries_id`=93;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='HMDI', `immi_code_num`='100' WHERE  `countries_id`=94;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='HND', `immi_code_4`='HOND', `immi_code_num`='101' WHERE  `countries_id`=95;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='HUN', `immi_code_4`='HUNG', `immi_code_num`='103' WHERE  `countries_id`=97;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='HKG', `immi_code_4`='HKON', `immi_code_num`='102' WHERE  `countries_id`=96;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ISL', `immi_code_4`='ICEL', `immi_code_num`='104' WHERE  `countries_id`=98;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='IND', `immi_code_4`='INDI', `immi_code_num`='105' WHERE  `countries_id`=99;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='IDN', `immi_code_4`='INDO', `immi_code_num`='106' WHERE  `countries_id`=100;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='IRN', `immi_code_4`='IRAN', `immi_code_num`='107' WHERE  `countries_id`=101;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='IRQ', `immi_code_4`='IRAQ', `immi_code_num`='108' WHERE  `countries_id`=102;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='IRL', `immi_code_4`='IREP', `immi_code_num`='109' WHERE  `countries_id`=103;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='IMAN', `immi_code_num`='110' WHERE  `countries_id`=250;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ISR', `immi_code_4`='ISRA', `immi_code_num`='111' WHERE  `countries_id`=104;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ITA', `immi_code_4`='ITAL', `immi_code_num`='112' WHERE  `countries_id`=105;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='JAM', `immi_code_4`='JAMA', `immi_code_num`='113' WHERE  `countries_id`=106;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='JPN', `immi_code_4`='JAPA', `immi_code_num`='114' WHERE  `countries_id`=107;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='JERS', `immi_code_num`='115' WHERE  `countries_id`=268;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='JOR', `immi_code_4`='JORD', `immi_code_num`='116' WHERE  `countries_id`=108;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KAZ', `immi_code_4`='KAZA', `immi_code_num`='117' WHERE  `countries_id`=109;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KEN', `immi_code_4`='KENY', `immi_code_num`='118' WHERE  `countries_id`=110;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KIR', `immi_code_4`='GIIS', `immi_code_num`='119' WHERE  `countries_id`=111;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PRK ', `immi_code_4`='NKOR', `immi_code_num`='120' WHERE  `countries_id`=112;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KOR', `immi_code_4`='SKOR', `immi_code_num`='121' WHERE  `countries_id`=113;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='RKS', `immi_code_4`='KOSO', `immi_code_num`='122' WHERE  `countries_id`=251;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KWT', `immi_code_4`='KUWA', `immi_code_num`='123' WHERE  `countries_id`=114;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KGZ', `immi_code_4`='KIRG', `immi_code_num`='124' WHERE  `countries_id`=115;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LAO', `immi_code_4`='LAOS', `immi_code_num`='125' WHERE  `countries_id`=116;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LVA', `immi_code_4`='LATV', `immi_code_num`='126' WHERE  `countries_id`=117;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LBN', `immi_code_4`='LEBA', `immi_code_num`='127' WHERE  `countries_id`=118;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LSO', `immi_code_4`='LESO', `immi_code_num`='128' WHERE  `countries_id`=119;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LBR', `immi_code_4`='LIBE', `immi_code_num`='129' WHERE  `countries_id`=120;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LBY', `immi_code_4`='LIBY', `immi_code_num`='130' WHERE  `countries_id`=121;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LIE', `immi_code_4`='LIEC', `immi_code_num`='131' WHERE  `countries_id`=122;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LUX', `immi_code_4`='LUXE', `immi_code_num`='133' WHERE  `countries_id`=124;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MAC', `immi_code_4`='MACS', `immi_code_num`='134' WHERE  `countries_id`=125;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MDG', `immi_code_4`='MADA', `immi_code_num`='135' WHERE  `countries_id`=127;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MWI', `immi_code_4`='MALW', `immi_code_num`='136' WHERE  `countries_id`=128;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MYS', `immi_code_4`='MALS', `immi_code_num`='137' WHERE  `countries_id`=129;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MDV', `immi_code_4`='MIS', `immi_code_num`='138' WHERE  `countries_id`=130;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MLI', `immi_code_4`='M', `immi_code_num`='139' WHERE  `countries_id`=131;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MLT', `immi_code_4`='MALT', `immi_code_num`='140' WHERE  `countries_id`=132;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MHL', `immi_code_4`='MAIS', `immi_code_num`='141' WHERE  `countries_id`=133;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MTQ', `immi_code_4`='MART', `immi_code_num`='142' WHERE  `countries_id`=134;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MRT', `immi_code_4`='MAUA', `immi_code_num`='143' WHERE  `countries_id`=135;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MUS', `immi_code_4`='MAUS', `immi_code_num`='144' WHERE  `countries_id`=136;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='MAYO', `immi_code_num`='145' WHERE  `countries_id`=137;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MEX', `immi_code_4`='MEXI', `immi_code_num`='146' WHERE  `countries_id`=138;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='FSM', `immi_code_4`='MICS', `immi_code_num`='147' WHERE  `countries_id`=139;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MDA', `immi_code_4`='MOLD', `immi_code_num`='148' WHERE  `countries_id`=140;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MCO', `immi_code_4`='MONA', `immi_code_num`='149' WHERE  `countries_id`=141;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MNG', `immi_code_4`='MONG', `immi_code_num`='150' WHERE  `countries_id`=142;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='MNE', `immi_code_4`='MNTE', `immi_code_num`='151' WHERE  `countries_id`=252;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MSR', `immi_code_4`='MONT', `immi_code_num`='152' WHERE  `countries_id`=143;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MAR', `immi_code_4`='MORO', `immi_code_num`='153' WHERE  `countries_id`=144;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='MOZ', `immi_code_4`='MOZA', `immi_code_num`='154' WHERE  `countries_id`=145;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='MYAN', `immi_code_num`='155' WHERE  `countries_id`=146;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NAM', `immi_code_4`='NAMI', `immi_code_num`='156' WHERE  `countries_id`=147;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NRU', `immi_code_4`='NAUR', `immi_code_num`='157' WHERE  `countries_id`=148;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NPL', `immi_code_4`='NEPA', `immi_code_num`='158' WHERE  `countries_id`=149;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NLD', `immi_code_4`='NETH', `immi_code_num`='159' WHERE  `countries_id`=150;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ANT', `immi_code_4`='NANT' WHERE  `countries_id`=151;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NCL', `immi_code_4`='NCAL', `immi_code_num`='160' WHERE  `countries_id`=152;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='NEWG', `immi_code_num`='161' WHERE  `countries_id`=253;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='NHEB', `immi_code_num`='162' WHERE  `countries_id`=254;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NZL', `immi_code_4`='NZEA', `immi_code_num`='163' WHERE  `countries_id`=153;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NIC', `immi_code_4`='NICA', `immi_code_num`='164' WHERE  `countries_id`=154;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NER', `immi_code_4`='NIGR', `immi_code_num`='165' WHERE  `countries_id`=155;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NGA', `immi_code_4`='NGRA', `immi_code_num`='166' WHERE  `countries_id`=156;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NIU', `immi_code_4`='NIUE', `immi_code_num`='167' WHERE  `countries_id`=157;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='NOIS', `immi_code_num`='168' WHERE  `countries_id`=158;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='NOIR', `immi_code_num`='169' WHERE  `countries_id`=269;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='NMAI', `immi_code_num`='170' WHERE  `countries_id`=159;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='NOR', `immi_code_4`='NORW', `immi_code_num`='171' WHERE  `countries_id`=160;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='OMN', `immi_code_4`='OMAN', `immi_code_num`='172' WHERE  `countries_id`=161;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PAK', `immi_code_4`='PKSN', `immi_code_num`='173' WHERE  `countries_id`=162;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PLW', `immi_code_4`='PALA', `immi_code_num`='174' WHERE  `countries_id`=163;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PSE', `immi_code_4`='PALE', `immi_code_num`='175' WHERE  `countries_id`=240;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PAN', `immi_code_4`='PANA', `immi_code_num`='176' WHERE  `countries_id`=164;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PNG', `immi_code_4`='NGUI', `immi_code_num`='177' WHERE  `countries_id`=165;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PRY', `immi_code_4`='PARA', `immi_code_num`='178' WHERE  `countries_id`=166;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PER', `immi_code_4`='PERU', `immi_code_num`='179' WHERE  `countries_id`=167;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PHL', `immi_code_4`='PHIL', `immi_code_num`='180' WHERE  `countries_id`=168;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PCN', `immi_code_4`='PIIS', `immi_code_num`='181' WHERE  `countries_id`=169;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='PLIS' WHERE  `countries_id`=270;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='POL', `immi_code_4`='POLA', `immi_code_num`='182' WHERE  `countries_id`=170;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PRT', `immi_code_4`='PORL', `immi_code_num`='183' WHERE  `countries_id`=171;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='PRI', `immi_code_4`='PRIC', `immi_code_num`='184' WHERE  `countries_id`=172;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='QAT', `immi_code_4`='QATA', `immi_code_num`='185' WHERE  `countries_id`=173;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='REU', `immi_code_4`='REIS', `immi_code_num`='186' WHERE  `countries_id`=174;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ROU', `immi_code_4`='ROUM', `immi_code_num`='187' WHERE  `countries_id`=175;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='RUS', `immi_code_4`='RFED', `immi_code_num`='188' WHERE  `countries_id`=176;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='RWA', `immi_code_4`='RWAN', `immi_code_num`='189' WHERE  `countries_id`=177;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='STBA', `immi_code_num`='190' WHERE  `countries_id`=255;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='SHN', `immi_code_4`='SHTC', `immi_code_num`='191' WHERE  `countries_id`=271;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='KNA', `immi_code_4`='SCKN', `immi_code_num`='214' WHERE  `countries_id`=178;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='LCA', `immi_code_4`='STLU', `immi_code_num`='192' WHERE  `countries_id`=179;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='VCT', `immi_code_4`='STVG', `immi_code_num`='216' WHERE  `countries_id`=180;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='STMA', `immi_code_num`='193' WHERE  `countries_id`=256;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='WSM', `immi_code_4`='SAMO', `immi_code_num`='194' WHERE  `countries_id`=181;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SMR', `immi_code_4`='SMAR', `immi_code_num`='195' WHERE  `countries_id`=182;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='STP', `immi_code_4`='STPR', `immi_code_num`='196' WHERE  `countries_id`=183;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SAU', `immi_code_4`='SAAR', `immi_code_num`='197' WHERE  `countries_id`=184;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='SCOT', `immi_code_num`='198' WHERE  `countries_id`=257;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SEN', `immi_code_4`='SENE', `immi_code_num`='199' WHERE  `countries_id`=185;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='SRB', `immi_code_4`='SERB', `immi_code_num`='200' WHERE  `countries_id`=258;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SYC', `immi_code_4`='SEYC', `immi_code_num`='201' WHERE  `countries_id`=186;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SLE', `immi_code_4`='SLEO', `immi_code_num`='202' WHERE  `countries_id`=187;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SGP', `immi_code_4`='SING', `immi_code_num`='203' WHERE  `countries_id`=188;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='SXM', `immi_code_4`='SXMN', `immi_code_num`='204' WHERE  `countries_id`=259;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SVK', `immi_code_4`='SVKA', `immi_code_num`='205' WHERE  `countries_id`=189;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SVN', `immi_code_4`='SLOV', `immi_code_num`='206' WHERE  `countries_id`=190;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SLB', `immi_code_4`='SOLI', `immi_code_num`='207' WHERE  `countries_id`=191;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SOM', `immi_code_4`='SOMA', `immi_code_num`='208' WHERE  `countries_id`=192;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ZAF', `immi_code_4`='SAFR', `immi_code_num`='209' WHERE  `countries_id`=193;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SSD', `immi_code_4`='SSDN', `immi_code_num`='210' WHERE  `countries_id`=242;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ESP', `immi_code_4`='SPAI', `immi_code_num`='211' WHERE  `countries_id`=195;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='SHEL', `immi_code_num`='213' WHERE  `countries_id`=197;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='STPM', `immi_code_num`='215' WHERE  `countries_id`=198;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SDN', `immi_code_4`='SUDA', `immi_code_num`='217' WHERE  `countries_id`=199;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SUR', `immi_code_4`='SURI', `immi_code_num`='218' WHERE  `countries_id`=200;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='SAJM', `immi_code_num`='219' WHERE  `countries_id`=201;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SWZ', `immi_code_4`='SWAZ', `immi_code_num`='220' WHERE  `countries_id`=202;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SWE', `immi_code_4`='SWED', `immi_code_num`='221' WHERE  `countries_id`=203;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='CHE', `immi_code_4`='SWIT', `immi_code_num`='222' WHERE  `countries_id`=204;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='SYR', `immi_code_4`='SYRI', `immi_code_num`='223' WHERE  `countries_id`=205;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='TAHI' WHERE  `countries_id`=272;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TWN', `immi_code_4`='TAIW', `immi_code_num`='224' WHERE  `countries_id`=206;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TJK', `immi_code_4`='TADZ', `immi_code_num`='225' WHERE  `countries_id`=207;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TZA', `immi_code_4`='TANZ', `immi_code_num`='226' WHERE  `countries_id`=208;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='THA', `immi_code_4`='THAI', `immi_code_num`='227' WHERE  `countries_id`=209;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='TIBE' WHERE  `countries_id`=273;");
        //$this->execute("UPDATE `country_master` SET `immi_code_3`='TLS', `immi_code_4`='TILE', `immi_code_num`='229' WHERE  `countries_id`=260;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TGO', `immi_code_4`='TOGO', `immi_code_num`='230' WHERE  `countries_id`=210;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TKL', `immi_code_4`='TOKE', `immi_code_num`='231' WHERE  `countries_id`=211;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TON', `immi_code_4`='TONG', `immi_code_num`='232' WHERE  `countries_id`=212;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TTO', `immi_code_4`='TRIN', `immi_code_num`='233' WHERE  `countries_id`=213;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TUN', `immi_code_4`='TUNI', `immi_code_num`='234' WHERE  `countries_id`=214;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TUR', `immi_code_4`='TURY', `immi_code_num`='235' WHERE  `countries_id`=215;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TKM', `immi_code_4`='TURM', `immi_code_num`='236' WHERE  `countries_id`=216;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TCA', `immi_code_4`='TCIS', `immi_code_num`='237' WHERE  `countries_id`=217;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='TUV', `immi_code_4`='TUVU', `immi_code_num`='238' WHERE  `countries_id`=218;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='USOI', `immi_code_num`='239' WHERE  `countries_id`=224;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='UGA', `immi_code_4`='UGAN', `immi_code_num`='240' WHERE  `countries_id`=219;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='UKR', `immi_code_4`='UKRA', `immi_code_num`='241' WHERE  `countries_id`=220;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ARE', `immi_code_4`='UAEM', `immi_code_num`='242' WHERE  `countries_id`=221;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='UK', `immi_code_num`='243' WHERE  `countries_id`=222;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='USA', `immi_code_4`='USA', `immi_code_num`='244' WHERE  `countries_id`=223;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='URY', `immi_code_4`='URUG', `immi_code_num`='245' WHERE  `countries_id`=225;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='UZB', `immi_code_4`='UZBE', `immi_code_num`='246' WHERE  `countries_id`=226;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='VUT', `immi_code_4`='VANU', `immi_code_num`='247' WHERE  `countries_id`=227;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='VAT', `immi_code_4`='VCIT', `immi_code_num`='248' WHERE  `countries_id`=228;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='VEN', `immi_code_4`='VENE', `immi_code_num`='249' WHERE  `countries_id`=229;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='VNM', `immi_code_4`='VIET', `immi_code_num`='250' WHERE  `countries_id`=230;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='VIIS', `immi_code_num`='251' WHERE  `countries_id`=232;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='VGB', `immi_code_4`='VIUK', `immi_code_num`='252' WHERE  `countries_id`=231;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='WALE', `immi_code_num`='253' WHERE  `countries_id`=261;");
        $this->execute("UPDATE `country_master` SET `immi_code_4`='WAFU', `immi_code_num`='254' WHERE  `countries_id`=233;");
        //$this->execute("UPDATE `country_master` SET `immi_code_4`='WBAN', `immi_code_num`='255' WHERE  `countries_id`=262;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='YEM', `immi_code_4`='YEME', `immi_code_num`='256' WHERE  `countries_id`=235;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='YUG', `immi_code_4`='FRY', `immi_code_num`='257' WHERE  `countries_id`=236;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ZAR', `immi_code_4`='ZAIR', `immi_code_num`='258' WHERE  `countries_id`=237;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ZMB', `immi_code_4`='ZAMB', `immi_code_num`='259' WHERE  `countries_id`=238;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='ZWE', `immi_code_4`='ZIMB', `immi_code_num`='260' WHERE  `countries_id`=239;");

        $this->execute("UPDATE `country_master` SET `immi_code_3`='LTU', `immi_code_4`='LITH', `immi_code_num`='132' WHERE  `countries_id`=123;");
        $this->execute("UPDATE `country_master` SET `immi_code_3`='GRC', `immi_code_4`='GREE', `immi_code_num`='89' WHERE  `countries_id`=84;");

    }

    public function down()
    {
        $this->execute("UPDATE `country_master` SET `immi_code_3` = Null;");
        $this->execute("UPDATE `country_master` SET `immi_code_4` = Null;");
        $this->execute("UPDATE `country_master` SET `immi_code_num` = Null;");


    }
}






























































