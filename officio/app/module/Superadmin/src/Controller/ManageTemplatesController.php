<?php

namespace Superadmin\Controller;

use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Templates\Model\SystemTemplate;
use Prospects\Service\Prospects;
use Officio\Templates\SystemTemplates;

/**
 * Manage System Templates Controller
 *
 * @author    Uniques Software Corp.
 * @copyright  Uniques
 */
class ManageTemplatesController extends BaseController
{

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var Company */
    protected $_company;

    /** @var Prospects */
    protected $_prospects;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_company         = $services[Company::class];
        $this->_prospects       = $services[Prospects::class];
        $this->_mailer          = $services[Mailer::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Manage Templates');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('booShowLeftPanel', $this->_acl->isAllowed('admin-tab-view'));

        return $view;
    }

    public function getTemplatesAction()
    {
        try {
            $template_type = $this->params()->fromPost('template_type');
            $templates     = [];

            if (in_array($template_type, array('system', 'mass_email', 'other'))) {
                $templates = SystemTemplate::loadMultipleByConditions(['type' => $template_type]);
                $templates = array_map(function ($template) {
                    $arrTemplate                = $template->toExtJs(['template_id' => 'system_template_id']);
                    $arrTemplate['create_date'] = $this->_settings->formatDate($template->create_date);
                    return $arrTemplate;
                }, $templates);
                usort($templates, function ($item1, $item2) {
                    return strtotime($item1['create_date']) <=> strtotime($item2['create_date']);
                });
            }
        } catch (\Exception $e) {
            $templates = [];
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $templates,
            'totalCount' => count($templates)
        );
        return new JsonModel($arrResult);
    }

    public function getTemplateInfoAction()
    {
        $template_id = $this->params()->fromPost('template_id');

        $template = SystemTemplate::load((int)$template_id);
        return new JsonModel($template->toExtJs());
    }

    public function getFieldsAction()
    {
        $fields = [];

        try {
            $templateType = $this->params()->fromPost('template_type');

            if (in_array($templateType, array('system', 'mass_email', 'other'))) {
                $fields = $this->_systemTemplates->getFields($templateType);
            }
        } catch (\Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'rows'       => $fields,
            'totalCount' => count($fields)
        );

        return new JsonModel($arrResult);
    }

    public function saveAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $data = array(
                'system_template_id' => $this->params()->fromPost('template_id'),
                'type'               => $this->params()->fromPost('template_type'),
                'title'              => $filter->filter(Json::decode($this->params()->fromPost('name'), Json::TYPE_ARRAY)),
                'template'           => Json::decode($this->params()->fromPost('body', ''), Json::TYPE_ARRAY),
                'from'               => $filter->filter(Json::decode($this->params()->fromPost('from'), Json::TYPE_ARRAY)),
                'to'                 => Json::decode($this->params()->fromPost('to'), Json::TYPE_ARRAY),
                'cc'                 => $filter->filter(Json::decode($this->params()->fromPost('cc'), Json::TYPE_ARRAY)),
                'bcc'                => $filter->filter(Json::decode($this->params()->fromPost('bcc'), Json::TYPE_ARRAY)),
                'subject'            => $filter->filter(Json::decode($this->params()->fromPost('subject'), Json::TYPE_ARRAY))
            );

            $data['template'] = str_replace('<BR>', '<br>', $data['template']);

            if (empty($data['system_template_id'])) {
                $data['create_date'] = date('Y-m-d');

                $systemTemplate = new SystemTemplate($data);
            } else {
                $systemTemplate = SystemTemplate::load((int)$data['system_template_id']);
                if (empty($systemTemplate)) {
                    $strError = $this->_tr->translate('Incorrect incoming info.');
                } else {
                    // Type cannot be updated
                    unset($data['system_template_id'], $data['type']);

                    $systemTemplate->setAttributes($data);
                }
            }

