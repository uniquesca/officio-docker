<?php

use Clients\Service\Clients;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\AutomaticReminders;
use Phinx\Migration\AbstractMigration;

class DeleteApplicantsAndCasesAgain extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        try {
            /** @var Clients $oClients */
            $oClients = Zend_Registry::get('serviceManager')->get(Clients::class);

            // Load case/client types, instead of hardcoding
            $arrCaseTypes = array(
                $oClients->getMemberTypeIdByName('case')
            );

            $arrClientTypes = array(
                $oClients->getMemberTypeIdByName('employer'),
                $oClients->getMemberTypeIdByName('individual'),
                $oClients->getMemberTypeIdByName('internal_contact'),
                $oClients->getMemberTypeIdByName('contact')
            );

            // Load list of companies
            $select = $db->select()
                ->from('company', 'company_id');

            $arrCompanyIds = $db->fetchCol($select);

            foreach ($arrCompanyIds as $companyId) {
                // Delete cases of the company
                $select = $db->select()
                    ->from('members', 'member_id')
                    ->where('company_id = ?', $companyId, 'INT')
                    ->where('userType IN (?)', $arrCaseTypes, 'INT');

                $arrCaseIds = $db->fetchCol($select);

                /** @var AutomaticReminders $oAutomaticReminders */
                $oAutomaticReminders = Zend_Registry::get('serviceManager')->get(AutomaticReminders::class);
                if (!empty($arrCaseIds)) {
                    foreach ($arrCaseIds as $caseId) {
                        $oClients->deleteClient($caseId, false, $oAutomaticReminders->getActions());
                    }
                }

                // Delete parent applicants of the company
                $select = $db->select()
                    ->from('members', 'member_id')
                    ->where('company_id = ?', $companyId, 'INT')
                    ->where('userType IN (?)', $arrClientTypes, 'INT');

                $arrMemberIds = $db->fetchCol($select);

                if (!empty($arrMemberIds)) {
                    $oClients->deleteMember($companyId, $arrMemberIds, '', false);
                }
            }

            $db->commit();


            /** @var StorageInterface $cache */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
        } catch (\Exception $e) {
            $db->rollBack();
            Zend_Registry::get('serviceManager')->get('log')->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}
