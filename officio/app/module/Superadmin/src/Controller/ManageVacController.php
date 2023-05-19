<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Clients\Service\Clients\CaseVACs;
use Officio\Service\Company;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * Manage CMI Settings Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageVacController extends BaseController
{
    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var CaseVACs */
    protected $_caseVACs;

    public function initAdditionalServices(array $services)
    {
        $this->_clients  = $services[Clients::class];
        $this->_company  = $services[Company::class];
        $this->_caseVACs = $this->_clients->getCaseVACs();
    }

    public function indexAction()
    {
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $title = $this->_tr->translate('Default Visa Application Centres (VACs)/Visa Offices');
        } else {
            $title = $this->_tr->translate('Manage Visa Application Centres (VACs)/Visa Offices');
        }

        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return new ViewModel();
    }

    public function getListAction()
    {
        $arrVACRecords = array();

        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            $arrVACRecords = $this->_caseVACs->getList($companyId);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrVACRecords,
            'totalCount' => count($arrVACRecords)
        );

        // Return invoices list
        return new JsonModel($arrResult);
    }

    public function saveRecordAction()
    {
        $vacRecordId = 0;
        $strError    = '';

        try {
            $vacRecordId = $this->params()->fromPost('client_vac_id');

            if (!empty($vacRecordId) && !$this->_caseVACs->hasAccessToVAC($vacRecordId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }

            if (empty($strError)) {
                $maxLength = 255;
                $filter    = new StripTags();

                $vacRecordCity = $filter->filter(trim($this->params()->fromPost('client_vac_city', '')));
                $vacRecordCity = strlen($vacRecordCity) > $maxLength ? substr($vacRecordCity, -1 * ($maxLength - 1)) : $vacRecordCity;

                if (empty($vacRecordCity)) {
                    $strError = $this->_tr->translate('City is a required field');
                }

                $vacRecordCountry = $filter->filter(trim($this->params()->fromPost('client_vac_country', '')));
                $vacRecordCountry = strlen($vacRecordCountry) > $maxLength ? substr($vacRecordCountry, -1 * ($maxLength - 1)) : $vacRecordCountry;

                $vacRecordLink = $filter->filter(trim($this->params()->fromPost('client_vac_link', '')));
                $vacRecordLink = strlen($vacRecordLink) > $maxLength ? substr($vacRecordLink, -1 * ($maxLength - 1)) : $vacRecordLink;

                if (empty($strError)) {
                    $vacRecordId = $this->_caseVACs->createUpdateVACRecord($vacRecordId, $vacRecordCity, $vacRecordCountry, $vacRecordLink);
                    if ($vacRecordId === false) {
                        $strError = $this->_tr->translate('Internal error.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'client_vac_id' => $vacRecordId,
            'success'       => empty($strError),
            'message'       => $strError
        );

        return new JsonModel($arrResult);
    }

    public function moveRecordAction()
    {
        $strError = '';

        try {
            $vacRecordId = Json::decode($this->params()->fromPost('client_vac_id'));
            if (!$this->_caseVACs->hasAccessToVAC($vacRecordId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }

            $booUp = Json::decode($this->params()->fromPost('direction_up'));
            if (empty($strError)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();

                $this->_caseVACs->moveVACRecord($companyId, $vacRecordId, $booUp);

                if ($companyId == $this->_company->getDefaultCompanyId()) {
                    $arrChildVACRecords = $this->_caseVACs->getChildVACRecords($vacRecordId);
                    foreach ($arrChildVACRecords as $arrChildVACRecordInfo) {
                        $this->_caseVACs->moveVACRecord($arrChildVACRecordInfo['company_id'], $arrChildVACRecordInfo['client_vac_id'], $booUp);
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

        return new JsonModel($arrResult);
    }

    public function deleteRecordAction()
    {
        $strError   = '';
        $strSuccess = '';

        try {
            $vacRecordId = Json::decode($this->params()->fromPost('client_vac_id'));

            if (!$this->_caseVACs->hasAccessToVAC($vacRecordId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }

            if (empty($strError)) {
                $strError = $this->_caseVACs->deleteVACRecord($vacRecordId);
                if (empty($strError)) {
                    $strSuccess = $this->_tr->translate('Record was successfully deleted.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $strSuccess : $strError
        );

        return new JsonModel($arrResult);
    }
}