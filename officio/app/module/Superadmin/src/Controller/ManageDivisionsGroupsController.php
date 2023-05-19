<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Manage Divisions Groups Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageDivisionsGroupsController extends BaseController
{
    /** @var Company */
    private $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $view->setVariable('arrSalutations', $this->_company->getCompanyDivisions()->getSalutations());
        $this->layout()->setVariable('title', $this->_tr->translate('Define Authorised Agents'));

        return $view;
    }

    public function getListAction()
    {
        $view = new JsonModel();
        $arrResult = array(
            'rows'       => array(),
            'totalCount' => 0
        );

        try {
            $companyId               = $this->_auth->getCurrentUserCompanyId();
            $arrResult['rows']       = $this->_company->getCompanyDivisions()->getDivisionsGroups($companyId, false);
            $arrResult['totalCount'] = count($arrResult['rows']);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrResult);
    }

    public function getRecordAction()
    {
        $view     = new JsonModel();
        $strError = '';
        $arrData  = array();

        try {
            $divisionGroupId = $this->findParam('division_group_id');
            if (!$this->_company->getCompanyDivisions()->hasAccessToDivisionGroup($divisionGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }

            if (empty($strError)) {
                $arrData = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($divisionGroupId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'data'    => $arrData
        );

        return $view->setVariables($arrResult);
    }

    public function saveRecordAction()
    {
        $view     = new JsonModel();
        $strError = '';

        try {
            $divisionGroupId = $this->findParam('division_group_id');
            if (!empty($divisionGroupId) && !$this->_company->getCompanyDivisions()->hasAccessToDivisionGroup($divisionGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }


            if (empty($strError)) {
                $arrDivisionGroupFields = array(
                    'division_group_salutation'      => 'combo',
                    'division_group_first_name'      => 'text',
                    'division_group_last_name'       => 'text',
                    'division_group_position'        => 'text',
                    'division_group_company'         => 'text',
                    'division_group_address1'        => 'text',
                    'division_group_address2'        => 'text',
                    'division_group_city'            => 'text',
                    'division_group_state'           => 'text',
                    'division_group_country'         => 'text',
                    'division_group_postal_code'     => 'text',
                    'division_group_phone_main'      => 'text',
                    'division_group_phone_secondary' => 'text',
                    'division_group_email_primary'   => 'text',
                    'division_group_email_other'     => 'text',
                    'division_group_fax'             => 'text',
                    'division_group_notes'           => 'memo',
                );

                $arrDivisionGroupInfo = array();

                $arrSalutationIds = $this->_company->getCompanyDivisions()->getSalutations(true);

                $filter = new StripTags();
                foreach ($arrDivisionGroupFields as $divisionGroupFieldId => $divisionGroupFieldType) {
                    $maxLength = $divisionGroupFieldType == 'memo' ? 65535 : 255;

                    $arrDivisionGroupInfo[$divisionGroupFieldId] = $divisionGroupFieldType == 'memo' ? trim($this->findParam($divisionGroupFieldId, '')) : $filter->filter(trim($this->params($divisionGroupFieldId, '')));

                    if ($divisionGroupFieldType == 'combo') {
                        $arrDivisionGroupInfo[$divisionGroupFieldId] = in_array($arrDivisionGroupInfo[$divisionGroupFieldId], $arrSalutationIds) ? $arrDivisionGroupInfo[$divisionGroupFieldId] : '';
                    }

                    $arrDivisionGroupInfo[$divisionGroupFieldId] = strlen($arrDivisionGroupInfo[$divisionGroupFieldId] ?? '') > $maxLength ? substr(
                        $arrDivisionGroupInfo[$divisionGroupFieldId],
                        -1 * ($maxLength - 1)
                    ) : $arrDivisionGroupInfo[$divisionGroupFieldId];
                    $arrDivisionGroupInfo[$divisionGroupFieldId] = strlen($arrDivisionGroupInfo[$divisionGroupFieldId] ?? '') === 0 ? null : $arrDivisionGroupInfo[$divisionGroupFieldId];
                }

                $companyId = $this->_auth->getCurrentUserCompanyId();
                if (!$this->_company->getCompanyDivisions()->createUpdateDivisionsGroup($companyId, $divisionGroupId, $arrDivisionGroupInfo, true)) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function updateRecordStatusAction()
    {
        $view     = new JsonModel();
        $strError = '';

        try {
            $divisionGroupId = $this->findParam('division_group_id');
            if (!$this->_company->getCompanyDivisions()->hasAccessToDivisionGroup($divisionGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }

            $strStatus = $this->findParam('division_group_status');
            if (empty($strError) && !in_array($strStatus, array('active', 'inactive', 'suspended'))) {
                $strError = $this->_tr->translate('Incorrectly selected status');
            }

            if (empty($strError)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
                if (!$this->_company->getCompanyDivisions()->updateDivisionsGroupStatus($companyId, $divisionGroupId, $strStatus)) {
                    $strError = $this->_tr->translate('Internal error.');
                }

                if (empty($strError)) {
                    switch ($strStatus) {
                        case 'active':
                            $intStatus = 1;
                            break;

                        case 'inactive':
                            $intStatus = 2;
                            break;

                        default:
                            $intStatus = 0;
                    }

                    if (!empty($intStatus)) {
                        $arrMemberIds = $this->_company->getCompanyDivisions()->getMembersToChangeStatus($companyId, $divisionGroupId, $intStatus);

                        $this->_members->toggleMemberStatus($arrMemberIds, $companyId, $this->_auth->getCurrentUserId(), $intStatus);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return $view->setVariables($arrResult);
    }
}
