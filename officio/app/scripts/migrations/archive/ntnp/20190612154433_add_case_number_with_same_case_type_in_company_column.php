<?php

use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddCaseNumberWithSameCaseTypeInCompanyColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            'ALTER TABLE `clients`
                ADD COLUMN `case_number_with_same_case_type_in_company` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `case_number_in_company`;'
        );

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        try {
            $select = $db->select()
                ->from('company', 'company_id');

            $arrCompanyIds = $db->fetchCol($select);

            foreach ($arrCompanyIds as $companyId) {
                $select = $db->select()
                    ->from(array('c' => 'clients'), array('client_type_id', 'case_number_in_company'))
                    ->joinInner(array('m' => 'members'), 'c.member_id = m.member_id', array('regTime', 'member_id'))
                    ->where('m.company_id = ?', $companyId, 'INT')
                    ->where('m.userType IN (?)', array(3), 'INT')
                    ->order('c.client_type_id ASC');

                $arrResult = $db->fetchAll($select);

                if (!empty($arrResult)) {
                    $arrGroupedCases = array();

                    foreach ($arrResult as $key => $case) {
                        $arrGroupedCases[$case['client_type_id']][$key] = $case;
                    }

                    ksort($arrGroupedCases, SORT_NUMERIC);

                    foreach ($arrGroupedCases as $clientTypeId => &$cases) {
                        usort(
                            $cases,
                            function ($a, $b) {
                                $regTimeCmp    = strcmp($a['regTime'], $b['regTime']);
                                $caseNumberCmp = strcmp($a['case_number_in_company'], $b['case_number_in_company']);
                                return !empty($regTimeCmp) ? $regTimeCmp : $caseNumberCmp;
                            }
                        );

                        foreach ($cases as $key => $case) {
                            $db->update(
                                'clients',
                                array(
                                    'case_number_with_same_case_type_in_company' => ++$key
                                ),
                                $db->quoteInto('member_id = ?', $case['member_id'], 'INT')
                            );
                        }
                    }
                    unset($cases);
                }
            }

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
        $this->execute("ALTER TABLE `clients` DROP COLUMN `case_number_with_same_case_type_in_company`;");
    }
}