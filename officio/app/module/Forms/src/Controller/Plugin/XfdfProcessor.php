<?php

namespace Forms\Controller\Plugin;

use Clients\Service\Clients;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Officio\BaseController;
use Officio\Common\Service\Log;

class XfdfProcessor extends AbstractPlugin
{

    /** @var array */
    protected $_config;

    /** @var Log */
    protected $_log;

    /** @var Clients */
    protected $_clients;

    /** @var Pdf */
    protected $_pdf;

    /** @var Files */
    protected $_files;

    /** @var Forms */
    protected $_forms;

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

        $this->_config  = $controller->getServiceManager()->get('config');
        $this->_log     = $controller->getServiceManager()->get('log');
        $this->_clients = $controller->getServiceManager()->get(Clients::class);
        $this->_pdf     = $controller->getServiceManager()->get(Pdf::class);
        $this->_files   = $controller->getServiceManager()->get(Files::class);
        $this->_forms   = $controller->getServiceManager()->get(Forms::class);

        $this->_dependenciesInitialized = true;
    }

    public function process($updateMemberId, $updateMemberCompanyId, $assignedFormInfo)
    {
        $this->initDependencies();

        // Get parent for the case
        $arrParents = $this->_clients->getParentsForAssignedApplicants(array($updateMemberId), false, false);
        if (!is_array($arrParents)) {
            return Pdf::XFDF_INCORRECT_INCOMING_XFDF;
        } else {
            $booIncorrectXfdf = false;
            foreach ($arrParents as $parent) {
                if ($parent['child_member_id'] == $updateMemberId) {
                    $booIncorrectXfdf = true;
                }
            }
            if (!$booIncorrectXfdf) {
                return Pdf::XFDF_INCORRECT_INCOMING_XFDF;
            }
        }

        $arrParentsData = array();
        $mainParentId = 0;
        foreach ($arrParents as $parent) {
            $parentMemberId = $parent['parent_member_id'];
            if ($parent['member_type_name'] == 'individual' || count($arrParents) == 1) {
                $mainParentId = $parentMemberId;
            }

            $parentMemberTypeId = $this->_clients->getMemberTypeIdByName($parent['member_type_name']);
            $arrInternalContacts = $this->_clients->getAssignedContacts($parentMemberId);

            // Get internal contacts for the parent case's record -
            // will be used during data saving/updating

            // Load all available/assigned fields for the case's parent
            $arrParentInfo = $this->_clients->getClientInfoOnly($parentMemberId);
            $arrParentFields = $this->_clients->getApplicantFields()->getAllGroupsAndFields($updateMemberCompanyId, $parentMemberTypeId, $arrParentInfo['applicant_type_id'], true);

            // Load options list for all combobox fields
            $arrComboFieldIds = array();
            foreach ($arrParentFields['blocks'] as $arrBlockInfo) {
                foreach ($arrBlockInfo['block_groups'] as $arrGroupInfo) {
                    foreach ($arrGroupInfo['group_fields'] as $arrFieldInfo) {
                        if ($arrFieldInfo['type'] == 'combo') {
                            $arrComboFieldIds[] = $arrFieldInfo['applicant_field_id'];
                        }
                    }
                }
            }
            $arrParentFieldsOptions = $this->_clients->getFields()->getFieldsOptions($arrComboFieldIds);

            $arrParentsData[] = array(
                'parent_member_id' => $parentMemberId,
                'parent_member_type_id' => $parentMemberTypeId,
                'fields' => $arrParentFields,
                'field_options' => $arrParentFieldsOptions,
                'internal_contacts' => $arrInternalContacts
            );
        }

        $familyMemberId = $assignedFormInfo['family_member_id'];
        $synFieldIds    = $this->_forms->getFormSynField()->fetchSynFieldsIds();
        // Get mapped fields list
        $fieldsMap = $this->_forms->getFormMap()->getMappedFieldsForFamilyMember(
            $familyMemberId,
            $synFieldIds
        );

        return [$mainParentId, $arrParentsData, $fieldsMap];
    }

    public function printXFDF($XMLData, $updateMemberId, $assignedFormInfo)
    {
        $this->initDependencies();

        // Create directory if it does not exist
        $config      = $this->_config['directory'];
        $tmpXFDFPath = $this->_files->createFTPDirectory($config['pdf_temp']);

        $xfdf_file_name = $tmpXFDFPath . '/' . uniqid(rand() . time(), true) . '.xfdf';
        if (file_exists($xfdf_file_name)) {
            unlink($xfdf_file_name);
        }

        $XMLData = $this->_pdf->clearEmptyFields($XMLData);
        $this->_pdf->removeInternalFields($XMLData);
        $XML_FILE_DATA = $XMLData->asXML();

        $thisXfdfCreationResult = $this->_pdf->saveXfdf($xfdf_file_name, $XML_FILE_DATA);
        if ($thisXfdfCreationResult != Pdf::XFDF_SAVED_CORRECTLY) {
            $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' File:' . $xfdf_file_name, $XML_FILE_DATA, 'pdf');

            return $thisXfdfCreationResult;
        }

        $arrFormInfo['formId'] = $assignedFormInfo['form_id'];
        $arrFormInfo['memberId'] = $updateMemberId;
        $arrFormInfo['useRevision'] = $assignedFormInfo['use_revision'];
        $arrFormInfo['filePath'] = $assignedFormInfo['file_path'];

        $arrPdfFormPath = $this->_pdf->_getPDFFormPath($arrFormInfo);
        $pdfFormPath    = $arrPdfFormPath['pdfFormPath'];

        if (!file_exists($pdfFormPath)) {
            return Pdf::PDF_FORM_DOES_NOT_EXIST;
        }

        $flattenPdfPath = $tmpXFDFPath . '/' . 'flatten_form_' . uniqid(rand() . time(), true) . '.pdf';
        $booResult = $this->_pdf->createFlattenPdf($pdfFormPath, $xfdf_file_name, $flattenPdfPath);
        if ($booResult && file_exists($flattenPdfPath)) {
            return new FileInfo($arrFormInfo['filePath'], $flattenPdfPath, true);
        } else {
            return Pdf::PDF_NOT_CREATED;
        }
    }

}