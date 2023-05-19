<?php

namespace Qnr\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Helper\HeadScript;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Mailer\Service\Mailer;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;

/**
 * Questionnaire Index Controller - The default controller class for Questionnaires
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Mailer */
    protected $_mailer;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_mailer           = $services[Mailer::class];
        $this->_systemTemplates  = $services[SystemTemplates::class];
    }


    /**
     * Check if incoming params are correct
     *
     * @param int $qId - questionnaire id
     * @param string $hash - hash generated for qnr id
     * @return array
     */
    private function _checkIncomingParams($qId, $hash)
    {
        $strError = '';
        $arrQnrInfo = '';

        // Check qnr id
        if (!is_numeric($qId)) {
            $strError = $this->_tr->translate('Incorrectly selected questionnaire.');
        }

        // Check the hash
        if (empty($strError)) {
            if (empty($hash) || $this->_companyProspects->getCompanyQnr()->generateHashForQnrId($qId) != $hash) {
                $strError = $this->_tr->translate('Incorrectly selected questionnaire.');
            }
        }

        // Load all information related to this qnr
        if (empty($strError)) {
            $arrQnrInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($qId);

            if (empty($arrQnrInfo)) {
                $strError = $this->_tr->translate('Incorrectly selected questionnaire.');
            }

            if (empty($strError)) {
                $errorCode = $this->_company->getCompanySubscriptions()->getCompanySubscriptionStatusCode($arrQnrInfo['company_id']);
                if (!empty($errorCode)) {
                    $strError = sprintf($this->_tr->translate('Access denied. Error %d.'), $errorCode);
                }
            }
        }

        return array('strError' => $strError, 'arrQInfo' => $arrQnrInfo);
    }


    /**
     * Load/show qnr fields for filling
     */
    public function indexAction()
    {
        $view = new ViewModel();

        // Get incoming params
        $q_id = (int)$this->findParam('id');
        $hash = $this->findParam('hash');


        $arrCheckResult = $this->_checkIncomingParams($q_id, $hash);
        $strError = $arrCheckResult['strError'];

        // Show error message if any
        // Use a separate view for it
        if (!empty($strError)) {
            return $this->renderError($this->_tr->translate($strError));
        } else {
            $view->setVariable('q_id', $q_id);
            $view->setVariable('hash', $hash);

            // Add Company Logo
            $logoOnTop       = $arrCheckResult['arrQInfo']['q_logo_on_top'];
            $arrCompanyInfo  = $this->_company->getCompanyInfo($arrCheckResult['arrQInfo']['company_id']);
            $companyLogoLink = $this->_company->getCompanyLogoLink($arrCompanyInfo);
            $view->setVariable('logoOnTop', $logoOnTop);
            $view->setVariable('companyLogoLink', $companyLogoLink);

            // Generate view for qnr
            $strView = $this->_companyProspects->getCompanyQnr()->generateQnrView($arrCheckResult['arrQInfo']);
            $view->setVariable('strQnrView', $strView);

            $this->layout()->setVariable('arrQnrInfo', $arrCheckResult['arrQInfo']);
            $view->setVariable('arrQnrInfo', $arrCheckResult['arrQInfo']);
            $view->setVariable('booRtl', $arrCheckResult['arrQInfo']['q_rtl'] == 'Y');
        }

        $arrProspectSettings = array(
            'jobSearchFieldId' => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_title'),
            'jobNocSearchFieldId' => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_noc'),
            'jobSpouseSearchFieldId' => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_spouse_title'),
            'jobSpouseNocSearchFieldId' => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_spouse_noc'),
        );

        /** @var HeadScript $headScript */
        $headScript = $this->_serviceManager->get('ViewHelperManager')->get('headScript');
        $headScript->appendScript("var arrProspectSettings = " . Json::encode($arrProspectSettings) . ";");
        $headScript->appendScript("var qnrJobSectionId = " . Json::encode($this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionJobId()) . ";");
        $headScript->appendScript("var qnrSpouseJobSectionId = " . Json::encode($this->_companyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobId()) . ";");

        return $view;
    }


    /**
     * Get filled qnr fields and save/create new prospect
     */
    public function saveAction()
    {
        $booSuccess = false;
        $strMessage = '';
        $scriptOnCompletion = '';
        $booNotXmlHttpRequest = false;

        try {
            // We allow only ajax request
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $booNotXmlHttpRequest = true;
            }

            // Check incoming params
            $filter = new StripTags();
            $params = array_merge($this->params()->fromPost(), $this->params()->fromQuery());
            $arrParams = Settings::filterParamsArray($params, $filter);

            $q_id = $arrParams['q_id'];
            $hash = $arrParams['hash'];

            if (empty($strMessage)) {
                $arrCheckResult = $this->_checkIncomingParams($q_id, $hash);
                $strMessage = $arrCheckResult['strError'];
            }


            // Check each field and its value
            if (empty($strMessage)) {
                $arrQInfo = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($q_id);

                // Add custom fields (use from QNR)
                $fieldPrefix = 'q_' . $q_id . '_field_';
                $arrParams[$fieldPrefix . 'preferred_language'] = $arrQInfo['q_preferred_language'];
                $arrParams[$fieldPrefix . 'office_id'] = $arrQInfo['q_office_id'];
                $arrParams[$fieldPrefix . 'agent_id'] = $arrQInfo['q_agent_id'];

                $arrCheckResult = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($arrParams, $q_id);
                $strMessage = $arrCheckResult['strError'];

                // If all incoming data is correct - create a new prospect
                if (empty($strMessage)) {
                    // Find NOC code by job title for all jobs (if they were not set)
                    if (isset($arrCheckResult['arrInsertData']['job']) && !array_key_exists('qf_job_noc', $arrCheckResult['arrInsertData']['job'])) {
                        foreach ($arrCheckResult['arrInsertData']['job']['qf_job_title'] as $key => $val) {
                            $arrNocInfo = $this->_companyProspects->getNocDetails($val);
                            if (!empty($arrNocInfo) && !empty($arrNocInfo['noc_code'])) {
                                $arrCheckResult['arrInsertData']['job']['qf_job_noc'][$key] = $arrNocInfo['noc_code'];
                            }
                        }
                    }

                    if (isset($arrCheckResult['arrInsertData']['job_spouse']) && is_array($arrCheckResult['arrInsertData']['job_spouse']) &&
                        !array_key_exists('qf_job_spouse_noc', $arrCheckResult['arrInsertData']['job_spouse']) &&
                        array_key_exists('qf_job_spouse_title', $arrCheckResult['arrInsertData']['job_spouse'])) {
                        foreach ($arrCheckResult['arrInsertData']['job_spouse']['qf_job_spouse_title'] as $key => $val) {
                            $arrNocInfo = $this->_companyProspects->getNocDetails($val);
                            if (count($arrNocInfo) && !empty($arrNocInfo['noc_code'])) {
                                $arrCheckResult['arrInsertData']['job_spouse']['qf_job_spouse_noc'][$key] = $arrNocInfo['noc_code'];
                            }
                        }
                    }

                    // Create a prospect
                    $arrCheckResult['arrInsertData']['prospect']['company_id'] = $arrQInfo['company_id'];
                    $arrCreationResult = $this->_companyProspects->createProspect($arrCheckResult['arrInsertData']);

                    // send email, depending on qualification
                    if (isset($arrCheckResult['arrInsertData']['categories'])) // prospect is qualified in 1+ categories
                    {
                        $company_categories = $this->_companyProspects->getCompanyQnr()->getCompanyCategoriesIds($arrQInfo['company_id'], true);

                        $top_category = null;
                        foreach ($company_categories as $category) {
                            if (in_array($category, $arrCheckResult['arrInsertData']['categories'])) {
                                $top_category = $category;
                                break;
                            }
                        }

                        $q_templates = $this->_companyProspects->getCompanyQnr()->getQuestionnaireTemplates($q_id);

                        if (isset($q_templates[$top_category])) {
                            $template_id = $q_templates[$top_category];
                        }
                    } else {
                        // prospect is not qualified
                        $template_id = $arrQInfo['q_template_negative'];
                    }

                    // if a template is selected by admin, send email
                    if (isset($template_id)) {
                        //get template
                        $templateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($template_id);

                        $message = $to = $from = $cc = $bcc = $subject = '';
                        if (is_array($templateInfo) && count($templateInfo)) {
                            $replacements = $this->_systemTemplates->getGlobalTemplateReplacements();
                            $replacements += $this->_companyProspects->getTemplateReplacements((int)$arrCreationResult['prospectId']);
                            list($message, $to, $subject) = $this->_systemTemplates->processText(
                                [
                                    $templateInfo['message'],
                                    $templateInfo['to'],
                                    $templateInfo['subject']
                                ],
                                $replacements
                            );

                            $from = $templateInfo['from'];
                        }

                        if (empty($to)) {
                            $prospectInfo = $this->_companyProspects->getProspectInfo($arrCreationResult['prospectId'], null);
                            $to = $prospectInfo['email'];
                        }

                        $form = array(
                            'friendly_name' => '',
                            'email' => $to,
                            'cc' => $cc,
                            'bcc' => $bcc,
                            'subject' => $subject,
                            'message' => $message,
                        );

                        if (!empty($from)) {
                            $form['from_email'] = $from;
                        } else {
                            // Use company admin email
                            $admin = $this->_company->loadCompanyAdminInfo($arrQInfo['company_id']);

                            $form['from_email'] = $admin['emailAddress'];
                        }

                        $senderInfo = $this->_members->getMemberInfo();
                        list($res, $email) = $this->_mailer->send($form, false, $senderInfo, false);
                        if ($res === true && empty($arrCreationResult['strError']) && !empty($arrCreationResult['prospectId'])) {
                            // Save in DB that email was sent
                            $this->_companyProspects->updateProspectSettings(
                                $arrQInfo['company_id'],
                                $arrCreationResult['prospectId'],
                                array('email_sent' => 'Y')
                            );

                            $arrMemberInfo = $this->_companyProspects->getProspectInfo($arrCreationResult['prospectId'], null);
                            $companyId = $arrMemberInfo['company_id'];
                            if (empty($companyId)) {
                                $companyId = $this->_auth->getCurrentUserCompanyId();
                            }
                            $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
                            $clientFolder = $this->_companyProspects->getPathToProspect($arrCreationResult['prospectId'], $companyId, $booLocal);
                            // Save this sent email to prospect's documents
                            $this->_mailer->saveRawEmailToClient($email, $form['subject'], 0, $arrCreationResult['prospectId'], $companyId, $this->_members->getMemberInfo(), 0, $clientFolder, $booLocal, true, false, false, false, '', true);
                        }
                    }

                    // email sent, now show message
                    if (empty($arrCreationResult['strError'])) {
                        // Load 'thank you template' and parse it
                        //get template
                        $templateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($arrQInfo['q_template_thank_you']);

                        $strMessage = '';
                        if (is_array($templateInfo) && count($templateInfo)) {
                            $replacements = $this->_systemTemplates->getGlobalTemplateReplacements();
                            $replacements += $this->_companyProspects->getTemplateReplacements((int)$arrCreationResult['prospectId']);
                            $strMessage   = $this->_systemTemplates->processText($templateInfo['message'], $replacements);
                        }

                        if (empty($strMessage)) {
                            // Template was NOT found or wasn't processed
                            // Use default message
                            $strMessage = '<div style="font-family: tahoma,arial,helvetica,sans-serif; text-align: center;">
                                    <div style="font-size: 30px; padding: 25px 0;">Questionnaire completed.<br/>Thank you for your submission.</div>
                                    <span style="font-size: 16px;">Your questionnaire has been received. You will hear from us shortly.</span>
                                </div>';
                        }

                        $scriptOnCompletion = $arrQInfo['q_script_analytics_on_completion'];
                        $booSuccess = true;
                    } else {
                        $strMessage = $arrCreationResult['strError'];
                    }
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'msg' => $strMessage,
            'q_script_analytics_on_completion' => $scriptOnCompletion
        );

        if (!$booNotXmlHttpRequest) {
            $view = new JsonModel();
            return $view->setVariables($arrResult);
        } else {
            $view = new ViewModel();
            $view->setTemplate('layout/plain');
            $view->setTerminal(true);

            return $view->setVariables(
                [
                    'content' => '<textarea>' . Json::encode($arrResult) . '</textarea>'
                ],
                true
            );
        }
    }


    /**
     * Search example job titles from NOC database
     */
    public function searchAction()
    {
        $arrResult    = array();
        $arrSearch    = array();
        $totalRecords = 0;

        try {
            $language = $this->params()->fromPost('lang', '');
            if (!in_array($language, ['en', 'fr'])) {
                $qId      = $this->params()->fromPost('q_id');
                $language = $qId ? $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($qId)['q_noc'] : null;
            }

            $filter = new StripTags();
            list($totalRecords, $arrResult, $arrSearch) = $this->_companyProspects->searchJobTitle(
                trim($filter->filter($this->params()->fromPost('query', ''))),
                (bool)$this->params()->fromPost('search_noc', 0),
                (bool)$this->params()->fromPost('booSearchByCodeAndJob', 0),
                (int)$this->params()->fromPost('start', 0),
                (int)$this->params()->fromPost('limit', 10),
                $language
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'totalCount' => $totalRecords,
            'rows'       => $arrResult,
            'search'     => $arrSearch
        );

        return new JsonModel($arrResult);
    }

    public function getNocUrlByCodeAction()
    {
        $url      = '';
        $strError = '';

        try {
            $type = Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY);
            $noc  = trim(Json::decode($this->params()->fromPost('noc', ''), Json::TYPE_ARRAY));
            $job  = trim(Json::decode($this->params()->fromPost('job', ''), Json::TYPE_ARRAY));

            if (!in_array($type, array('details', 'wages', 'jobs', 'outlook', 'job_requirements'))) {
                $strError = $this->_tr->translate('Incorrectly selected type.');
            }

            if (empty($strError) && !is_numeric($noc)) {
                $strError = $this->_tr->translate('Incorrectly selected NOC.');
            }

            if (empty($strError) && !strlen($job)) {
                $strError = $this->_tr->translate('Incorrectly selected job.');
            }

            if (empty($strError)) {
                $browserHeaders = array(
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:88.0) Gecko/20100101 Firefox/88.0',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-us,en;q=0.5',
                    'X-Requested-With:XMLHttpRequest',
                    'Connection: keep-alive'
                );

                $endpoint = 'https://www.jobbank.gc.ca/core/ta-jobtitle_en/select';
                $params   = array(
                    'q'    => $job . ' ' . $noc,
                    'wt'   => 'json',
                    'rows' => '25',
                );

                $ch = curl_init($endpoint . '?' . http_build_query($params));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $browserHeaders);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                $output = curl_exec($ch);
                curl_close($ch);

                $output = Json::decode($output, Json::TYPE_ARRAY);
                $govId  = $output['response']['docs'][0]['noc_job_title_concordance_id'] ?? 0;
                if (!empty($govId)) {
                    switch ($type) {
                        case 'details':
                            $url = 'https://www.jobbank.gc.ca/marketreport/occupation/' . $govId . '/ca';
                            break;

                        case 'wages':
                            $url = 'https://www.jobbank.gc.ca/marketreport/wages-occupation/' . $govId . '/ca';
                            break;

                        case 'jobs':
                            $url = 'https://www.jobbank.gc.ca/marketreport/jobs/' . $govId . '/ca';
                            break;

                        case 'outlook':
                            $url = 'https://www.jobbank.gc.ca/marketreport/skills/' . $govId . '/ca';
                            break;

                        case 'job_requirements':
                            $url = 'https://www.jobbank.gc.ca/marketreport/requirements/' . $govId . '/ca';
                            break;

                        default:
                            break;
                    }
                }
            }

            if (empty($strError) && empty($url)) {
                $strError = $this->_tr->translate('URL was not generated.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'url'     => $url
        );

        return new JsonModel($arrResult);
    }

}