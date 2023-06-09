<?php

use Phinx\Migration\AbstractMigration;

class UpdateAdvisors extends AbstractMigration
{
    public function up()
    {
        $output = $this->getOutput();

        $this->execute(
            "
            CREATE TABLE update_advisors_tmp (
              fileNumber VARCHAR(255),
              fName VARCHAR(255),
              lName VARCHAR(255)
            );

            INSERT INTO update_advisors_tmp VALUES
            ('BCS-15-00007','Leanna','Krause'),
            ('BCS-15-00012','Samuel','Lee'),
            ('BCS-15-00013','Marcela','Lenger'),
            ('BCS-15-00019','Samuel','Lee'),
            ('BCS-15-00020','Leanna','Krause'),
            ('BCS-15-00024','Marcela','Lenger'),
            ('BCS-15-00025','Leanna','Krause'),
            ('BCS-15-00030','Leanna','Krause'),
            ('BCS-15-00033','Leanna','Krause'),
            ('BCS-15-00036','Marcela','Lenger'),
            ('BCS-15-00037','Leanna','Krause'),
            ('BCS-15-00053','Sue','Gill'),
            ('BCS-15-00061','Marcela','Lenger'),
            ('BCS-15-00065','Marcela','Lenger'),
            ('BCS-15-00069','Leanna','Krause'),
            ('BCS-15-00070','Marcela','Lenger'),
            ('BCS-15-00081','Marcela','Lenger'),
            ('BCS-15-00087','Samuel','Lee'),
            ('BCS-15-00089','Marcela','Lenger'),
            ('BCS-15-00091','Marcela','Lenger'),
            ('BCS-15-00094','Marcela','Lenger'),
            ('BCS-15-00101','Daniela','Al-Kuwatli'),
            ('BCS-15-00105','Leanna','Krause'),
            ('BCS-15-00112','Marcela','Lenger'),
            ('BCS-15-00113','Marcela','Lenger'),
            ('BCS-15-00114','Daniela','Al-Kuwatli'),
            ('BCS-15-00119','Richard','Klassen'),
            ('BCS-15-00127','Marcela','Lenger'),
            ('BCS-15-00128','Pamela','Radcliffe'),
            ('BCS-15-00130','Richard','Klassen'),
            ('BCS-15-00131','Marcela','Lenger'),
            ('BCS-15-00132','Athena','Adan'),
            ('BCS-15-00133','Marcela','Lenger'),
            ('BCS-15-00134','Samuel','Lee'),
            ('BCS-15-00135','Richard','Klassen'),
            ('BCS-15-00137','Marcela','Lenger'),
            ('BCS-15-00138','Richard','Klassen'),
            ('BCS-15-00139','Richard','Klassen'),
            ('BCS-15-00142','Marcela','Lenger'),
            ('BCS-15-00144','Leanna','Krause'),
            ('BCS-15-00145','Richard','Klassen'),
            ('BCS-15-00152','Samuel','Lee'),
            ('BCS-15-00155','Marcela','Lenger'),
            ('BCS-15-00157','Marcela','Lenger'),
            ('BCS-15-00160','Marcela','Lenger'),
            ('BCS-15-00167','Marcela','Lenger'),
            ('BCS-15-00169','Richard','Klassen'),
            ('BCS-15-00170','Samuel','Lee'),
            ('BCS-15-00175','Richard','Klassen'),
            ('BCS-15-00179','Leanna','Krause'),
            ('BCS-15-00183','Richard','Klassen'),
            ('BCS-15-00188','Richard','Klassen'),
            ('BCS-15-00194','Leanna','Krause'),
            ('BCS-15-00199','Richard','Klassen'),
            ('BCS-15-00200','Athena','Adan'),
            ('BCS-15-00202','Daniela','Al-Kuwatli'),
            ('BCS-15-00203','Pamela','Radcliffe'),
            ('BCS-15-00206','Daniela','Al-Kuwatli'),
            ('BCS-15-00215','Daniela','Al-Kuwatli'),
            ('BCS-15-00222','Richard','Klassen'),
            ('BCS-15-00223','Pamela','Radcliffe'),
            ('BCS-15-00228','Leanna','Krause'),
            ('BCS-15-00233','Richard','Klassen'),
            ('BCS-15-00237','Richard','Klassen'),
            ('BCS-15-00239','Richard','Klassen'),
            ('BCS-15-00240','Richard','Klassen'),
            ('BCS-15-00254','Richard','Klassen'),
            ('BCS-15-00255','Athena','Adan'),
            ('BCS-15-00256','Leanna','Krause'),
            ('BCS-15-00262','Pamela','Radcliffe'),
            ('BCS-15-00269','Sue','Gill'),
            ('BCS-15-00271','Daniela','Al-Kuwatli'),
            ('BCS-15-00273','Pamela','Radcliffe'),
            ('BCS-15-00277','Sue','Gill'),
            ('BCS-15-00278','Sue','Gill'),
            ('BCS-15-00280','Sue','Gill'),
            ('BCS-15-00283','Leanna','Krause'),
            ('BCS-15-00289','Derek','So'),
            ('BCS-15-00290','Leanna','Krause'),
            ('BCS-15-00292','Athena','Adan'),
            ('BCS-15-00300','Pamela','Radcliffe'),
            ('BCS-15-00303','Derek','So'),
            ('BCS-15-00306','Derek','So'),
            ('BCS-15-00307','Richard','Klassen'),
            ('BCS-15-00308','Athena','Adan'),
            ('BCS-15-00310','Derek','So'),
            ('BCS-15-00312','Richard','Klassen'),
            ('BCS-15-00313','Derek','So'),
            ('BCS-15-00315','Athena','Adan'),
            ('BCS-15-00316','Daniela','Al-Kuwatli'),
            ('BCS-15-00317','Richard','Klassen'),
            ('BCS-15-00318','Derek','So'),
            ('BCS-15-00320','Pamela','Radcliffe'),
            ('BCS-15-00321','Daniela','Al-Kuwatli'),
            ('BCS-15-00322','Richard','Klassen'),
            ('BCS-15-00327','Athena','Adan'),
            ('BCS-15-00330','Athena','Adan'),
            ('BCS-15-00333','Daniela','Al-Kuwatli'),
            ('BCS-15-00334','Pamela','Radcliffe'),
            ('BCS-15-00335','Richard','Klassen'),
            ('BCS-15-00336','Pamela','Radcliffe'),
            ('BCS-15-00339','Athena','Adan'),
            ('BCS-15-00342','Richard','Klassen'),
            ('BCS-15-00345','Athena','Adan'),
            ('BCS-15-00346','Pamela','Radcliffe'),
            ('BCS-15-00349','Richard','Klassen'),
            ('BCS-15-00354','Pamela','Radcliffe'),
            ('BCS-15-00355','Pamela','Radcliffe'),
            ('BCS-15-00359','Pamela','Radcliffe'),
            ('BCS-15-00364','Richard','Klassen'),
            ('BCS-15-00365','Leanna','Krause'),
            ('BCS-15-00367','Pamela','Radcliffe'),
            ('BCS-15-00368','Leanna','Krause'),
            ('BCS-15-00369','Leanna','Krause'),
            ('BCS-15-00374','Leanna','Krause'),
            ('BCS-15-00376','Leanna','Krause'),
            ('BCS-15-00379','Leanna','Krause'),
            ('BCS-15-00380','Leanna','Krause'),
            ('BCS-15-00382','Leanna','Krause'),
            ('BCS-15-00385','Leanna','Krause'),
            ('BCS-15-00387','Leanna','Krause'),
            ('BCS-15-00390','Leanna','Krause'),
            ('BCS-15-00391','Samuel','Lee'),
            ('BCS-15-00394','Derek','So'),
            ('BCS-15-00395','Derek','So'),
            ('BCS-15-00396','Pamela','Radcliffe'),
            ('BCS-15-00402','Mindy','Nannar'),
            ('BCS-15-00403','Athena','Adan'),
            ('BCS-15-00404','Pamela','Radcliffe'),
            ('BCS-15-00407','Patricio','Ibarra'),
            ('BCS-15-00413','Athena','Adan'),
            ('BCS-15-00414','Pamela','Radcliffe'),
            ('BCS-15-00416','Richard','Klassen'),
            ('BCS-15-00417','Sue','Gill'),
            ('BCS-15-00419','Richard','Klassen'),
            ('BCS-15-00420','Richard','Klassen'),
            ('BCS-15-00422','Pamela','Radcliffe'),
            ('BCS-15-00423','Richard','Klassen'),
            ('BCS-15-00424','Richard','Klassen'),
            ('BCS-15-00425','Richard','Klassen'),
            ('BCS-15-00428','Richard','Klassen'),
            ('BCS-15-00429','Richard','Klassen'),
            ('BCS-15-00430','Richard','Klassen'),
            ('BCS-15-00431','Pamela','Radcliffe'),
            ('BCS-15-00433','Richard','Klassen'),
            ('BCS-15-00434','Richard','Klassen'),
            ('BCS-15-00436','Richard','Klassen'),
            ('BCS-15-00437','Richard','Klassen'),
            ('BCS-15-00442','Richard','Klassen'),
            ('BCS-15-00443','Pamela','Radcliffe'),
            ('BCS-15-00446','Marcela','Lenger'),
            ('BCS-15-00447','Derek','So'),
            ('BCS-15-00450','Derek','So'),
            ('BCS-15-00451','Derek','So'),
            ('BCS-15-00452','Derek','So'),
            ('BCS-15-00454','Derek','So'),
            ('BCS-15-00455','Daniela','Al-Kuwatli'),
            ('BCS-15-00458','Derek','So'),
            ('BCS-15-00459','Derek','So'),
            ('BCS-15-00460','Daniela','Al-Kuwatli'),
            ('BCS-15-00462','Derek','So'),
            ('BCS-15-00464','Mindy','Nannar'),
            ('BCS-15-00466','Mindy','Nannar'),
            ('BCS-15-00468','Richard','Klassen'),
            ('BCS-15-00469','Mindy','Nannar'),
            ('BCS-15-00470','Mindy','Nannar'),
            ('BCS-15-00471','Daniela','Al-Kuwatli'),
            ('BCS-15-00472','Daniela','Al-Kuwatli'),
            ('BCS-15-00478','Richard','Klassen'),
            ('BCS-15-00480','Richard','Klassen'),
            ('BCS-15-00482','Mindy','Nannar'),
            ('BCS-15-00483','Daniela','Al-Kuwatli'),
            ('BCS-15-00485','Derek','So'),
            ('BCS-15-00487','Athena','Adan'),
            ('BCS-15-00491','Derek','So'),
            ('BCS-15-00492','Patricio','Ibarra'),
            ('BCS-15-00494','Derek','So'),
            ('BCS-15-00496','Derek','So'),
            ('BCS-15-00497','Richard','Klassen'),
            ('BCS-15-00498','Patricio','Ibarra'),
            ('BCS-15-00500','Marcela','Lenger'),
            ('BCS-15-00502','Athena','Adan'),
            ('BCS-15-00505','Athena','Adan'),
            ('BCS-15-00517','Athena','Adan'),
            ('BCS-15-00519','Athena','Adan'),
            ('BCS-15-00520','Athena','Adan'),
            ('BCS-15-00521','Derek','So'),
            ('BCS-15-00522','Derek','So'),
            ('BCS-15-00524','Athena','Adan'),
            ('BCS-15-00525','Derek','So'),
            ('BCS-15-00527','Derek','So'),
            ('BCS-15-00528','Derek','So'),
            ('BCS-15-00529','Derek','So'),
            ('BCS-15-00531','Derek','So'),
            ('BCS-15-00532','Pamela','Radcliffe'),
            ('BCS-15-00533','Pamela','Radcliffe'),
            ('BCS-15-00535','Pamela','Radcliffe'),
            ('BCS-15-00537','Pamela','Radcliffe'),
            ('BCS-15-00538','Pamela','Radcliffe'),
            ('BCS-15-00547','Pamela','Radcliffe'),
            ('BCS-15-00548','Pamela','Radcliffe'),
            ('BCS-15-00549','Pamela','Radcliffe'),
            ('BCS-15-00552','Daniela','Al-Kuwatli'),
            ('BCS-15-00554','Daniela','Al-Kuwatli'),
            ('BCS-15-00556','Richard','Klassen'),
            ('BCS-15-00558','Daniela','Al-Kuwatli'),
            ('BCS-15-00564','Athena','Adan'),
            ('BCS-15-00567','Richard','Klassen'),
            ('BCS-15-00568','Athena','Adan'),
            ('BCS-15-00571','Richard','Klassen'),
            ('BCS-15-00572','Leanna','Krause'),
            ('BCS-15-00573','Leanna','Krause'),
            ('BCS-15-00576','Richard','Klassen'),
            ('BCS-15-00579','Richard','Klassen'),
            ('BCS-15-00582','Richard','Klassen'),
            ('BCS-15-00585','Leanna','Krause'),
            ('BCS-15-00588','Leanna','Krause'),
            ('BCS-15-00590','Leanna','Krause'),
            ('BCS-15-00591','Leanna','Krause'),
            ('BCS-15-00594','Leanna','Krause'),
            ('BCS-15-00595','Athena','Adan'),
            ('BCS-15-00597','Patricio','Ibarra'),
            ('BCS-15-00598','Richard','Klassen'),
            ('BCS-15-00601','Athena','Adan'),
            ('BCS-15-00602','Sara','Gardezi'),
            ('BCS-15-00604','Richard','Klassen'),
            ('BCS-15-00605','Patricio','Ibarra'),
            ('BCS-15-00606','Patricio','Ibarra'),
            ('BCS-15-00607','Mindy','Nannar'),
            ('BCS-15-00608','Richard','Klassen'),
            ('BCS-15-00610','Derek','So'),
            ('BCS-15-00611','Leanna','Krause'),
            ('BCS-15-00612','Richard','Klassen'),
            ('BCS-15-00616','Richard','Klassen'),
            ('BCS-15-00617','Richard','Klassen'),
            ('BCS-15-00619','Richard','Klassen'),
            ('BCS-15-00620','Richard','Klassen'),
            ('BCS-15-00621','Patricio','Ibarra'),
            ('BCS-15-00622','Derek','So'),
            ('BCS-15-00625','Derek','So'),
            ('BCS-15-00626','Patricio','Ibarra'),
            ('BCS-15-00630','Richard','Klassen'),
            ('BCS-15-00631','Patricio','Ibarra'),
            ('BCS-15-00633','Patricio','Ibarra'),
            ('BCS-15-00634','Pamela','Radcliffe'),
            ('BCS-15-00635','Pamela','Radcliffe'),
            ('BCS-15-00636','Pamela','Radcliffe'),
            ('BCS-15-00637','Pamela','Radcliffe'),
            ('BCS-15-00638','Pamela','Radcliffe'),
            ('BCS-15-00639','Leanna','Krause'),
            ('BCS-15-00643','Sara','Gardezi'),
            ('BCS-15-00644','Sara','Gardezi'),
            ('BCS-15-00647','Sara','Gardezi'),
            ('BCS-15-00649','Sara','Gardezi'),
            ('BCS-15-00653','Sara','Gardezi'),
            ('BCS-15-00657','Sara','Gardezi'),
            ('BCS-15-00659','Sara','Gardezi'),
            ('BCS-15-00660','Sara','Gardezi'),
            ('BCS-15-00661','Richard','Klassen'),
            ('BCS-15-00662','Patricio','Ibarra'),
            ('BCS-15-00663','Patricio','Ibarra'),
            ('BCS-15-00664','Patricio','Ibarra'),
            ('BCS-15-00671','Daniela','Al-Kuwatli'),
            ('BCS-15-00674','Sue','Gill'),
            ('BCS-15-00676','Patricio','Ibarra'),
            ('BCS-15-00677','Patricio','Ibarra'),
            ('BCS-15-00680','Richard','Klassen'),
            ('BCS-15-00682','Patricio','Ibarra'),
            ('BCS-15-00684','Derek','So'),
            ('BCS-15-00686','Derek','So'),
            ('BCS-15-00689','Derek','So'),
            ('BCS-15-00690','Athena','Adan'),
            ('BCS-15-00691','Pamela','Radcliffe'),
            ('BCS-15-00693','Athena','Adan'),
            ('BCS-15-00694','Richard','Klassen'),
            ('BCS-15-00696','Sue','Gill'),
            ('BCS-15-00698','Sue','Gill'),
            ('BCS-15-00701','Sue','Gill'),
            ('BCS-15-00703','Sue','Gill'),
            ('BCS-15-00704','Sue','Gill'),
            ('BCS-15-00705','Leanna','Krause'),
            ('BCS-15-00706','Richard','Klassen'),
            ('BCS-15-00711','Athena','Adan'),
            ('BCS-15-00712','Athena','Adan'),
            ('BCS-15-00713','Athena','Adan'),
            ('BCS-15-00714','Richard','Klassen'),
            ('BCS-15-00715','Athena','Adan'),
            ('BCS-15-00716','Athena','Adan'),
            ('BCS-15-00717','Richard','Klassen'),
            ('BCS-15-00719','Richard','Klassen'),
            ('BCS-15-00720','Athena','Adan'),
            ('BCS-15-00723','Athena','Adan'),
            ('BCS-15-00725','Sue','Gill'),
            ('BCS-15-00729','Sue','Gill'),
            ('BCS-15-00730','Richard','Klassen'),
            ('BCS-15-00732','Sue','Gill'),
            ('BCS-15-00733','Sue','Gill'),
            ('BCS-15-00736','Sue','Gill'),
            ('BCS-15-00737','Sue','Gill'),
            ('BCS-15-00738','Richard','Klassen'),
            ('BCS-15-00739','Sue','Gill'),
            ('BCS-15-00740','Richard','Klassen'),
            ('BCS-15-00742','Sue','Gill'),
            ('BCS-15-00744','Derek','So'),
            ('BCS-15-00745','Derek','So'),
            ('BCS-15-00746','Pamela','Radcliffe'),
            ('BCS-15-00747','Sue','Gill'),
            ('BCS-15-00750','Derek','So'),
            ('BCS-15-00751','Derek','So'),
            ('BCS-15-00752','Athena','Adan'),
            ('BCS-15-00753','Derek','So'),
            ('BCS-15-00754','Derek','So'),
            ('BCS-15-00756','Derek','So'),
            ('BCS-15-00758','Sue','Gill'),
            ('BCS-15-00759','Sue','Gill'),
            ('BCS-15-00760','Sue','Gill'),
            ('BCS-15-00761','Sue','Gill'),
            ('BCS-15-00762','Sue','Gill'),
            ('BCS-15-00767','Richard','Klassen'),
            ('BCS-15-00774','Richard','Klassen'),
            ('BCS-15-00775','Leanna','Krause'),
            ('BCS-15-00781','Richard','Klassen'),
            ('BCS-15-00786','Richard','Klassen'),
            ('BCS-15-00787','Richard','Klassen'),
            ('BCS-15-00803','Richard','Klassen'),
            ('BCS-15-00824','Samuel','Lee'),
            ('BCS-15-00825','Samuel','Lee'),
            ('BCS-15-00826','Samuel','Lee'),
            ('BCS-15-00830','Samuel','Lee'),
            ('BCS-15-00832','Samuel','Lee'),
            ('BCS-15-00833','Samuel','Lee'),
            ('BCS-15-00834','Richard','Klassen'),
            ('BCS-15-00836','Samuel','Lee'),
            ('BCS-15-00842','Richard','Klassen'),
            ('BCS-15-00845','Richard','Klassen'),
            ('BCS-15-00935','Samuel','Lee'),
            ('BCS-15-00936','Richard','Klassen'),
            ('BCS-15-00939','Richard','Klassen'),
            ('BCS-15-00942','Richard','Klassen'),
            ('BCS-15-00947','Richard','Klassen'),
            ('BCS-15-00950','Richard','Klassen'),
            ('BCS-15-00984','Pamela','Radcliffe'),
            ('BCS-15-01000','Samuel','Lee'),
            ('BCS-15-01005','Pamela','Radcliffe'),
            ('BCS-15-01008','Samuel','Lee'),
            ('BCS-15-01033','Patricio','Ibarra'),
            ('BCS-15-01035','Leanna','Krause'),
            ('BCS-15-01040','Leanna','Krause'),
            ('BCS-15-01042','Leanna','Krause'),
            ('BCS-15-01049','Daniela','Al-Kuwatli'),
            ('BCS-15-01051','Leanna','Krause'),
            ('BCS-15-01053','Samuel','Lee'),
            ('BCS-15-01054','Samuel','Lee'),
            ('BCS-15-01060','Samuel','Lee'),
            ('BCS-15-01061','Samuel','Lee'),
            ('BCS-15-01064','Daniela','Al-Kuwatli'),
            ('BCS-15-01065','Leanna','Krause'),
            ('BCS-15-01102','Samuel','Lee'),
            ('BCS-16-01126','Leanna','Krause'),
            ('BCS-16-01128','Derek','So'),
            ('BCS-16-01129','Pamela','Radcliffe'),
            ('BCS-16-01137','Sue','Gill'),
            ('BCS-16-01144','Samuel','Lee'),
            ('BCS-16-01145','Samuel','Lee'),
            ('BCS-16-01146','Samuel','Lee'),
            ('BCS-16-01162','Samuel','Lee'),
            ('BCS-16-03313','Sue','Gill'),
            ('BCS-16-03697','Samuel','Lee'),
            ('BCS-16-03791','Samuel','Lee'),
            ('BCS-16-03808','Samuel','Lee'),
            ('BCS-16-04210','Samuel','Lee'),
            ('BCS-16-04265','Pamela','Radcliffe'),
            ('BCS-16-04367','Kelvin','Wu'),
            ('BCS-16-04381','Daniela','Al-Kuwatli'),
            ('BCS-16-04468','Mindy','Nannar'),
            ('BCS-16-04494','Daniela','Al-Kuwatli'),
            ('BCS-16-04516','Aun','Jaffery'),
            ('BCS-16-04520','Samuel','Lee'),
            ('BCS-16-04521','Samuel','Lee'),
            ('BCS-16-04522','Samuel','Lee'),
            ('BCS-16-04585','Sue','Gill'),
            ('BCS-16-04644','Athena','Adan'),
            ('BCS-16-04791','Daniela','Al-Kuwatli'),
            ('BCS-16-04922','Samuel','Lee'),
            ('BCS-16-05002','Leanna','Krause'),
            ('BCS-16-05094','Mindy','Nannar'),
            ('BCS-16-05149','Samuel','Lee'),
            ('BCS-16-05151','Samuel','Lee'),
            ('BCS-16-05211','Pamela','Radcliffe'),
            ('BCS-16-05255','Samuel','Lee'),
            ('BCS-16-05320','Mindy','Nannar'),
            ('BCS-16-05469','Samuel','Lee'),
            ('BCS-16-05496','Athena','Adan'),
            ('BCS-16-05575','Samuel','Lee'),
            ('BCS-16-05629','Leanna','Krause'),
            ('BCS-16-05699','Samuel','Lee'),
            ('BCS-16-05941','Pamela','Radcliffe'),
            ('BCS-16-05974','Pamela','Radcliffe'),
            ('BCS-16-06030','Samuel','Lee'),
            ('BCS-16-06206','Pamela','Radcliffe'),
            ('BCS-16-06223','Samuel','Lee'),
            ('BCS-16-06352','Patricio','Ibarra'),
            ('BCS-16-06365','Richard','Klassen'),
            ('BCS-16-06389','Mindy','Nannar'),
            ('BCS-16-06394','Richard','Klassen'),
            ('BCS-16-06518','Richard','Klassen'),
            ('BCS-16-06556','Daniela','Al-Kuwatli'),
            ('BCS-16-06568','Patricio','Ibarra'),
            ('BCS-16-06778','Leanna','Krause');
        "
        );

        $rows = $this->fetchAll(
            "
          SELECT cl.member_id `case`, m.member_id advisor, cff.field_id, cfd.value, uat.*
          FROM update_advisors_tmp uat
          LEFT OUTER JOIN members m ON m.fName = uat.fName AND m.lName = uat.lName
          LEFT OUTER JOIN clients cl ON cl.fileNumber = uat.fileNumber
          INNER JOIN client_form_fields cff ON cff.company_field_id = 'decisionMadeBy'
          INNER JOIN company co ON cff.company_id = co.company_id
          LEFT OUTER JOIN client_form_data cfd ON cfd.member_id = cl.member_id AND cfd.field_id = cff.field_id
          WHERE co.companyName = 'BC PNP';
        "
        );

        $insertSQL    = array();
        $updateSQL    = array();
        $rowsUpdated  = 0;
        $rowsInserted = 0;
        foreach ($rows as $row) {
            if (!$row['case']) {
                $output->writeln('<error>Case ' . $row['fileNumber'] . ' not found.</error>');
                continue;
            }

            if (!$row['advisor']) {
                $output->writeln('<error>Advisor ' . $row['fName'] . ' ' . $row['lName'] . ' not found.</error>');
                continue;
            }

            if (!$row['field_id']) {
                $output->writeln('<error>Destination field not found.</error>');
                continue;
            }

            if ($row['value']) {
                $updateSQL[] = 'UPDATE client_form_data SET `value` = ' . $row['advisor'] . ' WHERE field_id = "' . $row['field_id'] . '" AND member_id = "' . $row['case'] . '";';
                $rowsUpdated++;
            } else {
                $insertSQL[] = '(' . $row['case'] . ', ' . $row['field_id'] . ', ' . $row['advisor'] . ')';
            }
        }

        if ($insertSQL) {
            $sql          = 'INSERT INTO client_form_data VALUES ' . implode(', ', $insertSQL) . ';';
            $rowsInserted = $this->execute($sql);
        }

        if ($updateSQL) {
            $sql = implode(' ', $updateSQL);
            $this->execute($sql);
        }

        $rowsAffected = $rowsInserted + $rowsUpdated;
        $output->writeln($rowsAffected . ' advisors assigned.');

        $this->execute('DROP TABLE update_advisors_tmp;');
    }

    public function down()
    {
    }
}
