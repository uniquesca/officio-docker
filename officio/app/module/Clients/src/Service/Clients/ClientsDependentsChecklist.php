<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use DateTime;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Service\BaseService;
use Uniques\Php\StdLib\FileTools;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;
use Laminas\Db\Sql\Expression;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ClientsDependentsChecklist extends BaseService implements SubServiceInterface
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_parent;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_files = $services[Files::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Load list of uploaded files for the member
     *
     * @param int $memberId
     * @return array
     */
    public function getUploadedFilesList($memberId)
    {
        $select = (new Select())
            ->from('client_form_dependents_uploaded_files')
            ->where(
                [
                    'member_id' => (int)$memberId
                ]
            );

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load count of uploaded files for clients
     *
     * @param $arrMemberIds
     * @return array
     */
    public function getClientsUploadedFilesCount($arrMemberIds)
    {
        $select = (new Select())
            ->from('client_form_dependents_uploaded_files')
            ->columns(array('member_id', 'count' => new Expression('COUNT(*)')))
            ->where(
                [
                    'member_id' => $arrMemberIds
                ]
            )
            ->group('member_id');

        $result = $this->_db2->fetchAll($select);

        $arrResult = [];
        foreach ($result as $row) {
            $arrResult[$row['member_id']] = $row['count'];
        }

        return $arrResult;
    }

    /**
     * Load info about the uploaded file
     *
     * @param int $uploadedFileId
     * @return array
     */
    public function getUploadedFileInfo($uploadedFileId)
    {
        $select = (new Select())
            ->from('client_form_dependents_uploaded_files')
            ->where(
                [
                    'uploaded_file_id' => (int)$uploadedFileId
                ]
            );

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load list of assigned tags to the uploaded file
     *
     * @param int $uploadedFileId
     * @return array
     */
    public function getUploadedFileTags($uploadedFileId)
    {
        $select = (new Select())
            ->from('client_form_dependents_uploaded_file_tags')
            ->columns(['tag'])
            ->where(
                [
                    'uploaded_file_id' => (int)$uploadedFileId
                ]
            )
            ->order('tag');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load list of all unique tags already assigned to uploaded files
     *
     * @param $memberId
     * @return array
     */
    public function getGroupedTags($memberId)
    {
        $arrMemberInfo = $this->getParent()->getMemberInfo($memberId);

        $select = (new Select())
            ->from('client_form_dependents_uploaded_file_tags')
            ->columns(['tag'])
            ->where(['company_id' => (int)$arrMemberInfo['company_id']])
            ->group('tag')
            ->order('tag');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load list of all tags already assigned to uploaded files for clients
     *
     * @param $arrMemberIds
     * @return array
     */
    public function getClientsUploadedFileTags($arrMemberIds)
    {
        $select = (new Select())
            ->from(array('t' => 'client_form_dependents_uploaded_file_tags'))
            ->columns(['tag'])
            ->join(array('f' => 'client_form_dependents_uploaded_files'), 't.uploaded_file_id = f.uploaded_file_id', 'member_id', Select::JOIN_LEFT)
            ->where(['f.member_id' => $arrMemberIds]);

        $arrClientUploadedFileTags = $this->_db2->fetchAll($select);

        $arrResult = array();
        foreach ($arrClientUploadedFileTags as $tagInfo) {
            $arrResult[$tagInfo['member_id']][] = $tagInfo['tag'];
        }

        return $arrResult;
    }

    /**
     * Set tag(s) to the uploaded file
     *
     * @param $memberId
     * @param int $uploadedFileId
     * @param array $arrTags
     * @return bool
     */
    public function setUploadedFileTags($memberId, $uploadedFileId, $arrTags)
    {
        $booSuccess = false;

        try {
            $arrMemberInfo = $this->getParent()->getMemberInfo($memberId);

            $this->_db2->delete('client_form_dependents_uploaded_file_tags', ['uploaded_file_id' => (int)$uploadedFileId]);

            $filter = new StripTags();
            foreach ($arrTags as $tag) {
                $this->_db2->insert(
                    'client_form_dependents_uploaded_file_tags',
                    [
                        'company_id'       => (int)$arrMemberInfo['company_id'],
                        'uploaded_file_id' => (int)$uploadedFileId,
                        'tag'              => $filter->filter($tag['tag']),
                    ]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Reassign uploaded file
     *
     * @param int $memberId
     * @param int $dependentId
     * @param int $uploadedFileId
     * @return bool
     */
    public function reassignFile($memberId, $dependentId, $uploadedFileId)
    {
        $booSuccess = false;

        try {
            $this->_db2->update(
                'client_form_dependents_uploaded_files',
                [
                    'member_id'    => $memberId,
                    'dependent_id' => empty($dependentId) ? null : $dependentId
                ],
                [
                    'member_id'        => (int)$memberId,
                    'uploaded_file_id' => (int)$uploadedFileId
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load list of required forms/files for the company
     *
     * @param $companyId
     * @return array
     */
    public function getRequiredFilesList($companyId)
    {
        $select = (new Select())
            ->from('client_form_dependents_required_files')
            ->where(['company_id' => (int)$companyId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load info about the required file
     *
     * @param int $requiredFileId
     * @return array
     */
    public function getRequiredFileInfo($requiredFileId)
    {
        $select = (new Select())
            ->from('client_form_dependents_required_files')
            ->where(['required_file_id' => (int)$requiredFileId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load list of required forms and uploaded files grouped by dependents for the member
     *
     * @param int $memberId
     * @return array
     */
    public function getListForCase($memberId)
    {
        try {
            $arrMemberInfo = $this->_parent->getMemberInfo($memberId);
            $arrRequiredFiles = $this->getRequiredFilesList($arrMemberInfo['company_id']);
            $arrUploadedFiles = $this->getUploadedFilesList($memberId);
            $arrDependents = $this->_parent->getFamilyMembersForClient($memberId);

            $arrResult = array();

            foreach ($arrRequiredFiles as $arrRequiredFileInfo) {
                $booShow = false;
                $booRequired = false;
                $arrThisFormFiles = array();
                $arrDependentsHaveAccess = $arrDependentsHaveFiles = array();

                foreach ($arrDependents as $arrDependentInfo) {
                    $booShowFiles = false;

                    if (in_array($arrDependentInfo['id'], array('sponsor', 'employer'))) {
                        continue;
                    }

                    if ($arrDependentInfo['id'] === 'main_applicant') {
                        $booShowFiles = $arrRequiredFileInfo['main_applicant_show'] === 'Y';
                        $booRequired = $booRequired || $arrRequiredFileInfo['main_applicant_required'] === 'Y';
                    } elseif (!empty($arrDependentInfo['DOB'])) {
                        $birthDate = new DateTime($arrDependentInfo['DOB']);
                        $currentDate = new DateTime();

                        $diff = $birthDate->diff($currentDate);

                        if ($diff->y >= 18) {
                            $showKey = 'adult_show';
                            $requiredKey = 'adult_required';
                        } elseif ($diff->y >= 16) {
                            $showKey = 'minor_16_and_above_show';
                            $requiredKey = 'minor_16_and_above_required';
                        } else {
                            $showKey = 'minor_less_16_show';
                            $requiredKey = 'minor_less_16_required';
                        }

                        $booShowFiles = $arrRequiredFileInfo[$showKey] === 'Y';
                        $booRequired = $booRequired || $arrRequiredFileInfo[$requiredKey] === 'Y';
                    }

                    $booShow = $booShow || $booShowFiles;

                    if ($booShowFiles) {
                        $arrDependentsHaveAccess[$arrDependentInfo['real_id']] = $arrDependentInfo['value'];
                        foreach ($arrUploadedFiles as $arrUploadedFileInfo) {
                            $arrUploadedFileInfo['dependent_id'] = empty($arrUploadedFileInfo['dependent_id']) ? 0 : $arrUploadedFileInfo['dependent_id'];
                            if ($arrUploadedFileInfo['dependent_id'] == $arrDependentInfo['real_id'] && $arrUploadedFileInfo['required_file_id'] == $arrRequiredFileInfo['required_file_id']) {
                                $arrThisFormFiles[] = array(
                                    'id' => $arrUploadedFileInfo['uploaded_file_id'],
                                    'text' => $arrUploadedFileInfo['uploaded_file_name'],
                                    'tag' => $this->getUploadedFileTags($arrUploadedFileInfo['uploaded_file_id']),
                                    'dependent' => $arrDependentInfo['value'],
                                    'dependent_id' => $arrUploadedFileInfo['dependent_id'],
                                    'uiProvider' => 'col',
                                    'is_file' => true,
                                    'iconCls' => $this->_files->getFileIcon(FileTools::getMimeByFileName($arrUploadedFileInfo['uploaded_file_name'])),
                                    'leaf' => true
                                );
                                $arrDependentsHaveFiles[$arrDependentInfo['real_id']] = $arrDependentInfo['value'];
                            }
                        }
                    }
                }

                if ($booShow) {
                    $arrMissedDependents = array_diff($arrDependentsHaveAccess, $arrDependentsHaveFiles);
                    $strMissedDependents = '';

                    if (!empty($arrMissedDependents) && $booRequired) {
                        $strMissedDependents = 'Missing ' . $arrRequiredFileInfo['required_file_description'] . ' for:';
                        foreach ($arrMissedDependents as $missedDependent) {
                            $strMissedDependents .= '<br>' . $missedDependent;
                        }
                    }

                    $arrResult[] = array(
                        'text'              => $arrRequiredFileInfo['required_file_description'],
                        'cls'               => $booRequired && !empty($arrMissedDependents) ? 'node-required' : '',
                        'iconCls'           => 'far fa-folder',
                        'uiProvider'        => 'col',
                        'is_form'           => true,
                        'required'          => $booRequired,
                        'el_id'             => $arrRequiredFileInfo['required_file_id'],
                        'children'          => $arrThisFormFiles,
                        'missed_dependents' => $strMissedDependents
                    );
                }
            }
        } catch (Exception $e) {
            $arrResult = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Upload files (create record in DB + upload to server/S3) related to the dependent and specific required form
     *
     * @param int $memberId
     * @param int $dependentId
     * @param int $requiredFileId
     * @param array $arrFiles
     * @return bool
     */
    public function uploadChecklistFiles($memberId, $dependentId, $requiredFileId, $arrFiles)
    {
        $booSuccess = false;

        try {
            $arrMemberInfo = $this->getParent()->getMemberInfo($memberId);
            $booLocal      = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);

            $pathToDir = $this->_files->getCompanyDependantsChecklistPath($arrMemberInfo['company_id'], $memberId, $dependentId, $booLocal);
            foreach ($arrFiles as $file) {
                // overwrite files with the same name for the dependent
                $select = (new Select())
                    ->from('client_form_dependents_uploaded_files')
                    ->columns(['uploaded_file_id'])
                    ->where(
                        [
                            'required_file_id'   => (int)$requiredFileId,
                            'member_id'          => (int)$memberId,
                            'uploaded_file_name' => $file['name']
                        ]
                    );

                if (empty($dependentId)) {
                    $select->where->isNull('dependent_id');
                } else {
                    $select->where->equalTo('dependent_id', (int)$dependentId);
                }

                $arrDuplicateFileIds = $this->_db2->fetchCol($select);

                foreach ($arrDuplicateFileIds as $duplicateFileId) {
                    $this->deleteUploadedFile($duplicateFileId);
                }

                $newFileId = $this->_db2->insert(
                    'client_form_dependents_uploaded_files',
                    [
                        'required_file_id'   => $requiredFileId,
                        'member_id'          => $memberId,
                        'dependent_id'       => empty($dependentId) ? null : $dependentId,
                        'uploaded_file_name' => $file['name'],
                        'uploaded_file_size' => $file['size'],
                    ]
                );

                $pathToSave = $pathToDir . '/' . $newFileId;
                if ($booLocal) {
                    $this->_files->createFTPDirectory($pathToDir);
                    $booSuccess = move_uploaded_file($file['tmp_name'], $pathToSave);
                } else {
                    $this->_files->createCloudDirectory($pathToDir);
                    $booSuccess = $this->_files->getCloud()->uploadFile($file['tmp_name'], $pathToSave);
                }

                if (!$booSuccess) {
                    break;
                }
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booSuccess;
    }

    /**
     * Delete uploaded file
     *
     * @param int $uploadedFileId
     * @return bool
     */
    public function deleteUploadedFile($uploadedFileId)
    {
        $booSuccess = false;

        try {
            $arrUploadedFileInfo = $this->getUploadedFileInfo($uploadedFileId);

            if (isset($arrUploadedFileInfo['member_id'])) {
                $memberId = $arrUploadedFileInfo['member_id'];
                $dependentId = $arrUploadedFileInfo['dependent_id'];
                $dependentId = empty($dependentId) ? 0 : $dependentId;

                $arrMemberInfo = $this->_parent->getMemberInfo($arrUploadedFileInfo['member_id']);
                $booLocal = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);

                $pathToFile = $this->_files->getCompanyDependantsChecklistPath($arrMemberInfo['company_id'], $memberId, $dependentId, $booLocal) . '/' . $arrUploadedFileInfo['uploaded_file_id'];
                $booSuccess = $this->_files->deleteFile($pathToFile, $booLocal);

                if ($booSuccess) {
                    $this->_db2->delete('client_form_dependents_uploaded_files', ['uploaded_file_id' => (int)$arrUploadedFileInfo['uploaded_file_id']]);
                }
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booSuccess;
    }

    /**
     * Delete checklist uploaded files for member/dependent
     *
     * @param int $memberId
     * @param int $dependentId
     * @return bool
     */
    public function deleteDependentUploadedFiles($memberId, $dependentId)
    {
        $booSuccess = false;

        try {
            $arrMemberInfo = $this->_parent->getMemberInfo($memberId);
            if (isset($arrMemberInfo['company_id']) && !empty($arrMemberInfo['company_id'])) {
                $booLocal = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);
                $pathToDir = $this->_files->getCompanyDependantsChecklistPath($arrMemberInfo['company_id'], $memberId, $dependentId, $booLocal) . '/';
                $this->_files->deleteFolder($pathToDir, $booLocal);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Download uploaded file
     * @param int $uploadedFileId
     * @return FileInfo|string
     */
    public function downloadFile($uploadedFileId)
    {
        try {
            $arrUploadedFileInfo = $this->getUploadedFileInfo($uploadedFileId);

            if (isset($arrUploadedFileInfo['member_id'])) {
                $memberId = $arrUploadedFileInfo['member_id'];
                $dependentId = $arrUploadedFileInfo['dependent_id'];
                $dependentId = empty($dependentId) ? 0 : $dependentId;

                if ($this->_parent->hasCurrentMemberAccessToMember($memberId)) {
                    $arrMemberInfo = $this->_parent->getMemberInfo($arrUploadedFileInfo['member_id']);
                    $booLocal = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);
                    $pathToFile = $this->_files->getCompanyDependantsChecklistPath($arrMemberInfo['company_id'], $memberId, $dependentId, $booLocal) . '/' . $arrUploadedFileInfo['uploaded_file_id'];
                    return new FileInfo($arrUploadedFileInfo['uploaded_file_name'], $pathToFile, $booLocal);
                } else {
                    $strError = 'No access to this file.';
                }
            } else {
                $strError = 'Internal error';
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Generates list of required documents which don't have uploaded files yet.
     * The result list will be grouped by dependents.
     *
     * @param int $memberId
     * @return array
     */
    public function getMissingRequiredFilesList($memberId)
    {
        $arrMissingFiles = array();

        try {
            $arrFullList = $this->getListForCase($memberId);
            foreach ($arrFullList as $arrDependentData) {
                foreach ($arrDependentData['children'] as $arrDependentFormData) {
                    if ($arrDependentFormData['required'] && empty($arrDependentFormData['children'])) {
                        $arrMissingFiles[$arrDependentData['text']][] = $arrDependentFormData['text'];
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMissingFiles;
    }
}
