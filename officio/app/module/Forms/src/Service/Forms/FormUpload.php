<?php

namespace Forms\Service\Forms;

use Forms\Service\Forms;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class FormUpload extends BaseService implements SubServiceInterface
{

    private $_arrFormVersions = array();

    /** @var Forms */
    private $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    private function _getFoldersStructure($parentId, $booAll = false, $searchStr = '')
    {
        $arrFolders = $this->getParent()->getFormFolder()->getFolderInfoByParent($parentId);

        $arrResult = array();
        if (is_array($arrFolders) && count($arrFolders) > 0) {
            foreach ($arrFolders as $folderInfo) {
                $arrChildren = $this->_getFoldersStructure($folderInfo['folder_id'], $booAll, $searchStr);

                // Get files list in this folder
                foreach ($this->_arrFormVersions as $formVersion) {
                    if (($formVersion['folder_id'] == $folderInfo['folder_id'])) {
                        if (!empty($searchStr) && stripos($formVersion['file_name'] ?? '', $searchStr) === false) {
                            continue;
                        }
                        $text = $booAll ? $formVersion['file_name'] . ': ' . date(
                                'Y-m-d',
                                strtotime($formVersion['version_date'])
                            ) : $formVersion['file_name'];

                        $arrChildren[] = array(
                            'text'                => $text,
                            'unique_form_id'      => $this->getUniqueFormId($formVersion['form_id'], $formVersion['file_name']),
                            'description'         => $formVersion['note1'], // Why removed and not updated here?
                            'pdf_form_version_id' => $formVersion['form_version_id'],
                            'version_date'        => date('Y-m-d', strtotime($formVersion['version_date'])),
                            'uploaded_date'       => date('Y-m-d', strtotime($formVersion['uploaded_date'])),
                            'cls'                 => 'x-tree-node-leaf-pdf',
                            'leaf'                => true,
                            'type'                => 'file'
                        );
                    }
                }

                // don't return folders if there are no forms found
                if (!empty($searchStr) && !count($arrChildren)) {
                    continue;
                }

                $arrResult[] = array(
                    'text'              => $folderInfo['folder_name'],
                    'folder_id'         => (int)$folderInfo['folder_id'],
                    'cls'               => 'folder-icon',
                    'singleClickExpand' => true,
                    'children'          => $arrChildren,
                    'type'              => 'folder'
                );
            }
        }

        return $arrResult;
    }

    public function getPdfForms($booAll = true)
    {
        $select = (new Select());
        if ($booAll) {
            $select->from(array('FV' => 'form_version'))
                ->join(array('FU' => 'form_upload'), 'FV.form_id = FU.form_id', array('folder_id'), Select::JOIN_LEFT_OUTER)
                ->order(array('FU.form_id ASC', 'FV.version_date DESC', 'FV.uploaded_date DESC'));
        } else {
            // Sort all form versions by dates
            $subSelect = (new Select())
                ->from(['FV' => 'form_version'])
                ->order(['FV.version_date DESC', 'FV.uploaded_date DESC']);

            $arrAllVersions = $this->_db2->fetchAll($subSelect);

            // Get the latest form version for each form id
            $arrFilteredVersions = array();
            foreach ($arrAllVersions as $arrVersionInfo) {
                if (!isset($arrFilteredVersions[$arrVersionInfo['form_id']])) {
                    $arrFilteredVersions[$arrVersionInfo['form_id']] = $arrVersionInfo['form_version_id'];
                }
            }
            $arrFilteredVersions = empty($arrFilteredVersions) ? array(0) : $arrFilteredVersions;

            // Load data for latest form versions
            $select->from(array('FV' => 'form_version'))
                ->join(array('FU' => 'form_upload'), 'FV.form_id = FU.form_id', array('folder_id'), Select::JOIN_LEFT_OUTER)
                ->where(['FV.form_version_id' => $arrFilteredVersions])
                ->order(array('FV.file_name'));
        }

        return $this->_db2->fetchAll($select);
    }


    public function getFormsAndFolders($booAll = true, $searchStr = '')
    {
        // 1. Load all or only latest pdf forms info
        $this->_arrFormVersions = $this->getPdfForms($booAll);

        // 2. Load all folders and their subfolders
        return $this->_getFoldersStructure(0, $booAll, $searchStr);
    }

    public function getFoldersOnly()
    {
        $this->_arrFormVersions = array();

        // 2. Load all folders and their subfolders
        return $this->_getFoldersStructure(0);
    }

    public function getUniqueFormId($form_id, $form_name)
    {
        return '&lt;%form' . $form_id . ' - ' . $form_name . '%&gt;';
    }
}
