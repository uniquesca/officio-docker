<?php

namespace Prospects\Controller;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Exception;
use Files\BufferedStream;
use Files\Model\FileInfo;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Laminas\Http\Client;
use Laminas\View\Helper\Partial;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Mailer\Service\Mailer;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Common\Json;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Email\Models\MailAccount;
use Officio\Email\Storage\Message;
use Officio\Service\Company;
use Officio\Templates\Model\SystemTemplate;
use Officio\Templates\SystemTemplates;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;
use Uniques\Php\StdLib\StringTools;

/**
 * Prospects Index Controller - The default controller class for Prospects
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    /** @var StripTags */
    protected $_filter;

    /** @var Documents */
    protected $_documents;

    /** @var Country */
    protected $_country;

    /** @var Pdf */
    protected $_pdf;

    /** @var Mailer */
    protected $_mailer;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var Tasks */
    protected $_tasks;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_clients          = $services[Clients::class];
        $this->_documents        = $services[Documents::class];
        $this->_country          = $services[Country::class];
        $this->_files            = $services[Files::class];
        $this->_pdf              = $services[Pdf::class];
        $this->_mailer           = $services[Mailer::class];
        $this->_tasks            = $services[Tasks::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_systemTemplates  = $services[SystemTemplates::class];
        $this->_encryption       = $services[Encryption::class];

        $this->_filter = new StripTags();
    }

    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        return $view;
    }

    public function getAdvSearchFieldsAction()
    {
        try {
            $panelType = $this->_filter->filter($this->params()->fromPost('panel_type'));

            $defaultQnrId = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();

            $fields = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFields($defaultQnrId, false, false);

            $include = $this->_companyProspects->getCompanyQnr()->getAdvancedSearchFields();
            $options = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsOptions($defaultQnrId);

            $arrStaticFieldIds = array();
            $arrStaticFields   = $this->_companyProspects->getCompanyQnr()->getStaticFields(true);

            $arrSkipMPFields = array('qf_office', 'qf_seriousness');
            foreach ($arrStaticFields as $arrStaticFieldInfo) {
                // Don't show specific fields for MP tab
                if ($panelType == 'marketplace' && in_array($arrStaticFieldInfo['q_field_unique_id'], $arrSkipMPFields)) {
                    continue;
                }

                $fields[]            = $arrStaticFieldInfo;
                $arrStaticFieldIds[] = $arrStaticFieldInfo['q_field_unique_id'];
            }

            $arrPlainOptionsEngCelpip  = array();
            $arrPlainOptionsEngGeneral = array();
            $arrPlainOptionsFrGeneral  = array();
            foreach ($fields as $field) {
                if (strpos($field['q_field_unique_id'] ?? '', 'qf_language_english_celpip') === 0 && !in_array($field['q_field_unique_id'], array('qf_language_english_celpip', 'qf_language_english_celpip_label'))) {
                    $opt = $options[$field['q_field_id']];
                    foreach ($opt as $o) {
                        @$arrPlainOptionsEngCelpip[$o['q_field_option_unique_id']] .= $o['q_field_option_id'] . ',';
                    }
                }

                if (strpos($field['q_field_unique_id'] ?? '', 'qf_language_eng_proficiency') === 0 && strpos($field['q_field_unique_id'], 'qf_language_english_general') === 0 && !in_array(
                        $field['q_field_unique_id'],
                        array(
                            'qf_language_english_general',
                            'qf_language_english_general_label'
                        )
                    )) {
                    $opt = $options[$field['q_field_id']];
                    foreach ($opt as $o) {
                        @$arrPlainOptionsEngGeneral[$o['q_field_option_unique_id']] .= $o['q_field_option_id'] . ',';
                    }
                }

                if (strpos($field['q_field_unique_id'] ?? '', 'qf_language_fr_proficiency') === 0 && strpos($field['q_field_unique_id'], 'qf_language_french_general') === 0 && !in_array(
                        $field['q_field_unique_id'],
                        array(
                            'qf_language_french_general',
                            'qf_language_french_general_label'
                        )
                    )) {
                    $opt = $options[$field['q_field_id']];
                    foreach ($opt as $o) {
                        @$arrPlainOptionsFrGeneral[$o['q_field_option_unique_id']] .= $o['q_field_option_id'] . ',';
                    }
                }
            }

            $arrCountries               = $this->_country->getCountries(true);
            $arrOffices                 = $this->_clients->getDivisions();
            $arrAgents                  = $this->_clients->getAgentsListFormatted();
            $arrSeriousnessFieldOptions = $this->_companyProspects->getCompanyQnr()->getSeriousnessFieldOptions();
            $arrDefaultOptions          = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireFieldsOptions();

            foreach ($fields as $key => $field) {
                if (!in_array($field['q_field_unique_id'], $include) && !in_array($field['q_field_unique_id'], $arrStaticFieldIds)) {
                    unset($fields[$key]);
                    continue;
                }

                // load options for langs
                if ($fields[$key]['q_field_type'] == 'language') {
                    $plain_options_array = array();
                    switch ($fields[$key]['q_field_unique_id']) {
                        case 'qf_language_english_celpip':
                            $plain_options_array = $arrPlainOptionsEngCelpip;
                            break;
                        case 'qf_language_english':
                        case 'qf_language_english_general':
                            $plain_options_array = $arrPlainOptionsEngGeneral;
                            break;
                        case 'qf_language_french_general':
                            $plain_options_array = $arrPlainOptionsFrGeneral;
                            break;
                    }

                    $lang_options = array();

                    foreach ($plain_options_array as $key2 => $v) {
                        $items          = explode(',', $v);
                        $lang_options[] = array(
                            'q_field_option_id'        => $v,
                            'q_field_id'               => $v,
                            'q_field_option_unique_id' => $key2,
                            'q_field_option_selected'  => 'N',
                            'q_field_option_order'     => 0,
                            'q_id'                     => 1,
                            'q_field_option_label'     => $arrDefaultOptions[reset($items)],
                            'q_field_option_visible'   => 'Y',
                        );
                    }

                    $fields[$key]['options'] = $lang_options;
                } // load options for type=country
                elseif ($fields[$key]['q_field_type'] == 'country') {
                    $countries_options = array();
                    foreach ($arrCountries as $key2 => $c) {
                        $countries_options[] = array(
                            'q_field_option_id'        => $key2,
                            'q_field_id'               => $key2,
                            'q_field_option_unique_id' => $c,
                            'q_field_option_selected'  => 'N',
                            'q_field_option_order'     => 0,
                            'q_id'                     => 1,
                            'q_field_option_label'     => $c,
                            'q_field_option_visible'   => 'Y',
                        );
                    }

                    $fields[$key]['options'] = $countries_options;
                } // load options for Assessment Summary
                elseif ($fields[$key]['q_field_unique_id'] == 'qf_assessment_summary') {
                    $cats = $this->_companyProspects->getCompanyQnr()->getCategories();

                    $assess_options = array();
                    foreach ($cats as $c) {
                        $assess_options[] = array(
                            'q_field_option_id'        => $c['prospect_category_id'],
                            'q_field_id'               => $c['prospect_category_id'],
                            'q_field_option_unique_id' => $c['prospect_category_name'],
                            'q_field_option_selected'  => 'N',
                            'q_field_option_order'     => 0,
                            'q_id'                     => 1,
                            'q_field_option_label'     => $c['prospect_category_name'],
                            'q_field_option_visible'   => 'Y',
                        );
                    }

                    $fields[$key]['options'] = $assess_options;
                    // TODO: if needed, add to options Unqualified, PNP and Other
                } // load options for seriousness
                elseif ($fields[$key]['q_field_unique_id'] == 'qf_seriousness') {
                    $seriousnessOptions = array();
                    foreach ($arrSeriousnessFieldOptions as $optionId => $optionLabel) {
                        if (!empty($optionId)) {
                            $seriousnessOptions[] = array(
                                'q_field_option_id'        => $optionId,
                                'q_field_id'               => $optionId,
                                'q_field_option_unique_id' => $optionLabel,
                                'q_field_option_selected'  => 'N',
                                'q_field_option_order'     => 0,
                                'q_id'                     => 1,
                                'q_field_option_label'     => $optionLabel,
                                'q_field_option_visible'   => 'Y'
                            );
                        }
                    }

                    $fields[$key]['options'] = $seriousnessOptions;
                } // load options for Office
                elseif ($fields[$key]['q_field_unique_id'] == 'qf_office') {
                    $office_options = array();
                    foreach ($arrOffices as $o) {
                        $office_options[] = array(
                            'q_field_option_id'        => $o['division_id'],
                            'q_field_id'               => $o['division_id'],
                            'q_field_option_unique_id' => $o['name'],
                            'q_field_option_selected'  => 'N',
                            'q_field_option_order'     => 0,
                            'q_id'                     => 1,
                            'q_field_option_label'     => $o['name'],
                            'q_field_option_visible'   => 'Y',
                        );
                    }

                    $fields[$key]['options'] = $office_options;
                } // load options for Agent
                elseif ($fields[$key]['q_field_unique_id'] == 'qf_agent') {
                    $agents_options = array();
                    foreach ($arrAgents as $key2 => $a) {
                        $agents_options[] = array(
                            'q_field_option_id'        => $key2,
                            'q_field_id'               => $key2,
                            'q_field_option_unique_id' => $a,
                            'q_field_option_selected'  => 'N',
                            'q_field_option_order'     => 0,
                            'q_id'                     => 1,
                            'q_field_option_label'     => $a,
                            'q_field_option_visible'   => 'Y',
                        );
                    }

                    $fields[$key]['options'] = $agents_options;
                } else {
                    $arrOptions = array();
                    if (is_array($options) && array_key_exists($field['q_field_id'], $options)) {
                        $arrOptions = $options[$field['q_field_id']];

                        if (is_array($arrOptions) && count($arrOptions) && is_array($arrOptions[0]) && array_key_exists('q_field_custom_option_id', $arrOptions[0])) {
                            foreach ($arrOptions as &$arrDetailedOptionInfo) {
                                foreach ($arrDetailedOptionInfo as $optionKey => $optionVal) {
                                    if (preg_match('/^q_field_custom_option_(.*)$/', $optionKey, $regs)) {
                                        $arrDetailedOptionInfo['q_field_option_' . $regs[1]] = $optionVal;
                                        unset($arrDetailedOptionInfo[$optionKey]);
                                    }
                                }
                            }
                        }
                    }

                    $fields[$key]['options'] = $arrOptions;
                }

                // Change field type, if needed
                switch ($fields[$key]['q_field_type']) {
                    case 'textfield':
                    case 'email':
                    case 'combo_custom':
                    case 'job':
                        $fields[$key]['q_field_type'] = 'text';
                        break;

                    case 'radio':
                    case 'country':
                        $fields[$key]['q_field_type'] = 'combo';
                        break;

                    case 'percentage':
                    case 'money':
                    case 'age':
                        $fields[$key]['q_field_type'] = 'number';
                        break;

                    default:
                        break;
                }

                // not all fields have 'q_field_prospect_profile_label'
                $fields[$key]['q_field_prospect_profile_label'] = $field['q_field_prospect_profile_label'] ?: $field['q_field_label'];

                // remove stupid signs at the end
                $fields[$key]['q_field_prospect_profile_label'] = strip_tags(rtrim($fields[$key]['q_field_prospect_profile_label'] ?? '', '?:'));

                // Rename the field, if needed
                switch ($fields[$key]['q_field_unique_id']) {
                    case 'qf_assessment_summary':
                        $fields[$key]['q_field_prospect_profile_label'] = 'Qualified as';
                        break;

                    case 'qf_total_number_of_children':
                        $fields[$key]['q_field_prospect_profile_label'] = 'Total number of children';
                        break;

                    case 'qf_prepared_to_invest':
                        $fields[$key]['q_field_prospect_profile_label'] = 'Invest at least AUD 1.5 Million';
                        break;

                    case 'qf_have_experience_in_managing':
                        $fields[$key]['q_field_prospect_profile_label'] = 'Experience in managing';
                        break;

                    case 'qf_is_your_net_worth':
                        $fields[$key]['q_field_prospect_profile_label'] = 'Net worth more than AUD 2.25 Million';
                        break;

                    default:
                        if (preg_match('/^qf_(education_spouse)|(job_spouse)|(language_spouse)/', $fields[$key]['q_field_unique_id'])) {
                            $fields[$key]['q_field_prospect_profile_label'] .= ' (spouse)';
                        }
                        break;
                }
            }

            $filters = $this->_getSearchFiltersList();
            $fields  = array_values($fields);

            // rename/move some fields
            $arrFieldsRebuild = array();
            if ($this->layout()->getVariable('site_version') == 'australia') {
                foreach ($fields as $f) {
                    if (preg_match('/^qf.*_english_test/', $f['q_field_unique_id'])) {
                        $f['q_field_prospect_profile_label'] = 'English test in last 36 months';
                    }
                    if ($f['q_field_unique_id'] == 'qf_total_number_of_children') {
                        $f['q_field_prospect_profile_label'] = 'Total number of children';
                    }
                    if ($f['q_field_unique_id'] == 'qf_prepared_to_invest') {
                        $f['q_field_prospect_profile_label'] = 'Invest at least AUD 1.5 Million';
                    }
                    if ($f['q_field_unique_id'] == 'qf_have_experience_in_managing') {
                        $f['q_field_prospect_profile_label'] = 'Experience in managing';
                    }
                    if ($f['q_field_unique_id'] == 'qf_is_your_net_worth') {
                        $f['q_field_prospect_profile_label'] = 'Net worth more than AUD 2.25 Million';
                    }
                    if ($f['q_field_unique_id'] == 'qf_assessment_summary') {
                        $f['q_field_prospect_profile_label'] = 'Qualified as';
                    }
                    if (preg_match('/^qf_(education_spouse)|(job_spouse)|(language_spouse)/', $f['q_field_unique_id'])) {
                        $f['q_field_prospect_profile_label'] .= ' (spouse)';
                    }
                    $arrFieldsRebuild[] = $f;
                }
            } else {
                $arrFieldsRebuild = $fields;
            }
        } catch (Exception $e) {
            $filters          = array();
            $arrFieldsRebuild = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $ret = array(
            'fields'  => $arrFieldsRebuild,
            'filters' => $filters
        );

        return new JsonModel($ret);
    }

    private function _getSearchFiltersList()
    {
        return array(
            'yes_no' => array(
                array('yes', $this->_tr->translate('Yes')),
                array('no', $this->_tr->translate('No'))
            ),

            'combo' => array(
                array('is', $this->_tr->translate('is')),
                array('is_not', $this->_tr->translate("is not")),
            ),

            'billing_frequency' => array(
                array(0, $this->_tr->translate('Not set')),
                array(1, $this->_tr->translate('Monthly')),
                array(2, $this->_tr->translate('Annually')),
                array(3, $this->_tr->translate('Biannually')),
            ),

            'number' => array(
                array('equal', '='),
                array('not_equal', '<>'),
                array('less', '<'),
                array('less_or_equal', '<='),
                array('more', '>'),
                array('more_or_equal', '>='),
            ),

            'text' => array(
                array('contains', $this->_tr->translate('contains')),
                array('does_not_contain', $this->_tr->translate("does not contain")),
                array('is', $this->_tr->translate('is')),
                array('is_not', $this->_tr->translate("is not")),
                array('starts_with', $this->_tr->translate('starts with')),
                array('ends_with', $this->_tr->translate('ends with')),
                array('is_empty', $this->_tr->translate('is empty')),
                array('is_not_empty', $this->_tr->translate('is not empty')),
            ),

            'date' => array(
                array('is', $this->_tr->translate('is')),
                array('is_not', $this->_tr->translate("is not")),
                array('is_before', $this->_tr->translate('is before')),
                array('is_after', $this->_tr->translate('is after')),
                array('is_empty', $this->_tr->translate('is empty')),
                array('is_not_empty', $this->_tr->translate('is not empty')),
                //                array('is_between_2_dates',                $this->_tr->translate('is between 2 dates')),
                array('is_between_today_and_date', $this->_tr->translate('is between today and date')),
                array('is_between_date_and_today', $this->_tr->translate('is between a date and today')),
                array('is_since_start_of_the_year_to_now', $this->_tr->translate('is since the start of the year to now')),
                array('is_from_today_to_the_end_of_year', $this->_tr->translate('is from today to the end of the year')),
                array('is_in_this_month', $this->_tr->translate('is in this month')),
                array('is_in_this_year', $this->_tr->translate('is in this year')),
            ),

            'status' => array(
                array('is_not_empty', $this->_tr->translate('is Active')),
                array('is_empty', $this->_tr->translate('is Inactive'))
            ),
        );
    }

    public function getProspectsPageAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $strError = '';

        try {
            $filter = new StripTags();

            $panelType = $filter->filter(Json::decode($this->findParam('panelType'), Json::TYPE_ARRAY));
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            $tabId                                      = $filter->filter(Json::decode($this->findParam('tabId'), Json::TYPE_ARRAY));
            $tab                                        = $filter->filter(Json::decode($this->findParam('tab'), Json::TYPE_ARRAY));
            $pid                                        = (int)$filter->filter(Json::decode($this->findParam('pid', 0), Json::TYPE_ARRAY));
            $subtab                                     = $filter->filter(Json::decode($this->findParam('subtab'), Json::TYPE_ARRAY));

            if (!empty($pid) && !$this->_companyProspects->allowAccessToProspect($pid)) {
                $strError = $this->_tr->translate('Access Denied');
            }

            $view->setVariable('panelType', $panelType);

            if (empty($strError)) {
                // Remember last opened prospects
                if (!empty($pid) && $subtab === 'profile') {
                    $this->_companyProspects->saveRecentlyOpenedProspect($pid);
                }

                switch ($tab) {
                    case 'new-prospect' :
                    case 'prospect' :
                        switch ($subtab) {
                            case 'tasks' :
                                $view->setVariable('pid', $pid);
                                $view->setVariable('tabId', $tabId);
                                $view->setTemplate('prospects/index/tasks.phtml');
                                break;

                            case 'notes' :
                                $view->setVariable('pid', $pid);
                                $view->setVariable('tabId', $tabId);
                                $view->setTemplate('prospects/index/notes.phtml');
                                break;

                            case 'documents' :
                                $view->setVariable('pid', $pid);
                                $view->setVariable('tabId', $tabId);
                                $view->setTemplate('prospects/index/documents.phtml');
                                break;

                            case 'assessment' :
                                $view->setVariable('pid', $pid);
                                $view->setVariable('tabId', $tabId);
                                $arrCategories = $this->_clients->getCaseCategories()->getCompanyCaseCategories($this->_auth->getCurrentUserCompanyId());
                                $view->setVariable('arrDefaultCategories', $arrCategories);

                                $arrProspectInfo = $arrProspectCategories = array();
                                if (!empty($pid)) {
                                    $arrProspectInfo       = $this->_companyProspects->getProspectInfo($pid, $panelType);
                                    $arrProspectCategories = $this->_companyProspects->getProspectAssignedCategories($pid);
                                }

                                $maritalStatusFieldId = $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_marital_status');
                                $arrProspectData      = $this->_companyProspects->getProspectData($pid);
                                $booHasProspectSpouse = false;
                                if ($arrProspectData) {
                                    $booHasProspectSpouse = $this->_companyProspects->hasProspectSpouse((int)$arrProspectData[$maritalStatusFieldId]);
                                }
                                $view->setVariable('booHasProspectSpouse', $booHasProspectSpouse);
                                // Get saved assessment data
                                $arrAssessmentInfo = array();
                                if (array_key_exists('assessment', $arrProspectInfo) && !empty($arrProspectInfo['assessment'])) {
                                    $arrAssessmentInfo = unserialize($arrProspectInfo['assessment']);
                                }
                                $view->setVariable('arrAssessmentInfo', $arrAssessmentInfo);
                                $view->setVariable('booExpressEntryEnabledForCompany',$this->_company->isExpressEntryEnabledForCompany());
                                $view->setVariable('prospectInfo', $arrProspectInfo);
                                $view->setVariable('prospectCategories', $arrProspectCategories);

                                $view->setVariable('arrCategories', $this->_companyProspects->getCompanyQnr()->getCategories());

                                $view->setTemplate('prospects/index/prospect-assessment.phtml');
                                break;

                            case 'occupations' :
                                $view->setVariable('pid', $pid);
                                $view->setVariable('tabId', $tabId . '-occupations');

                                $variables = array('noc_url_details', 'noc_url_prevailing', 'noc_url_jobs', 'noc_url_outlook', 'noc_url_education_job_requirements');

                                $select = (new Select())
                                    ->from('u_variable')
                                    ->where(['name' => $variables]);

                                $result = $this->_db2->fetchAll($select);

                                $variables = [];
                                foreach ($result as $row) {
                                    $variables[$row['name']] = $row['value'];
                                }
                                $view->setVariable('variables', $variables);

                                $defaultQnrID = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                                $arrQInfo     = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($defaultQnrID);
                                $arrQFields   = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFields($defaultQnrID, false, false);

                                $arrJobFields       = array();
                                $arrJobSpouseFields = array();
                                $jobSectionId       = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionJobId();
                                $jobSpouseSectionId = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobId();
                                foreach ($arrQFields as $arrFieldInfo) {
                                    if ($arrFieldInfo['q_field_type'] == 'radio') {
                                        $arrFieldInfo['q_field_type'] = 'combo';
                                    }

                                    switch ($arrFieldInfo['q_section_id']) {
                                        case $jobSectionId:
                                            $arrJobFields[] = $arrFieldInfo;
                                            break;

                                        case $jobSpouseSectionId:
                                            $arrJobSpouseFields[] = $arrFieldInfo;
                                            break;

                                        default:
                                            break;
                                    }
                                }
                                $view->setVariable('jobSectionId', $jobSectionId);
                                $view->setVariable('jobSpouseSectionId', $jobSpouseSectionId);
                                $view->setVariable('qnrFields', $arrJobFields);
                                $view->setVariable('qnrSpouseFields', $arrJobSpouseFields);


                                // Load options list and show 'please select' text as first option
                                $view->setVariable('qnrFieldsOptions', $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsOptions($defaultQnrID));

                                // Load countries list
                                $arrCountries = $this->_country->getCountries(true);
                                $view->setVariable('arrCountries', array('' => $arrQInfo['q_please_select']) + $arrCountries);


                                $view->setVariable('prospectAssignedJobs', $this->_companyProspects->getProspectAssignedJobs($pid));
                                $view->setVariable('prospectSpouseAssignedJobs', $this->_companyProspects->getProspectAssignedJobs($pid, false, 'spouse'));

                                $maritalStatusFieldId = $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_marital_status');
                                $arrProspectData      = $this->_companyProspects->getProspectData($pid);
                                $booShowSpouseSection = $this->_companyProspects->showSpouseSection($arrProspectData, $maritalStatusFieldId);
                                $view->setVariable('booShowSpouseSection', $booShowSpouseSection);
                                $view->setVariable('qnr', $this->_companyProspects->getCompanyQnr());

                                $view->setTemplate('prospects/index/prospect-occupations.phtml');
                                break;

                            case 'business' :
                                $view->setVariable('pid', $pid);
                                $view->setVariable('tabId', $tabId . '-business');

                                $defaultQnrID = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                                $arrQInfo     = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($defaultQnrID);
                                $arrQFields   = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFields($defaultQnrID, false, false);

                                $arrFields         = array();
                                $businessSectionId = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionBusinessId();
                                foreach ($arrQFields as $arrFieldInfo) {
                                    if ($arrFieldInfo['q_section_id'] == $businessSectionId) {
                                        if ($arrFieldInfo['q_field_type'] == 'radio') {
                                            $arrFieldInfo['q_field_type'] = 'combo';
                                        }
                                        $arrFields[] = $arrFieldInfo;
                                    }
                                }
                                $view->setVariable('qnrFields', $arrFields);

                                // Load options list and show 'please select' text as first option
                                $view->setVariable('qnrFieldsOptions', $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsOptions($defaultQnrID));

                                // Load countries list
                                $arrCountries    = $this->_country->getCountries(true);
                                $arrProspectData = $this->_companyProspects->getProspectData($pid);
                                $view->setVariable('arrCountries', array('' => $arrQInfo['q_please_select']) + $arrCountries);
                                $view->setVariable('prospectData', $arrProspectData);
                                $view->setVariable('qnr', $this->_companyProspects->getCompanyQnr());

                                $view->setTemplate(
                                    $this->layout()->getVariable('site_version') == 'australia' ? 'prospects/index/prospect-business-australia.phtml' : 'prospects/index/prospect-business.phtml'
                                );
                                break;

                            case 'profile' :
                            default:
                                $view->setVariable('pid', $pid);
                                $view->setVariable('tabId', $tabId);

                                $arrProspectInfo = $arrProspectData = $arrProspectOffices = array();
                                if (!empty($pid)) {
                                    $arrProspectInfo    = $this->_companyProspects->getProspectInfo($pid, $panelType);
                                    $arrProspectData    = $this->_companyProspects->getProspectData($pid);
                                    $arrProspectOffices = $this->_companyProspects->getCompanyProspectOffices()->getProspectOffices($pid);
                                } else {
                                    $arrDivisions = $this->_clients->getDivisions(true);
                                    if (is_array($arrDivisions) && count($arrDivisions) == 1) {
                                        $arrProspectOffices = $arrDivisions;
                                    }
                                }

                                $view->setVariable('prospectInfo', $arrProspectInfo);
                                $view->setVariable('prospectData', $arrProspectData);
                                $view->setVariable('prospectOffices', $arrProspectOffices);
                                $view->setVariable('prospectOfficeLabel', $this->_company->getCurrentCompanyDefaultLabel('office'));

                                // Get QNR info
                                $defaultQnrID = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                                $arrQInfo     = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($defaultQnrID);
                                $view->setVariable('arrQInfo', $arrQInfo);

                                // Get qnr fields, group them by section
                                $arrQFields               = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFields($defaultQnrID, false, false);
                                $arrFields                = array();
                                $jobSectionId             = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionJobId();
                                $jobSpouseSectionId       = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobId();
                                $jobSpouseSectionHeaderId = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobHeaderId();
                                $businessSectionId        = $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionBusinessId();

                                if ($this->layout()->getVariable('site_version') == 'australia') {
                                    $arrSectionsToCheck = array($jobSectionId, $jobSpouseSectionId, $businessSectionId);
                                } else {
                                    $arrSectionsToCheck = array($jobSectionId, $jobSpouseSectionId, $jobSpouseSectionHeaderId, $businessSectionId);
                                }

                                foreach ($arrQFields as $arrFieldInfo) {
                                        // Those sections will be showed in other tabs
                                    if (!in_array($arrFieldInfo['q_section_id'], $arrSectionsToCheck)) {
                                        if ($arrFieldInfo['q_field_type'] == 'radio') {
                                            $arrFieldInfo['q_field_type'] = 'combo';
                                        }
                                        $arrFields[$arrFieldInfo['q_section_prospect_profile']][] = $arrFieldInfo;
                                    }
                                }

                                $view->setVariable('qnrFields', $arrFields);

                                // Load options list and show 'please select' text as first option
                                $qnrFieldsOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsOptions($defaultQnrID);
                                $qnrFieldsOptions = $this->_companyProspects->getCompanyQnr()->getMultipleComboOptionsGrouped($this->_auth->getCurrentUserCompanyId(), $qnrFieldsOptions);
                                $view->setVariable('qnrFieldsOptions', $qnrFieldsOptions);

                                // Load countries list
                                $arrCountries = $this->_country->getCountries(true);
                                $view->setVariable('arrCountries', array('' => $arrQInfo['q_please_select']) + $arrCountries);

                                $view->setVariable('booProspectConverted', empty($pid) ? false : $this->_companyProspects->isProspectConverted($pid));
                                $view->setVariable('settings', $this->_settings);
                                $view->setVariable('qnr', $this->_companyProspects->getCompanyQnr());

                                if ($this->layout()->getVariable('site_version') == 'australia') {
                                    $view->setTemplate('prospects/index/prospect-australia.phtml');
                                } else {
                                    $view->setTemplate('prospects/index/prospect.phtml');
                                }
                                break;
                        }
                        break;

                    case 'waiting-for-assessment' :
                    case 'qualified-prospects' :
                    case 'unqualified-prospects' :
                    default :
                        $view->setVariable('tabId', $tabId);
                        $view->setTemplate('prospects/index/prospects-page.phtml');
                        break;
                }

                // Mark prospect 'as read'
                $this->_companyProspects->toggleProspectViewed($pid);
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
                ],
                true
            );
        }

        return $view;
    }

    public function getAllProspectsListAction()
    {
        $view = new JsonModel();

        set_time_limit(60 * 5); // 5 minutes
        ini_set('memory_limit', '1024M');

        try {
            $panelType = $this->_filter->filter($this->findParam('panelType'));
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            $arrProspects     = $this->_companyProspects->getProspectsList($panelType, 0, 0, 'all-prospects', '', null, 'cp.lName', 'ASC');
            $arrClientsParsed = array();
            if ($arrProspects) {
                foreach ($arrProspects['rows'] as $prospectInfo) {
                    $name                = $this->_companyProspects->generateProspectName($prospectInfo);
                    $arrClientsParsed [] = array(
                        'clientId'       => $prospectInfo ['prospect_id'],
                        'clientName'     => $name,
                        'clientFullName' => $name,
                        'emailAddresses' => $prospectInfo ['email']
                    );
                }
            }
            $booSuccess = true;
        } catch (Exception $e) {
            $arrClientsParsed = array();
            $booSuccess       = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrClientsParsed,
            'success'    => $booSuccess,
            'totalCount' => count($arrClientsParsed)
        );

        return $view->setVariables($arrResult);
    }

    public function getRecentProspectsAction()
    {
        $view = new JsonModel();

        $arrProspects = array();
        try {
            $panelType = $this->findParam('panelType');
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            $pid = $this->findParam('pid');
            if (!empty($pid) && $this->_companyProspects->allowAccessToProspect($pid)) {
                $this->_companyProspects->saveRecentlyOpenedProspect($pid);
            }

            $arrLastProspectsIds = $this->_companyProspects->getRecentlyOpenedProspects($panelType == 'marketplace');

            if (!empty($arrLastProspectsIds)) {
                $arrProspectsInfo = $this->_companyProspects->getProspectsInfo($arrLastProspectsIds);

                foreach ($arrLastProspectsIds as $prospectId) {
                    foreach ($arrProspectsInfo as $arrProspectInfo) {
                        if ($arrProspectInfo['prospect_id'] == $prospectId) {
                            $arrProspects[] = array(
                                'prospect_id' => $prospectId,
                                'fName'       => $arrProspectInfo['fName'],
                                'lName'       => $arrProspectInfo['lName'],
                            );
                            continue 2;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResults = array(
            'rows'       => $arrProspects,
            'totalCount' => count($arrProspects)
        );

        return $view->setVariables($arrResults);
    }


    public function getProspectsListAction()
    {
        $strError       = '';
        $arrRows        = [];
        $totalCount     = 0;
        $allProspectIds = [];

        try {
            session_write_close();
            set_time_limit(60 * 5); // 5 minutes
            ini_set('memory_limit', '1024M');

            $panelType = $this->params()->fromPost('panelType');
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            $start                     = (int)$this->params()->fromPost('start');
            $limit                     = (int)$this->params()->fromPost('limit');
            $booLoadAllIds             = (bool)$this->params()->fromPost('booLoadAllIds', false);
            $booActiveProspectsChecked = $this->params()->fromPost('activeChecked', 'false');
            $booActiveProspectsChecked = Json::decode($booActiveProspectsChecked, Json::TYPE_ARRAY);

            $arrAdvancedSearchParams = $this->params()->fromPost('advanced_search_params');
            if (!empty($arrAdvancedSearchParams)) {
                $arrAdvancedSearchParams = Json::decode($arrAdvancedSearchParams, Json::TYPE_ARRAY);
            }

            $filter = $this->params()->fromPost('filter', '');
            if ($filter !== '') {
                $filter = trim($this->_filter->filter(Json::decode($filter, Json::TYPE_ARRAY)));
            }

            // Check prospect's type (related to the grid)
            $type = $this->params()->fromPost('type');
            if ($type == 'office') {
                $type = 'all-prospects';

                // Filter by provided offices - only for the Prospects tab
                if ($panelType == 'prospects') {
                    $arrOffices = Json::decode($this->params()->fromPost('offices'), Json::TYPE_ARRAY);
                    if (empty($arrOffices)) {
                        $strError = $this->_tr->translate('Incorrectly selected offices.');
                    } else {
                        $booAllProspects = false;
                        $arrUserOffices  = $this->_members->getDivisions(true);
                        foreach ($arrOffices as $officeId) {
                            if (empty($officeId)) {
                                $booAllProspects = true;
                                break;
                            } elseif (!in_array($officeId, $arrUserOffices)) {
                                $strError = $this->_tr->translate('Insufficient access rights.');
                                break;
                            }
                        }

                        if (!$booAllProspects) {
                            // Check/use the passed office(s)
                            $arrAdvancedSearchParams = [
                                'arrMemberOffices' => $arrOffices
                            ];
                        }
                    }
                }
            } elseif (!in_array($type, array('waiting-for-assessment', 'qualified-prospects', 'all-prospects', 'unqualified-prospects', 'invited'))) {
                $type = 'waiting-for-assessment';
            }

            if (empty($strError)) {
                // Check ordering params
                $sort = $this->_filter->filter($this->params()->fromPost('sort'));
                if (empty($sort) && $panelType == 'marketplace' && $type == 'invited') {
                    $sort = 'invited_on';
                }

                // The list of fields we want to return
                $arrReturnFields = array(
                    'prospect_id',
                    'fName',
                    'fNameReadable',
                    'lName',
                    'email',
                    'viewed',
                    'qualified_as',
                    'seriousness',
                    'create_date',
                    'update_date',
                    'email_sent',
                    'invited_on',
                    'date_of_birth',
                    'spouse_date_of_birth',
                    'mp_prospect_expiration_date',
                    'did_not_arrive'
                );

                if (in_array($sort, $arrReturnFields)) {
                    switch ($sort) {
                        case 'qualified_as':
                            // There is no 'qualified_as' column in DB
                            $sort = 'cp.create_date';
                            break;

                        case 'invited_on':
                            $sort = 'cpi.invited_on';
                            break;

                        case 'email_sent':
                            $sort = 'cps.email_sent';
                            break;

                        default:
                            $sort = 'cp.' . $sort;
                            break;
                    }
                } else {
                    $sort = 'cp.create_date';
                }

                $dir = strtoupper($this->params()->fromPost('dir', ''));
                if ($dir != 'ASC') {
                    $dir = 'DESC';
                }


                // Load prospects list with passed info
                $arrAdditionalFields = Json::decode($this->params()->fromPost('advanced_search_fields', '[]'));
                if ($this->layout()->getVariable('site_version') == 'australia') {
                    $arrAdditionalFields[] = 'qf_agent';
                    $arrAdditionalFields[] = 'qf_initial_interview_date';
                } else {
                    $arrAdditionalFields[] = 'qf_country_of_citizenship';
                    $arrAdditionalFields[] = 'qf_country_of_residence';
                    $arrAdditionalFields[] = 'qf_cat_net_worth';
                    $arrAdditionalFields[] = 'qf_job_title';
                    $arrAdditionalFields[] = 'qf_area_of_interest';
                }
                $arrAdditionalFields = array_unique($arrAdditionalFields);

                $result = $this->_companyProspects->getProspectsList(
                    $panelType,
                    $start,
                    $limit,
                    $type,
                    $filter,
                    $arrAdvancedSearchParams,
                    $sort,
                    $dir,
                    null,
                    null,
                    $arrAdditionalFields,
                    $arrReturnFields,
                    $booLoadAllIds,
                    $booActiveProspectsChecked
                );

                $arrRows        = $result['rows'];
                $totalCount     = $result['totalCount'];
                $allProspectIds = $result['allProspectIds'];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResults = array(
            'success'        => empty($strError),
            'message'        => $strError,
            'rows'           => $arrRows,
            'totalCount'     => $totalCount,
            'allProspectIds' => $allProspectIds
        );

        return new JsonModel($arrResults);
    }


    public function getProspectsUnreadCountsAction()
    {
        $view = new JsonModel();

        set_time_limit(5 * 60); // 5 minutes
        ini_set('memory_limit', '1024M');

        // Close session for writing - so next requests can be done
        session_write_close();

        $result     = array();
        $booSuccess = false;

        try {
            $panelType = $this->findParam('panelType');
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            $typesParam      = $this->findParam('types');
            $arrTypes        = !empty($typesParam) ? Json::decode($typesParam, Json::TYPE_ARRAY) : array();
            $booCorrectTypes = true;
            if (empty($arrTypes)) {
                $booCorrectTypes = false;
            } else {
                foreach ($arrTypes as $type) {
                    if (!in_array($type, array('waiting-for-assessment', 'qualified-prospects', 'unqualified-prospects', 'invited', 'all-prospects'))) {
                        $booCorrectTypes = false;
                        break;
                    }
                }
            }

            if ($booCorrectTypes) {
                $companyId       = $this->_auth->getCurrentUserCompanyId();
                $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
                $result          = $this->_companyProspects->getProspectsUnreadCounts($panelType, $companyId, $divisionGroupId, $arrTypes);
                $booSuccess      = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResults = array(
            'success' => $booSuccess,
            'counts'  => $result
        );

        return $view->setVariables($arrResults);
    }

    public function getProspectTitleAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $prospectId = (int)$this->findParam('prospectId');
            $panelType  = $this->findParam('panelType');
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                $prospectInfo = $this->_companyProspects->getProspectInfo($prospectId, $panelType);
                $strResult    = $this->_companyProspects->generateProspectName($prospectInfo);
            } else {
                $strResult = $this->_tr->translate('Access denied');
            }
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(
            [
                'content' => $strResult
            ]
        );
    }

    /**
     *
     * Save prospect's jobs
     */
    public function saveOccupationsAction()
    {
        $fileFieldsToUpdate = array();

        try {
            $booError = true;
            $strError = '';

            $booAssess   = (int)$this->findParam('booAssess');
            $prospect_id = (int)$this->_filter->filter($this->findParam('pid'));

            // User can edit prospects only from own company
            if (!$this->_companyProspects->allowAccessToProspect($prospect_id)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            $arrCheckedCategories   = array();
            $arrUncheckedCategories = array();
            if (empty($strError)) {
                $arrParams = $this->findParams();

                $q_id                   = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                $arrCheckResult         = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($arrParams, $q_id, $prospect_id, 'prospect_occupations');
                $strError               = $arrCheckResult['strError'];
                $arrProspectData        = $arrCheckResult['arrInsertData'];
                $arrCheckedCategories   = $arrCheckResult['arrCheckedCategories'];
                $arrUncheckedCategories = $arrCheckResult['arrUncheckedCategories'];


                if (empty($strError) || !$booAssess) {
                    // Update data in DB
                    $arrUpdate            = $arrProspectData['job'] ?? array();
                    $fileFieldsToUpdate[] = $this->_companyProspects->saveProspectJob($arrUpdate, $prospect_id);

                    $arrUpdate            = $arrProspectData['job_spouse'] ?? array();
                    $fileFieldsToUpdate[] = $this->_companyProspects->saveProspectJob($arrUpdate, $prospect_id, 'spouse');
                    $fileFieldsToUpdate   = array_merge($fileFieldsToUpdate[0], $fileFieldsToUpdate[1]);
                    $fileFieldsToUpdate   = array_filter($fileFieldsToUpdate);

                    // Also update recalculated prospect's assessment
                    $this->_companyProspects->saveProspectPoints($arrProspectData['prospect']['assessment'], $prospect_id);

                    // Update categories list if it must be updated
                    if (array_key_exists('categories', $arrProspectData)) {
                        $booSuccess = $this->_companyProspects->saveProspectCategories($arrProspectData['categories'], $prospect_id);
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('Internal error.');
                        } else {
                            $booError = false;
                        }
                    } else {
                        $booError = false;
                    }


                    if (!empty($strError)) {
                        $strError = $this->_tr->translate('Information was updated successfully, but there are such errors:<br/>') . $strError;
                    }
                }
            }
        } catch (Exception $e) {
            $arrCheckedCategories   = array();
            $arrUncheckedCategories = array();
            $booError               = true;
            $strError               = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (empty($strError)) {
            $strMsg = $this->_formatUpdatedCategories($arrCheckedCategories);
            $strMsg .= $this->_formatUpdatedCategories($arrUncheckedCategories, false);

            if (empty($strMsg)) {
                $strMsg = $this->_tr->translate('Information was updated successfully.');
            }
        } else {
            $strMsg = $strError;
        }

        // Return result
        $arrResult = array(
            'fileFieldsToUpdate' => $fileFieldsToUpdate,
            'error'              => $booError,
            'msg'                => $strMsg
        );

        $view = new ViewModel(
            [
                'content' => Json::encode($arrResult)
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    private function _formatUpdatedCategories($arrCategories, $booAssessed = true)
    {
        $strMsg = '';
        if (count($arrCategories)) {
            $msg = $booAssessed ?
                $this->_tr->translate('The prospect was automatically assessed under:') :
                $this->_tr->translate('The prospect was automatically unassessed under:');

            $strMsg .= '<div style="font-weight: bold;">' . $msg . '</div>';
            foreach ($arrCategories as $strCategory) {
                $strMsg .= "<div style='padding-left: 5px;'>&bull;&nbsp;" . $this->_tr->translate($strCategory) . "</div>";
            }
        }
        return $strMsg;
    }

    /**
     *
     * Save prospect's 'Business' tab information
     */
    public function saveBusinessAction()
    {
        try {
            $booError               = true;
            $strError               = '';
            $arrCheckedCategories   = array();
            $arrUncheckedCategories = array();

            $booAssess   = (int)$this->findParam('booAssess');
            $prospect_id = $this->_filter->filter($this->findParam('pid'));

            // User can edit prospects only from own company
            if (!$this->_companyProspects->allowAccessToProspect($prospect_id)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrParams = $this->findParams();

                $q_id                   = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                $arrCheckResult         = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($arrParams, $q_id, $prospect_id, 'prospect_business');
                $strError               = $arrCheckResult['strError'];
                $arrProspectData        = $arrCheckResult['arrInsertData'];
                $arrCheckedCategories   = $arrCheckResult['arrCheckedCategories'];
                $arrUncheckedCategories = $arrCheckResult['arrUncheckedCategories'];

                if (empty($strError) || !$booAssess) {
                    unset($arrProspectData['prospect']);
                    $strError = $this->_companyProspects->updateProspect($arrProspectData, $prospect_id, true);

                    $booError = false;
                    if (!empty($strError)) {
                        $strError = $this->_tr->translate('Information was updated successfully, but there are such errors:<br/>') . $strError;
                    }
                }
            }

            if (empty($strError)) {
                $strMsg = $this->_formatUpdatedCategories($arrCheckedCategories);
                $strMsg .= $this->_formatUpdatedCategories($arrUncheckedCategories, false);

                if (empty($strMsg)) {
                    $strMsg = $this->_tr->translate('Information was updated successfully.');
                }
            } else {
                $strMsg = $strError;
            }
        } catch (Exception $e) {
            $booError = true;
            $strMsg   = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return result
        $arrResult = array(
            'error' => $booError,
            'msg'   => $strMsg
        );

        $view = new ViewModel(
            [
                'content' => Json::encode($arrResult)
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }


    public function saveAssessmentAction()
    {
        try {
            $filter = new StripTags();

            $booError               = true;
            $strError               = '';
            $arrCheckedCategories   = array();
            $arrUncheckedCategories = array();

            $booAssess = (int)$this->findParam('booAssess');

            // User can edit prospects only from own company
            $prospect_id = $filter->filter($this->findParam('pid'));
            if (!$this->_companyProspects->allowAccessToProspect($prospect_id)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrParams = $this->findParams();

                $q_id                   = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                $arrCheckResult         = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($arrParams, $q_id, $prospect_id, 'prospect_assessment');
                $strError               = $arrCheckResult['strError'];
                $arrProspectData        = $arrCheckResult['arrInsertData'];
                $arrCheckedCategories   = $arrCheckResult['arrCheckedCategories'];
                $arrUncheckedCategories = $arrCheckResult['arrUncheckedCategories'];

                if (empty($strError) || !$booAssess) {
                    $strError = $this->_companyProspects->updateProspect($arrProspectData, $prospect_id);

                    $booError = false;
                    if (!empty($strError)) {
                        $strError = $this->_tr->translate('Information was updated successfully, but there are such errors:<br/>') . $strError;
                    }
                }
            }

            if (empty($strError)) {
                $strMsg = $this->_formatUpdatedCategories($arrCheckedCategories);
                $strMsg .= $this->_formatUpdatedCategories($arrUncheckedCategories, false);

                if (empty($strMsg)) {
                    $strMsg = $this->_tr->translate('Information was updated successfully.');
                }
            } else {
                $strMsg = $strError;
            }
        } catch (Exception $e) {
            $booError = true;
            $strMsg   = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return result
        $arrResult = array(
            'error' => $booError,
            'msg' => $strMsg
        );

        $view = new ViewModel(
            [
                'content' => Json::encode($arrResult)
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }


    /**
     *
     * Save prospect's profile information
     *
     */
    public function saveAction()
    {
        $view = new JsonModel();

        try {
            $error                  = array();
            $arrCheckedCategories   = array();
            $arrUncheckedCategories = array();

            // We allow only ajax request
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $error[] = $this->_tr->translate('Incorrectly selected prospect.');
            }

            // Check incoming data
            $booAssess   = (int)$this->findParam('booAssess');
            $prospect_id = $this->_filter->filter($this->findParam('pid', 0));

            $panelType = $this->findParam('panelType');
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            $booUpdateProspect = !empty($prospect_id);
            if (!count($error) && $booUpdateProspect) {
                // Check if current user has access to this prospect
                if (!$this->_companyProspects->allowAccessToProspect($prospect_id)) {
                    $error[] = $this->_tr->translate('Insufficient access rights');
                }
            }

            // Get profile fields, check them
            $arrProspectData = array();
            if (!count($error)) {
                $arrParams = $this->findParams();

                $q_id                   = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                $companyId              = $booUpdateProspect ? null : $this->_auth->getCurrentUserCompanyId();
                $arrCheckResult         = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($arrParams, $q_id, $prospect_id, 'prospect_profile', $companyId);
                $arrAllErrors           = $arrCheckResult['arrErrors'];
                $arrProspectData        = $arrCheckResult['arrInsertData'];
                $arrCheckedCategories   = $arrCheckResult['arrCheckedCategories'];
                $arrUncheckedCategories = $arrCheckResult['arrUncheckedCategories'];

                /*
                 * Create new / Update client
                 *    1. Save - check only main fields
                 *    2. Assess - check all fields
                 */
                $arrErrors = array();
                if (!$booAssess) {
                    // Check if first and last name fields are correct ONLY
                    $firstNameFieldId = 0;
                    $lastNameFieldId  = 0;
                    foreach ($arrCheckResult['arrQFields'] as $arrFieldInfo) {
                        switch ($arrFieldInfo['q_field_unique_id']) {
                            case 'qf_first_name':
                                $firstNameFieldId = $arrFieldInfo['q_field_id'];
                                break;

                            case 'qf_last_name':
                                $lastNameFieldId = $arrFieldInfo['q_field_id'];
                                break;

                            default:
                                break;
                        }
                    }

                    if (array_key_exists($firstNameFieldId, $arrAllErrors)) {
                        $arrErrors[] = $arrAllErrors[$firstNameFieldId];
                    }

                    if (array_key_exists($lastNameFieldId, $arrAllErrors)) {
                        $arrErrors[] = $arrAllErrors[$lastNameFieldId];
                    }
                } else {
                    $arrErrors = $arrAllErrors;
                }

                if (count($arrErrors)) {
                    $error = array_merge($error, $arrErrors);
                }
            }


            // There are no errors?
            // So save data in DB!
            if (!count($error)) {
                if (!$booUpdateProspect) {
                    $arrCreationResult = $this->_companyProspects->createProspect($arrProspectData);
                    $prospect_id       = $arrCreationResult['prospectId'];
                    $strError          = $arrCreationResult['strError'];
                } else {
                    if (array_key_exists('job_spouse', $arrProspectData)) {
                        $this->_companyProspects->saveProspectJob($arrProspectData['job_spouse'], $prospect_id, 'spouse');
                    }

                    $strError = $this->_companyProspects->updateProspect($arrProspectData, $prospect_id);
                }

                if (!empty($strError)) {
                    $error[] = $strError;
                }
            }


            // Generate response
            $tabName = '';
            if (count($error)) {
                $strResult = 'error';
                $strMsg    = $error;
            } else {
                $strMsg = $this->_formatUpdatedCategories($arrCheckedCategories);
                $strMsg .= $this->_formatUpdatedCategories($arrUncheckedCategories, false);

                if (!$booUpdateProspect) {
                    $arrProspectInfo = $this->_companyProspects->getProspectInfo($prospect_id, $panelType);
                    $tabName         = $this->_companyProspects->generateProspectName($arrProspectInfo);

                    $strResult = 'added';
                    $strMsg    = empty($strMsg) ? $this->_tr->translate('Prospect was created successfully.') : $strMsg;
                } else {
                    $strResult = 'edited';
                    $strMsg    = empty($strMsg) ? $this->_tr->translate('Information was updated successfully.') : $strMsg;
                }
            }
        } catch (Exception $e) {
            $prospect_id = 0;
            $tabName     = '';
            $strResult   = 'error';
            $strMsg      = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return result
        $arrResult = array(
            'result'      => $strResult,
            'prospect_id' => $prospect_id,
            'tab_id'      => $tabName,
            'msg'         => $strMsg
        );

        return $view->setVariables($arrResult);
    }


    /**
     *
     * Load prospect's notes list
     */
    public function getNotesAction()
    {
        $view = new JsonModel();

        $booSuccess = false;
        $arrNotes   = array();
        $totalCount = 0;

        try {
            $start = (int)$this->findParam('start');
            $limit = (int)$this->findParam('limit');

            $dir = $this->findParam('dir');
            if (!in_array($dir, array('ASC', 'DESC'))) {
                $dir = 'DESC';
            }

            $sort = $this->_filter->filter($this->findParam('sort'));


            $prospectId = (int)$this->findParam('member_id');

            // Load prospect's notes list if user has access to this prospect
            if (!empty($prospectId) && $this->_companyProspects->allowAccessToProspect($prospectId)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
                list($arrNotes, $totalCount) = $this->_companyProspects->getNotes($companyId, $prospectId, false, $start, $limit, $sort, $dir);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrNotes,
            'totalCount' => $totalCount
        );

        return $view->setVariables($arrResult);
    }

    public function notesAddAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $filter = new StripTags();

            $noteId      = (int)$this->findParam('note_id');
            $prospectId  = (int)$this->findParam('member_id');
            $note        = trim($filter->filter(Json::decode($this->findParam('note', ''), Json::TYPE_ARRAY)));
            $attachments = Json::decode($this->findParam('note_file_attachments'), Json::TYPE_ARRAY);

            // Check if current user has access to the prospect
            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_companyProspects->updateNotes('add', $noteId, $prospectId, $note, $attachments)) {
                $strError = $this->_tr->translate('Note was not created. Please try later.');
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

    public function notesEditAction()
    {
        $view = new JsonModel();

        try {
            $filter = new StripTags();

            //get variables
            $note_id     = (int)$this->findParam('note_id');
            $prospect_id = (int)$this->findParam('member_id');
            $note        = trim($filter->filter(Json::decode($this->findParam('note', ''), Json::TYPE_ARRAY)));
            $attachments = Json::decode($this->findParam('note_file_attachments'), Json::TYPE_ARRAY);

            // Check note id
            $success = false;
            if (!empty($note_id) && $this->_companyProspects->hasAccessToNote($note_id)) {
                $success = $this->_companyProspects->updateNotes('edit', $note_id, $prospect_id, $note, $attachments);
            }
        } catch (Exception $e) {
            $success = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $success));
    }

    public function notesDeleteAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $notes = Json::decode($this->findParam('notes'), Json::TYPE_ARRAY);
            if (!$this->_companyProspects->deleteNotes($notes)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
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

    public function getNoteAction()
    {
        $view = new JsonModel();

        $strError = '';
        $arrNote  = '';

        try {
            $noteId = (int)$this->findParam('note_id');

            if (!$this->_companyProspects->hasAccessToNote($noteId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrNote = $this->_companyProspects->getNote($noteId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'note'    => $arrNote
        );

        return $view->setVariables($arrResult);
    }

    public function getDocumentsTreeAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        ini_set('memory_limit', '512M');
        session_write_close();

        $strError  = '';
        $arrResult = array();

        try {
            $prospectId = (int)$this->params()->fromPost('prospect_id');

            if (!empty($prospectId) && $this->_companyProspects->allowAccessToProspect($prospectId)) {
                $booLocal  = $this->_auth->isCurrentUserCompanyStorageLocal();
                $arrResult = $this->_companyProspects->loadFoldersAndFilesList($booLocal, $prospectId);

                if (!is_array($arrResult)) {
                    $strError = $booLocal ?
                        $this->_tr->translate('An error happened. Please refresh files/folders list.') :
                        $this->_tr->translate('Connection to Amazon S3 lost. Please refresh files/folders list.');
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = empty($strError) ? $arrResult : array('error' => $strError);

        return new JsonModel($arrResult);
    }

    public function addFolderAction()
    {
        $view = new JsonModel();

        try {
            $name       = $this->_filter->filter(trim(Json::decode(stripslashes($this->findParam('name', '')), Json::TYPE_ARRAY)));
            $name       = StringTools::stripInvisibleCharacters($name, false);
            $parentPath = $this->_filter->filter($this->findParam('parent_id', ''));
            $parentPath = empty($parentPath) ? '' : $this->_encryption->decode($parentPath);


            $path = rtrim($parentPath, '/') . '/' . $name;

            if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                $booCreated = $this->_files->createFTPDirectory($path);
            } else {
                $booCreated = $this->_files->createCloudDirectory($path);
            }
        } catch (Exception $e) {
            $booCreated = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booCreated,
            'message' => $booCreated ? $this->_tr->translate('Folder was created successfully.') : $this->_tr->translate('Folder was not created. Please try again later.')
        );

        return $view->setVariables($arrResult);
    }

    public function renameFolderAction()
    {
        $strError = '';
        try {
            $oldPath       = $this->_encryption->decode($this->findParam('folder_id', ''));
            $newFolderName = $this->_filter->filter(Json::decode(stripslashes($this->findParam('folder_name', '')), Json::TYPE_ARRAY));
            $newFolderName = StringTools::stripInvisibleCharacters($newFolderName, false);

            if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                $oldPath    = str_replace('\\', '/', $oldPath);
                $newPath    = substr($oldPath, 0, strrpos($oldPath, '/')) . '/' . $newFolderName;
                $booSuccess = $this->_files->renameFolder($oldPath, $newPath);
            } else {
                $newPath    = substr($oldPath, 0, strlen($oldPath) - strlen($this->_files->getCloud()->getFolderNameByPath($oldPath)) - 1) . $newFolderName . '/';
                $booSuccess = $this->_files->getCloud()->renameObject($oldPath, $newPath);
            }

            if (!$booSuccess) {
                $strError = $this->_tr->translate('The folder was not renamed. Internal error.');
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

    public function renameFileAction()
    {
        $strError = '';

        try {
            $filePath = $this->_encryption->decode($this->params()->fromPost('file_id'));
            $filename = $this->_filter->filter(Json::decode(stripslashes($this->params()->fromPost('filename', '')), Json::TYPE_ARRAY));
            $filename = FileTools::cleanupFileName($filename);

            $prospectId = $this->params()->fromPost('member_id');
            $prospectId = empty($prospectId) ? 0 : (int)Json::decode($prospectId, Json::TYPE_ARRAY);

            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $filePath)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
            if (empty($strError) && !$this->_documents->renameFile($booLocal, $filePath, $filename)) {
                $strError = $this->_tr->translate('File was not renamed. Please try again later.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    /**
     * Delete folders and/or files
     * @return JsonModel
     */
    public function deleteAction()
    {
        try {
            $strError   = '';
            $prospectId = (int)$this->params()->fromPost('member_id');
            $arrNodes   = Json::decode($this->params()->fromPost('nodes'), Json::TYPE_ARRAY);

            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && empty($arrNodes)) {
                $strError = $this->_tr->translate('Nothing to delete.');
            }

            $companyId = 0;
            $booLocal  = false;
            if (empty($strError)) {
                $arrProspectInfo = $this->_companyProspects->getProspectInfo($prospectId);
                $companyId       = $arrProspectInfo['company_id'] ?? 0;
                $booLocal        = $this->_company->isCompanyStorageLocationLocal($companyId);
            }

            if (empty($strError)) {
                //decode nodes
                $arrDirsToCheck = array();
                foreach ($arrNodes as &$node) {
                    $node = $this->_filter->filter($this->_encryption->decode($node));

                    if ($booLocal) {
                        if (is_dir($node)) {
                            $arrDirsToCheck[] = $node;
                        }
                    } elseif ($this->_files->getCloud()->isFolder($node)) {
                        $arrDirsToCheck[] = $node;
                    }
                }

                if ($this->_files->delete($arrNodes, $booLocal)) {
                    // Delete top level folders from the DB if they were used in the "office access rights"
                    $topSharedDocsPath = empty($companyId) ? '' : $this->_files->getCompanySharedDocsPath($companyId, false, $booLocal);
                    foreach ($arrDirsToCheck as $fullFolderPath) {
                        if (strpos($fullFolderPath, $topSharedDocsPath) === 0) {
                            $folderName = trim(substr($fullFolderPath, -1 * (strlen($fullFolderPath) - strlen($topSharedDocsPath))), '\/');
                            if (strpos($folderName, '/') === false) {
                                $this->_company->getCompanyDivisions()->deleteFolderBasedOnDivisionAccess($companyId, $folderName);
                            }
                        }
                    }
                } else {
                    $strError = $this->_tr->translate('Internal error.');
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

    //drag&drop folder /file
    public function dragAndDropAction()
    {
        $booSuccess = false;
        try {
            $filePath   = $this->_encryption->decode($this->params()->fromPost('file_id'));
            $folderId   = $this->_encryption->decode($this->params()->fromPost('folder_id'));
            $prospectId = (int)$this->params()->fromPost('member_id');

            if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                $booSuccess = $this->_files->dragAndDropFTPFile($filePath, $folderId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => $booSuccess));
    }

    public function filesUploadAction()
    {
        $view = new JsonModel();

        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';

        try {
            //get params
            $prospectId = (int)$this->findParam('member_id');
            $folderId   = $this->_encryption->decode($this->findParam('folder_id'));
            $filesCount = (int)$this->findParam('files');

            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                //get files info and size
                $files = array();
                for ($i = 0; $i < $filesCount; $i++) {
                    $id = 'docs-upload-file-' . $i;
                    if (!empty($_FILES[$id]['name']) && !empty($_FILES[$id]['tmp_name'])) {
                        $files[$i] = $_FILES[$id];
                    }
                }

                // When drag and drop method was used - receive data in other format
                if (empty($files) && isset($_FILES['docs-upload-file']) && isset($_FILES['docs-upload-file']['tmp_name'])) {
                    if (is_array($_FILES['docs-upload-file']['tmp_name'])) {
                        for ($i = 0; $i < $filesCount; $i++) {
                            if (isset($_FILES['docs-upload-file']['tmp_name'][$i]) && !empty($_FILES['docs-upload-file']['tmp_name'][$i])) {
                                $files[$i] = array(
                                    'name'     => $_FILES['docs-upload-file']['name'][$i],
                                    'type'     => $_FILES['docs-upload-file']['type'][$i],
                                    'tmp_name' => $_FILES['docs-upload-file']['tmp_name'][$i],
                                    'error'    => $_FILES['docs-upload-file']['error'][$i],
                                    'size'     => $_FILES['docs-upload-file']['size'][$i],
                                );
                            }
                        }
                    } else {
                        $files[$i] = $_FILES['docs-upload-file'];
                    }
                }

                $booSuccess  = false;
                $booLocal    = $this->_auth->isCurrentUserCompanyStorageLocal();
                $fileNewPath = $folderId == 'root' ? $this->_companyProspects->getPathToProspect($prospectId) : $folderId;

                foreach ($files as $file) {
                    $extension = FileTools::getFileExtension($file['name']);
                    if (!$this->_files->isFileFromWhiteList($extension)) {
                        $strError = $this->_tr->translate('File type is not from whitelist.');
                        break;
                    }
                }

                if (empty($strError)) {
                    if ($this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $fileNewPath)) {
                        if ($booLocal) {
                            $this->_files->createFTPDirectory($fileNewPath);
                        }

                        foreach ($files as $file) {
                            $fileName = FileTools::cleanupFileName($file['name']);
                            if ($booLocal) {
                                $booSuccess = move_uploaded_file($file['tmp_name'], $fileNewPath . '/' . $fileName);
                            } else {
                                $fileNewPath = $this->_files->getCloud()->isFolder($fileNewPath) ? $fileNewPath : $fileNewPath . '/';
                                $filePath    = $this->_files->getCloud()->preparePath($fileNewPath) . $fileName;

                                $booSuccess = $this->_files->getCloud()->uploadFile($file['tmp_name'], $filePath);
                            }

                            if (!$booSuccess) {
                                break;
                            }
                        }
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights (to folder).');
                    }
                }
                if (!$booSuccess && empty($strError)) {
                    $strError = $this->_tr->translate('File(s) was not provided or was not uploaded.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return $view->setVariables(array('success' => empty($strError), 'error' => $strError));
    }

    /**
     * Upload files from a Dropbox URL
     *
     * Can also be used for any URL.
     */
    public function filesUploadFromDropboxAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';
        try {
            //get params
            $prospectId = (int)$this->params()->fromPost('member_id');
            $folderId   = $this->_encryption->decode($this->params()->fromPost('folder_id'));

            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $fileNewPath = $folderId == 'root' ? $this->_companyProspects->getPathToProspect($prospectId) : $folderId;

            $booSuccess = false;
            $booLocal   = $this->_auth->isCurrentUserCompanyStorageLocal();

            $fileUrl  = $this->params()->fromPost('file_url', '');
            $fileName = urldecode(basename($fileUrl));
            $fileName = explode('?', $fileName)[0];
            $fileExt  = pathinfo($fileName, PATHINFO_EXTENSION);
            $dontOverwrite = false;

            // Special handling for dropbox/google drive sharing links
            if (empty($strError) && (strpos($fileUrl, 'https://www.dropbox.com') === 0 || strpos($fileUrl, 'https://drive.google.com') === 0)) {
                list($strError, $fileUrl, $fileName, $fileExt) = $this->_files->parseDropboxGoogleShareLink($fileUrl);

                // Filenames for these links are not processed on client side, so there isn't any warning for an overwrite.
                $dontOverwrite = true;
            }

            if (empty($strError) && !$this->_files->isFileFromWhiteList($fileExt)) {
                $strError = $this->_tr->translate('File type is not from whitelist.');
            }

            if(empty($strError)) {
                $fileContentMaxLenInMemory = 1024 * 1024 * 21; // 21MB
                $fileContentMaxLen         = 1024 * 1024 * 20; // 20MB
                $fileContent               = file_get_contents($fileUrl, false, null, 0, $fileContentMaxLenInMemory);

                if($fileContent === false){
                    $strError = $this->_tr->translate('Invalid file URL.');
                } elseif(strlen($fileContent) > $fileContentMaxLen){
                    $strError = $this->_tr->translate('File size too large (exceeded 20MB).');
                }
            }

            if (empty($strError)) {
                if ($this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $fileNewPath)) {
                    // Download file to temp
                    $tempName = tempnam($this->_config['directory']['tmp'], 'TMP_');
                    file_put_contents($tempName, $fileContent);

                    if ($booLocal) {

                        if($dontOverwrite){
                            if (file_exists($fileNewPath . '/' . FileTools::cleanupFileName($fileName))) {
                                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . date('Y-m-d_H-i-s') . '.' . $fileExt;
                            }
                        }

                        $booSuccess = rename($tempName, $fileNewPath . '/' . FileTools::cleanupFileName($fileName));
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error.');
                        }
                    } else {
                        $fileNewPath = $this->_files->getCloud()->isFolder($fileNewPath) ? $fileNewPath : $fileNewPath . '/';
                        $filePath    = $this->_files->getCloud()->preparePath($fileNewPath) . FileTools::cleanupFileName($fileName);

                        if($dontOverwrite){
                            if($this->_files->getCloud()->checkObjectExists($filePath)){
                                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . date('Y-m-d_H-i-s') . '.' . $fileExt;
                                $filePath = $this->_files->getCloud()->preparePath($fileNewPath) . FileTools::cleanupFileName($fileName);
                            }
                        }

                        $booSuccess = $this->_files->getCloud()->uploadFile($tempName, $filePath);
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error (cloud).');
                        }
                    }

                    // Remove temp file
                    unlink($tempName);
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights (to folder).');
                }
            }

            if (!$booSuccess && empty($strError)) {
                $strError = $this->_tr->translate('File(s) was not provided or was not uploaded.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }

    public function filesUploadFromGoogleDriveAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';
        try {
            $prospectId = (int)$this->params()->fromPost('member_id');
            $folderId   = $this->_encryption->decode($this->params()->fromPost('folder_id'));

            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $fileName = $this->params()->fromPost('file_name');
            $fileExt  = pathinfo($fileName, PATHINFO_EXTENSION);

            if (empty($strError) && !$this->_files->isFileFromWhiteList($fileExt)) {
                $strError = $this->_tr->translate('File type is not from whitelist.');
            }

            $googleDriveApiKey = $this->_config['google_drive']['api_key'];
            if (empty($strError) && empty($googleDriveApiKey)) {
                $strError = $this->_tr->translate('Google Drive is not enabled in the config.');
            }

            if (empty($strError)) {
                $fileNewPath = $folderId == 'root' ? $this->_companyProspects->getPathToProspect($prospectId) : $folderId;
                if ($this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $fileNewPath)) {
                    // Download file to temp
                    $tempName = tempnam($this->_config['directory']['tmp'], 'TMP_');

                    $googleDriveFileId     = $this->params()->fromPost('google_drive_file_id');
                    $googleDriveOauthToken = $this->params()->fromPost('google_drive_oauth_token');

                    $client = new Client();
                    $client->setUri('https://www.googleapis.com/drive/v3/files/' . $googleDriveFileId);
                    $client->setParameterGet([
                        'key' => $googleDriveApiKey,
                        'alt' => 'media',
                    ]);
                    $client->setHeaders([
                        'Authorization' => 'Bearer ' . $googleDriveOauthToken
                    ]);
                    $client->setStream($tempName);
                    $client->send();

                    $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
                    if ($booLocal) {
                        $booSuccess = rename($tempName, $fileNewPath . '/' . FileTools::cleanupFileName($fileName));
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error.');
                        }
                    } else {
                        $fileNewPath = $this->_files->getCloud()->isFolder($fileNewPath) ? $fileNewPath : $fileNewPath . '/';
                        $filePath    = $this->_files->getCloud()->preparePath($fileNewPath) . FileTools::cleanupFileName($fileName);

                        $booSuccess = $this->_files->getCloud()->uploadFile($tempName, $filePath);
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error (cloud).');
                        }
                    }

                    // Remove temp file
                    unlink($tempName);
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights (to folder).');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }

    public function saveFileToGoogleDriveAction()
    {
        $strError = '';

        try {
            if (!$this->_auth->hasIdentity()) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $filePath = '';
            $fileName = '';
            $params   = $this->params()->fromPost();
            if (empty($strError)) {
                $filePath   = $this->_encryption->decode($params['id']);
                $prospectId = (int)$params['member_id'];
                list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($filePath);
                $booHasAccess = $this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $filePath);
                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                } else {
                    $fileName = basename($filePath);

                    if (!$booLocal) {
                        $filePath = $this->_files->getCloud()->generateFileDownloadLink($filePath, false, true, $fileName, false);

                        if (empty($filePath)) {
                            $strError = $this->_tr->translate('Error during file downloading from the Cloud.');
                        }
                    }
                }
            }

            $googleDriveApiKey = $this->_config['google_drive']['api_key'];
            if (empty($strError) && empty($googleDriveApiKey)) {
                $strError = $this->_tr->translate('Google Drive is not enabled in the config.');
            }

            if (empty($strError)) {
                $googleDriveFolderId   = $params['google_drive_folder_id'];
                $googleDriveOauthToken = $params['google_drive_oauth_token'];

                $client = new Client();
                $client->setUri('https://www.googleapis.com/upload/drive/v3/files');
                $client->setMethod('post');
                $client->setParameterGet([
                    'key'        => $googleDriveApiKey,
                    'uploadType' => 'multipart',
                ]);

                // Construct body for multipart/related
                $boundaryStr = 'officio_google_drive_aSNjjYQnncNsMJEZpPKgWunaxHDsxSyq';
                $body        = '';

                // Part 1
                $body .= '--' . $boundaryStr . "\n";
                $body .= 'Content-Type: application/json; charset=UTF-8' . "\n";
                $body .= "\n";
                $meta = [
                    'name' => FileTools::cleanupFileName($fileName)
                ];
                if (!empty($googleDriveFolderId)) {
                    $meta['parents'] = [$googleDriveFolderId];
                }
                $body .= json_encode($meta) . "\n";

                // Part 2
                $body     .= '--' . $boundaryStr . "\n";
                $mimeType = FileTools::getMimeByFileName($fileName);
                $body     .= 'Content-Type: ' . $mimeType . "\n";
                $body     .= "\n";
                $data     = file_get_contents($filePath);
                $body     .= $data . "\n";
                $body     .= '--' . $boundaryStr . '--';

                $client->setRawBody($body);

                $client->setHeaders([
                    'Authorization'  => 'Bearer ' . $googleDriveOauthToken,
                    'Content-Type'   => 'multipart/related; boundary=' . $boundaryStr,
                    'Content-Length' => strlen($body),
                ]);

                $response = $client->send();

                if ($response->getStatusCode() != 200) {
                    throw new Exception("Google Drive API v3 multipart/related upload failed. Reason: " . $response->getBody());
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }

    public function getFileAction()
    {
        $params          = $this->findParams();
        $booAsAttachment = array_key_exists('attachment', $params) ? (bool)$params['attachment'] : true;

        if (array_key_exists('enc', $params)) {
            $arrInfo         = unserialize($this->_encryption->decode($params['enc']));
            $filePath        = $arrInfo['id'];
            $prospectId      = $arrInfo['mem'];
            $currentMemberId = $arrInfo['c_mem'];
            $booExpired      = (int)$arrInfo['exp'] < time();

            if ($booExpired) {
                return $this->renderError($this->_tr->translate('File link already expired.'));
            }
        } elseif ($this->_auth->hasIdentity()) {
            $filePath        = $this->_encryption->decode($params['id']);
            $prospectId      = (int)$params['member_id'];
            $currentMemberId = $this->_auth->getCurrentUserId();
        } else {
            return $this->renderError($this->_tr->translate('Insufficient access rights.'));
        }

        list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($filePath);

        /**
         * We need check access for such cases:
         * 1. Logged-in user tries to get a file - check access to the file
         * 2. Not logged-in user (must be Zoho) tries to get a file - expiration will be checked
         */
        $booHasAccess = false;
        if ($this->_auth->hasIdentity()) {
            $booHasAccess = $this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $filePath);
        } else {
            $arrMemberInfo   = $this->_clients->getMemberInfo($currentMemberId);
            $arrProspectInfo = $this->_companyProspects->getProspectInfo($prospectId);
            if (isset($arrMemberInfo['company_id']) && isset($arrProspectInfo['prospect_id'])) {
                $booHasAccess = $this->_companyProspects->checkProspectFolderAccess($arrMemberInfo['company_id'], $prospectId, $filePath);
            }
        }

        if (!$booHasAccess) {
            return $this->renderError($this->_tr->translate('Insufficient access rights.'));
        }

        if (empty($filePath)) {
            return $this->renderError($this->_tr->translate('Incorrect params.'));
        }

        if ($booLocal) {
            return $this->downloadFile($filePath, $this->_files::extractFileName($filePath), FileTools::getMimeByFileName($filePath), false, $booAsAttachment);
        } else {
            if ($url = $this->_files->getCloud()->getFile($filePath, $this->_files::extractFileName($filePath), false, $booAsAttachment)) {
                return $this->redirect()->toUrl($url);
            } else {
                return $this->renderError($this->_tr->translate('File not found.'));
            }
        }
    }

    public function getFileDownloadUrlAction()
    {
        $view       = new JsonModel();
        $strError   = "";
        $url        = "";
        $fileName   = "";

        $params          = $this->findParams();

        if ($this->_auth->hasIdentity()) {
            $filePath        = $this->_encryption->decode($params['id']);
            $prospectId        = (int)$params['member_id'];
        } else {
            $strError = $this->_tr->translate('Insufficient access rights.');
        }

        if(empty($strError)) {
            list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($filePath);
        }

        if (empty($strError)) {
            $booHasAccess = $this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $filePath);
            if (!$booHasAccess) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        }

        if (empty($strError) && empty($filePath)) {
            $strError = $this->_tr->translate('Incorrect params.');
        }

        if (empty($strError)) {

            $url = $this->_files->generateDownloadUrlForBrowser($prospectId, true, $filePath, $booLocal, true);
            $fileName = basename($filePath);

        }

        $arrResult = array(
            'success'   => empty($strError),
            'msg'       => $strError,
            'url'       => $url,
            'file_name' => $fileName,
        );

        return $view->setVariables($arrResult);
    }

    public function moveFilesAction()
    {
        $view = new JsonModel();

        $booSuccess = false;
        try {
            $files       = Json::decode($this->findParam('files'), Json::TYPE_ARRAY);
            $folder_id   = $this->_encryption->decode($this->findParam('folder_id'));
            $prospect_id = $this->findParam('member_id');
            $companyId   = $this->_auth->getCurrentUserCompanyId();

            if ($this->_companyProspects->allowAccessToProspect($prospect_id)) {
                //decode files
                $access = true;
                foreach ($files as &$file) {
                    $file   = $this->_filter->filter($this->_encryption->decode($file));
                    $access = $this->_companyProspects->checkProspectFolderAccess($companyId, $prospect_id, $file);
                    if (!$access) {
                        break;
                    }
                }

                if ($access && $this->_companyProspects->checkProspectFolderAccess($companyId, $prospect_id, $folder_id)) {
                    $booSuccess = $this->_documents->moveFiles($files, $folder_id);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function copyFilesAction()
    {
        $strError = '';

        try {
            $arrFiles   = Json::decode($this->params()->fromPost('files'), Json::TYPE_ARRAY);
            $folderPath = $this->_encryption->decode($this->params()->fromPost('folder_id'));
            $prospectId = $this->params()->fromPost('member_id');
            $companyId  = $this->_auth->getCurrentUserCompanyId();

            if (empty($strError) && !$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && (empty($arrFiles) || !is_array($arrFiles))) {
                $strError = $this->_tr->translate('Please select a file to copy.');
            }

            if (empty($strError)) {
                foreach ($arrFiles as &$file) {
                    $file = $this->_filter->filter($this->_encryption->decode($file));
                    if (!$this->_companyProspects->checkProspectFolderAccess($companyId, $prospectId, $file)) {
                        $strError = $this->_tr->translate('Insufficient access rights to the file.');
                        break;
                    }
                }
            }

            if (empty($strError) && !$this->_companyProspects->checkProspectFolderAccess($companyId, $prospectId, $folderPath)) {
                $strError = $this->_tr->translate('Insufficient access rights to the destination folder.');
            }

            if (empty($strError) && !$this->_documents->copyFiles($arrFiles, $folderPath)) {
                $strError = $this->_tr->translate('File(s) was not copied. Please try again later.');
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

    public function newFileAction()
    {
        $view = new JsonModel();

        $fileId = '';
        try {
            $folder_id   = $this->_encryption->decode($this->findParam('folder_id'));
            $name        = $this->_filter->filter(Json::decode(stripslashes($this->findParam('name', '')), Json::TYPE_ARRAY));
            $name        = FileTools::cleanupFileName($name);
            $type        = $this->_filter->filter($this->findParam('type'));
            $prospect_id = (int)$this->findParam('member_id');

            $strError = '';
            if (!in_array($type, array('doc', 'docx', 'html', 'rtf', 'sxw', 'txt'))) {
                $strError = $this->_tr->translate('Incorrectly selected file type.');
            }

            if (empty($strError) && !$this->_companyProspects->allowAccessToProspect($prospect_id)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                if ($folder_id == 'root') {
                    $folder_id = $this->_companyProspects->getPathToProspect($prospect_id);
                }
                $fileId = $this->_documents->newFile($folder_id, $name, $type);
                if ($fileId === false) {
                    $strError = $this->_tr->translate('File was not created. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'   => empty($strError),
            'msg'       => $strError,
            'file_id'   => empty($strError) ? $this->_encryption->encode($fileId) : '',
            'path_hash' => empty($strError) ? $this->_files->getHashForThePath($fileId) : ''
        );

        return $view->setVariables($arrResult);
    }

    public function saveToInboxAction()
    {
        $view = new JsonModel();

        try {
            $prospect_id = (int)$this->findParam('member_id');
            $ids         = Json::decode($this->findParam('ids'), Json::TYPE_ARRAY);

            $strError = '';
            if (!is_array($ids) || !count($ids)) {
                $strError = $this->_tr->translate('Please select emails.');
            }

            if (empty($strError) && empty($prospect_id)) {
                $strError = $this->_tr->translate('Please select a prospect.');
            }

            if (empty($strError) && !$this->_companyProspects->allowAccessToProspect($prospect_id)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $accountId = 0;
            if (empty($strError)) {
                // TODO Add check if officio-email is present
                $accountId = MailAccount::getDefaultAccount($this->_auth->getCurrentUserId());

                if (empty($accountId)) {
                    $strError = $this->_tr->translate('There is no email account to save emails to.');
                }
            }

            if (empty($strError)) {
                $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
                foreach ($ids as $file_id) {
                    $path = $this->_encryption->decode($file_id);

                    $content = '';
                    if ($booLocal) {
                        if (is_file($path) && is_readable($path)) {
                            $content = file_get_contents($path);
                        }
                    } else {
                        $content = $this->_files->getCloud()->getFileContent($path);
                    }

                    if (!empty($content)) {
                        $msg     = new Message(array('raw' => $content));
                        $rand = rand();
                        $success = $this->_mailer->saveMessageFromServerToFolder($msg, $this->_mailer->getUniquesEmailPrefix() . md5(uniqid((string)$rand, true)), $accountId);
                        if (!$success) {
                            $strError = $this->_tr->translate('Email was not saved.');
                        }
                    } else {
                        $strError = $this->_tr->translate('Incorrect email path.');
                    }

                    if (!empty($strError)) {
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Email was saved successfully.') : $strError
        );

        return $view->setVariables($arrResult);
    }

    public function createLetterAction()
    {
        $view = new JsonModel();

        $filePath = $filename = '';
        try {
            $templateId = (int)$this->findParam('template_id');
            $prospectId = (int)$this->findParam('member_id');
            $folderPath = $this->_encryption->decode($this->findParam('folder_id'));
            $filename   = $this->_filter->filter(trim(Json::decode(stripslashes($this->findParam('filename', '')), Json::TYPE_ARRAY)));

            if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                $message = '';
                if (!empty($templateId) && $this->_companyProspects->getCompanyQnr()->hasAccessToTemplate($templateId)) {
                    // Get template
                    $templateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($templateId);
                    if (is_array($templateInfo) && count($templateInfo)) {
                        $replacements = $this->_systemTemplates->getGlobalTemplateReplacements();
                        $replacements += $this->_companyProspects->getTemplateReplacements($prospectId);
                        list($message, $subject) = $this->_systemTemplates->processText(
                            [
                                $templateInfo['message'],
                                $templateInfo['subject']
                            ],
                            $replacements
                        );

                        $filename = empty($filename) ? $subject : $filename;
                    }
                }

                $filename = empty($filename) ? 'document' : $filename;
                $filename .= '.docx';
                $filename = FileTools::cleanupFileName($filename);

                if ($folderPath == 'root') {
                    $folderPath = $this->_companyProspects->getPathToProspect($prospectId);
                }

                $filePath = $this->_documents->createLetter($message, $folderPath, $filename);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'   => !empty($filePath),
            'path_hash' => !empty($filePath) ? $this->_files->getHashForThePath($filePath) : '',
            'file_id'   => !empty($filePath) ? $this->_encryption->encode($filePath) : '',
            'filename'  => $filename
        );

        return $view->setVariables($arrResult);
    }

    public function createLetterOnLetterheadAction()
    {
        $view = new JsonModel();

        try {
            $prospect_id                          = (int)$this->findParam('member_id');
            $letterhead_id                        = (int)$this->findParam('letterhead_id');
            $folder_id                            = $this->_encryption->decode($this->findParam('folder_id'));
            $filename                             = $this->_filter->filter(trim(Json::decode(stripslashes($this->findParam('filename', '')), Json::TYPE_ARRAY)));
            $filename                             = FileTools::cleanupFileName($filename);
            $message                              = $this->_settings->getHTMLPurifier(false)->purify(Json::decode($this->findParam('message'), Json::TYPE_ARRAY));
            $booPreview                           = (bool)$this->findParam('preview');
            $arrPageNumberSettings['location']    = $this->_filter->filter(Json::decode($this->findParam('location'), Json::TYPE_ARRAY));
            $arrPageNumberSettings['alignment']   = $this->_filter->filter(Json::decode($this->findParam('alignment'), Json::TYPE_ARRAY));
            $arrPageNumberSettings['distance']    = $this->_filter->filter($this->findParam('distance'));
            $arrPageNumberSettings['wording']     = $this->_filter->filter(Json::decode($this->findParam('wording'), Json::TYPE_ARRAY));
            $arrPageNumberSettings['skip_number'] = (bool) $this->findParam('skip_number');

            if ($this->_companyProspects->allowAccessToProspect($prospect_id)) {
                if ($folder_id === 'root') {
                    $folder_id = $this->_companyProspects->getPathToProspect($prospect_id);
                }
                $letterheadsPath = $this->_files->getCompanyLetterheadsPath(
                    $this->_auth->getCurrentUserCompanyId(),
                    $this->_company->isCompanyStorageLocationLocal($this->_auth->getCurrentUserCompanyId())
                );
                $arrResult       = $this->_documents->createLetterOnLetterhead($message, $folder_id, $filename, $letterhead_id, $letterheadsPath, $booPreview, $arrPageNumberSettings);
                if (isset($arrResult['filename'])) {
                    $arrResult['filename'] = $this->_encryption->encode($arrResult['filename'] . '#' . $prospect_id);
                }
            } else {
                $strError  = $this->_tr->translate('Insufficient access rights.');
                $arrResult = array('success' => false, 'error' => $strError);
            }
        } catch (Exception $e) {
            $strError  = $this->_tr->translate('Internal error.');
            $arrResult = array('success' => false, 'error' => $strError);
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrResult);
    }

    public function previewAction()
    {
        try {
            $fileId     = $this->_encryption->decode($this->params()->fromPost('file_id'));
            $prospectId = (int)$this->params()->fromPost('member_id');

            $result = $this->_documents->preview($fileId, $prospectId, true);
        } catch (Exception $e) {
            $result['success'] = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($result);
    }

    public function getPdfAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $strError = '';

        try {
            $realPath   = $this->_encryption->decode($this->findParam('id'));
            $booTmp     = (bool)$this->findParam('boo_tmp', 0);
            $fileName   = $this->_filter->filter($this->findParam('file'));
            $prospectId = (int)$this->findParam('member_id');

            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                if ($booTmp) {
                    $attachMemberId = 0;
                    if (preg_match('/(.*)#(\d+)/', $realPath, $regs)) {
                        $realPath       = $regs[1];
                        $attachMemberId = $regs[2];
                    }

                    if (!empty($attachMemberId) && $attachMemberId == $prospectId) {
                        return $this->downloadFile($realPath, $fileName);
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                } else {
                    list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($realPath);

                    if ($this->_companyProspects->checkProspectFolderAccess($this->_auth->getCurrentUserCompanyId(), $prospectId, $filePath)) {
                        if ($booLocal) {
                            return $this->downloadFile($filePath, $fileName, '', true, false);
                        } else {
                            $url = $this->_files->getCloud()->getFile($filePath, $fileName, true, false);
                            if ($url) {
                                return $this->redirect()->toUrl($url);
                            } else {
                                return $this->fileNotFound();
                            }
                        }
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(
            [
                'content' => $strError
            ],
            true
        );
    }

    public function openPdfAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $name        = $this->_filter->filter($this->findParam('file'));
        $prospect_id = $this->findParam('member_id');
        $file        = $this->findParam('id');

        // "p-12345" -> "1234"
        $prospect_id = str_replace('p-', '', $prospect_id);

        if (empty($prospect_id) || !$this->_companyProspects->allowAccessToProspect($prospect_id)) {
            $view->setTemplate('layout/plain');
            $view->setVariables(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );

            return $view;
        }

        return $view->setVariables(
            [
                'pageTitle' => $name,
                'embedUrl'  => $this->layout()->getVariable('baseUrl') . '/prospects/index/get-pdf?file=' . $name . '&id=' . urlencode($file) . '&member_id=' . $prospect_id
            ]
        );
    }

    public function saveFileAction()
    {
        $strError = '';

        try {
            $fileId         = $this->params()->fromPost('id');
            $file           = $_FILES['content'] ?? '';
            $arrCheckParams = unserialize($this->_encryption->decode($this->params()->fromPost('enc')));

            $currentMemberId = $arrCheckParams['c_mem'];
            $prospectId      = $arrCheckParams['member_id'];
            $filePath        = $this->_encryption->decode($fileId);

            $memberInfo = $this->_clients->getMemberInfo($currentMemberId);
            if (!isset($memberInfo['company_id']) || !$this->_companyProspects->checkProspectFolderAccess($memberInfo['company_id'], $prospectId, $filePath)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && empty($fileId) || empty($file)) {
                $this->_log->debugErrorToFile('', sprintf('Save-file action with params: file_id = %s, file_path = %s', $fileId, $filePath));
                $strError = $this->_tr->translate('No file to save');
            }

            if (empty($strError) && !$this->_files->saveFile($fileId, $file)) {
                // Show a message if something wrong
                $strError = $this->_tr->translate('File was not saved.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strError);

        return $view;
    }

    public function downloadEmailAction()
    {
        try {
            $filter             = new StripTags();
            $realPath           = $filter->filter($this->_encryption->decode($this->params()->fromPost('id')));
            $attachmentFileName = $filter->filter($this->params()->fromPost('file_name'));

            if (!empty($realPath) && !empty($attachmentFileName)) {
                $fileInfo = $this->_files->getFileEmail($realPath, $attachmentFileName);

                if ($fileInfo instanceof FileInfo) {
                    return $this->file($fileInfo->content, $fileInfo->name, $fileInfo->mime);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel(
            ['content' => $this->_tr->translate('File not found.')]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        return $view;
    }

    public function getDoneTasksListAction()
    {
        $view = new JsonModel();

        $strError = '';
        $arrTasks = array();

        try {
            $arrSavedTasks = $this->_tasks->getMemberTasks(
                'prospect',
                array(
                    'assigned'    => 'me',
                    'status'      => 'active',
                    'task_is_due' => 'Y'
                ),
                'task_due_on',
                'ASC'
            );

            // Return only specific data
            $arrKeysToLoad = array('task_id', 'task_subject', 'member_id', 'member_full_name');
            foreach ($arrSavedTasks as $arrSavedTaskInfo) {
                $arrTasks[] = array_intersect_key($arrSavedTaskInfo, array_flip($arrKeysToLoad));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrTasks,
            'count'   => count($arrTasks),
        );

        return $view->setVariables($arrResult);
    }

    public function convertToClientAction()
    {
        $strErrorMessage          = '';
        $applicantEncodedPassword = '';
        $booShowWelcomeMessage    = false;
        $caseId                   = 0;
        try {
            $arrProspects = Json::decode($this->params()->fromPost('prospects'), Json::TYPE_ARRAY);

            if (!is_array($arrProspects) || !count($arrProspects)) {
                $strErrorMessage = $this->_tr->translate('Incorrectly selected prospects.');
            }

            if (empty($strErrorMessage) && !$this->_companyProspects->allowAccessToProspect($arrProspects)) {
                $strErrorMessage = $this->_tr->translate('Access Denied');
            }

            $panelType = $this->_filter->filter(Json::decode($this->params()->fromPost('panel_type', ''), Json::TYPE_ARRAY));
            if (empty($strErrorMessage) && !in_array($panelType, array('prospects', 'marketplace'))) {
                $strErrorMessage = $this->_tr->translate('Incorrectly selected panel type.');
            }

            if (empty($strErrorMessage)) {
                // Check if all required fields of the prospect are ready
                $arrNotReadyProspects = array();
                foreach ($arrProspects as $prospectId) {
                    $booReadyForConverting = false;

                    $arrProspectInfo = $this->_companyProspects->getProspectInfo($prospectId, null, false);
                    if (is_array($arrProspectInfo) &&
                        array_key_exists('fName', $arrProspectInfo) && !empty($arrProspectInfo['fName']) &&
                        array_key_exists('lName', $arrProspectInfo) && !empty($arrProspectInfo['lName'])) {
                        $booReadyForConverting = true;
                    }

                    if (!$booReadyForConverting) {
                        $arrNotReadyProspects[] = sprintf(
                            $this->_tr->translate('Prospect with id %d is not ready for converting.'),
                            $prospectId
                        );
                    }
                }


                if (count($arrNotReadyProspects)) {
                    $strErrorMessage = implode('<br/>', $arrNotReadyProspects);
                }
            }

            // If this is a "marketplace" prospect - need to get list of offices from user
            $arrProspectOffices = (array)Json::decode($this->params()->fromPost('case_office', '[]'), Json::TYPE_ARRAY);
            $companyId          = $this->_auth->getCurrentUserCompanyId();
            if (empty($strErrorMessage)) {
                $msg = sprintf(
                    $this->_tr->translate('Incorrectly selected %s.'),
                    'Office'
                );

                if (!count($arrProspectOffices)) {
                    $strErrorMessage = $msg;
                } else {
                    // Check incoming offices
                    $arrCompanyOfficeIds = $this->_company->getDivisions(
                        $companyId,
                        $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId),
                        true
                    );
                    foreach ($arrProspectOffices as $officeId) {
                        if (!in_array($officeId, $arrCompanyOfficeIds)) {
                            $strErrorMessage = $msg;
                            break;
                        }
                    }
                }
            }

            // Get and check the Immigration Program
            $clientTypeId = 0;
            $caseTypeId   = (int)Json::decode($this->params()->fromPost('case_type', 0), Json::TYPE_ARRAY);
            if (empty($strErrorMessage)) {
                $strClientType         = 'individual';
                $companyId             = $this->_auth->getCurrentUserCompanyId();
                $clientTypeId          = $this->_clients->getMemberTypeIdByName($strClientType);
                $arrCompanyCaseTypeIds = $this->_clients->getCaseTemplates()->getTemplates($companyId, true, $clientTypeId, true);
                if (!in_array($caseTypeId, $arrCompanyCaseTypeIds)) {
                    $caseTypeTerm    = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                    $strErrorMessage = $this->_tr->translate('Incorrectly selected') . ' ' . $caseTypeTerm;
                }
            }

            // Charge company before prospects will be converted
            $companyInvoiceId = null;
            if (empty($strErrorMessage) && $panelType == 'marketplace') {
                // Get company info, check if PT number was created
                $arrCompanyInfo = $this->_company->getCompanyDetailsInfo($companyId);
                if (empty($arrCompanyInfo['paymentech_profile_id'])) {
                    $strErrorMessage = $this->_tr->translate('Please create PT profile before invoice creation');
                } else {
                    try {
                        $prospectsCount = count($arrProspects);
                        $rate           = $this->_settings->variableGet('price_marketplace_prospect_convert');
                        $subtotal       = $prospectsCount * $rate;
                        $subtotal       = round((double)$subtotal, 2);

                        // Calculate gst/hst
                        $gst = $subtotal * $arrCompanyInfo['gst_used'] / 100;
                        $gst = round((double)$gst, 2);

                        // Calculate total
                        $total = $subtotal + $gst;
                        $total = round($total, 2);

                        $invoiceData = array(
                            'subscription'               => $arrCompanyInfo['subscription'],
                            'pricing_category_id'        => $arrCompanyInfo['pricing_category_id'],
                            'payment_term'               => $arrCompanyInfo['payment_term'],
                            'subscription_fee'           => $arrCompanyInfo['subscription_fee'],
                            'support_fee'                => $arrCompanyInfo['support_fee'],
                            'free_users'                 => $arrCompanyInfo['free_users'],
                            'free_clients'               => $arrCompanyInfo['free_clients'],
                            'free_storage'               => $arrCompanyInfo['free_storage'],
                            'additional_users'           => 0,
                            'additional_users_fee'       => 0,
                            'additional_storage'         => 0,
                            'additional_storage_charges' => 0,
                            'gst'                        => $gst,
                            'subtotal'                   => $subtotal,
                            'total'                      => $total,
                        );

                        // Pass and parse company prospect's info
                        $templateInfo      = SystemTemplate::loadOne(['title' => 'Marketplace Prospect to Client Fee']);
                        $replacements      = $this->_systemTemplates->getGlobalTemplateReplacements();
                        $replacements      += $this->_company->getTemplateReplacements($arrCompanyInfo);
                        $replacements      += $this->_company->getCompanyInvoice()->getTemplateReplacements($invoiceData);
                        $replacements      += $this->_companyProspects->getTemplateReplacements(
                            [
                                'prospects_count' => $prospectsCount
                            ],
                            CompanyProspects::TEMPLATE_PROSPECT_CONVERSION
                        );
                        $processedTemplate = $this->_systemTemplates->processTemplate($templateInfo, $replacements, ['to', 'subject', 'template']);

                        // 2. Save Invoice to DB
                        $invoiceData = array(
                            'company_id'      => $companyId,
                            'invoice_number'  => $this->_company->getCompanyInvoice()->generateUniqueInvoiceNumber(),
                            'invoice_date'    => date('Y-m-d'),
                            'subtotal'        => round($subtotal, 2),
                            'tax'             => round($gst, 2),
                            'total'           => round($total, 2),
                            'message'         => $processedTemplate->template,
                            'subject'         => $processedTemplate->subject,
                            'mode_of_payment' => $arrCompanyInfo['paymentech_mode_of_payment'],
                        );

                        $companyInvoiceId = $this->_company->getCompanyInvoice()->insertInvoice($invoiceData);

                        // 3. Charge invoice in PT
                        $booSendRequestToPT = $this->_config['payment']['enabled'];
                        $arrOrderResult     = $this->_company->getCompanyInvoice()->chargeSavedInvoice($companyInvoiceId, $invoiceData, $booSendRequestToPT);

                        if ($arrOrderResult['error']) {
                            if (!empty($companyInvoiceId)) {
                                // Delete created invoice...
                                $this->_company->getCompanyInvoice()->deleteInvoices(array($companyInvoiceId));
                            }

                            // Error happened
                            $strErrorMessage = sprintf(
                                $this->_tr->translate('Processing error:' .
                                    "<div style='padding: 10px 0; font-style:italic;'>%s</div>" .
                                    'Please make sure your credit card information is correct. ' .
                                    'If the error shown is not related to your credit card, ' .
                                    'please contact our support to resolve the issue promptly.'
                                ),
                                $arrOrderResult['message']
                            );
                        }
                    } catch (Exception $e) {
                        $strErrorMessage = 'Internal error.';
                        $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                    }
                }
            }

            // If there are no errors - lets convert prospect(s)
            if (empty($strErrorMessage)) {
                $result = $this->_companyProspects->convertToClient($arrProspects, $arrProspectOffices, $companyInvoiceId, $caseTypeId, $clientTypeId);

                if (!$result['success']) {
                    $strErrorMessage = count($arrProspects) > 1 ?
                        $this->_tr->translate('Prospects were not converted. Please try again later.') :
                        $this->_tr->translate('Prospect was not converted. Please try again later.');

                    if (!empty($result['error'])) {
                        $strErrorMessage .= '<br>' . $result['error'];
                    }
                } elseif (count($arrProspects) == 1) {
                    $booShowWelcomeMessage    = $result['show_welcome_message'];
                    $applicantEncodedPassword = $result['applicantEncodedPassword'];
                    $caseId                   = $result['case_id'];
                }
            }
        } catch (Exception $e) {
            $strErrorMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'                  => empty($strErrorMessage),
            'msg'                      => $strErrorMessage,
            'show_welcome_message'     => $booShowWelcomeMessage,
            'applicantEncodedPassword' => $applicantEncodedPassword,
            'case_id'                  => $caseId
        );

        return new JsonModel($arrResult);
    }

    public function deleteProspectAction()
    {
        $view = new JsonModel();

        $strErrorMessage = '';
        try {
            $arrProspects = Json::decode($this->findParam('prospects'), Json::TYPE_ARRAY);

            if (!is_array($arrProspects) || !count($arrProspects)) {
                $strErrorMessage = $this->_tr->translate('Incorrectly selected prospects.');
            }

            if (empty($strErrorMessage) && !$this->_companyProspects->allowAccessToProspect($arrProspects, true)) {
                $strErrorMessage = $this->_tr->translate('Access Denied');
            }

            if (empty($strErrorMessage)) {
                if (!$this->_companyProspects->deleteProspects($arrProspects)) {
                    $strErrorMessage = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strErrorMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strErrorMessage), 'msg' => $strErrorMessage));
    }

    public function exportToPdfAction()
    {
        $view = new ViewModel();

        $strResult = '';

        try {
            // Check incoming params
            $prospectId = (int)Json::decode($this->params()->fromQuery('pid'), Json::TYPE_ARRAY);
            $panelType  = $this->_filter->filter($this->params()->fromQuery('panelType'));
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            if (!empty($prospectId) && !$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strResult = $this->_tr->translate('Access Denied');
            }

            // Load info about the prospect
            $arrProspectInfo          = $arrProspectDetailedData = $arrProspectJobData = $arrProspectSpouseJobData = $arrAssignedProspectCategories = array();
            $strAssessmentPointsTable = '';
            if (empty($strResult)) {
                $arrProspectInfo = $this->_companyProspects->getProspectInfo($prospectId, $panelType);
                if (!$arrProspectInfo) {
                    $strResult = $this->_tr->translate('Incorrectly selected prospect');
                } else {
                    $arrProspectDetailedData  = $this->_companyProspects->getProspectDetailedData($prospectId);
                    $arrProspectJobData       = $this->_companyProspects->getProspectAssignedJobs($prospectId, true);
                    $arrProspectSpouseJobData = $this->_companyProspects->getProspectAssignedJobs($prospectId, true, 'spouse');


                    if (array_key_exists('date_of_birth', $arrProspectInfo) && (!empty($arrProspectInfo['date_of_birth']) || $arrProspectInfo['date_of_birth'] != '')) {
                        $dateFormatFull                   = $this->_settings->variable_get('dateFormatFull');
                        $arrProspectInfo['date_of_birth'] = $this->_settings->reformatDate($arrProspectInfo['date_of_birth'], Settings::DATE_UNIX, $dateFormatFull);
                    }

                    // Load Assessment info
                    $arrAssignedProspectCategories = $this->_companyProspects->getProspectReadableAssignedCategories($prospectId, $arrProspectInfo);

                    // Get saved assessment data
                    $arrAssessmentInfo = array();
                    if (array_key_exists('assessment', $arrProspectInfo) && !empty($arrProspectInfo['assessment'])) {
                        $arrAssessmentInfo = unserialize($arrProspectInfo['assessment']);
                    }

                    // Make sure that we identified if there is a spouse
                    // This is used in points calculation
                    $booHasProspectSpouse = false;
                    $maritalStatusFieldId = $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_marital_status');
                    $arrProspectData      = $this->_companyProspects->getProspectData($prospectId);
                    if ($arrProspectData) {
                        $booHasProspectSpouse = $this->_companyProspects->hasProspectSpouse((int)$arrProspectData[$maritalStatusFieldId]);
                    }

                    /** @var HelperPluginManager $viewHelperManager */
                    $viewHelperManager = $this->_serviceManager->get('ViewHelperManager');
                    /** @var Partial $partial */
                    $partial = $viewHelperManager->get('partial');

                    $viewProspectAssessment = new ViewModel();
                    $viewProspectAssessment->setVariable('booHasProspectSpouse', $booHasProspectSpouse);
                    $viewProspectAssessment->setVariable('booOnlyPointsTable', true);
                    $viewProspectAssessment->setVariable('arrAssessmentInfo', $arrAssessmentInfo);
                    $viewProspectAssessment->setVariable('booExpressEntryEnabledForCompany',$this->_company->isExpressEntryEnabledForCompany());
                    $viewProspectAssessment->setTemplate('prospects/index/prospect-assessment.phtml');
                    $strAssessmentPointsTable = $partial($viewProspectAssessment);

                    // For pdf we need update some html things... :S
                    $strAssessmentPointsTable = str_replace('cellpadding="0"', 'cellpadding="2"', $strAssessmentPointsTable);
                    $strAssessmentPointsTable = str_replace("'", '"', $strAssessmentPointsTable);
                }
            }

            if (empty($strResult)) {
                // Generate and return pdf
                $arrCategories  = $this->_clients->getCaseCategories()->getCompanyCaseCategories($this->_auth->getCurrentUserCompanyId());
                $strHtmlContent = $this->_pdf->exportProspectDataToHtml(
                    array(
                        'main'       => $arrProspectInfo,
                        'data'       => $arrProspectDetailedData,
                        'job'        => $arrProspectJobData,
                        'job_spouse' => $arrProspectSpouseJobData,
                        'categories' => $arrAssignedProspectCategories,
                        'points'     => $strAssessmentPointsTable
                    ),
                    $arrCategories
                );

                // Output generated pdf to browser
                $booSuccess = $this->_pdf->generatePDFFromHtml(
                    'Questionnaire Summary for ' . $arrProspectInfo['fName'] . ' ' . $arrProspectInfo['lName'],
                    'Questionnaire Summary.pdf',
                    $strHtmlContent,
                    false
                );

                if (!$booSuccess) {
                    $strResult = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Output error if any
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view->setVariables(
            [
                'content' => $strResult
            ],
            true
        );
    }

    public function markAction()
    {
        $view = new JsonModel();

        $strErrorMessage = '';
        try {
            $arrProspects = Json::decode($this->findParam('prospects'), Json::TYPE_ARRAY);
            $booAsRead    = Json::decode($this->findParam('booAsRead'), Json::TYPE_ARRAY);

            if (!is_array($arrProspects) || !count($arrProspects)) {
                $strErrorMessage = $this->_tr->translate('Incorrectly selected prospects.');
            }

            if (empty($strErrorMessage) && !$this->_companyProspects->allowAccessToProspect($arrProspects)) {
                $strErrorMessage = $this->_tr->translate('Access Denied');
            }

            if (empty($strErrorMessage)) {
                $this->_companyProspects->toggleProspectViewed($arrProspects, $booAsRead);
            }
        } catch (Exception $e) {
            $strErrorMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strErrorMessage), 'msg' => $strErrorMessage));
    }

    public function exportToExcelAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $title       = $this->_filter->filter(Json::decode($this->findParam('title'), Json::TYPE_ARRAY));
            $cmArr       = Json::decode($this->findParam('cm'), Json::TYPE_ARRAY);
            $exportStart = (int)$this->findParam('exportStart');
            $exportStart = empty($exportStart) ? 0 : Json::decode($exportStart, Json::TYPE_ARRAY);
            $exportRange = (int)$this->findParam('exportRange');
            $exportRange = empty($exportRange) ? 0 : Json::decode($exportRange, Json::TYPE_ARRAY);
            $data        = array();

            $panelType           = $this->_filter->filter($this->findParam('panelType'));
            if (!in_array($panelType, array('prospects', 'marketplace'))) {
                $panelType = 'prospects';
            }

            if (!is_array($cmArr) || empty($cmArr)) {
                $arrResult = array(
                    'status'  => false,
                    'content' => $this->_tr->translate('Nothing to search. Please fill search form.')
                );
                return new JsonModel($arrResult);
            }

            if ($this->getRequest()->isPost()) {
                $filter = new StripTags();

                $arrFilteredRequest = array();

                $arrParams = $this->findParams();
                if (array_key_exists('advanced_search_params', $arrParams) && !empty($arrParams['advanced_search_params'])) {
                    $arrAdditionalParams = Json::decode($arrParams['advanced_search_params'], Json::TYPE_ARRAY);
                    $arrParams           = array_merge($arrAdditionalParams, $arrParams);

                    unset($arrParams['advanced_search_params']);
                }

                foreach ($arrParams as $key => $val) {
                    $arrFilteredRequest[$key] = substr($filter->filter($arrParams[$key]), 0, 1000);
                }

                $arrAdditionalFields = array();
                foreach ($cmArr as $arrInfo) {
                    $arrAdditionalFields[] = $arrInfo['id'];
                }

                // Check ordering params
                $sort            = $this->_filter->filter($this->findParam('sort'));
                $arrReturnFields = array(
                    'prospect_id',
                    'fName',
                    'fNameReadable',
                    'lName',
                    'email',
                    'viewed',
                    'qualified_as',
                    'seriousness',
                    'create_date',
                    'update_date',
                    'email_sent',
                    'invited_on',
                    'date_of_birth',
                    'spouse_date_of_birth',
                    'mp_prospect_expiration_date',
                    'did_not_arrive'
                );

                // There is no 'qualified_as' column in DB
                $staticFieldName = $this->_companyProspects::getStaticFieldNameInDB($sort);
                $sort            = empty($staticFieldName) ? $sort : $staticFieldName;
                if (in_array($sort, $arrReturnFields)) {
                    switch ($sort) {
                        case 'qualified_as':
                            $sort = 'cp.create_date';
                            break;

                        case 'invited_on':
                            $sort = 'cpi.invited_on';
                            break;

                        case 'email_sent':
                            $sort = 'cps.email_sent';
                            break;

                        default:
                            $sort = 'cp.' . $sort;
                            break;
                    }
                } else {
                    $sort = 'cp.create_date';
                }

                $dir = $this->_filter->filter(strtoupper($this->findParam('dir', '')));
                if ($dir != 'ASC') {
                    $dir = 'DESC';
                }

                $arrResult = $this->_companyProspects->getProspectsList($panelType, $exportStart, $exportRange, 'all-prospects', '', $arrFilteredRequest, $sort, $dir, null, null, $arrAdditionalFields, null, false, null, true);

                $data = $arrResult['rows'];
            }

            // Turn off warnings - issue when generate xls file
            if (!empty($data)) {
                $spreadsheet = $this->_companyProspects->exportToExcel($cmArr, $data, $title);
                $writer      = new Xlsx($spreadsheet);

                $worksheetName = $this->_files::checkPhpExcelFileName($title);
                $worksheetName = empty($worksheetName) ? 'Export Result' : $worksheetName;
                $worksheetName .= ' ' . date('d-m-Y_H-i-s') . '.xlsx';
                $disposition   = "attachment; filename=$worksheetName";

                $pointer        = fopen('php://output', 'wb');
                $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, $disposition);
                $bufferedStream->setStream($pointer);

                $writer->save('php://output');
                fclose($pointer);

                return $view->setVariable('content', null);
            } else {
                $view->setVariable('content', $this->_tr->translate('Nothing found.'));
            }
        } catch (Exception $e) {
            $view->setVariable('content', $this->_tr->translate('Internal error.'));
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    public function deleteResumeAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $strError      = '';
            $prospectId    = (int)$this->findParam('pid');
            $prospectJobId = (int)$this->findParam('id');

            if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                $this->_companyProspects->clearProspectJobResume($prospectJobId);
                $this->_files->deleteProspectResume($prospectId, $prospectJobId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(
            [
                'content' => $strError
            ],
            true
        );
    }

    public function downloadResumeAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $prospectId    = (int)$this->findParam('pid');
            $prospectJobId = (int)$this->findParam('id');
            if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                $arrJobInfo = $this->_companyProspects->getProspectJobById($prospectId, $prospectJobId);
                if (is_array($arrJobInfo) && count($arrJobInfo)) {
                    $filePath = $this->_companyProspects->getPathToCompanyProspectJobFiles($prospectId) . '/' . $prospectJobId;
                    if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                        return $this->downloadFile($filePath, $arrJobInfo['qf_job_resume']);
                    } else {
                        $url = $this->_files->getCloud()->getFile($filePath, $arrJobInfo['qf_job_resume']);
                        if ($url) {
                            return $this->redirect()->toUrl($url);
                        } else {
                            return $this->fileNotFound();
                        }
                    }
                }
                $strError = $this->_tr->translate('Incorrect incoming info.');
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(
            [
                'content' => $strError
            ],
            true
        );
    }

    public function convertToPdfAction()
    {
        $view = new JsonModel();

        $fileId   = 0;
        $fileSize = 0;
        try {
            $filePath   = $this->_encryption->decode($this->findParam('file_id'));
            $prospectId = (int)$this->findParam('member_id');
            $folderId   = $this->findParam('folder_id');

            if ($folderId == 'root') {
                $folderPath = $this->_companyProspects->getPathToProspect($prospectId);
            } elseif ($folderId == 'tmp') {
                $folderPath = '';
            } else {
                $folderPath = $this->_encryption->decode($folderId);
            }

            $fileName = $this->_filter->filter(Json::decode(stripslashes($this->findParam('filename', '')), Json::TYPE_ARRAY));
            $fileName = FileTools::cleanupFileName($fileName);

            if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                $arrConvertingResult = $this->_documents->convertToPdf($folderPath, $filePath, $fileName);
                $strError            = $arrConvertingResult['error'];

                if (empty($strError)) {
                    $fileId   = $this->_encryption->encode($arrConvertingResult['file_id'] . '#' . $prospectId);
                    $fileSize = $arrConvertingResult['file_size'];
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'   => empty($strError),
            'error'     => $strError,
            'file_id'   => $fileId,
            'file_size' => $fileSize
        );

        return $view->setVariables($arrResult);
    }

    public function uploadAttachmentsAction()
    {
        $view = new JsonModel();

        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';
        $arrFiles = array();
        try {
            //get params
            $noteId     = (int)$this->findParam('note_id');
            $filesCount = (int)$this->findParam('files');
            $act        = $this->_filter->filter($this->findParam('act'));
            $prospectId = (int)$this->findParam('member_id');

            if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && $act == 'edit' && (empty($noteId) || !is_numeric($noteId) || !$this->_companyProspects->hasAccessToNote($noteId))) {
                $strError = $this->_tr->translate('Insufficient access to note.');
            }

            if (empty($strError)) {
                //get files info and size
                for ($i = 0; $i < $filesCount; $i++) {
                    $id = 'note-attachment-' . $i;
                    if (!empty($_FILES[$id]['name']) && !empty($_FILES[$id]['tmp_name'])) {
                        $arrFiles[$i] = $_FILES[$id];
                    }
                }

                // When drag and drop method was used - receive data in other format
                if (empty($arrFiles) && isset($_FILES['note-attachment']) && isset($_FILES['note-attachment']['tmp_name'])) {
                    if (is_array($_FILES['note-attachment']['tmp_name'])) {
                        for ($i = 0; $i < $filesCount; $i++) {
                            if (isset($_FILES['note-attachment']['tmp_name'][$i]) && !empty($_FILES['note-attachment']['tmp_name'][$i])) {
                                $arrFiles[$i] = array(
                                    'name'     => $_FILES['note-attachment']['name'][$i],
                                    'type'     => $_FILES['note-attachment']['type'][$i],
                                    'tmp_name' => $_FILES['note-attachment']['tmp_name'][$i],
                                    'error'    => $_FILES['note-attachment']['error'][$i],
                                    'size'     => $_FILES['note-attachment']['size'][$i],
                                );
                            }
                        }
                    } else {
                        $arrFiles[$i] = $_FILES['note-attachment'];
                    }
                }

                foreach ($arrFiles as $file) {
                    $extension = FileTools::getFileExtension($file['name']);
                    if (!$this->_files->isFileFromWhiteList($extension)) {
                        $strError = $this->_tr->translate('File type is not from whitelist.');
                        break;
                    }
                }

                if (empty($strError)) {
                    $config     = $this->_config['directory'];
                    $targetPath = $config['tmp'] . '/uploads/';

                    foreach ($arrFiles as $key => $file) {
                        $tmpName = md5(time() . rand(0, 99999));
                        $tmpPath = str_replace('//', '/', $targetPath) . $tmpName;
                        $tmpPath = $this->_files->generateFileName($tmpPath, true);

                        $arrFiles[$key]['tmp_name']  = $this->_encryption->encode($tmpName . '#' . $prospectId);
                        $arrFiles[$key]['file_size'] = Settings::formatSize($file['size'] / 1024);

                        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
                            $strError = $this->_tr->translate('Internal error.');
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return $view->setVariables(array('success' => empty($strError), 'error' => $strError, 'files' => $arrFiles));
    }

    public function downloadAttachmentAction()
    {
        set_time_limit(30 * 60); // 30 minutes
        ini_set('memory_limit', '512M');

        try {
            $attachId = $this->findParam('attach_id');
            $type     = $this->_filter->filter($this->findParam('type', ''));
            $memberId = (int)$this->findParam('member_id');

            // Check if current user can edit notes for this prospect
            if (!$this->_companyProspects->allowAccessToProspect($memberId)) {
                exit('Insufficient access rights.');
            }

            if (in_array($type, array('uploaded', 'note_file_attachment'))) {
                switch ($type) {
                    case 'uploaded':
                        $fileName = $this->_encryption->decode($attachId);

                        $attachMemberId = 0;
                        // File path is in such format: path/to/file#client_id
                        if (preg_match('/(.*)#(\d+)/', $fileName, $regs)) {
                            $fileName       = $regs[1];
                            $attachMemberId = $regs[2];
                        }

                        if (!empty($attachMemberId) && $attachMemberId == $memberId) {
                            $path = $this->_config['directory']['tmp'] . '/uploads/' . $fileName;
                            if (!empty($path)) {
                                return $this->downloadFile(
                                    $path,
                                    $this->_filter->filter($this->findParam('name')),
                                    'application/force-download',
                                    true
                                );
                            }
                        }
                        break;

                    default:
                    case 'note_file_attachment':
                        $path       = $this->_encryption->decode($attachId);
                        $booLocal   = $this->_auth->isCurrentUserCompanyStorageLocal();
                        $noteId     = (int)$this->findParam('note_id');
                        $folderPath = $this->_files->getProspectNoteAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $memberId, $booLocal) . '/' . $noteId;

                        if (!empty($path)) {
                            if ($booLocal) {
                                $filePath = $folderPath . '/' . $this->_files::extractFileName($path);
                                if ($filePath == $path) {
                                    return $this->downloadFile(
                                        $path,
                                        $this->_filter->filter($this->findParam('name')),
                                        'application/force-download',
                                        true
                                    );
                                }
                            } else {
                                $filePath = $folderPath . '/' . $this->_files->getCloud()->getFileNameByPath($path);
                                if ($filePath == $path) {
                                    $url = $this->_files->getCloud()->getFile(
                                        $path,
                                        $this->_filter->filter($this->findParam('name'))
                                    );
                                    if ($url) {
                                        return $this->redirect()->toUrl($url);
                                    } else {
                                        return $this->fileNotFound();
                                    }
                                }
                            }
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $this->fileNotFound();
    }

}
