<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddCaseNumberWithSameCaseTypeInCompanyColumn extends AbstractMigration
{
    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

        $this->execute('ALTER TABLE `clients`
                ADD COLUMN `case_number_with_same_case_type_in_company` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `case_number_in_company`;');

        $this->getAdapter()->beginTransaction();

        try {
            $statement = $builder
                ->select('company_id')
                ->from('company')
                ->execute();

            $arrCompanyIds = array_column($statement->fetchAll(), 0);

            foreach ($arrCompanyIds as $companyId) {
                $statement = $builder
                    ->select(array('c.client_type_id', 'c.case_number_in_company', 'm.regTime', 'm.member_id'))
                    ->from(array('c' => 'clients'))
                    ->innerJoin(array('m' => 'members'), 'c.member_id = m.member_id')
                    ->where(function ($exp) {
                        return $exp->in('m.userType IN', array(3));
                    })
                    ->andWhere(
                        [
                            'm.company_id' => (int)$companyId
                        ]
                    )
                    ->order('c.client_type_id ASC')
                    ->execute();

                $arrResult = $statement->fetchAll('assoc');

                if (!empty($arrResult)) {
                    $arrGroupedCases = array();

                    foreach ($arrResult as $key => $case) {
                        $arrGroupedCases[$case['client_type_id']][$key] = $case;
                    }

                    ksort($arrGroupedCases, SORT_NUMERIC);

                    foreach ($arrGroupedCases as $clientTypeId => &$cases) {
                        usort($cases, function ($a, $b) {
                            $regTimeCmp = strcmp($a['regTime'], $b['regTime']);
                            $caseNumberCmp = strcmp($a['case_number_in_company'], $b['case_number_in_company']);
                            return !empty($regTimeCmp) ? $regTimeCmp : $caseNumberCmp;
                        });

                        foreach ($cases as $key => $case) {
                            $builder
                                ->update('clients')
                                ->set(
                                array(
                                    'case_number_with_same_case_type_in_company' => ++$key
                                    )
                                )
                                ->where(
                                    [
                                        'member_id' => (int)$case['member_id']
                                    ]
                                )
                                ->execute();
                        }
                    }
                    unset($cases);
                }
            }

            $this->getAdapter()->commitTransaction();
        } catch (\Exception $e) {
            $this->getAdapter()->rollbackTransaction();
            /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }

    public function down()
    {
        $this->execute("ALTER TABLE `clients` DROP COLUMN `case_number_with_same_case_type_in_company`;");
    }
}