<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\AutomaticReminders;
use Officio\Service\Log;
use Phinx\Migration\AbstractMigration;
use Tasks\Service\Tasks;

class DeleteApplicantsAndCases extends AbstractMigration
{

    public function up()

    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        try {
            $select = $db->select()
                ->from('company', 'company_id');

            $arrCompanyIds = $db->fetchCol($select);

            /** @var Clients $oClients */
            $oClients = Zend_Registry::get('serviceManager')->get(Clients::class);
            /** @var AutomaticReminders $oAutomaticReminders */
            $oAutomaticReminders = Zend_Registry::get('serviceManager')->get(AutomaticReminders::class);
            /** @var Forms $forms */
            $forms = Zend_Registry::get('serviceManager')->get(Forms::class);
            /** @var Files $oFiles */
            $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);
            /** @var Tasks $oTasks */
            $oTasks = Zend_Registry::get('serviceManager')->get(Tasks::class);

            foreach ($arrCompanyIds as $companyId) {
                // delete all applicants and cases
                $select = $db->select()
                    ->from('members', 'member_id')
                    ->where('userType IN (?)', array(3, 7, 8, 9, 10), 'INT');

                $arrMemberIds = $db->fetchCol($select);

                if (!empty($arrMemberIds)) {
                    $select = $db->select()
                        ->from('members', 'member_id')
                        ->where('userType IN (?)', array(3), 'INT');

                    $arrCaseIds = $db->fetchCol($select);

                    $oClients->deleteMember($companyId, $arrMemberIds, '', false);

                    if (!empty($arrCaseIds)) {
                        foreach ($arrCaseIds as $caseId) {
                            // Find all revisions and delete them
                            $arrAssignedFormIds = $forms->getFormAssigned()->getAssignedFormIdsByClientId($caseId);
                            if (is_array($arrAssignedFormIds) && count($arrAssignedFormIds)) {
                                $arrRevisionIds = $forms->getFormRevision()->getRevisionIdsByFormAssignedIds($arrAssignedFormIds);
                                if (is_array($arrRevisionIds) && count($arrRevisionIds)) {
                                    $forms->getFormRevision()->deleteRevision($caseId, $arrRevisionIds);
                                }
                            }
                            // And after that delete all assigned forms
                            $db->delete('FormAssigned', $db->quoteInto('ClientMemberId = ?', $caseId));


                            // Delete folders/files
                            // Always delete local and remote files/folders, if any
                            $oFiles->deleteFolder($oFiles->getMemberFolder($companyId, $caseId, true, false), false);
                            $oFiles->deleteFolder($oFiles->getMemberFolder($companyId, $caseId, true, true), true);


                            $oAutomaticReminders->getActions()->deleteClientActions($caseId);


                            // Delete tasks
                            $oTasks->deleteMemberTasks($caseId);
                        }
                        $strWhere = $db->quoteInto('member_id IN (?)', $arrCaseIds, 'INT');

                        // Delete all assigned invoices
                        $select      = $db->select()
                            ->from('u_invoice', 'invoice_id')
                            ->where($strWhere);
                        $arrInvoices = $db->fetchCol($select);

                        $db->delete('u_invoice', $strWhere);
                        if (is_array($arrInvoices) && !empty($arrInvoices)) {
                            $db->delete('u_assigned_withdrawals', sprintf('invoice_id IN (%s)', $db->quote($arrInvoices)));
                        }
                    }
                }
            }

            $db->commit();


            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
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
    }

}
