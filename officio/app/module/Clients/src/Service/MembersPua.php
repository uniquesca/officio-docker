<?php

/**
 * Members Planned or Unplanned Absence (i.e. death)
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */

namespace Clients\Service;

use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

class MembersPua extends BaseService
{
    /** @var Members */
    protected $_members;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var Pdf */
    protected $_pdf;

    /** @var PhpRenderer */
    protected $_renderer;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_members    = $services[Members::class];
        $this->_company    = $services[Company::class];
        $this->_files      = $services[Files::class];
        $this->_pdf        = $services[Pdf::class];
        $this->_renderer   = $services[PhpRenderer::class];
        $this->_encryption = $services[Encryption::class];
    }

    /**
     * Check if current user can access to specific PUA record
     *
     * @param int $puaRecordId
     * @return bool true if has access
     */
    public function hasAccessToPuaRecord($puaRecordId)
    {
        $booHasAccess = false;
        if (is_numeric($puaRecordId) && !empty($puaRecordId)) {
            $arrRecord = $this->getPuaRecordInfo($puaRecordId);

            $booHasAccess = isset($arrRecord['member_id']) && $arrRecord['member_id'] == $this->_auth->getCurrentUserId();
        }

        return $booHasAccess;
    }

    /**
     * Load PUA record info
     *
     * @param int $puaRecordId
     * @return array
     */
    public function getPuaRecordInfo($puaRecordId)
    {
        $select = (new Select())
            ->from(array('p' => 'members_pua'))
            ->where(['p.pua_id' => (int)$puaRecordId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load list of PUA records
     *
     * @param $puaType
     * @param $sort
     * @param $dir
     * @param $start
     * @param $limit
     * @return array
     */
    public function getPuaRecordsList($puaType, $sort = 'pua_id', $dir = 'DESC', $start = null, $limit = null)
    {
        try {
            if (!in_array($dir, array('ASC', 'DESC'))) {
                $dir = 'DESC';
            }

            $arrAllColumns = array(
                'pua_id',
                'pua_type',

                'pua_designated_person_type',
                'pua_designated_person_form',
                'pua_designated_person_full_name',

                'pua_business_contact_or_service',
                'pua_business_contact_name',
                'pua_business_contact_phone',
                'pua_business_contact_email',
                'pua_business_contact_username',
                'pua_business_contact_password',
                'pua_business_contact_instructions',
                'pua_created_by',
                'pua_created_on',
                'pua_updated_by',
                'pua_updated_on',
            );
            if (!in_array($sort, $arrAllColumns)) {
                $sort = 'pua_id';
            }

            if (!is_null($start)) {
                if (!is_numeric($start) || $start <= 0) {
                    $start = 0;
                }
            }

            if (!is_null($limit)) {
                if (!is_numeric($limit) || $limit <= 0) {
                    $limit = 25;
                }
            }

            if (!in_array($puaType, array('designated_person', 'business_contact'))) {
                $puaType = 'designated_person';
            }

            $select = (new Select())
                ->from(array('p' => 'members_pua'))
                ->where(
                    [
                        'p.member_id' => $this->_auth->getCurrentUserId(),
                        'p.pua_type' => $puaType
                    ]
                )
                ->order(array($sort . ' ' . $dir));

            if (!empty($start) || !empty($limit)) {
                $select
                    ->limit($limit)
                    ->offset($start);
            }

            $arrRecords   = $this->_db2->fetchAll($select);
            $totalRecords = $this->_db2->fetchResultsCount($select);

            // Decrypt data for specific fields if needed
            foreach ($arrRecords as $key => $arrRecordData) {
                if (!empty($arrRecordData['pua_business_contact_password'])) {
                    $arrRecords[$key]['pua_business_contact_password'] = $this->_encryption->decode($arrRecordData['pua_business_contact_password']);
                }
            }
        } catch (Exception $e) {
            $arrRecords = array();
            $totalRecords = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'rows' => $arrRecords,
            'totalCount' => $totalRecords
        );
    }

    /**
     * Create/update PUA record
     *
     * @param array $arrPuaRecordInfo
     * @return int record id, empty on error
     */
    public function managePuaRecord($arrPuaRecordInfo)
    {
        try {
            // Encrypt data for specific fields if needed
            if (!empty($arrPuaRecordInfo['pua_business_contact_password']) && !($arrPuaRecordInfo['pua_business_contact_password'] instanceof Expression)) {
                $arrPuaRecordInfo['pua_business_contact_password'] = $this->_encryption->encode($arrPuaRecordInfo['pua_business_contact_password']);
            }

            if (empty($arrPuaRecordInfo['pua_id'])) {
                unset($arrPuaRecordInfo['pua_id']);

                $arrPuaRecordInfo['member_id']      = $this->_auth->getCurrentUserId();
                $arrPuaRecordInfo['pua_created_by'] = $this->_auth->getCurrentUserId();
                $arrPuaRecordInfo['pua_created_on'] = date('c');

                $puaRecordId = $this->_db2->insert('members_pua', $arrPuaRecordInfo);
            } else {
                $puaRecordId = $arrPuaRecordInfo['pua_id'];
                unset($arrPuaRecordInfo['pua_id']);

                $arrPuaRecordInfo['pua_updated_by'] = $this->_auth->getCurrentUserId();
                $arrPuaRecordInfo['pua_updated_on'] = date('c');

                $this->_db2->update('members_pua', $arrPuaRecordInfo, ['pua_id' => (int)$puaRecordId]);
            }
        } catch (Exception $e) {
            $puaRecordId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $puaRecordId;
    }


    /**
     * Delete PUA record(s)
     * @param array $arrIds
     *
     * @return bool true on success
     */
    public function deletePuaRecords($arrIds)
    {
        $booSuccess = false;

        if (is_array($arrIds) && count($arrIds)) {
            $booHasAccess = true;
            foreach ($arrIds as $puaRecordId) {
                if (!$this->hasAccessToPuaRecord($puaRecordId)) {
                    $booHasAccess = false;
                    break;
                }
            }

            if ($booHasAccess) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
                $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);

                foreach ($arrIds as $puaRecordId) {
                    $arrRecord = $this->getPuaRecordInfo($puaRecordId);
                    $this->_db2->delete('members_pua', ['pua_id' => (int)$puaRecordId]);

                    if (isset($arrRecord['pua_designated_person_form']) && !empty($arrRecord['pua_designated_person_form'])) {
                        $pathToFile = $this->_files->getPuaRecordPath($companyId, $booLocal, $puaRecordId);
                        $this->_files->deleteFile($pathToFile, $booLocal);
                    }
                }

                $booSuccess = true;
            }
        }

        return $booSuccess;
    }

    /**
     * Export saved PUA records to the specific format
     *
     * @param string $type
     * @param int $puaRecordId , if empty - export all with "business_contact" type
     * @return FileInfo|string FileInfo on success, otherwise error message
     */
    public function export($type, $puaRecordId)
    {
        $strError = '';

        try {
            switch ($type) {
                case 'pdf':
                    $strHtml = '';
                    if (!empty($puaRecordId)) {
                        $pdfFormPath = realpath(__DIR__ . '/../../view/pdf/pua-designation-form.pdf');
                        $pdfFormPath = str_replace(getcwd(), '', $pdfFormPath); // We need relative path, not absolute
                        if (substr($pdfFormPath, 0, 1) === '/') {
                            $pdfFormPath = ltrim($pdfFormPath, '/');
                        }

                        // Path, where temp xfdf and flatten pdf files will be created
                        $tmpPdfPath = $this->_files->createFTPDirectory($this->_config['directory']['pdf_temp']);
                        $flattenPdfPath = $tmpPdfPath . '/' . 'pua_flatten_form_' . uniqid(rand() . time(), true) . '.pdf';
                        if (file_exists($flattenPdfPath)) {
                            unlink($flattenPdfPath);
                        }

                        $xfdfPath = $tmpPdfPath . '/' . 'pua_flatten_form_' . uniqid(rand() . time(), true) . '.xfdf';
                        if (file_exists($xfdfPath)) {
                            unlink($xfdfPath);
                        }

                        // Generate XFDF
                        $emptyXfdf = $this->_pdf->getEmptyXfdf();
                        $oXml = simplexml_load_string($emptyXfdf);

                        // Fill xfdf with data
                        $arrPUARecordInfo = $this->getPuaRecordInfo($puaRecordId);
                        foreach ($arrPUARecordInfo as $key => $value) {
                            $this->_pdf->updateFieldInXfdf($key, $value, $oXml);
                        }

                        $thisXfdfCreationResult = $this->_pdf->saveXfdf($xfdfPath, $oXml->asXML());

                        if ($thisXfdfCreationResult == Pdf::XFDF_SAVED_CORRECTLY) {
                            $booResult = $this->_pdf->createFlattenPdf($pdfFormPath, $xfdfPath, $flattenPdfPath);

                            if ($booResult && file_exists($flattenPdfPath)) {
                                // Generate file name
                                return new FileInfo(date('Y-m-d H-i-s') . ' designation form.pdf', $flattenPdfPath, true);
                            }
                        }

                        // Can't be here if everything is ok
                        $strError = $this->_tr->translate('Internal error.');
                    } else {
                        // Export all records
                        $arrBusinessContactOrServiceRecords = $this->getPuaRecordsList('business_contact');
                        $arrBusinessContactOrServiceRecords = $arrBusinessContactOrServiceRecords['rows'];

                        if (empty($arrBusinessContactOrServiceRecords)) {
                            $strHtml .= '<h1>There are no records to export</h1>';
                        } else {
                            $viewModel = new ViewModel(
                                [
                                    'arrData' => $arrBusinessContactOrServiceRecords
                                ]
                            );
                            $viewModel->setTemplate('officio/partials/pua-export-business-contacts.phtml');
                            $strHtml = $this->_renderer->render($viewModel);
                        }

                        $this->_pdf->htmlToPdf(
                            $strHtml,
                            date('Y-m-d H-i-s') . ' pua report.pdf',
                            'I',
                            array(
                                'header_title' => 'Report Date ' . $this->_settings->formatDate(date('Y-m-d')),
                                'setHeaderFont' => array('helvetica', '', 8),
                                'PDF_PAGE_ORIENTATION' => 'L',
                                'SetFont' => array(
                                    'name' => 'helvetica',
                                    'style' => '',
                                    'size' => '11',
                                )
                            )
                        );
                    }

                    break;

                default:
                    $strError = $this->_tr->translate('Unsupported file format.');
                    break;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $strError;
    }

}
