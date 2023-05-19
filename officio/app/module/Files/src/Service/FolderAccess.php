<?php

namespace Files\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class FolderAccess extends BaseService implements SubServiceInterface
{

    /** @var Folders */
    protected $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Load folders access by role ids
     *
     * @param array $arrRoleIds
     * @return array
     */
    public function getFoldersAccessByRoles($arrRoleIds)
    {
        $arrFoldersAccess = array();

        if (is_array($arrRoleIds) && count($arrRoleIds)) {
            $select = (new Select())
                ->from('folder_access')
                ->where(['role_id' => $arrRoleIds]);

            $arrFoldersAccess = $this->_db2->fetchAll($select);
        }

        return $arrFoldersAccess;
    }

    /**
     * Load folder access records by arrays of roles and folders
     *
     * @param $arrRoleIds
     * @param $arrFolderIds
     * @return array
     */
    public function getFoldersAccessByRolesAndFolders($arrRoleIds, $arrFolderIds)
    {
        $arrFoldersAccess = array();

        if (is_array($arrRoleIds) && count($arrRoleIds) && is_array($arrFolderIds) && count($arrFolderIds)) {
            $select = (new Select())
                ->from('folder_access')
                ->columns(array('folder_id', 'role_id', 'access'))
                ->where(
                    [
                        'role_id'   => $arrRoleIds,
                        'folder_id' => $arrFolderIds
                    ]
                );

            $arrFoldersAccess = $this->_db2->fetchAll($select);
        }

        return $arrFoldersAccess;
    }

    /**
     * Load folder access record by role id and folder id
     *
     * @param $intRoleId
     * @param $intFolderId
     * @return array
     */
    public function getFolderAccessInfoByRoleAndFolder($intRoleId, $intFolderId)
    {
        $arrFoldersAccess = array();

        if (is_numeric($intRoleId) && is_numeric($intFolderId)) {
            $select = (new Select())
                ->from('folder_access')
                ->where(
                    [
                        'role_id'   => (int)$intRoleId,
                        'folder_id' => (int)$intFolderId
                    ]
                );

            $arrFoldersAccess = $this->_db2->fetchRow($select);
        }

        return $arrFoldersAccess;
    }

