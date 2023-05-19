<?php

namespace Officio\Service;

use Exception;
use Files\Service\Files;
use Clients\Service\Members;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\ServiceContainerHolder;


/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Letterheads extends BaseService
{

    use ServiceContainerHolder;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_files = $services[Files::class];
    }

    public function isAllowed($letterheadId, $companyId = null)
    {
        try {
            if ($companyId == null) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            $select = (new Select())
                ->from('letterheads')
                ->where([
                    'letterhead_id' => (int)$letterheadId,
                    'company_id'    => (int)$companyId
                ]);

            $letterhead = $this->_db2->fetchAll($select);
            $booSuccess = is_array($letterhead) && count($letterhead);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function getLetterhead($letterheadId)
    {
        $result = array();
        try {
            $select = (new Select())
                ->from(array('l' => 'letterheads'))
                ->join(array('f' => 'letterheads_files'), 'f.letterhead_id = l.letterhead_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->where(['l.letterhead_id' => (int)$letterheadId]);

            $letterhead = $this->_db2->fetchAll($select);
            if (!$letterhead) {
                return false;
            }

            if ($letterhead[0]['same_subsequent'] == 1) {
                $letterhead[1] = $letterhead[0];
            }

            $result = array(
                'company_id'           => $letterhead[0]['company_id'],
                'letterhead_id'        => $letterhead[0]['letterhead_id'],
                'same_subsequent'      => $letterhead[0]['same_subsequent'] == 1,
                'name'                 => $letterhead[0]['name'],
                'create_date'          => $this->_settings->formatDate($letterhead[0]['create_date'], true, 'Y-m-d'),
                'type'                 => $letterhead[0]['type'],
                'first_file_id'        => $letterhead[0]['letterhead_file_id'],
                'first_margin_left'    => $letterhead[0]['margin_left'],
                'first_margin_right'   => $letterhead[0]['margin_right'],
                'first_margin_top'     => $letterhead[0]['margin_top'],
                'first_margin_bottom'  => $letterhead[0]['margin_bottom'],
                'second_file_id'       => $letterhead[1]['letterhead_file_id'],
                'second_margin_left'   => $letterhead[1]['margin_left'],
                'second_margin_right'  => $letterhead[1]['margin_right'],
                'second_margin_top'    => $letterhead[1]['margin_top'],
                'second_margin_bottom' => $letterhead[1]['margin_bottom']
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $result;
    }

    public function deleteLetterheadFile($letterheadFileId, $companyId, $booLocal)
    {
        $booSuccess = false;
        try {
            $folderPath = $this->_files->getCompanyLetterheadsPath($companyId, $booLocal);

            $this->_files->deleteFile($folderPath . '/' . $letterheadFileId, $booLocal);
            $this->_files->deleteFile($folderPath . '/' . $letterheadFileId . '_small', $booLocal);

            $this->_db2->delete('letterheads_files', ['letterhead_file_id' => $letterheadFileId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function deleteLetterhead($letterheadId, $companyId, $booLocal)
    {
        $booSuccess = false;
        try {
            $select = (new Select())
                ->from('letterheads_files')
                ->where(['letterhead_id' => (int)$letterheadId]);

            $letterheadFiles = $this->_db2->fetchAll($select);

            foreach ($letterheadFiles as $file) {
                $this->deleteLetterheadFile($file['letterhead_file_id'], $companyId, $booLocal);
            }

            $booSuccess = $this->_db2->delete('letterheads', ['letterhead_id' => $letterheadId]);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function getLetterheadFile($letterheadId, $order)
    {
        try {
            $select = (new Select())
                ->from('letterheads')
                ->where(['letterhead_id' => (int)$letterheadId]);

            $letterhead = $this->_db2->fetchRow($select);

            if ($letterhead['same_subsequent'] == 1) {
                $order = 1;
            }

            $letterheadFile = $this->loadLetterFileInfo($letterheadId, $order);
        } catch (Exception $e) {
            $letterheadFile = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $letterheadFile;
    }

    public function loadLetterFileInfo($letterheadId, $order)
    {
        $select = (new Select())
            ->from('letterheads_files')
            ->where([
                'letterhead_id' => (int)$letterheadId,
                'number'        => (int)$order
            ]);

        return $this->_db2->fetchRow($select);
    }

    public function getLetterheadsList($companyId, $booWithoutOther = true)
    {
        $arrLetterheads = array();
        try {
            if (!$booWithoutOther) {
                $arrLetterheads[] = array(
                    'letterhead_id' => 0,
                    'name'          => 'No Letterhead',
                    'type'          => '',
                    'create_date'   => time(),
                );
            }
            /** @var Members $oMembers */
            $oMembers = $this->_serviceContainer->get(Members::class);
            $select = (new Select())
                ->from(array('l' => 'letterheads'))
                ->join(array('m' => 'members'), 'm.member_id = l.created_by', array('fName', 'lName'), Select::JOIN_LEFT_OUTER)
                ->where(['l.company_id' => (int)$companyId])
                ->order('l.name ASC');

            $arrLetterheads = array_merge($arrLetterheads, $this->_db2->fetchAll($select));
            foreach ($arrLetterheads as &$letterhead) {
                $type                     = ucwords(str_replace('_', ' ', $letterhead['type'] ?? ''));
                $author                   = $oMembers::generateMemberName($letterhead);
                $letterhead['date']       = $this->_settings->formatDate($letterhead['create_date']);
                $letterhead['created_by'] = $author['full_name'];
                $letterhead['type']       = $type;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrLetterheads;
    }

    public function saveLetterhead($arrParams, $files, $memberId, $companyId, $booLocal)
    {
        $strError = '';

        try {
            //get author ID
            $authorId  = empty($arrParams['created_by']) ? $memberId : $arrParams['created_by'];
            $action    = $arrParams['type_action'];

            $arrData = array(
                'name'            => $arrParams['name'],
                'type'            => $arrParams['type'],
                'same_subsequent' => $arrParams['same_subsequent'],
            );

            if ($action == 'add') {
                $arrData['created_by']  = (int)$authorId;
                $arrData['create_date'] = date('c');

                if (is_numeric($companyId)) {
                    $arrData['company_id'] = (int)$companyId;
                }

                $letterheadId = $this->_db2->insert('letterheads', $arrData, 0);
            } else {

                $this->_db2->update('letterheads', $arrData, ['letterhead_id' => $arrParams['letterhead_id']]);

                $letterheadId = $arrParams['letterhead_id'];
                if ($arrData['same_subsequent'] == 1) {
                    $letterheadFileDelete = $this->loadLetterFileInfo($letterheadId, 2);
                    $this->deleteLetterheadFile($letterheadFileDelete['letterhead_file_id'], $companyId, $booLocal);
                }
            }

            foreach ($files as $key => &$file) {
                $tmpName             = $file['tmp_name'];
                $booInsertSecondFile = false;
                unset($file['tmp_name']);
                if ($action == 'edit' && $key == 2) {
                    $arrLetterheadFile = $this->loadLetterFileInfo($letterheadId, 2);

                    $letterheadFileId = isset($arrLetterheadFile['letterhead_file_id']) ? $arrLetterheadFile['letterhead_file_id'] : null;
                    if (!$letterheadFileId) {
                        $booInsertSecondFile = true;
                    }
                }

                if ($action == 'add' || $booInsertSecondFile) {
                    $file['letterhead_file_id'] = null;
                    $file['letterhead_id']      = $letterheadId;

                    $letterheadFileId = $this->_db2->insert('letterheads_files', $file);
                } else {
                    if (empty($tmpName)) {
                        unset($file['file_name'], $file['size']);
                    }

                    $arrLetterheadFile = $this->loadLetterFileInfo($letterheadId, $key);
                    $letterheadFileId  = $arrLetterheadFile['letterhead_file_id'];

                    $this->_db2->update('letterheads_files', $file, ['letterhead_file_id' => $letterheadFileId]);
                }

                if (!empty($tmpName)) {
                    $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();

                    $fileNewPath = $this->_files->getCompanyLetterheadsPath($companyId, $booLocal);
                    $arrResult   = $this->_files->saveLetterheadImage(
                        $fileNewPath,
                        'letterhead-upload-file-' . $key,
                        $letterheadFileId,
                        $booLocal
                    );
                    $strError    = $arrResult['error'];
                }

                unset($file);
            }

        } catch (Exception $e) {
            $strError = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }
}
