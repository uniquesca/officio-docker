<?php

namespace Forms\Controller\Plugin;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Select;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Officio\BaseController;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\AuthenticationService;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Log;
use SimpleXMLElement;

class XfdfPreprocessor extends AbstractPlugin
{

    /** @var Log */
    protected $_log;

    /** @var AuthenticationService */
    protected $_auth;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var DbAdapterWrapper */
    protected $_db2;

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Pdf */
    protected $_pdf;

    /** @var Files */
    protected $_files;

    /** @var Forms */
    protected $_forms;

    /** @var Encryption */
    protected $_encryption;

    /** @var bool Flag whether dependencies are initialized */
    private $_dependenciesInitialized = false;

    protected function initDependencies()
    {
        if ($this->_dependenciesInitialized) {
            return;
        }

        $controller = $this->getController();
        if (!$controller instanceof BaseController) {
            throw new Exception('XfdfVerifier plugin can be used only with Officio\\BaseController or it\'s ancestors.');
        }

        $this->_log        = $controller->getServiceManager()->get('log');
        $this->_db2        = $controller->getServiceManager()->get('db2');
        $this->_auth       = $controller->getServiceManager()->get('auth');
        $this->_authHelper = $controller->getServiceManager()->get(AuthHelper::class);
        $this->_clients    = $controller->getServiceManager()->get(Clients::class);
        $this->_company    = $controller->getServiceManager()->get(Company::class);
        $this->_pdf        = $controller->getServiceManager()->get(Pdf::class);
        $this->_files      = $controller->getServiceManager()->get(Files::class);
        $this->_forms      = $controller->getServiceManager()->get(Forms::class);
        $this->_encryption = $controller->getServiceManager()->get(Encryption::class);

        $this->_dependenciesInitialized = true;
    }

    /**
     * Verifies incoming XML for integrity and access.
     * @param SimpleXMLElement $XMLData
     * @param bool $print
     * @param false|int $pdfId
     * @return int|array Returns array with
     */
    public function __invoke(SimpleXMLElement $XMLData, $print = false, $pdfId = false)
    {
        $this->initDependencies();

        if (empty($XMLData) || !is_object($XMLData)) {
            if (!empty($XMLData)) {
                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $XMLData, 'pdf');
            }
            return Pdf::XFDF_INCORRECT_INCOMING_XFDF;
        }

        $booCorrectLoginAndPass = false;
        $currentMemberId = 0;
        $currentMemberDivisionGroupId = null;
        list($login, $pass, $pdfId, $formTimestamp) = $this->_pdf->parsePdfForCredentials($XMLData, $pdfId);
        if (!empty($login) && !empty($pass) && !empty($pdfId)) {
            // Check if this login info is correct
            // and assigned pdf is related to this client

            $arrMemberInfo = $this->_clients->getMemberInfoByUsername($login);
            if (is_array($arrMemberInfo) && !empty($arrMemberInfo['password']) && $this->_encryption->checkPasswords($pass, $arrMemberInfo['password'])) {
                // Login and pass are okay, now check if this user has access
                // to assigned pdf form (has access to client)
                $select = (new Select())
                    ->from('form_assigned')
                    ->columns(['client_member_id'])
                    ->where(['form_assigned_id' => $pdfId]);

                $assignedMemberId = $this->_db2->fetchOne($select);

                if (!empty($assignedMemberId) && $this->_clients->isAlowedClient($assignedMemberId, $arrMemberInfo['member_id'])) {
                    $currentMemberId              = $arrMemberInfo['member_id'];
                    $currentMemberDivisionGroupId = $arrMemberInfo['division_group_id'];
                    $booCorrectLoginAndPass = true;
                }
            }

            if (!$booCorrectLoginAndPass) {
                return Pdf::XFDF_INCORRECT_LOGIN_INFO;
            }
        }

        if (!$booCorrectLoginAndPass && empty($pdfId)) {
            $pdfId = $this->_pdf->parsePdfIdFromUrl($XMLData);
        }
        if (!$pdfId) {
            return Pdf::XFDF_INCORRECT_INCOMING_XFDF;
        }

        $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($pdfId);
        if (!$assignedFormInfo) {
            return Pdf::XFDF_INCORRECT_INCOMING_XFDF;
        }

        // Get information about assigned member to this pdf form
        $updateMemberId = $assignedFormInfo['client_member_id'];
        $arrUpdateMemberInfo = $this->_clients->getMemberInfo($updateMemberId);
        $updateMemberCompanyId = $arrUpdateMemberInfo['company_id'];

        // Check if current user has access to this PDF form (to member)
        if ($booCorrectLoginAndPass) {
            // Check if this user has access to this member
            if (!$this->_clients->isAlowedClient($updateMemberId, $currentMemberId) || (!$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($updateMemberId, $currentMemberDivisionGroupId) && !$print)) {
                return Pdf::XFDF_INSUFFICIENT_ACCESS_TO_PDF;
            }
        } else {
            if (!$this->_clients->isAlowedClient($updateMemberId) || (!$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($updateMemberId) && !$print)) {
                return Pdf::XFDF_INSUFFICIENT_ACCESS_TO_PDF;
            }
            $currentMemberId = $this->_auth->getCurrentUserId();
        }

        // Check if current user is client
        if ($currentMemberId == $updateMemberId) {
            // Check if this client is locked
            $locked = $this->_clients->isLockedClient($updateMemberId);

            if ($locked) {
                return Pdf::XFDF_CLIENT_LOCKED;
            }
        }

        // Timestamp must be same as in DB
        if (!empty($formTimestamp) && $formTimestamp != $assignedFormInfo['last_update_date']) {
            return Pdf::XFDF_INCORRECT_TIME_STAMP;
        }

        // Unset specific fields
        if (isset($XMLData->f)) {
            unset($XMLData->f);
        }
        if (isset($XMLData->ids)) {
            unset($XMLData->ids);
        }

        $xfdfDirectory = $this->_files->getClientXFDFFTPFolder($updateMemberId);
        if (!$this->_files->createFTPDirectory($xfdfDirectory)) {
            $this->_log->debugErrorToFile('Directory not created', $xfdfDirectory, 'files');
            return Pdf::XFDF_DIRECTORY_NOT_CREATED;
        }

        return [$pdfId, $currentMemberId, $updateMemberId, $updateMemberCompanyId, $assignedFormInfo];
    }

}