            if (empty($strError) && !empty($systemTemplate)) {
                if (empty($systemTemplate->save())) {
                    $strError = $this->_tr->translate('Internal error. Please try again later.');
                }
            }
        } catch (\Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = [
            'success' => empty($strError),
            'message' => $strError,
        ];

        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError = '';

        try {
            $templateId = Json::decode($this->params()->fromPost('template_id'), Json::TYPE_ARRAY);

            $systemTemplate = new SystemTemplate(['system_template_id' => $templateId]);
            if (empty($systemTemplate)) {
                $strError = $this->_tr->translate('Template not found.');
            }

            if (empty($strError) && $systemTemplate->type == SystemTemplate::TEMPLATE_TYPE_SYSTEM) {
                $strError = $this->_tr->translate('System templates cannot be deleted.');
            }

            if (empty($strError) && empty($systemTemplate->delete())) {
                $strError = $this->_tr->translate('Internal error. Please try again later.');
            }
        } catch (\Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = [
            'success' => empty($strError),
            'message' => $strError,
        ];

        return new JsonModel($arrResult);
    }

    public function getEmailTemplateAction()
    {
        $view = new JsonModel();
        $showTemplates = Json::decode(stripslashes($this->findParam('showTemplates', '')), Json::TYPE_ARRAY);
        $allowedTemplates = Json::decode(stripslashes($this->findParam('allowedTemplates', '')), Json::TYPE_ARRAY);

        //get templates list
        $templates = array();
        if($showTemplates) {
            $arrWhere = [];
            if (is_array($allowedTemplates) && count($allowedTemplates)) {
                $arrWhere['title'] = $allowedTemplates;
            } else {
                $arrWhere[] = (new Where())->notEqualTo('type', 'system');
            }
            $templates = SystemTemplate::loadMultipleByConditions($arrWhere);
            $templates = array_map(function ($template) {
                return $template->toExtJs([
                    'templateId' => 'system_template_id',
                    'templateName' => 'title'
                ]);
            }, $templates);
            usort($templates, function ($item1, $item2) {
                return strtotime($item1['create_date']) <=> strtotime($item2['create_date']);
            });
        }

        return $view->setVariables(array('templates' => $templates));
    }
    
    public function getMessageAction()
    {
        $view = new JsonModel();
        $template_id = $this->findParam('template_id');
        $company_id = Json::decode(stripslashes($this->findParam('company_id', '')), Json::TYPE_ARRAY);
        $prospects = Json::decode(stripslashes($this->findParam('prospects', '')), Json::TYPE_ARRAY);

        if(!empty($template_id)) {
            //1 - First Invoice, 2 - Recurring Invoice
            if (($template_id == 1 && !empty($prospects)) || ($template_id == 2 && !empty($company_id))) {
                // get template
                $template = SystemTemplate::load((int)$template_id);

                // get parsed message
                $invoiceData  = $this->_company->getCompanyInvoice()->prepareDataForRecurringInvoice($company_id);
                $replacements = $this->_company->getCompanyInvoice()->getTemplateReplacements($invoiceData);
                $result       = $this->_systemTemplates->processTemplate($template, $replacements)->toExtJs();
            }
            elseif (!empty($prospects)) {
                //get template
                $templateInfo = SystemTemplate::load((int)$template_id);
                $result = array(
                    'message' => $templateInfo->template,
                    'from'    => $templateInfo->from,
                    'email'   => $templateInfo->to,
                    'cc'      => $templateInfo->cc,
                    'bcc'     => $templateInfo->bcc,
                    'subject' => $templateInfo->subject
                );
            }
            elseif (!empty($company_id)) {
                $template = SystemTemplate::load((int)$template_id);

                $companyInfo  = $this->_company->getCompanyAndDetailsInfo($company_id);
                $adminInfo    = $this->_members->getMemberInfo($companyInfo['admin_id']);
                $replacements = $this->_company->getTemplateReplacements($companyInfo, $adminInfo);

                $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements);

                $result = [
                    'success' => !empty($message) && !empty($template),
                    'message' => $processedTemplate->template,
                    'from'    => $processedTemplate->from,
                    'email'   => $processedTemplate->to,
                    'cc'      => $processedTemplate->cc,
                    'bcc'     => $processedTemplate->bcc,
                    'subject' => $processedTemplate->subject
                ];
            } else {
                $result = array('success' => false);
            }
        } else {
            $result = array('success' => false);
        }

        return $view->setVariables($result);
    }
    
    public function sendAction()
    {
        $view = new JsonModel();
        $filter = new StripTags();
        
        $booParseProspects = Json::decode(stripslashes($this->findParam('parseProspects', '')), Json::TYPE_ARRAY);
        $prospects = Settings::filterParamsArray(Json::decode(stripslashes($this->findParam('prospects', '')), Json::TYPE_ARRAY), $filter);
        
        if($booParseProspects && !empty($prospects)) {
            $requestData = $this->findParams();
            foreach ($prospects as $prospectId) {
                $processedData = $this->_systemTemplates->processText(
                    [
                        'message' => $requestData['message'],
                        'email'   => $requestData['email']
                    ],
                    $this->_prospects->getTemplateReplacements($prospectId)
                );
                $data          = array_merge($requestData, $processedData);

                // send
                $this->_mailer->processAndSendMail(
                    $data['email'],
                    $data['subject'],
                    $data['message'],
                    $data['from'] ?? null,
                    $data['cc'] ?? null,
                    $data['bcc'] ?? null
                );
            }
        } else {
            $data = $this->findParams();
            $this->_mailer->processAndSendMail(
                $data['email'],
                $data['subject'],
                $data['message'],
                $data['from'] ?? null,
                $data['cc'] ?? null,
                $data['bcc'] ?? null
            );
        }

        return $view->setVariables(array('success' => true));
    }
    
    /**
     * Run recurring payment action
     * (when superadmin runs this action manually)
     */
    public function createInvoiceAction()
    {
        $view = new JsonModel();
        $companyId = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
        $booRecurring = Json::decode($this->findParam('booRecurring'), Json::TYPE_ARRAY);

        $booSuccess = false;
        $strMessage = '';

        $arrCompanyInfo = array();
        if(empty($strMessage) && !is_numeric($companyId)) {
            $strMessage = 'Incorrectly selected company';
        } else {
            $arrCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);
        }
        
        if(empty($strMessage) && empty($arrCompanyInfo['paymentech_profile_id'])) {
            $strMessage = 'Please create PT profile before invoice creation';
        }
        
        if(empty($strMessage) && !empty($arrCompanyInfo['next_billing_date'])) {
            // Check if this date is in the future
            if(strtotime($arrCompanyInfo['next_billing_date']) > time()) {
                $strMessage = 'Next billing date must be in the past.';
            }
        }

        if(empty($strMessage) && $arrCompanyInfo['Status'] != 1) {
            $strMessage = 'Only active company can be processed.';
        }

        
        if(empty($strMessage)) {
            if($booRecurring) {
                $arrResult = $this->_company->getCompanyInvoice()->createInvoice(array($arrCompanyInfo));
            } else {
                $arrFirstInvoiceInfo = array(
                    'company_id'       => $companyId,
                    'customerRefNum'   => $arrCompanyInfo['paymentech_profile_id'],
                    'mode_of_payment'  => $arrCompanyInfo['paymentech_mode_of_payment'],
                );
                $arrResult = $this->_prospects->createFirstInvoice($arrFirstInvoiceInfo, true, false);
            }
            
            $booSuccess = $arrResult['success'];
            $strMessage = $arrResult['message'];
        }

        return $view->setVariables(array('success' => $booSuccess, 'message' => $strMessage));
    }
}
