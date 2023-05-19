<?php

namespace Forms\Service\Forms;

use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class FormVersion extends BaseService implements SubServiceInterface
{

    /** @var Files */
    protected $_files;

    /** @var Forms */
    protected $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    public function initAdditionalServices(array $services)
    {
        $this->_files = $services[Files::class];
    }

    /**
     * Load list of all form versions
     *
     * @return array
     */
    public function getAllFormVersionsIds()
    {
        $select = (new Select())
            ->from('form_version')
            ->columns(['form_version_id']);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Check if pdf form version exists in db
     *
     * @param int $pdfFormVersionId
     * @return bool
     */
    public function formVersionExists($pdfFormVersionId)
    {
        $select = (new Select())
            ->from('form_version')
            ->columns(['form_version_id'])
            ->where(['form_version_id' => (int)$pdfFormVersionId]);

        $arrResult = $this->_db2->fetchAll($select);

        return is_array($arrResult) && count($arrResult);
    }

    /**
     * Load form version info by form version id
     *
     * @param $pdfFormVersionId
     * @return array
     */
    public function getFormVersionInfo($pdfFormVersionId)
    {
        $select = (new Select())
            ->from('form_version')
            ->where(['form_version_id' => $pdfFormVersionId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load form versions info by form id
     *
     * @param int $formId
     * @return array
     */
    public function getFormVersionsByFormId($formId)
    {
        $arrFormVersions = array();
        if (!empty($formId)) {
            $select = (new Select())
                ->from('form_version')
                ->where(['form_id' => (int)$formId])
                ->order('version_date DESC');

            $arrFormVersions = $this->_db2->fetchAll($select);
        }

        return $arrFormVersions;
    }

    /**
     * Get old form version info by form id
     *
     * @param int $formId
     * @param $formVersionDate
     * @return array
     */
    public function getOldFormVersionByFormId($formId, $formVersionDate)
    {
        $arrFormVersionInfo = array();
        if (!empty($formId)) {
            $select = (new Select())
                ->from('form_version')
                ->where([
                    'form_id' => (int)$formId,
                    (new Where())->lessThanOrEqualTo('version_date', $formVersionDate)
                ])
                ->order('version_date DESC');

            $arrFormVersionInfo = $this->_db2->fetchRow($select);
        }

        return $arrFormVersionInfo;
    }

    /**
     * Load latest form version info by form id
     *
     * @param int $formId
     * @return array
     */
    public function getLatestFormInfo($formId)
    {
        $arrFormVersionInfo = array();
        if (!empty($formId)) {
            $select = (new Select())
                ->from('form_version')
                ->where(['form_id' => (int)$formId])
                ->limit(1)
                ->offset(0)
                ->order('version_date DESC');

            $arrFormVersionInfo = $this->_db2->fetchRow($select);
        }

        return $arrFormVersionInfo;
    }

    /**
     * Load latest form version info by form version id
     *
     * @param int $pdfFormVersionId
     * @return array
     */
    public function getLatestFormVersionInfo($pdfFormVersionId)
    {
        $pdfId = 0;
        if (!empty($pdfFormVersionId)) {
            $select = (new Select())
                ->from('form_version')
                ->columns(['form_id'])
                ->where(['form_version_id' => (int)$pdfFormVersionId]);

            $pdfId = $this->_db2->fetchOne($select);
        }

        return $this->getLatestFormInfo($pdfId);
    }

    /**
     * Get path to pdf file by its version id, will be empty if file doesn't exists
     *
     * @param int $versionId
     * @return array
     */
    public function getPdfFilePathByVersionId($versionId)
    {
        $arrFormInfo = $this->getFormVersionInfo($versionId);

        $fileName = isset($arrFormInfo['file_name']) ? $arrFormInfo['file_name'] . '.pdf' : '';
        $filePath = $arrFormInfo['file_path'] ?? '';
        $realPath = empty($filePath) ? '' : $this->_config['directory']['pdfpath_physical'] . '/' . $filePath;

        if (empty($realPath) || !file_exists($realPath)) {
            $fileName = '';
            $realPath = '';
        }

        return array($realPath, $fileName);
    }

    /**
     * Get files (pdf forms) list by folder id
     *
     * @param  $folderId
     * @param  $booLoadAll
     * @param  $orderByField
     * @param  $orderBy
     * @param  $start
     * @param  $limit
     * @return array
     */
    public function getFormsByFolderId($folderId, $booLoadAll, $orderByField, $orderBy, $start, $limit)
    {
        $select = (new Select())
            ->from('form_upload')
            ->columns(['form_id'])
            ->where(['folder_id' => $folderId]);

        $arrPdfIds = $this->_db2->fetchCol($select);

        $arrResult    = array();
        $totalRecords = 0;
        if (is_array($arrPdfIds) && count($arrPdfIds) > 0) {
            if (!is_numeric($start)) {
                $start = 0;
            }

            if (!is_numeric($limit)) {
                $limit = 25;
            }

            $orderBy = strtoupper($orderBy ?? '');
            if ($orderBy !== 'DESC') {
                $orderBy = 'ASC';
            }

            switch ($orderByField) {
                case "date_uploaded":
                    $orderByField = 'uploaded_date';
                    break;

                case "date_version":
                    $orderByField = 'version_date';
                    break;

                case "size":
                    $orderByField = 'size';
                    break;

                case "file_name":
                default:
                    $orderByField = 'file_name';
                    break;
            }


            // Filter latest forms
            if (!$booLoadAll) {
                $select = (new Select())
                    ->from(['fv' => 'form_version'])
                    ->columns(['form_version_id', 'form_id', 'version_date'])
                    ->where(['fv.form_id' => $arrPdfIds])
                    ->order('fv.version_date DESC');

                $arrLastestRecords = $this->_db2->fetchAll($select);

                $arrPdfIds = [];
                foreach ($arrLastestRecords as $arrLastestRecordInfo) {
                    if (!isset($arrPdfIds[$arrLastestRecordInfo['form_id']])) {
                        $arrPdfIds[$arrLastestRecordInfo['form_id']] = $arrLastestRecordInfo['form_version_id'];
                    }
                }
                $arrPdfIds = array_values($arrPdfIds);

                $whereField = 'form_version_id';
            } else {
                $whereField = 'form_id';
            }

            $selectMain = (new Select())
                ->from(['v' => 'form_version'])
                ->where([$whereField => $arrPdfIds])
                ->limit($limit)
                ->offset($start)
                ->order($orderByField . ' ' . $orderBy);

            $arrResult    = $this->_db2->fetchAll($selectMain);
            $totalRecords = $this->_db2->fetchResultsCount($selectMain);

            if (!is_array($arrResult)) {
                $arrResult = array();
            }
        }

        return array('rows' => $arrResult, 'totalCount' => $totalRecords);
    }

    /**
     * Delete form versions (DB records + saved files) by their ids
     *
     * @param $arrVersionIds
     * @return bool
     */
    public function deleteVersionForms($arrVersionIds)
    {
        try {
            if (!is_array($arrVersionIds)) {
                return false;
            }

            if (count($arrVersionIds) > 0) {
                // Collect pdf form information
                $select = (new Select())
                    ->from('form_version')
                    ->columns(['form_version_id', 'form_id', 'file_path'])
                    ->where(['form_version_id' => $arrVersionIds]);

                $arrResult = $this->_db2->fetchAll($select);

                if (is_array($arrResult) && count($arrResult)) {
                    // Collect pdf form ids
                    $arrFormIds = array();
                    foreach ($arrResult as $arrFormVersionInfo) {
                        $arrFormIds[] = $arrFormVersionInfo['form_id'];
                    }
                    $arrFormIds = array_unique($arrFormIds);

                    // Delete default forms related to this version
                    $this->_db2->delete('form_default', ['form_version_id' => $arrVersionIds]);

                    // Delete form version records
                    $this->_db2->delete('form_version', ['form_version_id' => $arrVersionIds]);

                    $pdfPath = $this->_config['directory']['pdfpath_physical'] . '/';
                    foreach ($arrResult as $arrFormVersionInfo) {
                        // Delete pdf files
                        $this->_files->deleteFile($pdfPath . $arrFormVersionInfo['file_path']);

                        // Delete xod files
                        $xodFilePath = $this->_files->getConvertedXodFormPath($arrFormVersionInfo['file_path']);
                        if (!empty($xodFilePath)) {
                            $this->_files->deleteFile($xodFilePath);
                        }
                    }

                    // Check if we need remove from 'FormUpload' table
                    $select = (new Select())
                        ->from('form_version')
                        ->columns(['form_id'])
                        ->where(['form_id' => $arrFormIds])
                        ->group('form_id');

                    $arrAfterDeleteFormIds = $this->_db2->fetchCol($select);

                    $arrDeletedPdfForms = array_diff($arrFormIds, $arrAfterDeleteFormIds);
                    if (count($arrDeletedPdfForms)) {
                        // Delete from original table all orphan pdf forms
                        $this->_db2->delete('form_upload', ['form_id' => $arrDeletedPdfForms]);
                    }
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Search form version by name
     *
     * @param string $searchName
     * @param string|false $version Can be version timestamp, 'latest' for latest version or 'all' for all versions
     * @param bool $booExactMatch
     * @return array
     */
    public function searchFormByName($searchName, $version, $booExactMatch = false)
    {
        if ($version == 'latest') {
            $innerSelect = (new Select())
                ->from('form_version')
                ->columns(['max_version_date' => new Expression("MAX(version_date)"), 'form_id'])
                ->group('form_id');

            $select = (new Select())
                ->from('form_version')
                ->join(
                    array('p1' => $innerSelect),
                    'form_version.form_id = p1.form_id AND form_version.version_date = p1.max_version_date'
                )
                ->order('form_version.version_date DESC');
        } else {
            $select = (new Select())
                ->from('form_version')
                ->order(['form_id ASC', 'version_date DESC']);
            if ($version && ($version !== 'all')) {
                $versionTime = strtotime($version);
                if ($versionTime) {
                    $select
                        ->where((new Where())->lessThanOrEqualTo('version_date', date('c', $versionTime)))
                        ->limit(1);
                }
            }
        }

        
        $arrWhere = [(new Where())->notEqualTo('form_version.form_type', 'officio-form')];
        if ($searchName != '') {
            if ($booExactMatch) {
                $arrWhere['form_version.file_name'] = $searchName;
            } else {
                $arrWhere[] = (new Where())->like('form_version.file_name', '%' . $searchName . '%');
            }
        }
        $select->where($arrWhere);

        return $this->_db2->fetchAll($select);
    }


    /**
     * Check if form(s) are used somewhere
     * e.g. Assigned Forms or Templates
     *
     * @param array $arrFormVersionIds
     * @return bool used or not
     */
    public function isFormUsed($arrFormVersionIds)
    {
        $booUsed = false;

        // Check if some forms were assigned
        $count = (int)$this->getParent()->getFormAssigned()->getAssignedFormsCountByVersions($arrFormVersionIds);

        if (empty($count)) {
            // Check if some forms are used in 'templates' table
            $count2 = $this->getParent()->getFormTemplates()->getFormsCount($arrFormVersionIds);

            if (!empty($count2)) {
                $booUsed = true;
            }
        } else {
            $booUsed = true;
        }

        return $booUsed;
    }

    /**
     * Identify which format of the form is supported
     *
     * @param array $arrAssignedFormInfo
     * @return string pdf or html or angular or xod
     */
    public function getFormFormat($arrAssignedFormInfo)
    {
        $formVersionId = $arrAssignedFormInfo['form_version_id'];
        if ($arrAssignedFormInfo['form_type'] === 'officio-form') {
            $strFormFormat = 'officio-form';
        } elseif ($arrAssignedFormInfo['form_type'] != 'bar' && $this->isFormVersionXod($formVersionId)) {
            $strFormFormat = 'xod';
        } elseif ($this->isFormVersionAngular($formVersionId)) {
            $strFormFormat = 'angular';
        } elseif ($this->isFormVersionHtml($formVersionId)) {
            $strFormFormat = 'html';
        } else {
            $strFormFormat = 'pdf';
        }

        return $strFormFormat;
    }

    /**
     * Check if form version has pdf file
     *
     * @param $formVersionId
     * @return bool true if has pdf file
     */
    public function isFormVersionPdf($formVersionId)
    {
        $arrFormInfo = $this->getFormVersionInfo($formVersionId);

        $fileName    = $arrFormInfo['file_path'] ?? '';
        $pdfFilePath = empty($fileName) ? '' : $this->_config['directory']['pdfpath_physical'] . '/' . $fileName;

        return !empty($pdfFilePath) && file_exists($pdfFilePath) && filesize($pdfFilePath) > 0;
    }

    /**
     * Check if form version has html (angular) or pdf version
     *
     * @param $formVersionId
     * @return bool true if is html version
     */
    public function isFormVersionHtml($formVersionId)
    {
        $htmlFileName = $this->_files->getConvertedPDFFormPath(
                (int)$formVersionId
            ) . DIRECTORY_SEPARATOR . 'index.html';
        $phpFileName  = $this->_files->getConvertedPDFFormPath(
                (int)$formVersionId
            ) . DIRECTORY_SEPARATOR . 'index.php';

        return (file_exists($htmlFileName) && filesize($htmlFileName) > 0) || (file_exists($phpFileName) && filesize(
                    $phpFileName
                ) > 0);
    }

    /**
     * Check if form version has xod or pdf version
     *
     * @param $formVersionId
     * @return bool true if is html version
     */
    public function isFormVersionXod($formVersionId)
    {
        $booIsXod = false;

        $arrFormVersionInfo = $this->getFormVersionInfo($formVersionId);
        if (isset($arrFormVersionInfo['file_path'])) {
            $xodFileName = $this->_files->getConvertedXodFormPath($arrFormVersionInfo['file_path']);
            $booIsXod    = !empty($xodFileName) && file_exists($xodFileName) && filesize($xodFileName) > 0;
        }

        return $booIsXod;
    }

    /**
     * Check if form version has angular version
     *
     * @param $formVersionId
     * @return bool true if is angular version
     */
    public function isFormVersionAngular($formVersionId)
    {
        $htmlFileName = $this->_files->getConvertedPDFFormPath(
                (int)$formVersionId
            ) . DIRECTORY_SEPARATOR . 'Form';

        return is_dir($htmlFileName);
    }

    /**
     * Check if pdf form version files exist in db
     *
     * @return array
     */
    public function checkFormPdfFilesExist()
    {
        $arrResult = array();

        $select = (new Select())
            ->from('form_version');

        $arrForms = $this->_db2->fetchAll($select);

        foreach ($arrForms as $form) {
            $fileName = $form['file_path'];
            $realPath = $this->_config['directory']['pdfpath_physical'] . '/' . $fileName;

            if (!file_exists($realPath)) {
                $arrResult[] = array(
                    'id'   => $form['form_version_id'],
                    'name' => $form['file_name']
                );
            }
        }

        $totalCount = count($arrResult);

        return array($arrResult, $totalCount);
    }

    /**
     * Load forms with unique form id
     *
     * @return array
     */
    public function getFormVersionsWithUniqueFormId()
    {
        $select = (new Select())
            ->from('form_version')
            ->columns(['form_id' => 'form_id', 'form_name' => 'file_name'])
            ->group('form_id')
            ->order('file_name ASC');

        return $this->_db2->fetchAll($select);
    }

}
