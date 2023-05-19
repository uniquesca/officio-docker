<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Comms\Service\Mailer;
use Prospects\Service\CompanyProspects;
use Officio\Common\Service\Settings;
use Officio\Service\Company;

/**
 * Manage Company Prospects Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageCompanyProspectsController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var CompanyProspects */
    protected $_companyProspects;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_clients          = $services[Clients::class];
        $this->_companyProspects = $services[CompanyProspects::class];
    }

    public function addCustomOptionsAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $defaultQnrId                  = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
            $arrQnrIds                     = $this->_companyProspects->getCompanyQnr()->getAllQnr();
            $arrDefaultFieldsCustomOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsCustomOptions($defaultQnrId, false, false);

            foreach ($arrQnrIds as $qId) {
                if ($qId == $defaultQnrId) {
                    continue;
                }

                $this->_companyProspects->getCompanyQnr()->copyCustomOptions($qId, $arrDefaultFieldsCustomOptions);
            }

            $strResult = 'Done.';
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strResult = 'Exception:' . $e->getMessage();
        }
        $view->setVariables(
            [
                'content' => $strResult
            ],
            true
        );

        return $view;
    }


    /**
     * Show index page for prospects management tab
     */
    public function indexAction()
    {
        $view = new ViewModel();

        if ($this->_auth->isCurrentUserSuperadmin()) {
            $title = $this->_tr->translate('Default Prospects Questionnaires');
        } else {
            $title = $this->_tr->translate('Prospects Questionnaires');
        }
        $this->layout()->setVariable('title', $title);

        $arrCategories = $this->_companyProspects->getCompanyQnr()->getCategories(false, true);
        $view->setVariable('arrCategories', $arrCategories);

        $companyId            = $this->_auth->getCurrentUserCompanyId();
        $divisionGroupId      = $this->_auth->getCurrentUserDivisionGroupId();
        $arrCompanyCategories = $this->_companyProspects->getCompanyQnr()->getCompanyCategories($companyId);
        $view->setVariable('arrCompanyCategories', $arrCompanyCategories);

        $arrDefaultQnrs = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaires();
        $view->setVariable('arrDefaultQnrs', $arrDefaultQnrs);
        $view->setVariable('prospectOfficeLabel', $this->_company->getCurrentCompanyDefaultLabel('office'));

        $arrAllCompanyOffices = $this->_company->getDivisions($companyId, $divisionGroupId);
        $arrCompanyOffices    = array();
        foreach ($arrAllCompanyOffices as $arrCompanyOfficeInfo) {
            $arrCompanyOffices[] = array(
                'office_id'   => $arrCompanyOfficeInfo['division_id'],
                'office_name' => $arrCompanyOfficeInfo['name']
            );
        }
        $view->setVariable('arrCompanyOffices', $arrCompanyOffices);

        $arrQSections = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSections($this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId());
        $view->setVariable('default_qnr_sections', $arrQSections);


        $arrCompanyTemplates = $this->_companyProspects->getCompanyQnr()->getProspectTemplates($companyId);
        $arrTemplates        = array();
        $arrTemplates[]      = array(
            'templateId'   => 0,
            'templateName' => 'Do not send automated responses'
        );
        foreach ($arrCompanyTemplates as $arrTemplateInfo) {
            $arrTemplates[] = array(
                'templateId'   => $arrTemplateInfo['prospect_template_id'],
                'templateName' => $arrTemplateInfo['name']
            );
        }
        $view->setVariable('arrProspectTemplates', $arrTemplates);
        $view->setVariable('qnrJobSectionId', $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionJobId());
        $view->setVariable('qnrSpouseJobSectionId', $this->_companyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobId());


        $view->setVariable('company_id', $companyId);

        return $view;
    }


    /**
     * Load prospects templates list
     * for combobox
     */
    public function getTemplatesListAction()
    {
        $view = new JsonModel();
        try {
            $companyId           = $this->_auth->getCurrentUserCompanyId();
            $arrCompanyTemplates = $this->_companyProspects->getCompanyQnr()->getProspectTemplates($companyId);
            $prospectId          = $this->findParam('member_id');
            $booShowNoTemplate   = Json::decode($this->findParam('show_no_template'), Json::TYPE_ARRAY);
            $prospectEmail       = '';

            // Load prospect's email only if user has access to
            if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                $arrProspectInfo = $this->_companyProspects->getProspectInfo($prospectId, null, false);
                $prospectEmail   = $arrProspectInfo['email'] ?? '';
            }


            $arrTemplates = array();
            if ($booShowNoTemplate) {
                $arrTemplates[] = array(
                    'templateId'   => 0,
                    'templateName' => 'No Template'
                );
            }

            foreach ($arrCompanyTemplates as $arrTemplateInfo) {
                $arrTemplates[] = array(
                    'templateId'   => $arrTemplateInfo['prospect_template_id'],
                    'templateName' => $arrTemplateInfo['name']
                );
            }

            $intDefaultTemplateId = $this->_companyProspects->getCompanyQnr()->getCompanyDefaultTemplateId($companyId);
            if (empty($intDefaultTemplateId) && $booShowNoTemplate) {
                $intDefaultTemplateId = 0;
            }
        } catch (Exception $e) {
            $arrTemplates         = array();
            $prospectEmail        = '';
            $intDefaultTemplateId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'totalCount'          => count($arrTemplates),
            'prospectEmail'       => $prospectEmail,
            'rows'                => $arrTemplates,
            'default_template_id' => $intDefaultTemplateId
        );

        return $view->setVariables($arrResult);
    }

    public function getTemplateInfoAction()
    {
        $view     = new JsonModel();
        $template = array();

        try {
            $templateId = $this->findParam('template_id');

            if ($this->_companyProspects->getCompanyQnr()->hasAccessToTemplate($templateId)) {
                $template = $this->_companyProspects->getCompanyQnr()->getTemplate($templateId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($template);
    }

    public function getFieldsAction()
    {
        $view   = new JsonModel();
        $fields = $this->_companyProspects->getCompanyQnr()->getFields();
        return $view->setVariables(
            [
                'success'    => true,
                'rows'       => $fields,
                'totalCount' => count($fields)
            ]
        );
    }

    public function saveTemplateAction()
    {
        try {
            $strError   = '';
            $templateId = $this->params()->fromPost('template_id');

            $filter = new StripTags();

            $data = array(
                'name'          => $filter->filter(Json::decode($this->params()->fromPost('name'), Json::TYPE_ARRAY)),
                'message'       => $this->_settings->getHTMLPurifier(false)->purify(Json::decode($this->params()->fromPost('body', ''), Json::TYPE_ARRAY)),
                'from'          => $filter->filter(Json::decode($this->params()->fromPost('from'), Json::TYPE_ARRAY)),
                'to'            => Json::decode($this->params()->fromPost('to'), Json::TYPE_ARRAY),
                'cc'            => $filter->filter(Json::decode($this->params()->fromPost('cc'), Json::TYPE_ARRAY)),
                'bcc'           => $filter->filter(Json::decode($this->params()->fromPost('bcc'), Json::TYPE_ARRAY)),
                'subject'       => $filter->filter(Json::decode($this->params()->fromPost('subject'), Json::TYPE_ARRAY)),
                'update_date'   => date('c'),
                'updated_by_id' => $this->_auth->getCurrentUserId(),
            );

            if (!empty($templateId) && !$this->_companyProspects->getCompanyQnr()->hasAccessToTemplate($templateId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the template.');
            }

            // Check incoming params
            if (empty($strError) && !empty($data['from'])) {
                $data['from'] = implode(',', Mailer::parseEmails($data['from'], true));

                if (!Settings::_isCorrectMail($data['from'])) {
                    $strError = $this->_tr->translate('Incorrect "From" Email Address.');
                }
            }

            if (empty($strError) && !empty($data['to'])) {
                $data['to'] = implode(',', Mailer::parseEmails($data['to'], true));

                if (!Settings::_isCorrectMail($data['to'])) {
                    $strError = $this->_tr->translate('Incorrect "To" Email Address.');
                }
            }

            if (empty($strError) && !empty($data['cc'])) {
                $data['cc'] = implode(',', Mailer::parseEmails($data['cc'], true));
                if (!Settings::_isCorrectMail($data['cc'])) {
                    $strError = $this->_tr->translate('Incorrect "CC" Email Address.');
                }
            }

            if (empty($strError) && !empty($data['bcc'])) {
                $data['bcc'] = implode(',', Mailer::parseEmails($data['bcc'], true));
                if (!Settings::_isCorrectMail($data['bcc'])) {
                    $strError = $this->_tr->translate('Incorrect "BCC" Email Address.');
                }
            }

            if (empty($strError)) {
                $data['message'] = str_replace('<BR>', '<br>', $data['message']);

                $templateId = $this->_companyProspects->getCompanyQnr()->saveTemplate($templateId, $data);
                if (empty($templateId)) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $templateId = 0;
            $strError   = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'     => empty($strError),
            'message'     => $strError,
            'template_id' => $templateId
        );

        return new JsonModel($arrResult);
    }

    /**
     * Delete prospect template by received template id.
     * Access rights will be checked.
     */
    public function templateDeleteAction()
    {
        $view       = new JsonModel();
        $strMessage = '';

        $template_id     = (int)Json::decode($this->findParam('template_id'), Json::TYPE_ARRAY);
        $arrTemplateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($template_id);

        if (empty($strMessage) && empty($arrTemplateInfo)) {
            $strMessage = $this->_tr->translate('Incorrectly selected template.');
        }


        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToTemplate($template_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage) && $this->_companyProspects->getCompanyQnr()->isTemplateUsed($template_id)) {
            $strMessage = $this->_tr->translate('This template cannot be deleted because it is already used in Questionnaire(s).');
        }

        // All is correct - delete template
        if (empty($strMessage)) {
            try {
                $this->_companyProspects->getCompanyQnr()->deleteTemplate($template_id);
            } catch (Exception $e) {
                $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        return $view->setVariables(array('success' => empty($strMessage), 'message' => $strMessage));
    }


    /**
     * Mark prospect's template as default by received template id.
     * Access rights will be checked.
     */
    public function templateMarkAsDefaultAction()
    {
        $view       = new JsonModel();
        $strMessage = '';
        $booSuccess = false;

        $template_id     = (int)Json::decode($this->findParam('template_id'), Json::TYPE_ARRAY);
        $arrTemplateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($template_id);

        if (empty($strMessage) && empty($arrTemplateInfo)) {
            $strMessage = $this->_tr->translate('Incorrectly selected template.');
        }


        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToTemplate($template_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        // All is correct - mark template as default
        if (empty($strMessage)) {
            try {
                $companyId              = $this->_auth->getCurrentUserCompanyId();
                $arrCompanyTemplatesIds = $this->_companyProspects->getCompanyQnr()->getProspectTemplates($companyId, true);

                $this->_companyProspects->getCompanyQnr()->markAsDefaultTemplate($template_id, $arrCompanyTemplatesIds);
                $booSuccess = true;
            } catch (Exception $e) {
                $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        return $view->setVariables(array('success' => $booSuccess, 'message' => $strMessage));
    }


    /**
     * Load prospects templates list
     * for Templates grid only
     */
    public function getTemplatesListForTreeAction()
    {
        try {
            $companyId           = $this->_auth->getCurrentUserCompanyId();
            $dateFormatFull      = $this->_settings->variable_get('dateFormatFull');
            $arrCompanyTemplates = $this->_companyProspects->getCompanyQnr()->getProspectTemplates($companyId);

            $arrTemplates = array();
            foreach ($arrCompanyTemplates as $arrTemplateInfo) {
                $arrTemplateInfo = $this->_members->generateUpdateMemberName($arrTemplateInfo);

                // Show updated by/on in the tooltip if creation/update dates are different
                $createdOnDate = $this->_settings->formatDate($arrTemplateInfo['create_date']);
                $updatedOnDate = $this->_settings->formatDate($arrTemplateInfo['update_date']);


                // Son't show "00:00:00" in the tooltip
                $createdOnDateTime = $this->_settings->formatDate($arrTemplateInfo['create_date'], true, $dateFormatFull . ' ' . 'H:i:s');
                $createdOnDateTime = strpos($createdOnDateTime, '00:00:00') !== false ? $createdOnDate : $createdOnDateTime;

                $tooltip = $this->_tr->translate('Created By: ') . $arrTemplateInfo['full_name'] . $this->_tr->translate(' on ') . $createdOnDateTime;

                if ($arrTemplateInfo['update_date'] != $arrTemplateInfo['create_date']) {
                    // Don't show "00:00:00" in the tooltip
                    $updatedOnDateTime = $this->_settings->formatDate($arrTemplateInfo['update_date'], true, $dateFormatFull . ' ' . 'H:i:s');
                    $updatedOnDateTime = strpos($updatedOnDateTime, '00:00:00') !== false ? $updatedOnDate : $updatedOnDateTime;

                    $tooltip = $this->_tr->translate('Updated By: ') . $arrTemplateInfo['update_full_name'] . $this->_tr->translate(' on ') . $updatedOnDateTime . '<br>' . $tooltip;
                }


                $arrTemplates[] = array(
                    'template_id'      => $arrTemplateInfo['prospect_template_id'],
                    'filename'         => $arrTemplateInfo['name'],
                    'default_template' => $arrTemplateInfo['template_default'] == 'Y',
                    'author'           => $arrTemplateInfo['full_name'],
                    'author_update'    => $arrTemplateInfo['update_full_name'],
                    'create_date'      => $this->_settings->formatDate($arrTemplateInfo['create_date']),
                    'update_date'      => $updatedOnDate,
                    'template_tooltip' => $tooltip,
                );
            }
        } catch (Exception $e) {
            $arrTemplates = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrTemplates,
            'totalCount' => count($arrTemplates)
        );

        return new JsonModel($arrResult);
    }


    /**
     * Save prospect's settings
     * e.g. Categories list, their order and assigned templates
     */
    public function saveAction()
    {
        $view       = new JsonModel();
        $booSuccess = false;
        $strMessage = '';

        // Get incoming params
        $company_id    = $this->findParam('company_id');
        $arrCategories = Json::decode($this->findParam('arrCategories'), Json::TYPE_ARRAY);

        // Check incoming info
        if (empty($strMessage) && !is_numeric($company_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected company.');
        }

        if (empty($strMessage) && !$this->_members->hasCurrentMemberAccessToCompany($company_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage) && (!is_array($arrCategories) || empty($arrCategories))) {
            $strMessage = $this->_tr->translate('Incorrectly selected categories.');
        }

        $arrCategoryIds = array();
        if (empty($strMessage)) {
            foreach ($arrCategories as $arrCategoryInfo) {
                if (!is_array($arrCategoryInfo) ||
                    !array_key_exists('prospect_category_id', $arrCategoryInfo) ||
                    !is_numeric($arrCategoryInfo['prospect_category_id']) ||
                    !array_key_exists('order', $arrCategoryInfo) ||
                    !is_numeric($arrCategoryInfo['order'])) {
                    $strMessage = $this->_tr->translate('Incorrectly selected categories.');
                    break;
                }
                $arrCategoryIds[] = $arrCategoryInfo['prospect_category_id'];
            }
        }

        // Check if unselected category is already used by some prospect
        if (empty($strMessage)) {
            $arrSavedCategories = $this->_companyProspects->getCompanyQnr()->getCompanyCategoriesIds($company_id);

            if (!empty($arrSavedCategories)) {
                // There are already saved categories
                $arrRemovedCategories = array();
                foreach ($arrSavedCategories as $savedCategoryId) {
                    if (!in_array($savedCategoryId, $arrCategoryIds)) {
                        $arrRemovedCategories[] = $savedCategoryId;
                    }
                }

                if (!empty($arrRemovedCategories)) {
                    if ($this->_companyProspects->isCategoryUsedByProspect($company_id, $arrRemovedCategories)) {
                        $strMessage = $this->_tr->translate('Some categories were already used by prospects. Please unassign category for each prospect and try again.');
                    }
                }
            }
        }

        if (empty($strMessage)) {
            // Save/update info in DB

            // Update prospect categories
            $booResult = $this->_companyProspects->getCompanyQnr()->updateProspectCategories($company_id, $arrCategories);


            if ($booResult) {
                $booSuccess = true;
                $strMessage = $this->_tr->translate('Changes were successfully saved.');
            } else {
                $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            }
        }


        $arrResult = array('success' => $booSuccess, 'message' => $strMessage);
        return $view->setVariables($arrResult);
    }


    /**
     * Load qnrs list -
     * will be showed in the grid
     */
    public function qnrListAction()
    {
        $view           = new JsonModel();
        $companyId      = $this->_auth->getCurrentUserCompanyId();
        $arrCompanyQnrs = $this->_companyProspects->getCompanyQnr()->getCompanyQuestionnaires($companyId);

        $arrQuestionnaires = array();
        foreach ($arrCompanyQnrs as $arrQInfo) {
            $arrQuestionnaires[] = array(
                'q_id'         => $arrQInfo['q_id'],
                'q_name'       => $arrQInfo['q_name'],
                'q_noc'        => $arrQInfo['q_noc'],
                'q_author'     => '',
                'q_created_on' => empty($arrQInfo['q_created_on']) ? '' : $this->_settings->formatDate($arrQInfo['q_created_on']),
                'q_updated_on' => empty($arrQInfo['q_updated_on']) ? '' : $this->_settings->formatDate($arrQInfo['q_updated_on'])
            );
        }

        return $view->setVariables(array('totalCount' => count($arrQuestionnaires), 'rows' => $arrQuestionnaires));
    }


    /**
     * Delete specific qnr
     */
    public function qnrDeleteAction()
    {
        $view       = new JsonModel();
        $strMessage = '';
        $booSuccess = false;

        // Get and check incoming info
        $q_id = Json::decode($this->findParam('q_id'), Json::TYPE_ARRAY);

        if (!is_numeric($q_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($q_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage) && $q_id == 1) {
            $strMessage = $this->_tr->translate('This questionnaire cannot be deleted.');
        }

        if (empty($strMessage)) {
            $booSuccess = $this->_companyProspects->getCompanyQnr()->deleteQnr($q_id);
            if ($booSuccess) {
                $strMessage = $this->_tr->translate('Questionnaire was successfully deleted.');
            } else {
                $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            }
        }

        $arrResult = array('success' => $booSuccess, 'message' => $strMessage);
        return $view->setVariables($arrResult);
    }

    /**
     * Add new qnr for specific company
     */
    public function qnrAddAction()
    {
        $view       = new JsonModel();
        $strMessage = '';
        $booSuccess = false;
        $q_id       = 0;

        $filter = new StripTags();


        // Get and check incoming info
        $q_name        = trim($filter->filter(Json::decode($this->findParam('q_name', ''), Json::TYPE_ARRAY)));
        $q_noc         = Json::decode($this->findParam('q_noc'), Json::TYPE_ARRAY);
        $q_template_id = Json::decode($this->findParam('q_template_id'), Json::TYPE_ARRAY);
        $q_office_id   = Json::decode($this->findParam('q_office_id'), Json::TYPE_ARRAY);
        $q_simplified  = Json::decode($this->findParam('q_simplified'), Json::TYPE_ARRAY);

        if (empty($q_name)) {
            $strMessage = $this->_tr->translate('Please enter questionnaire name.');
        }

        if (empty($strMessage) && !in_array($q_noc, array('en', 'fr'))) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire NOC.');
        }

        if (empty($strMessage)) {
            $arrDefaultQnrsIds = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaires(true);
            if (!in_array($q_template_id, $arrDefaultQnrsIds)) {
                $strMessage = $this->_tr->translate('Incorrectly selected template.');
            }
        }


        $companyId           = $this->_auth->getCurrentUserCompanyId();
        $divisionGroupId     = $this->_auth->getCurrentUserDivisionGroupId();
        $arrCompanyOfficeIds = $this->_company->getDivisions($companyId, $divisionGroupId, true);
        if (empty($strMessage)) {
            if (count($arrCompanyOfficeIds)) {
                if (!in_array($q_office_id, $arrCompanyOfficeIds)) {
                    $strMessage = $this->_tr->translate('Please select an office.');
                }
            } else {
                $q_office_id = 0;
            }
        }

        if (empty($strMessage)) {
            try {
                $userId = $this->_auth->getCurrentUserId();
                $q_id   = $this->_companyProspects->getCompanyQnr()->createQnr($companyId, $userId, $q_name, $q_noc, $q_template_id, $q_office_id, 0, 0, false, $q_simplified);

                $booSuccess = $q_id > 0;
            } catch (Exception $e) {
                $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        $arrResult = array('success' => $booSuccess, 'message' => $strMessage, 'q_id' => $q_id);
        return $view->setVariables($arrResult);
    }


    /**
     * Create a copy of specific qnr
     */
    public function qnrDuplicateAction()
    {
        $view       = new JsonModel();
        $strMessage = '';
        $booSuccess = false;

        // Get and check incoming info
        $qnrOriginalId     = Json::decode($this->findParam('q_id'), Json::TYPE_ARRAY);
        $qnrDuplicatedId   = 0;
        $qnrDuplicatedName = '';

        if (!is_numeric($qnrOriginalId)) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($qnrOriginalId)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage)) {
            try {
                $userId    = $this->_auth->getCurrentUserId();
                $companyId = $this->_auth->getCurrentUserCompanyId();

                $arrOriginalQnrInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($qnrOriginalId);

                // Generate new unique name
                $try = 0;
                do {
                    $qnrDuplicatedName = sprintf(
                        '%s (copy%s)',
                        $arrOriginalQnrInfo['q_name'],
                        $try > 0 ? ' ' . $try : ''
                    );
                    $try++;
                } while ($this->_companyProspects->getCompanyQnr()->checkQnrNameUsed($companyId, $qnrDuplicatedName));

                // Create a copy
                $qnrDuplicatedId = $this->_companyProspects->getCompanyQnr()->createQnr(
                    $companyId,
                    $userId,
                    $qnrDuplicatedName,
                    $arrOriginalQnrInfo['q_noc'],
                    $qnrOriginalId,
                    0,
                    $arrOriginalQnrInfo['q_template_negative'],
                    $arrOriginalQnrInfo['q_template_thank_you'],
                    true
                );

                $booSuccess = $qnrDuplicatedId > 0;
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        if (empty($strMessage) && !$booSuccess) {
            $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
        }

        $arrResult = array(
            'success' => $booSuccess,
            'message' => $strMessage,
            'q_id'    => $qnrDuplicatedId,
            'q_name'  => $qnrDuplicatedName
        );
        return $view->setVariables($arrResult);
    }


    /**
     * Load information about specific qnr
     * and show a page to edit its settings
     */
    public function qnrEditAction()
    {
        $view = new ViewModel();

        $strMessage = '';

        // Get and check incoming info
        $q_id = Json::decode($this->findParam('q_id'), Json::TYPE_ARRAY);

        if (!is_numeric($q_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($q_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage)) {
            $view->setVariable('qnr_url', $this->layout()->getVariable('topBaseUrl') . '/qnr?id=' . urlencode($q_id) . '&hash=' . urlencode($this->_companyProspects->getCompanyQnr()->generateHashForQnrId($q_id)));
            $view->setVariable('qnr_id', $q_id);

            // Load info about this qnr
            $arrQInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($q_id);
            $arrQInfo['q_simplified'] = $arrQInfo['q_simplified'] === 'Y';
            $arrQInfo['q_logo_on_top'] = $arrQInfo['q_logo_on_top'] === 'Y';
            $view->setVariable('qnr_info', $arrQInfo);

            // Generate view for qnr
            $strView = $this->_companyProspects->getCompanyQnr()->generateQnrView($arrQInfo, true);
            $view->setVariable('strQnrView', $strView);

            // Generate view for qnr
            $companyId     = $this->_auth->getCurrentUserCompanyId();
            $arrCategories = $this->_companyProspects->getCompanyQnr()->getCompanyCategories($companyId);
            $view->setVariable('arrCategories', $arrCategories);

            // Get agents list for current company
            $arrAgents = $this->_clients->getAgentsListFormatted(true);
            $arrAgents = empty($arrAgents) ? $arrAgents : array('' => '-- Please select--') + $arrAgents;
            $view->setVariable('arrAgents', $arrAgents);

            // Get offices list for current company
            $arrFormattedOffices = array();
            $arrOffices          = $this->_members->getDivisions();
            if (count($arrOffices) > 0) {
                $arrFormattedOffices[''] = '-- Please select--';
                foreach ($arrOffices as $arrOfficeInfo) {
                    $arrFormattedOffices[$arrOfficeInfo['division_id']] = $arrOfficeInfo['name'];
                }
            }
            $view->setVariable('arrOffices', $arrFormattedOffices);

            // Get templates list
            $arrQnrTemplates = $this->_companyProspects->getCompanyQnr()->getQuestionnaireTemplates($q_id);
            $view->setVariable('arrQnrTemplates', $arrQnrTemplates);
            $view->setVariable('prospectOfficeLabel', $this->_company->getCurrentCompanyDefaultLabel('office'));
        } else {
            $view->setTemplate('layout/plain');
            $view->setVariables(
                [
                    'content' => $strMessage
                ],
                true
            );
        }

        $view->setTerminal(true);

        return $view;
    }


    /**
     * Update qnr settings
     * e.g. Name, NOC, etc.
     */
    public function qnrUpdateSettingsAction()
    {
        $view       = new JsonModel();
        $booSuccess = false;
        $strMessage = '';

        $filter = new StripTags();


        // Get and check incoming info
        $q_id                 = Json::decode($this->findParam('q_id'), Json::TYPE_ARRAY);
        $q_noc                = Json::decode($this->findParam('q_noc'), Json::TYPE_ARRAY);
        $q_name               = trim($filter->filter(Json::decode($this->findParam('q_name', ''), Json::TYPE_ARRAY)));
        $q_applicant_name     = trim($filter->filter(Json::decode($this->findParam('q_applicant_name', ''), Json::TYPE_ARRAY)));
        $q_office_id          = Json::decode($this->findParam('q_office_id'), Json::TYPE_ARRAY);
        $q_agent_id           = Json::decode($this->findParam('q_agent_id'), Json::TYPE_ARRAY);
        $q_simplified         = Json::decode($this->findParam('q_simplified'), Json::TYPE_ARRAY);
        $q_logo_on_top        = Json::decode($this->findParam('q_logo_on_top'), Json::TYPE_ARRAY);
        $q_preferred_language = trim($filter->filter(Json::decode($this->findParam('q_preferred_language', ''), Json::TYPE_ARRAY)));
        $q_please_select      = trim($filter->filter(Json::decode($this->findParam('q_please_select', ''), Json::TYPE_ARRAY)));
        $q_please_answer_all  = trim($filter->filter(Json::decode($this->findParam('q_please_answer_all', ''), Json::TYPE_ARRAY)));
        $q_please_press_next  = trim($filter->filter(Json::decode($this->findParam('q_please_press_next', ''), Json::TYPE_ARRAY)));
        $q_next_page_button   = trim($filter->filter(Json::decode($this->findParam('q_next_page_button', ''), Json::TYPE_ARRAY)));
        $q_prev_page_button   = trim($filter->filter(Json::decode($this->findParam('q_prev_page_button', ''), Json::TYPE_ARRAY)));

        $q_step1 = trim($filter->filter(Json::decode($this->findParam('q_step1', ''), Json::TYPE_ARRAY)));
        $q_step2 = trim($filter->filter(Json::decode($this->findParam('q_step2', ''), Json::TYPE_ARRAY)));
        $q_step3 = trim($filter->filter(Json::decode($this->findParam('q_step3', ''), Json::TYPE_ARRAY)));
        $q_step4 = trim($filter->filter(Json::decode($this->findParam('q_step4', ''), Json::TYPE_ARRAY)));

        $q_section_bg_color   = trim($filter->filter(Json::decode($this->findParam('q_section_bg_color', ''), Json::TYPE_ARRAY)));
        $q_section_text_color = trim($filter->filter(Json::decode($this->findParam('q_section_text_color', ''), Json::TYPE_ARRAY)));
        $q_button_color       = trim($filter->filter(Json::decode($this->findParam('q_button_color', ''), Json::TYPE_ARRAY)));
        $q_rtl                = Json::decode($this->findParam('q_rtl'), Json::TYPE_ARRAY);

        $q_category_templates             = Json::decode($this->findParam('q_category_templates'), Json::TYPE_ARRAY);
        $q_template_negative              = (int)Json::decode($this->findParam('q_template_negative'), Json::TYPE_ARRAY);
        $q_template_thank_you             = (int)Json::decode($this->findParam('q_template_thank_you'), Json::TYPE_ARRAY);
        $q_script_google_analytics        = Json::decode($this->findParam('q_script_google_analytics'), Json::TYPE_ARRAY);
        $q_script_facebook_pixel          = Json::decode($this->findParam('q_script_facebook_pixel'), Json::TYPE_ARRAY);
        $q_script_analytics_on_completion = Json::decode($this->findParam('q_script_analytics_on_completion'), Json::TYPE_ARRAY);

        if (!is_numeric($q_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($q_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }


        if (empty($strMessage) && empty($q_name)) {
            $strMessage = $this->_tr->translate('Please enter questionnaire name.');
        }

        $companyId           = $this->_auth->getCurrentUserCompanyId();
        $divisionGroupId     = $this->_auth->getCurrentUserDivisionGroupId();
        $arrCompanyOfficeIds = $this->_company->getDivisions($companyId, $divisionGroupId, true);
        if (empty($strMessage)) {
            if (count($arrCompanyOfficeIds)) {
                if (!in_array($q_office_id, $arrCompanyOfficeIds)) {
                    $strMessage = $this->_tr->translate('Please select an office.');
                }
            } else {
                $q_office_id = null;
            }
        }

        if (empty($strMessage)) {
            if (is_numeric($q_agent_id)) {
                $arrAgentsIds = $this->_clients->getAgents(true);
                if (!in_array($q_agent_id, $arrAgentsIds)) {
                    $strMessage = $this->_tr->translate('Incorrectly selected agent.');
                }
            } else {
                $q_agent_id = null;
            }
        }

        if (empty($strMessage) && empty($q_applicant_name)) {
            $strMessage = $this->_tr->translate('Please enter applicant name.');
        }

        if (empty($strMessage) && empty($q_please_select)) {
            $strMessage = $this->_tr->translate("Please enter the text for 'Please select' field.");
        }

        if (empty($strMessage) && empty($q_next_page_button)) {
            $strMessage = $this->_tr->translate("Please enter the caption for 'Next Page' button.");
        }

        if (empty($strMessage) && empty($q_prev_page_button)) {
            $strMessage = $this->_tr->translate("Please enter the caption for 'Previous Page' button.");
        }

        if (empty($strMessage) && empty($q_step1)) {
            $strMessage = $this->_tr->translate("Please enter the caption for 'Step 1' navigation item.");
        }

        if (empty($strMessage) && empty($q_step2)) {
            $strMessage = $this->_tr->translate("Please enter the caption for 'Step 2' navigation item.");
        }

        if (empty($strMessage) && empty($q_step3)) {
            $strMessage = $this->_tr->translate("Please enter the caption for 'Step 3' navigation item.");
        }

        if (empty($strMessage) && empty($q_step4)) {
            $strMessage = $this->_tr->translate("Please enter the caption for 'Step 4' navigation item.");
        }

        if ($this->layout()->getVariable('site_version') == 'canada') {
            if (empty($strMessage) && !in_array($q_noc, array('en', 'fr'))) {
                $strMessage = $this->_tr->translate('Incorrectly selected questionnaire NOC.');
            }
        }

        if (empty($strMessage) && !in_array($q_rtl, array('Y', 'N'))) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire layout direction.');
        }


        // Check categories and templates
        $company_id   = $this->_auth->getCurrentUserCompanyId();
        $arrTemplates = $this->_companyProspects->getCompanyQnr()->getProspectTemplates($company_id, true);

        // Check if template is correctly selected
        if (empty($strMessage)) {
            if (empty($q_template_negative)) {
                $q_template_negative = null;
            } elseif (!in_array($q_template_negative, $arrTemplates)) {
                $strMessage = $this->_tr->translate('Incorrectly selected negative response template.');
            }
        }


        if (empty($strMessage)) {
            if (empty($q_template_thank_you)) {
                $q_template_thank_you = null;
            } elseif (!in_array($q_template_thank_you, $arrTemplates)) {
                $strMessage = $this->_tr->translate('Incorrectly selected thank you template.');
            }
        }

        if (empty($strMessage) && empty($q_script_google_analytics)) {
            $q_script_google_analytics = null;
        }

        if (empty($strMessage) && empty($q_script_facebook_pixel)) {
            $q_script_facebook_pixel = null;
        }

        if (empty($strMessage) && empty($q_script_analytics_on_completion)) {
            $q_script_analytics_on_completion = null;
        }

        if (empty($strMessage)) {
            $booError = false;

            if ($this->layout()->getVariable('site_version') != 'australia' && !$q_simplified) {
                if (is_array($q_category_templates) && !empty($q_category_templates)) {
                    $arrCategoriesIds = $this->_companyProspects->getCompanyQnr()->getCompanyCategoriesIds($company_id);
                    foreach ($q_category_templates as $arrCategoryInfo) {
                        // Check if it is in correct format?
                        if (!is_array($arrCategoryInfo) ||
                            !array_key_exists('cat_id', $arrCategoryInfo) ||
                            !array_key_exists('template_id', $arrCategoryInfo)) {
                            $booError = true;
                            break;
                        }

                        // Only correct category ids and template ids are allowed
                        if (!in_array($arrCategoryInfo['cat_id'], $arrCategoriesIds) ||
                            (!empty($arrCategoryInfo['template_id']) && !in_array($arrCategoryInfo['template_id'], $arrTemplates))) {
                            $booError = true;
                            break;
                        }
                    }
                } else {
                    $booError = true;
                }
            }

            if ($booError) {
                $strMessage = $this->_tr->translate('Incorrectly selected category templates.');
            }
        }


        // All is correct - update data in DB
        if (empty($strMessage)) {
            try {
                $arrUpdateSettings = array(
                    'q_name'                           => $q_name,
                    'q_noc'                            => $q_noc,
                    'q_applicant_name'                 => $q_applicant_name,
                    'q_section_bg_color'               => $q_section_bg_color,
                    'q_section_text_color'             => $q_section_text_color,
                    'q_button_color'                   => $q_button_color,
                    'q_office_id'                      => $q_office_id,
                    'q_agent_id'                       => $q_agent_id,
                    'q_preferred_language'             => $q_preferred_language,
                    'q_simplified'                     => $q_simplified ? 'Y' : 'N',
                    'q_logo_on_top'                    => $q_logo_on_top ? 'Y' : 'N',
                    'q_please_select'                  => $q_please_select,
                    'q_please_answer_all'              => $q_please_answer_all,
                    'q_please_press_next'              => $q_please_press_next,
                    'q_next_page_button'               => $q_next_page_button,
                    'q_prev_page_button'               => $q_prev_page_button,
                    'q_step1'                          => $q_step1,
                    'q_step2'                          => $q_step2,
                    'q_step3'                          => $q_step3,
                    'q_step4'                          => $q_step4,
                    'q_rtl'                            => $q_rtl,
                    'q_template_negative'              => $q_template_negative,
                    'q_template_thank_you'             => $q_template_thank_you,
                    'q_script_google_analytics'        => $q_script_google_analytics,
                    'q_script_facebook_pixel'          => $q_script_facebook_pixel,
                    'q_script_analytics_on_completion' => $q_script_analytics_on_completion,
                    'q_updated_by'                     => $this->_auth->getCurrentUserId(),
                    'q_updated_on'                     => date('c')
                );


                $this->_db2->update(
                    'company_questionnaires',
                    $arrUpdateSettings,
                    [
                        'q_id'       => $q_id,
                        'company_id' => $company_id
                    ]
                );

                // Update category templates
                $this->_db2->delete('company_questionnaires_category_template', ['q_id' => $q_id]);
                foreach ($q_category_templates as $arrCategoryInfo) {
                    // Save record if template is selected
                    if (!empty($arrCategoryInfo['template_id'])) {
                        $arrNewSettings = array(
                            'q_id'                 => $q_id,
                            'prospect_category_id' => $arrCategoryInfo['cat_id'],
                            'prospect_template_id' => $arrCategoryInfo['template_id'],
                        );

                        $this->_db2->insert('company_questionnaires_category_template', $arrNewSettings);
                    }
                }

                $booSuccess = true;
            } catch (Exception $e) {
                $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }
        $arrResult = array('success' => $booSuccess, 'message' => $strMessage);
        return $view->setVariables($arrResult);
    }


    /**
     * Update label for specific section
     * [can be different for each qnr]
     */
    public function qnrUpdateSectionAction()
    {
        $view       = new JsonModel();
        $strMessage = '';
        $booSuccess = false;

        // Get and check incoming info
        $filter              = new StripTags();
        $q_id                = Json::decode($this->findParam('q_id'), Json::TYPE_ARRAY);
        $q_section_id        = Json::decode($this->findParam('q_section_id'), Json::TYPE_ARRAY);
        $q_section_hidden    = Json::decode($this->findParam('q_section_hidden'), Json::TYPE_ARRAY);
        $q_section_name      = $filter->filter(trim(Json::decode($this->findParam('q_section_name', ''), Json::TYPE_ARRAY)));
        $q_section_help      = trim(Json::decode($this->findParam('q_section_help', ''), Json::TYPE_ARRAY));
        $q_section_help_show = Json::decode($this->findParam('q_section_help_show'), Json::TYPE_ARRAY);

        if (!is_numeric($q_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($q_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage) && !is_numeric($q_section_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected section.');
        }

        if (empty($strMessage) && empty($q_section_name)) {
            $strMessage = $this->_tr->translate('Please enter a section name.');
        }

        if (empty($strMessage) && !in_array($q_section_help_show, array('yes', 'no'))) {
            $strMessage = $this->_tr->translate('Incorrectly selected help radio.');
        }


        if (empty($strMessage) && $q_section_help_show == 'yes' && (empty($q_section_help) || $q_section_help == '<br>')) {
            $strMessage = $this->_tr->translate('Please enter help description.');
        }


        if (empty($strMessage)) {
            try {
                // Create or update section details
                $select = (new Select())
                    ->from(['t' => 'company_questionnaires_sections_templates'])
                    ->columns(['count' => new Expression('COUNT(t.q_id)')])
                    ->where([
                        't.q_section_id' => $q_section_id,
                        't.q_id'         => $q_id
                    ]);

                $count = $this->_db2->fetchOne($select);
                
                
                $arrNewSettings = array(
                    'q_section_template_name' => $q_section_name,
                    'q_section_help'          => $q_section_help,
                    'q_section_help_show'     => $q_section_help_show == 'yes' ? 'Y' : 'N',
                    'q_section_hidden'        => $q_section_hidden ? 'Y' : 'N'
                );

                if (empty($count)) {
                    $arrNewSettings['q_id']         = $q_id;
                    $arrNewSettings['q_section_id'] = $q_section_id;
                    $this->_db2->insert('company_questionnaires_sections_templates', $arrNewSettings);
                } else {
                    $this->_db2->update(
                        'company_questionnaires_sections_templates',
                        $arrNewSettings,
                        [
                            'q_id'         => $q_id,
                            'q_section_id' => $q_section_id
                        ]
                    );
                }

                $booSuccess = true;
            } catch (Exception $e) {
                $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        $arrResult = array('success' => $booSuccess, 'message' => $strMessage);
        return $view->setVariables($arrResult);
    }


    /**
     * Load qnr section info
     */
    public function getSectionDetailsAction()
    {
        $view       = new JsonModel();
        $strMessage = '';
        $booSuccess = false;


        // Get and check incoming info
        $q_id         = Json::decode($this->findParam('q_id'), Json::TYPE_ARRAY);
        $q_section_id = Json::decode($this->findParam('q_section_id'), Json::TYPE_ARRAY);

        if (!is_numeric($q_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($q_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage) && !is_numeric($q_section_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected field.');
        }


        $arrSectionInfo = array();
        if (empty($strMessage)) {
            $arrSectionInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionInfo($q_id, $q_section_id);
            if (empty($arrSectionInfo)) {
                $strMessage = $this->_tr->translate('This section does not exists.');
            } else {
                $arrSectionInfo = array(
                    'name'      => $arrSectionInfo['q_section_template_name'],
                    'help'      => $arrSectionInfo['q_section_help'],
                    'help_show' => $arrSectionInfo['q_section_help_show'] == 'Y',
                    'hidden'    => $arrSectionInfo['q_section_hidden'] == 'Y',
                );

                $booSuccess = true;
            }
        }


        $arrResult = array('success' => $booSuccess, 'message' => $strMessage, 'arrInfo' => $arrSectionInfo);
        return $view->setVariables($arrResult);
    }


    /**
     * Load specific field's options
     * [combobox only]
     */
    public function getFieldOptionsAction()
    {
        $view             = new JsonModel();
        $strMessage       = $strDefaultName = $strFieldHelp = '';
        $booFieldHelpShow = false;

        // Get and check incoming info
        $q_id       = Json::decode($this->findParam('q_id'), Json::TYPE_ARRAY);
        $q_field_id = Json::decode($this->findParam('q_field_id'), Json::TYPE_ARRAY);

        if (!is_numeric($q_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        if (empty($strMessage) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($q_id)) {
            $strMessage = $this->_tr->translate('Insufficient access rights.');
        }

        if (empty($strMessage) && !is_numeric($q_field_id)) {
            $strMessage = $this->_tr->translate('Incorrectly selected field.');
        }


        $arrOptions = array();
        if (empty($strMessage)) {
            // Load default info for this field
            $defaultQNRId        = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
            $arrDefaultOptions   = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireFieldsOptions();
            $arrDefaultFieldInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldInfo($defaultQNRId, $q_field_id);
            $strDefaultName      = $arrDefaultFieldInfo['q_field_label'];

            // Load info about this field
            $arrThisFieldInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldInfo($q_id, $q_field_id);
            $strFieldHelp     = $arrThisFieldInfo['q_field_help'];
            $strFieldHidden   = $arrThisFieldInfo['q_field_hidden'] == 'Y';
            $booFieldHelpShow = $arrThisFieldInfo['q_field_help_show'] == 'Y';

            if ($arrThisFieldInfo['q_field_type'] == 'combo_custom') {
                $arrSavedOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldCustomOptions($q_id, $q_field_id);

                foreach ($arrSavedOptions as $arrOptionInfo) {
                    $arrOptions[] = array(
                        'option_id'            => $arrOptionInfo['q_field_custom_option_id'],
                        'option_original_name' => '',
                        'option_name'          => $arrOptionInfo['q_field_custom_option_label'],
                        'option_selected'      => $arrOptionInfo['q_field_custom_option_selected'] == 'Y',
                        'option_order'         => $arrOptionInfo['q_field_custom_option_order'],
                        'option_visible'       => $arrOptionInfo['q_field_custom_option_visible'] == 'Y',
                    );
                }
            } else {
                $arrSavedOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldOptions($q_id, $q_field_id);
                if (!is_array($arrSavedOptions) || !count($arrSavedOptions)) {
                    $arrSavedOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldOptions($defaultQNRId, $q_field_id);
                }

                foreach ($arrSavedOptions as $arrOptionInfo) {
                    $strOriginalLabel = $arrDefaultOptions[$arrOptionInfo['q_field_option_id']] ?? '';

                    $arrOptions[] = array(
                        'option_id'            => $arrOptionInfo['q_field_option_id'],
                        'option_original_name' => $strOriginalLabel,
                        'option_name'          => $arrOptionInfo['q_field_option_label'],
                        'option_selected'      => $arrOptionInfo['q_field_option_selected'] == 'Y',
                        'option_order'         => $arrOptionInfo['q_field_option_order'],
                        'option_visible'       => $arrOptionInfo['q_field_option_visible'] == 'Y',
                    );
                }
            }
        }

        $arrResult = array(
            'totalCount'    => count($arrOptions),
            'rows'          => $arrOptions,
            'defaultName'   => $strDefaultName,
            'fieldHelp'     => $strFieldHelp,
            'fieldHidden'     => $strFieldHidden,
            'fieldHelpShow' => $booFieldHelpShow,
            'message'       => $strMessage
        );
        return $view->setVariables($arrResult);
    }


    /**
     * Update field's settings
     * e.g. title, options
     */
    public function qnrUpdateFieldAction()
    {
        $strError = '';

        try {
            // Get and check incoming info
            $filter                         = new StripTags();
            $q_id                           = (int)Json::decode($this->params()->fromPost('q_id'), Json::TYPE_ARRAY);
            $q_field_id                     = (int)Json::decode($this->params()->fromPost('q_field_id'), Json::TYPE_ARRAY);
            $q_field_name                   = $filter->filter(Json::decode($this->params()->fromPost('q_field_name', ''), Json::TYPE_ARRAY));
            $q_field_help                   = $this->_settings->getHTMLPurifier(false)->purify(Json::decode($this->params()->fromPost('q_field_help'), Json::TYPE_ARRAY));
            $q_field_help_show              = Json::decode($this->params()->fromPost('q_field_help_show'), Json::TYPE_ARRAY);
            $q_field_options                = Json::decode($this->params()->fromPost('q_field_options'), Json::TYPE_ARRAY);
            $q_field_prospect_profile_label = $filter->filter(Json::decode($this->params()->fromPost('q_field_prospect_profile_label'), Json::TYPE_ARRAY));
            $q_field_hidden                 = Json::decode($this->params()->fromPost('q_field_hidden'), Json::TYPE_ARRAY);

            //Right trimming
            $q_field_name = substr($q_field_name, 0, 254);
            $q_field_name = rtrim(preg_replace('/(?:<(?!.+>)|&(?!.+;)).*$/us', '', $q_field_name));

            if (!is_numeric($q_id)) {
                $strError = $this->_tr->translate('Incorrectly selected questionnaire.');
            }

            if (empty($strError) && !$this->_companyProspects->getCompanyQnr()->hasAccessToQnr($q_id)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && (!is_numeric($q_field_id) || empty($q_field_id))) {
                $strError = $this->_tr->translate('Incorrectly selected field.');
            }

            // Check if options are received only for specific field types
            // E.g. combo/radio
            $arrFieldInfo = array();
            if (empty($strError)) {
                $arrFieldInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsList($q_field_id);
                if (empty($arrFieldInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                } elseif (in_array($arrFieldInfo['q_field_type'], array('combo', 'combo_custom', 'radio', 'checkbox')) &&
                    (empty($q_field_options)) || !is_array($q_field_options)) {
                    $strError = $this->_tr->translate('Please add at least one option.');
                }
            }

            if (empty($strError) && empty($q_field_name)) {
                $arrFieldDetailedInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldInfo($q_id, $q_field_id);
                if (!empty($arrFieldDetailedInfo['q_field_label'])) {
                    $strError = $this->_tr->translate('Please enter field name.');
                }
            }

            // Check help info
            if (empty($strError) && !in_array($q_field_help_show, array('yes', 'no'))) {
                $strError = $this->_tr->translate('Incorrectly selected help radio.');
            }


            if (empty($strError) && $q_field_help_show == 'yes' && (empty($q_field_help) || $q_field_help == '<br>')) {
                $strError = $this->_tr->translate('Please enter help description.');
            }


            if (empty($strError)) {
                if ($q_id != $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId()) {
                    $q_field_prospect_profile_label = null;
                }

                // Update field label
                $booSuccess = $this->_companyProspects->getCompanyQnr()->updateFieldTemplate(
                    $q_id,
                    $q_field_id,
                    $q_field_name,
                    $q_field_help,
                    $q_field_help_show == 'yes',
                    $q_field_prospect_profile_label,
                    $q_field_hidden
                );

                if (!$booSuccess) {
                    $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                }
            }

            if (empty($strError)) {
                // Update options
                if (!empty($q_field_options)) {
                    if ($arrFieldInfo['q_field_type'] == 'combo_custom') {
                        $arrSavedOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldCustomOptions($q_id, $q_field_id);
                        $arrNewOptionIds = array();

                        // Custom options
                        foreach ($q_field_options as $arrOptionInfo) {
                            if (empty($arrOptionInfo['option_id'])) {
                                $arrOptionSettings = array(
                                    'q_id'                          => $q_id,
                                    'q_field_id'                    => $q_field_id,
                                    'q_field_custom_option_label'   => $filter->filter(trim($arrOptionInfo['option_name'] ?? '')),
                                    'q_field_custom_option_visible' => $arrOptionInfo['option_visible'] ? 'Y' : 'N',
                                    'q_field_custom_option_order'   => (int)$arrOptionInfo['option_order']
                                );

                                $arrNewOptionIds[] = $this->_db2->insert('company_questionnaires_fields_custom_options', $arrOptionSettings);
                            } else {
                                $arrOptionSettings = array(
                                    'q_field_custom_option_label'   => $filter->filter(trim($arrOptionInfo['option_name'] ?? '')),
                                    'q_field_custom_option_visible' => $arrOptionInfo['option_visible'] ? 'Y' : 'N',
                                    'q_field_custom_option_order'   => (int)$arrOptionInfo['option_order']
                                );

                                $this->_db2->update(
                                    'company_questionnaires_fields_custom_options',
                                    $arrOptionSettings,
                                    [
                                        'q_id'                     => $q_id,
                                        'q_field_custom_option_id' => $arrOptionInfo['option_id']
                                    ]
                                );

                                $arrNewOptionIds[] = $arrOptionInfo['option_id'];
                            }
                        }

                        $arrOptionsDelete = array();
                        foreach ($arrSavedOptions as $arrSavedOptionInfo) {
                            if (!in_array($arrSavedOptionInfo['q_field_custom_option_id'], $arrNewOptionIds)) {
                                $arrOptionsDelete[] = $arrSavedOptionInfo['q_field_custom_option_id'];
                            }
                        }

                        if (count($arrOptionsDelete)) {
                            $this->_db2->delete(
                                'company_questionnaires_fields_custom_options',
                                [
                                    'q_id'                     => (int)$q_id,
                                    'q_field_id'               => (int)$q_field_id,
                                    'q_field_custom_option_id' => $arrOptionsDelete
                                ]
                            );
                        }
                    } else {
                        // Global options
                        foreach ($q_field_options as $arrOptionInfo) {
                            $arrOptionSettings = array(
                                'q_field_option_label'   => $filter->filter($arrOptionInfo['option_name']),
                                'q_field_option_visible' => $arrOptionInfo['option_visible'] ? 'Y' : 'N'
                            );
                            $this->_db2->update(
                                'company_questionnaires_fields_options_templates',
                                $arrOptionSettings,
                                [
                                    'q_id'              => $q_id,
                                    'q_field_option_id' => $arrOptionInfo['option_id']
                                ]
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return result in json format
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}
