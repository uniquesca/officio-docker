<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Clients\Service\Analytics;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;

/**
 * Default Analytics Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageDefaultAnalyticsController extends BaseController
{
    /** @var Analytics */
    private $_analytics;

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_analytics = $services[Analytics::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Analytics');
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $title = $this->_tr->translate('Default') . ' ' . $title;
        }

        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        try {
            // Load default list of fields, groups, etc.
            $arrAllSettings = $this->_clients->getSettings(0, $this->_company->getDefaultCompanyId(), 0);

            $arrApplicantFields = array();

            // Case's special fields
            $arrApplicantFields[] = array(
                'field_generated_full_id' => 'case_created_on',
                'field_unique_id'         => 'created_on',
                'field_client_type'       => 'case',
                'field_name'              => 'Created On',
                'field_type'              => 'date',
                'field_template_id'       => 0,
                'field_template_name'     => '',
                'field_group_name'        => ''

            );

            $arrApplicantFields[] = array(
                'field_generated_full_id' => 'case_ob_total',
                'field_unique_id'         => 'ob_total',
                'field_client_type'       => 'case',
                'field_name'              => 'Cases who owe money',
                'field_type'              => 'special',
                'field_template_id'       => 0,
                'field_template_name'     => '',
                'field_group_name'        => ''
            );

            $arrApplicantFields[] = array(
                'field_generated_full_id' => 'case_ta_total',
                'field_unique_id'         => 'ta_total',
                'field_client_type'       => 'case',
                'field_name'              => 'Available Total',
                'field_type'              => 'special',
                'field_template_id'       => 0,
                'field_template_name'     => '',
                'field_group_name'        => ''
            );

            // Individual + Employer fields
            $arrApplicantTypes = array('individual', 'employer');
            foreach ($arrApplicantTypes as $applicantType) {
                foreach ($arrAllSettings['groups_and_fields'][$applicantType][0]['fields'] as $arrGroup) {
                    foreach ($arrGroup['fields'] as $arrFieldInfo) {
                        if ($arrFieldInfo['field_encrypted'] === 'N') {
                            $arrApplicantFields[] = array(
                                'field_generated_full_id' => $applicantType . '_' . $arrFieldInfo['field_unique_id'],
                                'field_unique_id'         => $arrFieldInfo['field_unique_id'],
                                'field_client_type'       => $applicantType,
                                'field_name'              => $arrFieldInfo['field_name'],
                                'field_type'              => $arrFieldInfo['field_type'],
                                'field_template_id'       => 0,
                                'field_template_name'     => '',
                                'field_group_name'        => $arrGroup['group_title']
                            );
                        }
                    }
                }
            }

            // Cases fields (unique from all case templates)
            $arrGroupedCaseFields = array();
            $arrFieldIds          = array();
            foreach ($arrAllSettings['case_group_templates'] as $templateId => $arrTemplates) {
                $templateName = '';
                foreach ($arrAllSettings['case_templates'] as $arrCaseTemplateInfo) {
                    if ($arrCaseTemplateInfo['case_template_id'] == $templateId) {
                        $templateName = $arrCaseTemplateInfo['case_template_name'];
                        break;
                    }
                }

                foreach ($arrTemplates as $arrGroup) {
                    foreach ($arrGroup['fields'] as $arrFieldInfo) {
                        if ($arrFieldInfo['field_encrypted'] === 'N' && !in_array($arrFieldInfo['field_unique_id'], $arrFieldIds)) {
                            $arrFieldIds[] = $arrFieldInfo['field_unique_id'];

                            $arrGroupedCaseFields[] = array(
                                'field_generated_full_id' => 'case_' . $arrFieldInfo['field_unique_id'],
                                'field_unique_id'         => $arrFieldInfo['field_unique_id'],
                                'field_client_type'       => 'case',
                                'field_name'              => $arrFieldInfo['field_name'],
                                'field_type'              => $arrFieldInfo['field_type'],
                                'field_template_id'       => $templateId,
                                'field_template_name'     => $templateName,
                                'field_group_name'        => empty($arrFieldInfo['field_group_name']) ? 'Case Details' : $arrFieldInfo['field_group_name']
                            );
                        }
                    }
                }
            }

            usort($arrGroupedCaseFields, function ($a, $b) {
                return strcmp(strtolower($a['field_name'] ?? ''), strtolower($b['field_name'] ?? ''));
            });

            $arrApplicantFields = array_merge($arrApplicantFields, $arrGroupedCaseFields);


            $arrContactFields = array();
            foreach ($arrAllSettings['groups_and_fields']['contact'][0]['fields'] as $arrGroup) {
                foreach ($arrGroup['fields'] as $arrFieldInfo) {
                    if ($arrFieldInfo['field_encrypted'] === 'N') {
                        $arrContactFields[] = array(
                            'field_generated_full_id' => 'contact_' . $arrFieldInfo['field_unique_id'],
                            'field_unique_id'         => $arrFieldInfo['field_unique_id'],
                            'field_client_type'       => 'contact',
                            'field_name'              => $arrFieldInfo['field_name'],
                            'field_type'              => $arrFieldInfo['field_type'],
                            'field_template_id'       => 0,
                            'field_template_name'     => '',
                            'field_group_name'        => $arrGroup['group_title']
                        );
                    }
                }
            }

            $arrFields = array(
                'applicants' => $arrApplicantFields,
                'contacts'   => $arrContactFields,
            );

        } catch (Exception $e) {
            $arrFields = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('arrFields', $arrFields);

        return $view;
    }

    public function getDefaultAnalyticsAction()
    {
        try {
            $analyticsType = $this->findParam('analytics_type', 'applicants');
            $analyticsType = in_array($analyticsType, array('applicants', 'contacts')) ? $analyticsType : 'applicants';

            $arrDefaultAnalytics = $this->_analytics->getCompanyAnalytics(0, $analyticsType);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess          = false;
            $arrDefaultAnalytics = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrDefaultAnalytics,
            'totalCount' => count($arrDefaultAnalytics)
        );

        return new JsonModel($arrResult);
    }

    public function updateAction()
    {
        $strError = '';
        try {
            $analyticsId   = Json::decode($this->findParam('analytics_id'), Json::TYPE_ARRAY);
            $analyticsType = Json::decode($this->findParam('analytics_type'), Json::TYPE_ARRAY);

            if (empty($strError) && !in_array($analyticsType, array('applicants', 'contacts'))) {
                $strError = $this->_tr->translate('Incorrect type.');
            }

            if (empty($strError) && !empty($analyticsId) && !$this->_analytics->hasAccessToSavedAnalytics($analyticsId, $analyticsType)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $filter        = new StripTags();
            $analyticsName = $filter->filter(trim(Json::decode($this->findParam('analytics_name', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && !strlen($analyticsName)) {
                $strError = $this->_tr->translate('Name is a required field.');
            }

            $arrAnalyticsParams = Settings::filterParamsArray(Json::decode($this->findParam('analytics_params'), Json::TYPE_ARRAY), $filter);
            if (empty($strError)) {
                list($strError, $arrAnalyticsParams) = $this->_analytics->getAnalyticsParams($analyticsType, $arrAnalyticsParams);
            }

            if (empty($strError)) {
                $this->_analytics->createUpdateAnalytics(
                    $this->_auth->getCurrentUserCompanyId(),
                    $analyticsId,
                    $analyticsName,
                    $analyticsType,
                    $arrAnalyticsParams
                );
            }


        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError = '';
        try {
            $arrAnalyticsIds = Json::decode($this->findParam('analytics_ids'), Json::TYPE_ARRAY);
            $analyticsType   = Json::decode($this->findParam('analytics_type'), Json::TYPE_ARRAY);

            if (!is_array($arrAnalyticsIds) || empty($arrAnalyticsIds)) {
                $strError = $this->_tr->translate('Incorrect params.');
            }

            if (empty($strError) && !in_array($analyticsType, array('applicants', 'contacts'))) {
                $strError = $this->_tr->translate('Incorrect type.');
            }

            if (empty($strError)) {
                foreach ($arrAnalyticsIds as $analyticsId) {
                    if (!$this->_analytics->hasAccessToSavedAnalytics($analyticsId, $analyticsType)) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                foreach ($arrAnalyticsIds as $analyticsId) {
                    if (!$this->_analytics->delete($analyticsId)) {
                        $strError = $this->_tr->translate('Internal error.');
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}