    /**
     * Create/update/delete folder access records by specific role id and array of folders
     * @param $arrFolders
     * @param $arrCompanyFolders
     * @param $roleId
     * @return string error, empty on success
     */
    public function createUpdateDefaultFolderAccess($arrFolders, $arrCompanyFolders, $roleId)
    {
        $strError = '';

        try {
            $checkedIds = array();
            foreach ($arrCompanyFolders as $arrCompanyFolderInfo) {
                $folderId = $arrCompanyFolderInfo['folder_id'];

                // Search for sub folders for this folder
                $children = array();
                foreach ($arrCompanyFolders as $arrThisCompanyFolderInfo) {
                    if ($arrThisCompanyFolderInfo['parent_id'] == $folderId) {
                        $children[] = $arrThisCompanyFolderInfo['folder_id'];
                    }
                }

                if (!empty($children)) {
                    //Prevent duplication in code execution if there are a lot of hierarchy folder branches
                    $children = array_diff($children, $checkedIds);
                    array_unshift($children, $folderId);


                    for ($i = 0; $i < count($children) - 1; $i++) {
                        $folderFirstInfo  = array();
                        $folderSecondInfo = array();
                        foreach ($arrCompanyFolders as $arrThisCompanyFolderInfo) {
                            if ($arrThisCompanyFolderInfo['folder_id'] == $children[$i]) {
                                $folderFirstInfo = $arrThisCompanyFolderInfo;
                            }

                            if ($arrThisCompanyFolderInfo['folder_id'] == $children[$i + 1]) {
                                $folderSecondInfo = $arrThisCompanyFolderInfo;
                            }
                        }

                        if ($arrFolders[$children[$i]] < $arrFolders[$children[$i + 1]] && $folderFirstInfo['parent_id'] != $folderSecondInfo['parent_id']) {
                            $strError = 'Some sub folder(s) has greater access level than its parent folder. Check folders access level in Default Documents Tab.';
                            break 2;
                        }

                        //Check situation if parent has few child folders with different access level but in one  hierarchy level
                        if ($folderFirstInfo['parent_id'] == $folderSecondInfo['parent_id']) {
                            if ($arrFolders[$folderFirstInfo['parent_id']] < $arrFolders[$children[$i]] || $arrFolders[$folderFirstInfo['parent_id']] < $arrFolders[$children[$i + 1]]) {
                                $strError = "Some sub folder(s) has greater access level than its parent folder. Check folders access level in Default Documents Tab.";
                                break 2;
                            }
                        }
                    }

                    $checkedIds = array_merge($children, $checkedIds);
                }
            }


            if (empty($strError)) {
                foreach ($arrCompanyFolders as $arrCompanyFolderInfo) {
                    $folderId            = $arrCompanyFolderInfo['folder_id'];
                    $arrFolderAccessInfo = $this->getFolderAccessInfoByRoleAndFolder($roleId, $folderId);
                    $folderAccess        = $arrFolderAccessInfo['access'] ?? '';

                    //get new access value
                    $newAccess = false;
                    if (isset($arrFolders[$folderId]) && $arrFolders[$folderId] > 0) {
                        $newAccess = $arrFolders[$folderId] == 1 ? 'R' : 'RW';
                    }

                    //insert/update info
                    if ($newAccess) { //Read & Write
                        if ($folderAccess === $newAccess) { //already in DB
                            continue;
                        }

                        if ($folderAccess) { //update
                            $this->_db2->update(
                                'folder_access',
                                ['access' => $newAccess],
                                [
                                    'folder_id' => $folderId,
                                    'role_id'   => $roleId
                                ]
                            );
                        } else { //create new record
                            $this->_db2->insert(
                                'folder_access',
                                [
                                    'folder_id' => $folderId,
                                    'role_id'   => $roleId,
                                    'access'    => $newAccess
                                ]
                            );
                        }
                    } else {
                        if ($folderAccess) { //remove info
                            $this->_db2->delete(
                                'folder_access',
                                [
                                    'folder_id' => $folderId,
                                    'role_id'   => $roleId
                                ]
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Create folder access records by array of roles and folders
     * @param $rolesMapping
     * @param $arrFoldersMapping
     */
    public function createDefaultFolderAccess($rolesMapping, $arrFoldersMapping)
    {
        $arrFolderAccess = $this->getFoldersAccessByRolesAndFolders(
            array_keys($rolesMapping),
            array_keys($arrFoldersMapping)
        );

        foreach ($arrFolderAccess as $fa) {
            $fa['folder_id'] = $arrFoldersMapping[$fa['folder_id']];
            $fa['role_id']   = $rolesMapping[$fa['role_id']];

            $this->_db2->insert('folder_access', $fa);
        }
    }

    /**
     * Delete folder access records by folder ids
     * @param $arrSubFolderIds
     */
    public function deleteByFolderIds($arrSubFolderIds)
    {
        if (is_array($arrSubFolderIds) && count($arrSubFolderIds)) {
            $this->_db2->delete('folder_access', ['folder_id' => $arrSubFolderIds]);
        }
    }

    /**
     * Delete folder access records by role ids
     * @param $arrRoleIds
     */
    public function deleteByRoleIds($arrRoleIds)
    {
        if (is_numeric($arrRoleIds)) {
            $arrRoleIds = array($arrRoleIds);
        }

        if (is_array($arrRoleIds) && count($arrRoleIds)) {
            $this->_db2->delete('folder_access', ['role_id' => $arrRoleIds]);
        }
    }

    /**
     * Save access rights for default folders
     *
     * @param $companyId
     * @param $roleId
     * @param $arrFolders
     * @return string error, empty on success
     */
    public function saveDefaultFoldersAccess($companyId, $roleId, $arrFolders)
    {
        $arrCompanyFolders = $this->_parent->getCompanyFolders($companyId, 0, null, false, true);
        return $this->createUpdateDefaultFolderAccess($arrFolders, $arrCompanyFolders, $roleId);
    }
}
