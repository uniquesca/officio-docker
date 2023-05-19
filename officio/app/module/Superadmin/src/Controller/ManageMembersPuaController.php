<?php

namespace Superadmin\Controller;

use Exception;
use Files\Model\FileInfo;
use Laminas\Filter\File\RenameUpload;
use Laminas\Filter\StripTags;
use Files\Service\Files;
use Laminas\InputFilter\FileInput;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Laminas\Validator\File\FilesSize;
use Laminas\Validator\File\UploadFile;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Service\Users;
use Clients\Service\MembersPua;
use Officio\Service\Company;

/**
 * Manage Users' PUA Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageMembersPuaController extends BaseController
{
    /** @var MembersPua */
    private $_pua;

    /** @var Users */
    protected $_users;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_pua = $services[MembersPua::class];
        $this->_users = $services[Users::class];
        $this->_files = $services[Files::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $strTitle = $this->_tr->translate('Planned or Unplanned Absence (PUA) Planning');
        $this->layout()->setVariable('title', $strTitle);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($strTitle);

        $arrCurrentUserInfo = $this->_users->getUserInfo();

        $view->setVariable('currentUserFullName', $arrCurrentUserInfo['full_name'] ?? 'UNKNOWN');

        return $view;
    }

    public function listAction()
    {
        $view = new JsonModel();
        try {
            // Get params
            $sort    = $this->findParam('sort');
            $dir     = $this->findParam('dir');
            $start   = $this->findParam('start');
            $limit   = $this->findParam('limit');
            $puaType = $this->findParam('pua_type');

            $arrFormsList = $this->_pua->getPuaRecordsList($puaType, $sort, $dir, $start, $limit);
        } catch (Exception $e) {
            $arrFormsList = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrFormsList);
    }

    public function manageAction()
    {
        $view = new ViewModel();
        $strError = '';

        try {
            $filter = new StripTags();

            $arrPuaRecordInfo = array(
                'pua_id'   => $this->findParam('pua_id'),
                'pua_type' => $this->findParam('pua_type'),

                'pua_designated_person_type'                           => $this->findParam('pua_designated_person_type'),
                'pua_designated_person_full_name'                      => trim($filter->filter($this->findParam('pua_designated_person_full_name', ''))),
                'pua_designated_person_given_name'                     => trim($filter->filter($this->findParam('pua_designated_person_given_name', ''))),
                'pua_designated_person_family_name'                    => trim($filter->filter($this->findParam('pua_designated_person_family_name', ''))),
                'pua_designated_person_primary_address'                => trim($filter->filter($this->findParam('pua_designated_person_primary_address', ''))),
                'pua_designated_person_secondary_address'              => trim($filter->filter($this->findParam('pua_designated_person_secondary_address', ''))),
                'pua_designated_person_phone'                          => trim($filter->filter($this->findParam('pua_designated_person_phone', ''))),
                'pua_designated_person_email'                          => trim($filter->filter($this->findParam('pua_designated_person_email', ''))),
                'pua_designated_person_primary_rcic_full_name'         => trim($filter->filter($this->findParam('pua_designated_person_primary_rcic_full_name', ''))),
                'pua_designated_person_primary_rcic_given_name'        => trim($filter->filter($this->findParam('pua_designated_person_primary_rcic_given_name', ''))),
                'pua_designated_person_primary_rcic_family_name'       => trim($filter->filter($this->findParam('pua_designated_person_primary_rcic_family_name', ''))),
                'pua_designated_person_primary_rcic_primary_address'   => trim($filter->filter($this->findParam('pua_designated_person_primary_rcic_primary_address', ''))),
                'pua_designated_person_primary_rcic_secondary_address' => trim($filter->filter($this->findParam('pua_designated_person_primary_rcic_secondary_address', ''))),
                'pua_designated_person_primary_rcic_phone'             => trim($filter->filter($this->findParam('pua_designated_person_primary_rcic_phone', ''))),
                'pua_designated_person_primary_rcic_email'             => trim($filter->filter($this->findParam('pua_designated_person_primary_rcic_email', ''))),

                'pua_business_contact_or_service'   => trim($filter->filter($this->findParam('pua_business_contact_or_service', ''))),
                'pua_business_contact_name'         => trim($filter->filter($this->findParam('pua_business_contact_name', ''))),
                'pua_business_contact_phone'        => trim($filter->filter($this->findParam('pua_business_contact_phone', ''))),
                'pua_business_contact_email'        => trim($filter->filter($this->findParam('pua_business_contact_email', ''))),
                'pua_business_contact_username'     => trim($filter->filter($this->findParam('pua_business_contact_username', ''))),
                'pua_business_contact_password'     => trim($filter->filter($this->findParam('pua_business_contact_password', ''))),
                'pua_business_contact_instructions' => trim($filter->filter($this->findParam('pua_business_contact_instructions', ''))),
            );

            // Check received fields
            if (empty($strError) && !empty($arrPuaRecordInfo['pua_id']) && !$this->_pua->hasAccessToPuaRecord($arrPuaRecordInfo['pua_id'])) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                switch ($arrPuaRecordInfo['pua_type']) {
                    case 'designated_person':
                        foreach ($arrPuaRecordInfo as $key => $val) {
                            if (preg_match('/^pua_business_contact_(.*)$/i', $key)) {
                                $arrPuaRecordInfo[$key] = null;
                            }
                        }

                        if (!in_array($arrPuaRecordInfo['pua_designated_person_type'], array('responsible_person', 'authorized_representative'))) {
                            $strError = $this->_tr->translate('Incorrect type.');
                        }

                        if (empty($strError) && empty($arrPuaRecordInfo['pua_designated_person_full_name'])) {
                            $strError = $this->_tr->translate('Person full name is a required field.');
                        }
                        break;

                    case 'business_contact':
                        foreach ($arrPuaRecordInfo as $key => $val) {
                            if (preg_match('/^pua_designated_person_(.*)$/i', $key)) {
                                $arrPuaRecordInfo[$key] = null;
                            }
                        }
                        break;

                    default:
                        $strError = $this->_tr->translate('Type selected incorrectly.');
                        break;
                }
            }

            if (empty($strError)) {
                $puaRecordId = $this->_pua->managePuaRecord($arrPuaRecordInfo);
                if (empty($puaRecordId)) {
                    $strError = $this->_tr->translate('Internal Error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal Error. Please contact to the web site support.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', Json::encode($arrResult));

        return $view;
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $ids = Json::decode($this->findParam('ids'), Json::TYPE_ARRAY);

            if (!$this->_pua->deletePuaRecords($ids)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function downloadDesignationFormAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $strError = '';

        try {
            $puaRecordId = $this->findParam('pua_id');

            if (empty($strError) && !$this->_pua->hasAccessToPuaRecord($puaRecordId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrRecord = $this->_pua->getPuaRecordInfo($puaRecordId);
                if (empty($arrRecord['pua_designated_person_form'])) {
                    $strError = $this->_tr->translate('There is no form for this record.');
                }

                if (empty($strError)) {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                    $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);

                    $pathToFile = $this->_files->getPuaRecordPath($companyId, $booLocal, $puaRecordId);

                    if ($booLocal) {
                        return $this->downloadFile(
                            $pathToFile,
                            $arrRecord['pua_designated_person_form'],
                            FileTools::getMimeByFileName($arrRecord['pua_designated_person_form']),
                            true
                        );
                    } else {
                        $url = $this->_files->getCloud()->getFile(
                            $pathToFile,
                            $arrRecord['pua_designated_person_form']
                        );
                        if ($url) {
                            return $this->redirect()->toUrl($url);
                        }
                        else {
                            return $this->fileNotFound();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $view->setVariable('content', $strError);
        return $view;
    }

    public function deleteDesignationFormAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $puaRecordId = $this->findParam('pua_id');

            if (empty($strError) && !$this->_pua->hasAccessToPuaRecord($puaRecordId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrRecord = $this->_pua->getPuaRecordInfo($puaRecordId);
                if (empty($arrRecord['pua_designated_person_form'])) {
                    $strError = $this->_tr->translate('There is no form for this record.');
                }

                if (empty($strError)) {
                    $arrPuaRecordInfo = array(
                        'pua_id'                     => $puaRecordId,
                        'pua_designated_person_form' => '',
                    );
                    $this->_pua->managePuaRecord($arrPuaRecordInfo);

                    $companyId  = $this->_auth->getCurrentUserCompanyId();
                    $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);
                    $pathToFile = $this->_files->getPuaRecordPath($companyId, $booLocal, $puaRecordId);

                    if (!$this->_files->deleteFile($pathToFile, $booLocal)) {
                        $strError = $this->_tr->translate('Internal error.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function uploadDesignationFormAction()
    {
        $strError = '';
        try {
            $puaRecordId = $this->findParam('pua_id');

            if (empty($strError) && !$this->_pua->hasAccessToPuaRecord($puaRecordId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $tempFileLocation = $this->_config['directory']['tmp'];

            $arrPuaRecordInfo = array();
            if (empty($strError)) {
                $fileInput = new FileInput('file');
                $fileInput->getValidatorChain()
                    ->attach(new FilesSize(['min' => 1, 'max' => 5242880]))
                    ->attach(new UploadFile());
                $fileInput->getFilterChain()
                    ->attach(new RenameUpload([
                        'target'    => $tempFileLocation,
                        'randomize' => false,
                        'overwrite' => true,
                        'use_upload_name' => true,
                        'use_upload_extension' => true
                ]));
                $fileInput->setBreakOnFailure(false);

                $fileFieldId = 'pua_designated_person_form_file';
                $fileInput->setValue($_FILES[$fileFieldId]);
                if ($fileInput->isEmptyFile([$fileFieldId=>$_FILES[$fileFieldId]])) {
                    $strError = $this->_tr->translate('Please upload the file.');
                } else if (!$fileInput->isValid() || !$fileInput->getValue()) {
                    $strError = $fileInput->getMessages();
                    if(is_array($strError)) {
                        $strError = implode('<br/>', $strError);
                    }
                } else {
                    $fileInfo = $fileInput->getValue();
                    $arrPuaRecordInfo = array(
                        'pua_id'                     => $puaRecordId,
                        'pua_designated_person_form' => $fileInfo['name'],
                    );
                }
            }

            if (empty($strError) && empty($arrPuaRecordInfo)) {
                $strError = $this->_tr->translate('Hmm, something wrong.');
            }

            if (empty($strError)) {
                $puaRecordId = $this->_pua->managePuaRecord($arrPuaRecordInfo);
                if (empty($puaRecordId)) {
                    $strError = $this->_tr->translate('Internal Error.');
                }
            }

            if (empty($strError)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
                $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);

                $this->_files->moveLocalFileToCloudOrLocalStorage(
                    $tempFileLocation . '/' . $arrPuaRecordInfo['pua_designated_person_form'],
                    $this->_files->getPuaRecordPath($companyId, $booLocal, $puaRecordId),
                    $booLocal
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        $view = new ViewModel(['content' => Json::encode($arrResult)]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function exportAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $strError = '';

        try {
            $type        = $this->findParam('type');
            $puaRecordId = $this->findParam('pua_id');

            if (empty($strError) && !empty($puaRecordId) && !$this->_pua->hasAccessToPuaRecord($puaRecordId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $result = $this->_pua->export($type, $puaRecordId);
                if ($result instanceof FileInfo) {
                    return $this->downloadFile($result->path, $result->name, 'application/pdf');
                }
                else {
                    $strError = $result;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $strError);
        return $view;
    }

    public function getDesignationFormAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $strError = '';

        try {
            $puaRecordId = $this->findParam('pua_id');

            if (empty($strError) && !empty($puaRecordId) && !$this->_pua->hasAccessToPuaRecord($puaRecordId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                if (empty($puaRecordId)) {
                    // Prepare default values
                    $arrCurrentUserInfo = $this->_users->getUserInfo();

                    $arrData = array(
                        'pua_designated_person_type'                           => 'authorized_representative',

                        // For primary RCIC - use current user's details
                        'pua_designated_person_primary_rcic_full_name'         => $arrCurrentUserInfo['full_name'] ?? '',
                        'pua_designated_person_primary_rcic_given_name'        => $arrCurrentUserInfo['fName'] ?? '',
                        'pua_designated_person_primary_rcic_family_name'       => $arrCurrentUserInfo['lName'] ?? '',
                        'pua_designated_person_primary_rcic_primary_address'   => $arrCurrentUserInfo['address'] ?? '',
                        'pua_designated_person_primary_rcic_secondary_address' => '',
                        'pua_designated_person_primary_rcic_phone'             => $arrCurrentUserInfo['workPhone'] ?? '',
                        'pua_designated_person_primary_rcic_email'             => $arrCurrentUserInfo['emailAddress'] ?? '',
                    );
                } else {
                    $arrData = $this->_pua->getPuaRecordInfo($puaRecordId);
                }

                $view->setVariable('arrData', $arrData);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $view->setTemplate('layout/plain');
            $view->setVariables(
                [
                    'content' => $strError
                ]
            );
        }

        return $view;
    }


}
