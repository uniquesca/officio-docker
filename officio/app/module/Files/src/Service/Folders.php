<?php

namespace Files\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\ServiceManager\ServiceManager;
use Officio\Common\SubServiceInterface;
use Officio\Common\SubServiceOwner;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Folders extends SubServiceOwner implements SubServiceInterface
{

    /** @var Files */
    protected $_parent;

    /** @var FolderAccess */
    protected $_folderAccess;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * @return FolderAccess
     */
    public function getFolderAccess()
    {
        if (is_null($this->_folderAccess)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_folderAccess = $this->_serviceContainer->build(FolderAccess::class, ['parent' => $this]);
            } else {
                $this->_folderAccess = $this->_serviceContainer->get(FolderAccess::class);
                $this->_folderAccess->setParent($this);
            }
        }
        return $this->_folderAccess;
    }

    /**
     * Check if currently logged in user has access to specific folder:
     * 1. If he is an author OR
     * 2. Folder type is STR (Root Shared Templates)
     *
     * @param $folderId
     * @param string $action (create/rename/delete)
     * @return bool true if has access
     */
    public function hasCurrentMemberAccessToFolder($folderId, $action)
    {
        $booHasAccess = false;
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $booHasAccess = true;
        } else {
            $arrFolderInfo = $this->getFolderInfo($folderId, $this->_auth->getCurrentUserCompanyId());

            if (is_array($arrFolderInfo) && count($arrFolderInfo)) {
                switch ($action) {
                    case 'create':
                        if ($arrFolderInfo['type'] == 'T') {
                            $booHasAccess = true;
                        } else {
                            $booHasAccess = $arrFolderInfo['type'] == 'STR' || $arrFolderInfo['author_id'] == $this->_auth->getCurrentUserId();
                        }
                        break;

                    case 'rename':
                    case 'delete':
                        if (in_array($arrFolderInfo['type'], array('STR', 'T'))) {
                            $booHasAccess = empty($arrFolderInfo['parent_id']) ? false : $arrFolderInfo['author_id'] == $this->_auth->getCurrentUserId();
                        } else {
                            $booHasAccess = $arrFolderInfo['author_id'] == $this->_auth->getCurrentUserId();
                        }
                        break;

                    default:
                        break;
                }
            }
        }

        return $booHasAccess;
    }


    /**
     * Check if current user has access to the folder
     *
     * @param int $folderId
     * @return bool true if user has access
     */
    public function hasAccessCurrentMemberToDefaultFolders($folderId)
    {
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $booHasAccess = true;
        } else {
            $arrFolderInfo = $this->getFolderInfo($folderId, $this->_auth->getCurrentUserCompanyId());
            $booHasAccess  = is_array($arrFolderInfo) && count($arrFolderInfo);
        }

        return $booHasAccess;
    }

    /**
     * Load folders list for default company (will be copied to new companies)
     *
     * @param ?int $companyId
     * @param string $type
     * @return array
     */
    public function getDefaultFolders($companyId = null, $type = '')
    {
        $select = (new Select())
            ->from('u_folders')
            ->order('parent_id ASC');

        if (is_numeric($companyId)) {
            $select->where->equalTo('company_id', $companyId);
        } else {
            $select->where->isNull('company_id');
        }


        if (!empty($type)) {
            $select->where->equalTo('type', $type);
        }

        return $this->_db2->fetchAll($select);
    }

    /**
     * @param ?int $fromCompanyId
     * @return mixed|null
     */
    public function getDefaultSharedFolderId($fromCompanyId = null)
    {
        $select = (new Select())
            ->from('u_folders')
            ->columns(['folder_id'])
            ->where(['type' => 'STR']);

        if (is_null($fromCompanyId)) {
            $select->where->isNull('company_id');
        } else {
            $select->where->equalTo('company_id', (int)$fromCompanyId);
        }

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load company folders list
     *
     * @param $companyId
     * @param int|array $parentFolderId
     * @param null $arrType
     * @param bool $booIdsOnly
     * @param bool $booLoadSubFolders
     * @return array
     */
    public function getCompanyFolders($companyId, $parentFolderId = 0, $arrType = null, $booIdsOnly = false, $booLoadSubFolders = false)
    {
        $whatSelect = $booIdsOnly ? 'folder_id' : Select::SQL_STAR;

        $select = (new Select())
            ->from('u_folders')
            ->columns([$whatSelect])
            ->where(
                [
                    'parent_id' => $parentFolderId
                ]
            )
            ->order('folder_name ASC');

        if (is_null($companyId)) {
            $select->where->isNull('company_id');
        } else {
            $select->where->equalTo('company_id', (int)$companyId);
        }

        if (is_null($arrType)) {
            $select->where->in('type', array('CD', 'C', 'F', 'SD', 'SDR'));
        } elseif (is_array($arrType) && count($arrType)) {
            $select->where(
                [
                    'type'=> $arrType
                ]
            );
        }


        $arrResult = $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
        $arrIds = array();
        if (!$booIdsOnly) {
            foreach ($arrResult as $arrInfo) {
                $arrIds[] = $arrInfo['folder_id'];
            }
        } else {
            $arrIds = $arrResult;
        }

        if ($booLoadSubFolders && count($arrIds)) {
            $arrSubFolders = $this->getCompanyFolders($companyId, $arrIds, $arrType, $booIdsOnly, $booLoadSubFolders);
            $arrResult = array_merge($arrResult, $arrSubFolders);
        }


        return $arrResult;
    }

    /**
     * Load folder(s) info by id(s)
     * If parent folder id will be provided - all child folders will be loaded only
     *
     * @param array $arrFolderIds
     * @param int $parentFolderId
     * @return array
     */
    public function getFoldersInfo($arrFolderIds, $parentFolderId = 0)
    {
        $arrInfo = array();

        try {
            if (is_array($arrFolderIds) && count($arrFolderIds)) {
                $select = (new Select())
                    ->from('u_folders')
                    ->where(
                        [
                            'folder_id' => $arrFolderIds,
                            'parent_id' => (int)$parentFolderId,
                            'type'      => array('CD', 'C', 'F', 'SD', 'SDR')
                        ]
                    )
                    ->order('folder_name ASC');

                $arrInfo = $this->_db2->fetchAll($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrInfo;
    }

    /**
     * Load folder name by type for specific company
     *
     * @param int $companyId
     * @param string $type
     * @return string
     */
    public function getCompanyFolderName($companyId, $type = 'C')
    {
        try {
            $select = (new Select())
                ->from('u_folders')
                ->columns(['folder_name'])
                ->where(
                    [
                        'company_id' => (int)$companyId,
                        'type' => $type
                    ]
                );

            $folderName = $this->_db2->fetchOne($select);
        } catch (Exception $e) {
            $folderName = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $folderName;
    }

    /**
     * Load folder info by folder id and company id
     *
     * @param int $folderId
     * @param int $companyId
     * @param bool $booAllowNull
     * @return array
     */
    public function getFolderInfo($folderId, $companyId = null, $booAllowNull = false)
    {
        $arrInfo = array();

        try {
            $select = (new Select())
                ->from('u_folders')
                ->where(
                    [
                        'folder_id' => $folderId
                    ]
                );

            if (isset($companyId)) {
                $select->where->equalTo('company_id', (int)$companyId);
            } else {
                if ($booAllowNull) {
                    $select->where->isNull('company_id');
                }
            }

            $arrInfo = $this->_db2->fetchRow($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrInfo;
    }

    /**
     * Create new folder record
     *
     * @param $companyId
     * @param $authorId
     * @param $parentFolderId
     * @param $folderName
     * @param $folderType
     * @return int created folder id, empty on error
     */
    public function createFolder($companyId, $authorId, $parentFolderId, $folderName, $folderType)
    {
        try {
            $arrToInsert = array(
                'parent_id'   => (int)$parentFolderId,
                'company_id'  => is_null($companyId) ? null : (int)$companyId,
                'author_id'   => (int)$authorId,
                'folder_name' => $folderName,
                'upd_date'    => date('c'),
                'type'        => $folderType
            );

            $folderId = $this->_db2->insert('u_folders', $arrToInsert);
        } catch (Exception $e) {
            $folderId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $folderId;
    }

    /**
     * Update folder's info
     *
     * @param $folderId
     * @param $folderName
     * @param null $authorId
     * @return bool true on success
     */
    public function updateFolder($folderId, $folderName, $authorId = null)
    {
        try {
            $arrToUpdate = array(
                'folder_name' => $folderName,
                'upd_date' => date('c')
            );

            if (isset($authorId) && is_numeric($authorId)) {
                $arrToUpdate['author_id'] = (int)$authorId;
            }

            $this->_db2->update('u_folders', $arrToUpdate, ['folder_id' => $folderId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete folder(s) by id(s)
     *
     * @param array $arrFolderIds
     * @return bool true on success
     */
    public function deleteFolders($arrFolderIds)
    {
        $booSuccess = false;

        try {
            if (is_array($arrFolderIds) && count($arrFolderIds)) {
                $this->_db2->delete('u_folders', ['folder_id' => $arrFolderIds]);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load all folders/subfolders ids for specific folder
     *
     * @param int $folderId
     * @param array &$arrSubFolderIds - result folder ids will be returned here
     */
    public function getSubFolderIds($folderId, &$arrSubFolderIds)
    {
        $select = (new Select())
            ->from('u_folders')
            ->columns(['folder_id'])
            ->where(
                [
                    'parent_id' => $folderId
                ]
            );

        $arrSubFolders = $this->_db2->fetchCol($select);

        if (count($arrSubFolders)) {
            // There are subfolders too
            foreach ($arrSubFolders as $subFolderId) {
                $this->getSubFolderIds($subFolderId, $arrSubFolderIds);
            }
        }
        $arrSubFolderIds[] = $folderId;
    }

    /**
     * Load folders with "template" type for specific parent folder and company
     *
     * @param int $companyId
     * @param int $parentFolderId
     * @param int $authorId
     * @return array
     */
    public function getTemplateFolders($companyId, $parentFolderId, $authorId = null)
    {
        try {
            $select = (new Select())
                ->from('u_folders')
                ->where(
                    [
                        'company_id' => (int)$companyId,
                        'parent_id' => (int)$parentFolderId,
                        'type' => array('T', 'ST', 'STR')
                    ]
                )
                ->order(array('type ASC', 'folder_name ASC'));

            if (isset($authorId)) {
                $select->where([
                    (new Where())
                    ->nest()
                    ->equalTo('author_id', $authorId)
                    ->or
                    ->equalTo('parent_id', 0)
                    ->or
                    ->equalTo('type', 'STR')
                    ->unnest()
                ]);
            }

            $arrFolders = $this->_db2->fetchAll($select);
        } catch (Exception $e) {
            $arrFolders = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrFolders;
    }

    /**
     * Load folders info and their access rights
     *
     * @param $arrFolders
     * @return array
     */
    public function getSubmittedFoldersInfo($arrFolders)
    {
        $arrResult = array();

        if (is_array($arrFolders) && !empty($arrFolders)) {
            $arrFoldersIds = array_keys($arrFolders);

            $arrFoldersInfo = $this->getFoldersInfo($arrFoldersIds);

            foreach ($arrFoldersInfo as $folder) {
                switch ($arrFolders[$folder['folder_id']]) {
                    case 1:
                        $access = 'R';
                        break;

                    case 2:
                        $access = 'RW';
                        break;

                    default:
                        $access = '';
                        break;
                }

                $arrResult[] = array(
                    'folder_id' => $folder['folder_id'],
                    'folder_name' => $folder['folder_name'],
                    'access' => $access
                );
            }
        }

        return $arrResult;
    }

    /**
     * Load default folders for specific company and role
     *
     * @param $companyId
     * @param $roleId
     * @return array
     */
    public function getDefaultFoldersByRoleId($companyId, $roleId)
    {
        $arrResult = array();

        $companyId  = empty($companyId) && $this->_auth->isCurrentUserSuperadmin() ? null : $companyId;
        $arrFolders = $this->getCompanyFolders($companyId, 0, null, false, true);

        foreach ($arrFolders as $key => $folder) {
            $arrFolderAccessInfo = $this->getFolderAccess()->getFolderAccessInfoByRoleAndFolder($roleId, $folder['folder_id']);
            $arrFolders[$key]['access'] = $arrFolderAccessInfo['access'] ?? '';
        }

        foreach ($arrFolders as $folder) {
            if (empty($folder['parent_id']) && !empty($folder['folder_id'])) {
                $folderWithChildren = $folder;
                $folderWithChildren['children'] = $this->_getChildFolders($folder['folder_id'], $arrFolders);
                $arrResult[] = $folderWithChildren;
            }
        }

        return $arrResult;
    }

    /**
     * Load inner folders for specific folders
     *
     * @param $folderId
     * @param $arrFolders
     * @return array
     */
    private function _getChildFolders($folderId, &$arrFolders)
    {
        $result = array();
        foreach ($arrFolders as $folder) {
            if ($folder['parent_id'] == $folderId) {
                $folder['children'] = $this->_getChildFolders($folder['folder_id'], $arrFolders);
                $result[] = $folder;
            }
        }

        return $result;
    }
}
