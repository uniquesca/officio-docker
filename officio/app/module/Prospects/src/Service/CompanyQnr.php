<?php

namespace Prospects\Service;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\EscapeHtmlAttr;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Country;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use Officio\Common\SubServiceInterface;
use Officio\Common\SubServiceOwner;
use Officio\View\Helper\FormDropdown;
use Clients\Service\Members;
use Laminas\Validator\EmailAddress;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Company questionnaires and all related functionality
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class CompanyQnr extends SubServiceOwner implements SubServiceInterface
{

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var CompanyProspectsPoints */
    protected $_companyProspectsPoints;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var CompanyProspects */
    protected $_parent;

    /* Limitations for specific fields */
    private $_field_number_min = 0;
    private $_field_number_max = 100000;

    private $_field_age_min = 1;
    // http://en.wikipedia.org/wiki/Maximum_life_span#In_humans
    private $_field_age_max = 150; // ;)

    private $_field_money_min = 0;
    private $_field_money_max = 100000000;

    private $_field_percentage_min = 0;
    private $_field_percentage_max = 100;

    public function initAdditionalServices(array $services)
    {
        $this->_viewHelperManager = $services['ViewHelperManager'];
        $this->_country = $services[Country::class];
        $this->_company = $services[Company::class];
        $this->_triggers = $services[SystemTriggers::class];
    }

    public function getParent()
    {
        return $this->_parent;
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    /**
     * @return CompanyProspectsPoints
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getCompanyProspectsPoints()
    {
        if (is_null($this->_companyProspectsPoints)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyProspectsPoints = $this->_serviceContainer->build(CompanyProspectsPoints::class, ['parent' => $this]);
            } else {
                $this->_companyProspectsPoints = $this->_serviceContainer->get(CompanyProspectsPoints::class);
                $this->_companyProspectsPoints->setParent($this);
            }
        }

        return $this->_companyProspectsPoints;
    }

    public function onEnableCompanyProspects(EventInterface $e)
    {
        try {
            $companyId = $e->getParam('company_id');

            // Check if there are records in DB
            $arrCompanyCategories = $this->getCompanyCategories($companyId);

            if (empty($arrCompanyCategories)) {
                // Load default settings
                $arrDefaultTemplates  = $this->getProspectTemplates(0);
                $arrDefaultCategories = $this->getCompanyCategories(0);

                // Get company admin
                $adminId = $this->_company->getCompanyAdminId($companyId);

                // Create prospect templates
                $arrTemplateIds = array();
                foreach ($arrDefaultTemplates as $arrTemplateInfo) {
                    $arrInsert = array(
                        'company_id'  => $companyId,
                        'author_id'   => $adminId,
                        'name'        => $arrTemplateInfo['name'],
                        'subject'     => $arrTemplateInfo['subject'],
                        'from'        => $arrTemplateInfo['from'],
                        'cc'          => $arrTemplateInfo['cc'],
                        'bcc'         => $arrTemplateInfo['bcc'],
                        'message'     => $arrTemplateInfo['message'],
                        'create_date' => date('c')
                    );

                    $arrTemplateIds[$arrTemplateInfo['prospect_template_id']] = $this->_db2->insert('company_prospects_templates', $arrInsert);
                }

                // Create prospect categories (checked)
                foreach ($arrDefaultCategories as $arrCategoryInfo) {
                    $arrInsert = array(
                        'company_id'           => $companyId,
                        'prospect_category_id' => $arrCategoryInfo['prospect_category_id'],
                        'order'                => $arrCategoryInfo['order']
                    );

                    $this->_db2->insert('company_prospects_selected_categories', $arrInsert);
                }

                // Load info about default QNR
                $defaultQnrId  = $this->getDefaultQuestionnaireId();
                $arrDefaultQnr = $this->getQuestionnaireInfo($defaultQnrId);


                // Create new QNR
                $negativeTemplateId = 0;
                if (array_key_exists($arrDefaultQnr['q_template_negative'], $arrTemplateIds)) {
                    $negativeTemplateId = $arrTemplateIds[$arrDefaultQnr['q_template_negative']];
                }

                $thankYouTemplateId = 0;
                if (array_key_exists($arrDefaultQnr['q_template_thank_you'], $arrTemplateIds)) {
                    $thankYouTemplateId = $arrTemplateIds[$arrDefaultQnr['q_template_thank_you']];
                }

                $qnrId = $this->createQnr(
                    $companyId,
                    $adminId,
                    $arrDefaultQnr['q_name'],
                    $arrDefaultQnr['q_noc'],
                    $defaultQnrId,
                    0,
                    $negativeTemplateId,
                    $thankYouTemplateId
                );

                if (!empty($qnrId)) {
                    // Update category templates for this new QNR
                    $arrDefaultCategoryTemplates = $this->getQuestionnaireTemplates($defaultQnrId);
                    $arrValues                   = array();
                    foreach ($arrDefaultCategoryTemplates as $categoryId => $templateId) {
                        if (array_key_exists($templateId, $arrTemplateIds)) {
                            $arrValues['q_id'] = $qnrId;
                            $arrValues['prospect_category_id'] = (int)$categoryId;
                            $arrValues['prospect_template_id'] = (int)$arrTemplateIds[$templateId];
                        }
                        // Insert all at once
                        if (count($arrValues)) {
                            $this->_db2->insert('company_questionnaires_category_template', $arrValues);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function onDeleteCompany(EventInterface $e)
    {
        $companyId = $e->getParam('id');
        if (!is_array($companyId)) {
            $companyId = array($companyId);
        }

        // Collect company members
        $select = (new Select())
            ->from('members')
            ->columns(['member_id'])
            ->where(['company_id' => $companyId]);

        $arrMembersIds = $this->_db2->fetchCol($select);

        $arrWhere   = [];
        $arrWhere[] = (new Where())->equalTo('company_id', $companyId);

        // Make sure that there are users
        if (!empty($arrMembersIds)) {
            if (!is_array($arrMembersIds)) {
                $arrMembersIds = [$arrMembersIds];
            }
            $arrWhere[] = (new Where())->in('q_created_by', $arrMembersIds);
            $arrWhere[] = (new Where())->in('q_updated_by', $arrMembersIds);
        }

        $select = (new Select())
            ->from('company_questionnaires')
            ->columns(['q_id'])
            ->where($arrWhere, Where::OP_OR);

        $arrQNRsIds = $this->_db2->fetchCol($select);
        if (is_array($arrQNRsIds) && count($arrQNRsIds)) {
            $this->deleteQnr($arrQNRsIds);
        }
    }

    private function _generateFieldClass($arrClasses, $arrMetaClasses, $booBootstrap = false)
    {
        if ($booBootstrap) {
            $strMeta = '';
        } else {
            $strMeta = Json::encode($arrMetaClasses);
            $strMeta = $strMeta === '[]' ? '' : $strMeta;
        }
        $strClasses = implode(' ', $arrClasses);

        return empty($strMeta) && empty($strClasses) ? '' : sprintf("class='%s'", $strClasses . ' ' . $strMeta);
    }

    private function _generateFieldAttr($arrMetaClasses)
    {
        $strAttr = [];
        if (isset($arrMetaClasses['max'])) {
            $strAttr[] =  sprintf('max="%s"', $arrMetaClasses['max']);
        }
        if (isset($arrMetaClasses['min'])) {
            $strAttr[] =  sprintf('min="%s"', $arrMetaClasses['min']);
        }
        return join(' ', $strAttr);
    }

    /**
     * Retrieves IDs of questionnaires either belonging to a company, or
     * authored or changed by particular members.
     * @param int|false $companyId
     * @param array $authorIds
     * @param array $changersIds
     */
    public function getQnrIds($companyId = false, $authorIds = [], $changersIds = []) {
        $select = (new Select())
            ->from('company_questionnaires')
            ->columns(['q_id']);

        if ($companyId) {
            if (!is_array($companyId)) $companyId = array($companyId);
            $select->where->addPredicate((new Where())->or->equalTo('company_id', $companyId));
        }

        if ($authorIds) {
            if (!is_array($authorIds)) {
                $authorIds = [$authorIds];
            }
            $select->where->addPredicate((new Where())->or->in('q_created_by', $authorIds));
        }

        if ($changersIds) {
            if (!is_array($changersIds)) {
                $changersIds = [$changersIds];
            }
            $select->where->addPredicate((new Where())->or->in('q_updated_by', $changersIds));
        }

        return $this->_db2->fetchCol($select);
    }

    /**
     * @param int|string $qId
     * @param array $arrCustomOptions
     */
    public function copyCustomOptions($qId, $arrCustomOptions)
    {
        foreach ($arrCustomOptions as $arrCustomOption) {
            $arrInsert = [
                'q_id'                           => (int)$qId,
                'q_field_id'                     => (int)$arrCustomOption['q_field_id'],
                'q_field_custom_option_label'    => $arrCustomOption['q_field_custom_option_label'],
                'q_field_custom_option_visible'  => $arrCustomOption['q_field_custom_option_visible'],
                'q_field_custom_option_selected' => $arrCustomOption['q_field_custom_option_selected'],
                'q_field_custom_option_order'    => $arrCustomOption['q_field_custom_option_order'],
            ];

            $this->_db2->insert('company_questionnaires_fields_custom_options', $arrInsert);
        }
    }


    public function getCustomOptions($qId)
    {
        $select = (new Select())
            ->from('company_questionnaires_fields_custom_options')
            ->where(['q_id' => $qId]);

        return $this->_db2->fetchAll($select);
    }


    public function getCustomOptionLabelById($optionId)
    {
        $select = (new Select())
            ->from('company_questionnaires_fields_custom_options')
            ->columns(['q_field_custom_option_label'])
            ->where(['q_field_custom_option_id' => $optionId]);

        return $this->_db2->fetchOne($select);
    }


    public function getSeriousnessFieldOptions()
    {
        return array(
            ''  => '',
            'A' => 'A',
            'B' => 'B',
            'C' => 'C',
            'D' => 'D',
        );
    }

    public static function isFieldRequiredForSave($fieldId)
    {
        return in_array($fieldId, array('qf_first_name', 'qf_last_name', 'office_id'));
    }

    public function getUnreadableValue($value)
    {
        $fixedValue =  trim($value ?? '');
        if ($fixedValue !== '') {
            $fixedValue = $fixedValue[0] . '***';
        }

        return $fixedValue;
    }

    public function getFieldIdsWithUnreadableValue($panelType)
    {
        if ($panelType == 'marketplace') {
            $arrFieldIdsToResetValue = array(
                'qf_email',
                'qf_phone',
            );
        } else {
            $arrFieldIdsToResetValue = array();
        }

        if (count($arrFieldIdsToResetValue)) {
            foreach ($arrFieldIdsToResetValue as $fieldId) {
                $staticName = $this->getParent()::getStaticFieldNameInDB($fieldId);
                if (!empty($staticName)) {
                    $arrFieldIdsToResetValue[] = $staticName;
                }
            }
        }

        return $arrFieldIdsToResetValue;
    }


    /**
     * Generate view for field in QNR/prospect profile
     *
     * @param $booProspectProfile
     * @param string $fieldId - is used to identify the field
     * @param string $fieldId2 - is used to identify the field option(s)
     * @param int $q_id - QNR id
     * @param array $arrFieldInfo - information about that field
     * @param array $arrQFieldsOptions - possible options for this field
     * @param array $arrCountries - countries list, required to use for specific fields
     * @param string $fieldValue - default field value
     * @param string $layoutDirection - css text direction style (rtl or ltr)
     * @param bool $booShowCurrencyLabel
     * @param string $strPleaseSelect - text which will be showed for specific fields
     *
     * @param string $panelType
     * @param bool $booBootstrap
     * @return string generated view for this field
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function generateQnrField($booProspectProfile, $fieldId, $fieldId2, $q_id, $arrFieldInfo, $arrQFieldsOptions, $arrCountries, $fieldValue = '', $layoutDirection = 'ltr', $booShowCurrencyLabel = true, $strPleaseSelect = '', $panelType = 'prospects', $booBootstrap = false)
    {
        // By default all fields are required
        $arrClasses     = array();
        $arrMetaClasses = array();
        $strFieldSuffix = '';
        $icon           = '';

        if ($booBootstrap) {
            $arrClasses[] = 'form-control';
        } else {
            $arrClasses[] = 'x-form-field';
            $arrClasses[] = 'x-form-text';
        }

        /** @var FormDropdown $formDropdown */
        $formDropdown = $this->_viewHelperManager->get('formDropdown');
        /** @var EscapeHtmlAttr $escapeHtmlAttr */
        $escapeHtmlAttr = $this->_viewHelperManager->get('escapeHtmlAttr');

        // Don't return value for specific field, make it unreadable
        $arrFieldIdsToResetValue = $this->getFieldIdsWithUnreadableValue($panelType);
        if (count($arrFieldIdsToResetValue) && in_array($arrFieldInfo['q_field_unique_id'], $arrFieldIdsToResetValue)) {
            $fieldValue = $this->getUnreadableValue($fieldValue);
        }

        $pleaseSelect = empty($strPleaseSelect) ? $this->_tr->translate('-- Please select --') : $strPleaseSelect;
        if ($arrFieldInfo['q_field_required'] == 'Y') {
            $arrClasses[] = 'field_required';
            if (self::isFieldRequiredForSave($arrFieldInfo['q_field_unique_id'])) {
                $arrClasses[] = 'field_required_for_save';
            }

            $arrMetaClasses['pageRequired'] = true;
        }
        $requiredAttr = ($arrFieldInfo['q_field_required'] == 'Y') ? 'required' : '';

        if ($arrFieldInfo['q_field_unique_id'] == 'qf_relative_postcode') {
            $arrMetaClasses['postcode'] = true;
        }

        if (in_array($arrFieldInfo['q_field_unique_id'], array('qf_job_resume', 'qf_job_spouse_resume'))) {
            $arrClasses[] = 'field_do_not_duplicate';
        }

        switch ($arrFieldInfo['q_field_type']) {
            case 'label':
                $strField = '';
                break;

            case 'agent':
                /** @var Clients $clients */
                $clients   = $this->_serviceContainer->get(Clients::class);
                $arrAgents = $clients->getAgentsListFormatted(true);
                $arrAgents = empty($arrAgents) ? $arrAgents : array('' => $pleaseSelect) + $arrAgents;

                $arrClasses[] = 'profile-select';
                if ($booProspectProfile) {
                    $arrClasses[] = 'replace-select';
                }

                $strField = empty($arrAgents) ?
                    '<div style="color: #FF6600;">' . $this->_tr->translate('There are no agents for this company.') . '</div>' :
                    $formDropdown($fieldId, $arrAgents, $fieldValue, $this->_generateFieldClass($arrClasses, $arrMetaClasses));
                break;

            case 'office':
            case 'office_multi':
                // Get offices list for current company
                $arrFormattedOffices = array();

                /** @var Members $members */
            $members    = $this->_serviceContainer->get(Members::class);
            $arrOffices = $members->getDivisions();
                if (count($arrOffices)) {
                    if ($arrFieldInfo['q_field_type'] != 'office_multi') {
                        $arrFormattedOffices[''] = $pleaseSelect;
                    }

                    foreach ($arrOffices as $arrOfficeInfo) {
                        $arrFormattedOffices[$arrOfficeInfo['division_id']] = $arrOfficeInfo['name'];
                    }
                }

                $strExtra = '';
                if ($arrFieldInfo['q_field_type'] == 'office_multi') {
                    $strExtra .= ' multiple = "multiple"';
                    $arrClasses[] = 'combo-multiple';
                    $arrClasses[] = 'profile-select';
                }
                $strExtra .= $this->_generateFieldClass($arrClasses, $arrMetaClasses);

                $strField = empty($arrFormattedOffices) ?
                    '<div style="color: #FF6600;">' . $this->_tr->translate('There are no offices for this company.') . '</div>' :
                    $formDropdown($fieldId, $arrFormattedOffices, $fieldValue, $strExtra);

                break;

            case 'combo_custom':

                if ($booProspectProfile) {

                    $arrOptions = array();
                    $selected   = '';
                    $i          = 0;
                    foreach ($arrQFieldsOptions[$arrFieldInfo['q_field_id']] as $arrOptionInfo) {
                        if ($arrFieldInfo['q_field_unique_id'] == 'qf_referred_by' || $arrFieldInfo['q_field_unique_id'] == 'qf_did_not_arrive') {
                            $key = ++$i;
                        } else {
                            $key = $arrOptionInfo['q_field_custom_option_id'];
                        }
                        $arrOptions[$key] = $arrOptionInfo['q_field_custom_option_label'];
                        if ($arrOptionInfo['q_field_custom_option_label'] == $fieldValue) {
                            $selected = $key;
                        } else {
                            $selected = $fieldValue;
                        }
                    }

                    $extraClass = 'editable-combo';
                    if ($arrFieldInfo['q_field_unique_id'] == 'qf_did_not_arrive') {
                        $extraClass = 'combo-dna';
                    }

                    $strField = '<div id="' . $fieldId . '" class=" ' . $extraClass . ' hidden">' .
                        Json::encode(array('options' => $arrOptions, 'id' => $fieldId, 'default' => $selected)) .
                        '</div>';

                } else {
                    if (array_key_exists($arrFieldInfo['q_field_id'], $arrQFieldsOptions)) {
                        if ($arrFieldInfo['q_field_unique_id'] == 'qf_referred_by') {
                            $arrClasses[] = 'profile-referred-by';
                        }

                        if ($arrFieldInfo['q_field_unique_id'] == 'qf_did_not_arrive') {
                            $arrClasses[] = 'profile-did-not-arrive';
                        }

                        $selOptionId = empty($fieldValue) ? 0 : $fieldValue;

                        $arrOptions = array();
                        foreach ($arrQFieldsOptions[$arrFieldInfo['q_field_id']] as $arrOptionInfo) {
                            $arrOptions[$arrOptionInfo['q_field_custom_option_id']] = $arrOptionInfo['q_field_custom_option_label'];
                            if (empty($selOptionId)) {
                                $selOptionId = $arrOptionInfo['q_field_custom_option_id'];
                            }
                        }

                        // For some fields we need show 'Please select' option
                        if ($arrFieldInfo['q_field_show_please_select'] == 'Y') {
                            $arrOptions = array('' => $pleaseSelect) + $arrOptions;
                        }

                        if ($booBootstrap) {
                            $arrClasses[] = 'form-control';

                            $arrOptions = array('' => '') + $arrOptions;
                        }

                        $strField = $formDropdown($fieldId, $arrOptions, (int)$selOptionId, $this->_generateFieldClass($arrClasses, $arrMetaClasses));

                    } else {
                        $strField = $this->_tr->translate('There are no custom options for this combobox');
                    }
                }
                break;

            case 'combo':
                if (array_key_exists($arrFieldInfo['q_field_id'], $arrQFieldsOptions)) {

                    $arrOptions = array();
                    if ($booProspectProfile) {
                        $arrCanBeEmptyFields = array(
                            // Children
                            'qf_children_count',
                            'qf_children_age_1',
                            'qf_children_age_2',
                            'qf_children_age_3',
                            'qf_children_age_4',
                            'qf_children_age_5',
                            'qf_children_age_6',

                            // Education
                            'qf_study_previously_studied',
                            'qf_education_studied_in_canada_period',
                            'qf_education_spouse_previously_studied',
                            'qf_education_spouse_studied_in_canada_period',

                            // Work
                            'qf_work_temporary_worker',
                            'qf_work_years_worked',
                            'qf_work_currently_employed',
                            'qf_work_leave_employment',
                            'qf_study_previously_studied',
                            'qf_work_offer_of_employment',
                            'qf_work_noc',

                            // Family Relations
                            'qf_family_have_blood_relative',
                            'qf_family_relationship',
                            'qf_family_relative_wish_to_sponsor',
                            'qf_family_sponsor_age',
                            'qf_family_employment_status',
                            'qf_family_sponsor_financially_responsible',
                            'qf_family_sponsor_income',
                            'qf_family_currently_fulltime_student',
                            'qf_family_been_fulltime_student',

                            /* BUSINESS/FINANCE */
                            'qf_cat_have_experience',
                            'qf_cat_managerial_experience',
                            'qf_cat_staff_number',
                            'qf_cat_own_this_business',
                            'qf_cat_percentage_of_ownership',
                            'qf_cat_annual_sales',
                            'qf_cat_annual_net_income',
                            'qf_cat_net_assets'
                        );

                        // Add empty value for these fields
                        if (in_array($arrFieldInfo['q_field_unique_id'], $arrCanBeEmptyFields)) {
                            $arrOptions[] = '';
                        }

                        $arrClasses[] = 'replace-select';
                    }

                    // This combobox must have an empty value always
                    if (in_array($arrFieldInfo['q_field_unique_id'], array('qf_job_province', 'qf_job_spouse_province'))) {
                        $arrOptions[''] = '';
                    }

                    $selOptionId = empty($fieldValue) ? 0 : $fieldValue;

                    foreach ($arrQFieldsOptions[$arrFieldInfo['q_field_id']] as $arrOptionInfo) {
                        // Don't show "not sure" if "not sure" option isn't selected
                        $booAddOption = true;
                        if (in_array($arrFieldInfo['q_field_unique_id'], array('qf_language_english_done', 'qf_language_french_done', 'qf_language_spouse_english_done', 'qf_language_spouse_french_done')) && $arrOptionInfo['q_field_option_unique_id'] == 'not_sure' && $selOptionId != $arrOptionInfo['q_field_option_id']) {
                            $booAddOption = false;
                        }

                        if ($booAddOption) {
                            $arrOptions[$arrOptionInfo['q_field_option_id']] = array(
                                'label' => $arrOptionInfo['q_field_option_label'],
                                'data'  => $arrOptionInfo['q_field_option_unique_id']
                            );

                            if (empty($selOptionId)) {
                                $selOptionId = $arrOptionInfo['q_field_option_selected'] == 'Y' ? $arrOptionInfo['q_field_option_id'] : 0;
                            }
                        }
                    }

                    // For some fields we need show 'Please select' option
                    if ($arrFieldInfo['q_field_show_please_select'] == 'Y') {
                        $arrOptions = array('' => $pleaseSelect) + $arrOptions;
                    }

                    $arrClasses[] = 'profile-select';

                    if ($booBootstrap) {
                        $arrClasses[] = 'form-control';
                        $arrClasses[] = 'combo';
                    }

                    $strField = $formDropdown($fieldId, $arrOptions, (int)$selOptionId, $this->_generateFieldClass($arrClasses, $arrMetaClasses));
                } else {
                    $strField = $this->_tr->translate('There are no options for this combobox');
                }
                break;

            case 'country':
                $arrClasses[] = 'profile-select';
                $arrClasses[] = 'profile-country';

                if ($booProspectProfile) {
                    $arrClasses[] = 'replace-select';
                }

                $fieldValue   = $fieldValue === '' ? '' : (int)$fieldValue;

                if ($booBootstrap) {
                    $arrClasses[] = 'form-control';
                    $arrClasses[] = 'combo';
                }

                $strField = $formDropdown($fieldId, $arrCountries, $fieldValue, $this->_generateFieldClass($arrClasses, $arrMetaClasses) . ' required');
                break;

            case 'seriousness':
                $arrClasses[] = 'profile-select';
                $arrClasses[] = 'profile-seriousness';
                if ($booProspectProfile) {
                    $arrClasses[] = 'replace-select';
                }

                $fieldValue   = $fieldValue === '' ? '' : $fieldValue;
                $strField     = $formDropdown($fieldId, $this->getSeriousnessFieldOptions(), $fieldValue, $this->_generateFieldClass($arrClasses, $arrMetaClasses));
                break;

            case 'radio':
                $strField = '';
                if (!empty($arrQFieldsOptions[$arrFieldInfo['q_field_id']])) {
                    $strField          = '<div class="col-xl-12 row no-gutters" style="gap: 16px; display: flex;">';
                    $labelClass = count($arrQFieldsOptions[$arrFieldInfo['q_field_id']]) > 2 ?  'uf-radio col-sm-auto' : 'uf-radio';
                    foreach ($arrQFieldsOptions[$arrFieldInfo['q_field_id']] as $arrOptionInfo) {
                        $optionId = 'option_' . $fieldId2 . '_' . $arrOptionInfo['q_field_option_id'];

                        $checked = '';
                        if ((!empty($fieldValue) && $arrOptionInfo['q_field_option_id'] == $fieldValue) || $arrOptionInfo['q_field_option_selected'] == 'Y') {
                            $checked = 'checked="checked"';
                        }

                        $class = $this->_generateFieldClass($arrClasses, $arrMetaClasses);
                        $strField .= "<label class='$labelClass' for='$optionId' style='align-items: center; gap: 5px;'>";
                        $strField .= "<input id='$optionId' name='$fieldId' type='radio' $checked $class data-val='" . $arrOptionInfo['q_field_option_unique_id'] . "' value='" . $arrOptionInfo['q_field_option_id'] . "'/>";
                        $strField .= $arrOptionInfo['q_field_option_label'] . "</label>";
                    }
                    $strField .= '</div>';
                }
                break;

            case 'checkbox':
                $strField = '';
                if (!empty($arrQFieldsOptions[$arrFieldInfo['q_field_id']])) {
                    $strField          = '<div class="uf-checkbox-group col-xl-12 row no-gutters" style="gap: 5px; display: flex; flex-wrap: wrap; flex-direction: column;">';
                    $arrValues = explode(',', $fieldValue);
                    foreach ($arrQFieldsOptions[$arrFieldInfo['q_field_id']] as $arrOptionInfo) {
                        $optionId = 'option_' . $fieldId2 . '_' . $arrOptionInfo['q_field_option_id'];

                        $checked = '';
                        if (
                            ($arrValues && count($arrValues) && in_array($arrOptionInfo['q_field_option_id'], $arrValues)) ||
                            $arrOptionInfo['q_field_option_selected'] == 'Y'
                        ) {
                            $checked = 'checked="checked"';
                        }

                        $class = $this->_generateFieldClass($arrClasses, $arrMetaClasses);
                        $strField .= "<label class='uf-checkbox col-xl-12' for='$optionId' style='align-items: center; gap: 5px; display: flex;'>";
                        $strField .= "<input data-readable-value='" . $arrOptionInfo['q_field_option_unique_id'] . "' id='$optionId' name='{$fieldId}[]' type='checkbox' $checked $class data-val='" . $arrOptionInfo['q_field_option_unique_id'] . "' value='" . $arrOptionInfo['q_field_option_id'] . "' style='height: 23px !important; width: 23px !important;' />";
                        $strField .= $arrOptionInfo['q_field_option_label'] . "</label>";

                    }
                    $strField .= '</div>';
                }
                break;

            case 'status':
                $strField  = '<table class="no-top-padding">'; // Use table because of rtl direction...
                $optionId  = 'option_' . $fieldId2 . '_' . 'active';
                $checked   = $fieldValue == 'active' ? 'checked="checked"' : '';
                $class     = $this->_generateFieldClass($arrClasses, $arrMetaClasses);

                $strField .= "<tr><td><input class='prospect_status' id='$optionId' name='{$fieldId}[]' type='checkbox' $checked $class value='active'/>";
                $strField .= "<label for='$optionId' style='padding: 0 5px;'>" . 'Active' . "</label></td></tr>";
                $strField .= '</table>';
                break;

            case 'date':
                $arrClasses[] = 'datepicker';

                if (!$booBootstrap) {
                    $arrClasses[] = 'dir-' . $layoutDirection;
                    if ($booProspectProfile) {
                        $arrClasses[] = 'prospect-profile-date';
                    }
                }
                $class        = $this->_generateFieldClass($arrClasses, $arrMetaClasses);
                // Format date, so it will be showed correctly in datepicker
                $fieldValue = Settings::isDateEmpty($fieldValue) ? '' : $fieldValue;
                if (!empty($fieldValue)) {
                    $dateFormatFull = $this->_settings->variable_get('dateFormatFull');
                    $fieldValue     = date($dateFormatFull, strtotime($fieldValue));
                }

                if ($booProspectProfile) {
                    $strField = "<div><input type='text' id='$fieldId' name='$fieldId' $requiredAttr $class value='" . $escapeHtmlAttr($fieldValue) . "' /></div>";
                } else {
                    $strField = "<input type='text' id='$fieldId' name='$fieldId' $requiredAttr $class value='" . $escapeHtmlAttr($fieldValue) . "' />";
                }

                break;

            case 'job':
                $arrClasses[] = 'dir-' . $layoutDirection;
                $arrClasses[] = 'job_search';
                if ($booBootstrap) {
                    $arrClasses[] = 'form-control';
                    $arrClasses[] = 'ajax-typeahead';
                }

                $class    = $this->_generateFieldClass($arrClasses, $arrMetaClasses);
                $strField = "<input type='text' id='$fieldId' name='$fieldId' $requiredAttr $class value='" . $escapeHtmlAttr($fieldValue) . "' autocomplete='off' />";
                break;

            case 'job_and_noc':
                $arrClasses[] = 'dir-' . $layoutDirection;
                $arrClasses[] = 'job_and_noc_search';
                if ($booBootstrap) {
                    $arrClasses[] = 'form-control';
                    $arrClasses[] = 'ajax-typeahead';
                }
                $class = $this->_generateFieldClass($arrClasses, $arrMetaClasses);

                if ($fieldValue !== '') {
                    $arrAllJobTitles = $this->getParent()->getAllJobTitles();
                    foreach ($arrAllJobTitles as $arrJobTitleInfo) {
                        if ($arrJobTitleInfo['noc_job_title'] == $fieldValue) {
                            $fieldValue = $arrJobTitleInfo['noc_job_and_code'];
                            break;
                        }
                    }
                }

                $strField = "<input type='text' id='$fieldId' name='$fieldId' $requiredAttr $class value='" . $escapeHtmlAttr($fieldValue) . "' />";
                break;

            case 'file':
                if (!$booBootstrap) {
                    $arrClasses[] = 'form-control-file';
                } else {
                    $arrClasses[] = 'dir-' . $layoutDirection;
                    $arrClasses[] = 'file';
                }
                $class    = $this->_generateFieldClass($arrClasses, $arrMetaClasses);
                $strField = "<input type='file' id='$fieldId' name='$fieldId' $requiredAttr $class value='" . $escapeHtmlAttr($fieldValue) . "' />";

                break;

            case 'textarea':
                $arrClasses[] = 'profile-textarea';
                if ($booBootstrap) {
                    $arrClasses[] = 'form-control';

                    $style = "style='width: 100%; min-height: 120px;'";
                } else {
                    $arrClasses[] = 'dir-' . $layoutDirection;

                    $style = "style='width: 80%; min-height: 120px;'";
                }

                $class    = $this->_generateFieldClass($arrClasses, $arrMetaClasses);
                $strField = "<textarea $style id='$fieldId' name='$fieldId' $requiredAttr $class>$fieldValue</textarea>";
                break;

            default:
                $arrClasses[] = 'dir-' . $layoutDirection;
                if ($booProspectProfile) {
                    $arrClasses[] = 'prospect-profile-text';
                }

                if ($booBootstrap) {
                    $arrClasses[] = 'form-control';
                }
                // For specific fields apply custom styles
                if (in_array($arrFieldInfo['q_field_unique_id'], array('qf_phone', 'qf_fax'))) {
                    $arrClasses[] = 'profile-phone';
                }
                $inputType = 'text';

                // Additional rules for validator
                switch ($arrFieldInfo['q_field_type']) {
                    case 'email':
                        $arrClasses[]            = 'profile-email';
                        $arrMetaClasses['email'] = true;

                        if ($arrFieldInfo['q_field_unique_id'] == 'qf_email_confirmation') {
                            $emailFieldId              = $this->getFieldIdByUniqueId('qf_email');
                            $arrMetaClasses['equalToIgnoreCase'] = "#q_" . $q_id . "_field_$emailFieldId";
                        }
                        $inputType = 'email';
                        break;

                    case 'number':
                        if ($booBootstrap) {
                            $arrClasses[] = 'form-control';
                        } else {
                            $arrClasses[] = 'profile-number';
                        }
                        if (preg_match("/^qf_language_english_ielts_score_/", $arrFieldInfo['q_field_unique_id']) || preg_match("/^qf_language_spouse_english_ielts_score_/", $arrFieldInfo['q_field_unique_id'])) {
                            $arrMetaClasses['integer'] = false;
                            $arrMetaClasses['min']     = 0;
                            $arrMetaClasses['max']     = 10;
                        } elseif (preg_match("/^qf_language_french_tef_score_/", $arrFieldInfo['q_field_unique_id']) || preg_match("/^qf_language_spouse_french_tef_score_/", $arrFieldInfo['q_field_unique_id'])) {
                            $arrMetaClasses['integer'] = true;
                            $arrMetaClasses['min']     = 0;
                            $arrMetaClasses['max']     = 900;
                        } else {
                            $arrMetaClasses['integer'] = true;
                            $arrMetaClasses['min']     = $this->_field_number_min;
                            $arrMetaClasses['max']     = $this->_field_number_max;
                        }
                        $inputType = 'number';
                        break;

                    case 'age':
                        if ($booBootstrap) {
                            $arrClasses[] = 'form-control';
                        } else {
                            $arrClasses[] = 'profile-age';
                        }
                        $arrMetaClasses['integer'] = true;
                        $arrMetaClasses['min']     = $this->_field_age_min;
                        $arrMetaClasses['max']     = $this->_field_age_max;
                        $inputType = 'number';
                        break;

                    case 'money':
                        if ($booBootstrap) {
                            $arrClasses[] = 'form-control';
                            $icon = $booShowCurrencyLabel ? '<div class="input-group-append">
                                <span class="input-group-text">' . Clients\Accounting::getCurrencyLabel($this->_settings->getSiteDefaultCurrency(false)) . '</span>
                                </div>' : '';
                        } else {
                            $arrClasses[] = 'profile-money';
                            $strFieldSuffix = $booShowCurrencyLabel ? '&nbsp;' . Clients\Accounting::getCurrencyLabel($this->_settings->getSiteDefaultCurrency(false)) : '';
                            $arrMetaClasses['number'] = true;
                        }
                        $arrMetaClasses['min']    = $this->_field_money_min;
                        $arrMetaClasses['max']    = $this->_field_money_max;
                        $inputType                = 'number';
                        break;

                    case 'percentage':
                        if ($booBootstrap) {
                            $arrClasses[] = 'form-control';
                            $icon = '<div class="input-group-append">
                                <span class="input-group-text">%</span>
                                </div>';
                        } else {
                            $arrClasses[]   = 'profile-percentage';
                            $strFieldSuffix = '&nbsp;%';
                        }
                        $arrMetaClasses['number'] = true;
                        $arrMetaClasses['min']    = $this->_field_percentage_min;
                        $arrMetaClasses['max']    = $this->_field_percentage_max;
                        $inputType = 'number';

                        break;

                    default:
                        break;
                }

                $class    = $this->_generateFieldClass($arrClasses, $arrMetaClasses, $booBootstrap);
                $attrs    = $this->_generateFieldAttr($arrMetaClasses);
                $strField = "<input type='$inputType' id='$fieldId' name='$fieldId' $requiredAttr $attrs $class value='" . $escapeHtmlAttr($fieldValue) . "'/> $icon$strFieldSuffix";
                break;
        }

        if ($panelType == 'marketplace') {
            //Handle empty Resume fields
            if(strpos($strField, "type='file'") !== false && strpos($strField, "value=''") !== false) {
                //Set "-" only for first applicant and spouse job section
                $arr = explode(' ', $strField);
                if(substr_count($arr[2], '_') != 4){
                    $strField = "<div style='text-align: left;'>-</div>";
                }
            }

            if(strpos($strField, '<input') !== false) {
                $strField = str_replace('<input', '<input disabled ', $strField);
            }

            if(strpos($strField, '<select') !== false) {
                $strField =  str_replace('<select', '<select disabled ', $strField);
            }

            //Disable checkboxes
            if(strpos($strField, 'checkbox') !== false) {
                $strField = str_replace('<input', '<input disabled ', $strField);
            }

            //Disable textarea
            if(strpos($strField, 'textarea') !== false) {
                $strField = str_replace('<textarea', '<textarea disabled ', $strField);
            }

            //"Referred by" field handling
            if(strpos($strField, '<div') !== false) {
                $strField = str_replace('<div', '<div disabled ', $strField);
            }

        }

        return $strField;
    }


    /**
     * Remove special symbols at the end of the field label
     * E.g. remove ':'
     *
     * @param string $fieldLabel
     * @return string filtered label
     */
    private function _getReadableFieldLabel($fieldLabel)
    {
        return (substr($fieldLabel, -1, 1) == ':') ?
            substr($fieldLabel, 0, strlen($fieldLabel) - 1) : $fieldLabel;
    }


    /**
     * Check incoming QNR data
     *
     * @param array $arrParams - incoming data to check
     * @param int $q_id - QNR id, for which we'll check data
     * @param string $prospect_id - prospect id is required
     * to check if prospect's profile was updated
     * @param string $method
     *
     * @param null $companyId
     * @return array :
     *  strError - error details if any
     * arrInsertData - array of collected data, grouped by table and field
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function checkIncomingQNRData($arrParams, $q_id, $prospect_id = '', $method = 'qnr', $companyId = null)
    {
        $filter         = new StripTags();
        $emailValidator = new EmailAddress();
        $arrQInfo       = $this->getQuestionnaireInfo($q_id);

        /** @var Clients $oClients */
        $oClients = $this->_serviceContainer->get(Clients::class);

        // Get company id (from QNR or from prospect info)
        if (is_numeric($prospect_id)) {
            if (empty($prospect_id)) {
                $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
            } else {
                $arrProspectInfo = $this->getParent()->getProspectInfo($prospect_id, null, false);
                $companyId       = is_null($companyId) ? $arrProspectInfo['company_id'] : $companyId;
            }
        } else {
            $companyId = $arrQInfo['company_id'];
        }

        $arrOffices = [];
        if (!empty($companyId)) {
            $divisionGroupId = $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId);
            $arrOffices = $oClients->getDivisions(true, $companyId, $divisionGroupId);
        }

        $arrCaseCategories = $oClients->getCaseCategories()->getCompanyCaseCategories($companyId);

        $arrInsertData = array();

        // Collect received fields and their selected values
        // One field can have several values - so save them in array
        $arrReceivedFields = array();

        // QNR or prospect will be saved
        // So fields are named in almost same way
        $fieldMask         = !is_numeric($prospect_id) ? '/q_' . $q_id . '_field_(.*)/i' : '/p_' . $prospect_id . '_field_(.*)/i';
        $fieldEmployerMask = !is_numeric($prospect_id) ? '/q_' . $q_id . '_(.*)employer_field_(.*)/i' : '/p_' . $prospect_id . '_(.*)employer_field_(.*)/i';

        // Load fields and options list for this qnr
        $arrQFields        = $this->getQuestionnaireFields($q_id, true, $method == 'qnr');
        $arrQFieldsOptions = $this->getQuestionnaireFieldsOptions($q_id);

        $arrErrors = array();
        $strError  = '';

        // Get incoming files
        foreach ($_FILES as $file => $val) {
            if ($file) {
                $val['id'] = $file;
                if (preg_match($fieldMask, $file, $regs)) {
                    if (preg_match('/(\d+)_(\d+)/i', $regs[1], $regs2)) {
                        $fieldId = $regs2[1];
                    } elseif (is_numeric($regs[1])) {
                        $fieldId = $regs[1];
                    }
                    if (!empty($fieldId)) {
                        if (preg_match('/^qf_job_spouse_/', $arrQFields[$fieldId]['q_field_unique_id'])) {
                            $arrInsertData['job_spouse'][$arrQFields[$fieldId]['q_field_unique_id']][] = $val;
                        } elseif (preg_match('/^qf_job_/', $arrQFields[$fieldId]['q_field_unique_id'])) {
                            $arrInsertData['job'][$arrQFields[$fieldId]['q_field_unique_id']][] = $val;
                        } else {
                            $arrInsertData['data'][$fieldId] = $val;
                        }
                    }
                }
            }
        }

        // Get only 'our' fields from incoming params
        foreach ($arrParams as $fullFieldId => $fieldVal) {
            if (preg_match($fieldMask, $fullFieldId, $regs)) {
                $fieldVal = is_array($fieldVal) ? $fieldVal : $filter->filter(trim($fieldVal));
                $fieldId  = 0;
                if (preg_match('/(\d+)_(\d+)/i', $regs[1], $regs2)) {
                    $fieldId    = $regs2[1];
                    $fieldValId = $regs2[2];
                } elseif (is_numeric($regs[1])) {
                    $fieldId    = $regs[1];
                    $fieldValId = 0;
                } else {
                    switch ($regs[1]) {
                        case 'preferred_language':
                        case 'agent_id':
                            $arrInsertData['prospect'][$regs[1]] = empty($fieldVal) ? null : $fieldVal;
                            break;

                        case 'qf_job_spouse_has_experience':
                            break;

                        case 'office_id':
                            if ($method == 'prospect_profile') {
                                if (count($arrOffices)) {
                                    if (empty($fieldVal)) {
                                        $strError = $this->_tr->translate('Please select office.');
                                    } else {
                                        $arrSelectedOffices = explode(',', $fieldVal);
                                        foreach ($arrSelectedOffices as $selectedOffice) {
                                            if (!in_array($selectedOffice, $arrOffices)) {
                                                $strError = $this->_tr->translate('Office was selected incorrectly.');
                                                break;
                                            }
                                        }
                                    }
                                } else {
                                    $fieldVal = '';
                                }
                            }
                            $arrInsertData['prospect_offices'] = empty($fieldVal) ? '' : explode(',', $fieldVal);
                            break;

                        case 'seriousness':
                            $arrOptions = $this->getSeriousnessFieldOptions();
                            if (!array_key_exists($fieldVal, $arrOptions)) {
                                $fieldVal = '';
                            }
                            $arrInsertData['prospect'][$regs[1]] = empty($fieldVal) ? null : $fieldVal;
                            break;

                        case 'visa':
                            $booFound = false;
                            foreach ($arrCaseCategories as $arrOptionInfo) {
                                if ($arrOptionInfo['client_category_id'] == $fieldVal) {
                                    $booFound = true;
                                    break;
                                }
                            }

                            if (!$booFound) {
                                $fieldVal = '';
                            }

                            $arrInsertData['prospect']['visa'] = $fieldVal;
                            break;

                        case 'notes':
                            $arrInsertData['prospect']['notes'] = empty($fieldVal) ? null : $fieldVal;
                            break;

                        case 'status':
                            $arrInsertData['prospect']['status'] = empty($fieldVal) ? 'inactive' : 'active';
                            break;

                        case 'did_not_arrive':
                            $arrInsertData['prospect']['did_not_arrive'] = empty($fieldVal) ? null : 1;
                            break;

                        default:
                            break;
                    }
                    $fieldValId = 0;
                }

                if (!isset($arrInsertData['prospect']['status']) && $method == 'prospect_profile') {
                    $arrInsertData['prospect']['status'] = 'inactive';
                }


                if (!empty($fieldId)) {
                    $arrReceivedFields[$fieldId][$fieldValId] = $fieldVal;
                }
            } elseif (preg_match($fieldEmployerMask, $fullFieldId, $regs)) {
                $fieldVal   = is_array($fieldVal) ? $fieldVal : $filter->filter(trim($fieldVal));
                $fieldId    = 0;
                $fieldValId = 0;
                if (preg_match('/(\d+)_(\d+)/i', $regs[2], $regs2)) {
                    $fieldId    = $regs2[1];
                    $fieldValId = $regs2[2];
                    if ($regs[1] == 'spouse_') {
                        $arrInsertData['job_spouse']['qf_job_order'][] = $fieldValId;
                    } else {
                        $arrInsertData['job']['qf_job_order'][] = $fieldValId;
                    }
                } elseif (is_numeric($regs[2])) {
                    $fieldId    = $regs[2];
                    if ($regs[1] == 'spouse_') {
                        $arrInsertData['job_spouse']['qf_job_order'][] = $fieldValId;
                    } else {
                        $arrInsertData['job']['qf_job_order'][] = $fieldValId;
                    }
                }

                if (!empty($fieldId)) {
                    $arrReceivedFields[$fieldId][$fieldValId] = $fieldVal;
                }
            }
        }

        // Get countries list
        $arrCountriesList = $this->_country->getCountries(true);
        $arrCountriesIds  = array_keys($arrCountriesList);

        // Collect only field ids
        $arrQnrFieldsIds = array();
        foreach ($arrQFields as $arrFieldInfo) {
            $arrQnrFieldsIds[] = $arrFieldInfo['q_field_id'];
        }


        // Collect data, group by tables
        // Additional data from prospect's profile
        if (empty($strError) && is_numeric($prospect_id) && $method == 'prospect_assessment') {
            $arrCategories = $this->getCategories(true);

            // Categories
            $booCheckedUnqualified = false;
            $mask                  = '/p_' . $prospect_id . '_category[_]?(.*)/i';
            foreach ($arrParams as $fieldId => $fieldVal) {
                if (preg_match($mask, $fieldId, $regs)) {
                    // Only allowed company categories can be selected
                    switch ($regs[1]) {
                        case 'pnp':
                            $val = trim($arrParams['p_' . $prospect_id . '_category_pnp-val'] ?? '');
                            if (empty($val)) {
                                $strError = $this->_tr->translate('Please enter PNP category in the field.');
                                break 2;
                            }
                            break;

                        case 'pnp-val':
                            $val = '';
                            if (isset($arrParams['p_' . $prospect_id . '_category_pnp'])) {
                                $val = trim($arrParams['p_' . $prospect_id . '_category_pnp-val'] ?? '');
                            }
                            $arrInsertData['prospect']['category_pnp'] = $val;
                            break;

                        case 'other':
                            $val = trim($arrParams['p_' . $prospect_id . '_category_other-val'] ?? '');
                            if (empty($val)) {
                                $strError = $this->_tr->translate('Please enter Other category in the field.');
                                break 2;
                            }
                            break;

                        case 'other-val':
                            $val = '';
                            if (isset($arrParams['p_' . $prospect_id . '_category_other'])) {
                                $val = trim($arrParams['p_' . $prospect_id . '_category_other-val'] ?? '');
                            }
                            $arrInsertData['prospect']['category_other'] = $val;
                            break;

                        case 'unqualified':
                            $booCheckedUnqualified = true;
                            break;

                        default:
                            // We can pass checked categories as radios (so one value will be provided)
                            // or we can send a list of checkboxes, so we'll use value from the name
                            if ($regs[1] === '' && (in_array($fieldVal, $arrCategories) || $fieldVal == 'unqualified')) {
                                if ($fieldVal == 'unqualified') {
                                    $booCheckedUnqualified = true;
                                } else {
                                    $arrInsertData['categories'][] = $fieldVal;
                                }
                            } else {
                                if (!empty($arrCategories) && !in_array($regs[1], $arrCategories)) {
                                    $strError = $this->_tr->translate('Incorrectly selected categories.');
                                    break 2;
                                }
                                $arrInsertData['categories'][] = $regs[1];
                            }
                            break;
                    }
                }
            }

            if ($booCheckedUnqualified) {
                $strError = '';

                // Ignore checked categories
                $arrInsertData['categories']                 = array();
                $arrInsertData['prospect']['category_pnp']   = '';
                $arrInsertData['prospect']['category_other'] = '';
                $arrInsertData['prospect']['qualified']      = 'N';
                $arrInsertData['prospect']['visa']           = '';
            } else {
                if (empty($arrInsertData['categories'])) {
                    // Reset categories if needed
                    $arrInsertData['categories'] = array();
                }

                if (empty($arrInsertData['categories']) &&
                    empty($arrInsertData['prospect']['category_other']) &&
                    empty($arrInsertData['prospect']['category_pnp'])
                ) {
                    // No categories were selected -
                    // mark this prospect as 'Waiting for Assessment'
                    $arrInsertData['prospect']['qualified'] = null;
                } else {
                    $arrInsertData['prospect']['qualified'] = 'Y';
                }
            }
        }

        if (!empty($strError)) {
            $arrErrors[] = $strError;
        }

        // Don't proceed if error was met
        $maritalStatus = '';
        if (empty($strError) || $method == 'prospect_profile') {
            if ($this->_config['site_version']['version'] == 'australia' && $method == 'prospect_profile') {
                $areaOfInterestFieldId                = $this->getFieldIdByUniqueId('qf_area_of_interest');
                $additionalQualificationFieldId       = $this->getFieldIdByUniqueId('qf_education_additional_qualification');
                $additionalQualificationSpouseFieldId = $this->getFieldIdByUniqueId('qf_education_spouse_additional_qualification');
                $dnaFieldId                           = $this->getFieldIdByUniqueId('qf_did_not_arrive');
                $maritalStatusFieldId                 = $this->getFieldIdByUniqueId('qf_marital_status');

                if (!empty($areaOfInterestFieldId) && !array_key_exists($areaOfInterestFieldId, $arrReceivedFields)) {
                    $arrReceivedFields[$areaOfInterestFieldId] = array('');
                }

                if (!empty($additionalQualificationFieldId) && !array_key_exists($additionalQualificationFieldId, $arrReceivedFields)) {
                    $arrReceivedFields[$additionalQualificationFieldId] = array('');
                }

                if (!empty($additionalQualificationSpouseFieldId) && !array_key_exists($additionalQualificationSpouseFieldId, $arrReceivedFields)) {
                    $arrReceivedFields[$additionalQualificationSpouseFieldId] = array('');
                }

                if (!empty($dnaFieldId) && isset($arrInsertData['prospect']['status']) && $arrInsertData['prospect']['status'] == 'active') {
                    $arrReceivedFields[$dnaFieldId] = array('');
                }

                if (!empty($additionalQualificationSpouseFieldId) && !empty($maritalStatusFieldId)) {
                    // Fix saving of unchecked spouse's "Additional qualification" checkbox if you toggle marital status "without spouse"
                    $arrOptions           = $this->getQuestionnaireFieldOptions($q_id, $maritalStatusFieldId);
                    $idsForCheck          = array();
                    foreach ($arrOptions as $arrOption) {
                        if (in_array($arrOption['q_field_option_unique_id'], array('married', 'engaged', 'common_law'))) {
                            $idsForCheck[] = $arrOption['q_field_option_id'];
                        }
                    }

                    if (array_key_exists($maritalStatusFieldId, $arrReceivedFields) && !in_array($arrReceivedFields[$maritalStatusFieldId][0], $idsForCheck)) {
                        $arrReceivedFields[$additionalQualificationSpouseFieldId] = array('');
                    }
                }
            }

            // Load all job titles, use for 'job and noc' fields
            $arrAllJobTitles = $this->getParent()->getAllJobTitles();

            // Check if each received field value (option) is correct
            foreach ($arrReceivedFields as $checkFieldId => $arrFieldValues) {
                // Trim value of the field in relation to the type.
                // Text area field is allowed to have more info
                $maxLength = $arrQFields[$checkFieldId]['q_field_type'] == 'textarea' ? 60000 : 255;

                // Check each field value for correctness
                foreach ($arrFieldValues as $checkFieldVal) {
                    if (!in_array($checkFieldId, $arrQnrFieldsIds)) {
                        $arrErrors[$checkFieldId] = $this->_tr->translate('Incorrectly selected field.');
                        break 2;
                    }

                    // Apply some checks
                    if (is_array($checkFieldVal)) {
                        foreach ($checkFieldVal as $key => $checkVal) {
                            $checkFieldVal[$key] = substr(trim($checkVal), 0, $maxLength);
                        }
                    } else {
                        $checkFieldVal = trim($checkFieldVal);
                        $checkFieldVal = substr($checkFieldVal, 0, $maxLength);
                    }


                    // Get readable field label (without special symbols at the end)
                    if (is_numeric($prospect_id) && !empty($arrQFields[$checkFieldId]['q_field_prospect_profile_label'])) {
                        $fieldLabel = $arrQFields[$checkFieldId]['q_field_prospect_profile_label'];
                    } else {
                        $fieldLabel = $arrQFields[$checkFieldId]['q_field_label'];
                    }

                    $readableFieldLabel = $this->_getReadableFieldLabel($fieldLabel);

                    // Check 'referred_by' or 'DNA' field as a textfield
                    if (in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_referred_by', 'qf_did_not_arrive'))) {
                        $arrQFields[$checkFieldId]['q_field_type'] = 'textfield';
                    }

                    // Don't check these fields if they are hidden/not used
                    $booCheckValue = true;
                    if ($arrQFields[$checkFieldId]['q_field_required'] != 'Y' && $checkFieldVal == '') {
                        $booCheckValue = false;
                    }

                    $booCheckIfEmpty = true;
                    if ($method != 'qnr') {
                        $booCheckIfEmpty = is_array($checkFieldVal) ? count($checkFieldVal) : !empty($checkFieldVal);
                    }

                    // Andron: I'm not sure if this check is correct...
                    if (!in_array($method, array('prospect_business', 'prospect_assessment'))) {
                        if ($this->_config['site_version']['version'] == 'canada') {
                            /* FAMILY RELATIONS IN CANADA */
                            if ($arrQFields[$checkFieldId]['q_field_unique_id'] == 'qf_family_sponsor_income') {
                                if (!isset($arrReceivedFields[35][0]) || !in_array($arrReceivedFields[35][0], array('139', '143')) || !isset($arrReceivedFields[36][0]) || $arrReceivedFields[36][0] != 146) {
                                    $booCheckValue = false;
                                }
                            }

                            if ($booCheckValue) {
                                /* BUSINESS/FINANCE */
                                $arrIgnoreFields = array(
                                    'qf_cat_staff_number',
                                    'qf_cat_percentage_of_ownership',
                                    'qf_cat_annual_sales',
                                    'qf_cat_annual_net_income',
                                    'qf_cat_net_assets'
                                );
                                if (in_array($arrQFields[$checkFieldId]['q_field_unique_id'], $arrIgnoreFields) &&
                                    // If qf_cat_have_experience radio is not set to yes
                                    (($arrReceivedFields[49][0] ?? null) != '188')
                                ) {
                                    $booCheckValue = false;
                                }
                            }

                            if ($booCheckValue) {
                                /* Children */
                                $arrCheckOptions = array(316, 317, 318, 319, 320, 321);
                                $mask            = '/qf_children_age_(.*)/i';
                                if (preg_match($mask, $arrQFields[$checkFieldId]['q_field_unique_id'], $regs)) {
                                    $num = $regs[1];
                                    for ($i = 0; $i < $num - 1; $i++) {
                                        array_shift($arrCheckOptions);
                                    }


                                    if (array_key_exists(97, $arrReceivedFields) && in_array($arrReceivedFields[97][0], $arrCheckOptions)) {
                                        $booCheckValue = true;
                                    } else {
                                        $booCheckValue = false;
                                    }
                                }
                            }
                        }
                    }

                    if ($booCheckValue) {
                        // There are such field types:
                        // 'textfield','textarea','combo','checkbox','radio','date','country','email','label'
                        switch ($arrQFields[$checkFieldId]['q_field_type']) {
                            case 'label':
                                // Skip such field
                                break;

                            // Check if age is correct
                            case 'age':
                                if ($booCheckIfEmpty && (!is_numeric($checkFieldVal) ||
                                        $checkFieldVal < $this->_field_age_min ||
                                        $checkFieldVal > $this->_field_age_max)
                                ) {
                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrect value for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                } else {
                                    $checkFieldVal = (int)date('Y') - (int)$checkFieldVal;
                                }
                                break;

                            // Check if money field is correct
                            case 'money':
                                if ($booCheckIfEmpty && (!is_numeric($checkFieldVal) ||
                                        $checkFieldVal < $this->_field_money_min ||
                                        $checkFieldVal > $this->_field_money_max)
                                ) {

                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrect value for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                }
                                break;

                            // Check if number field is correct
                            case 'number':
                                if ($booCheckIfEmpty && (!is_numeric($checkFieldVal) ||
                                        $checkFieldVal < $this->_field_number_min ||
                                        $checkFieldVal > $this->_field_number_max)
                                ) {

                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrect value for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                }
                                break;

                            // Check if percentage field is correct
                            case 'percentage':
                                if ($booCheckIfEmpty && (!is_numeric($checkFieldVal) ||
                                        $checkFieldVal < $this->_field_percentage_min ||
                                        $checkFieldVal > $this->_field_percentage_max)
                                ) {

                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrect value for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                }
                                break;

                            // Check if job is qualified
                            case 'job':
                                break;

                            case 'job_and_noc':
                                // Use job title instead of 'job and code'
                                foreach ($arrAllJobTitles as $arrJobTitleInfo) {
                                    if ($arrJobTitleInfo['noc_job_and_code'] == $checkFieldVal) {
                                        $checkFieldVal = $arrJobTitleInfo['noc_job_title'];
                                        break;
                                    }
                                }
                                break;

                            // For comboboxes, checkboxes and radios check option id
                            case 'combo':
                            case 'radio':
                                // Get options list for this field
                                $arrOptionsInfo = array_key_exists($checkFieldId, $arrQFieldsOptions) ? $arrQFieldsOptions[$checkFieldId] : array();

                                // Collect options for this field
                                $arrOptionsList = array();
                                foreach ($arrOptionsInfo as $arrOption) {
                                    $arrOptionsList[] = $arrOption['q_field_option_id'];
                                }

                                if (!count($arrOptionsInfo) || (!in_array($checkFieldVal, $arrOptionsList) && !empty($checkFieldVal))) {
                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrectly selected option for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                }
                                break;

                            case 'checkbox':
                                // Get options list for this field
                                $arrOptionsInfo = array_key_exists($checkFieldId, $arrQFieldsOptions) ? $arrQFieldsOptions[$checkFieldId] : array();

                                // Collect options for this field
                                $arrOptionsList = array();
                                foreach ($arrOptionsInfo as $arrOption) {
                                    $arrOptionsList[] = $arrOption['q_field_option_id'];
                                }

                                $booCorrect    = true;
                                $checkFieldVal = is_array($checkFieldVal) ? $checkFieldVal : array($checkFieldVal);
                                foreach ($checkFieldVal as $checkFieldValRec) {
                                    if (!in_array($checkFieldValRec, $arrOptionsList) && !empty($checkFieldValRec)) {
                                        $booCorrect = false;
                                        break;
                                    }
                                }

                                if (!count($arrOptionsInfo) || !$booCorrect) {
                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrectly selected option for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                }
                                break;

                            case 'country':
                                if ($booCheckIfEmpty && !in_array($checkFieldVal, $arrCountriesIds)) {
                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrectly selected country for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                }

                                break;

                            // Check for valid email
                            case 'email':
                                if ($booCheckIfEmpty && !$emailValidator->isValid($checkFieldVal)) {
                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Email address for <i>%s</i> field is incorrect.'),
                                        $readableFieldLabel
                                    );
                                }
                                break;

                            case 'date':
                                $dateFormatFull = $this->_settings->variable_get('dateFormatFull');
                                if ($booCheckIfEmpty && !Settings::isValidDateFormat($checkFieldVal, $dateFormatFull)) {
                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Incorrect date for <i>%s</i> field.'),
                                        $readableFieldLabel
                                    );
                                } elseif (!empty($checkFieldVal)) {
                                    $checkFieldVal = $this->_settings->reformatDate($checkFieldVal, $dateFormatFull, Settings::DATE_UNIX);
                                }
                                break;

                            default:
                                if ($booCheckIfEmpty && $arrQFields[$checkFieldId]['q_field_required'] == 'Y' && $checkFieldVal == '') {
                                    $arrErrors[$checkFieldId] = sprintf(
                                        $this->_tr->translate('Field <i>%s</i> is required.'),
                                        $readableFieldLabel
                                    );
                                }
                                break;
                        }
                    } else {
                        // If field wasn't checked - we reset its value :)
                        $checkFieldVal = '';
                    }


                    // For future, if all will be okay -
                    // we'll have all data ready to insert to DB
                    // (grouped by tables)
                    switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                        case 'qf_first_name':
                            $arrInsertData['prospect']['fName'] = $checkFieldVal;
                            break;
                        case 'qf_last_name':
                            $arrInsertData['prospect']['lName'] = $checkFieldVal;
                            break;
                        case 'qf_email':
                            $arrInsertData['prospect']['email'] = $checkFieldVal;
                            break;
                        case 'qf_age':
                            $arrInsertData['prospect']['date_of_birth'] = empty($checkFieldVal) ? '' : date('Y-m-d', strtotime($checkFieldVal));
                            break;
                        case 'qf_spouse_age':
                            $arrInsertData['prospect']['spouse_date_of_birth'] = empty($checkFieldVal) ? '' : date('Y-m-d', strtotime($checkFieldVal));
                            break;
                        case 'qf_referred_by':
                            if (is_numeric($checkFieldVal) && !empty($checkFieldVal)) {
                                $savedLabel = $this->getCustomOptionLabelById($checkFieldVal);
                                if (!empty($savedLabel)) {
                                    $checkFieldVal = $savedLabel;
                                }
                            }

                            $arrInsertData['prospect']['referred_by'] = $checkFieldVal;
                            break;

                        case 'qf_did_not_arrive':
                            if (is_numeric($checkFieldVal) && !empty($checkFieldVal)) {
                                $savedLabel = $this->getCustomOptionLabelById($checkFieldVal);
                                if (!empty($savedLabel)) {
                                    $checkFieldVal = $savedLabel;
                                }
                            }

                            $arrInsertData['prospect']['did_not_arrive'] = $checkFieldVal;
                            break;

                        default:
                            if (preg_match('/^qf_job_spouse_/', $arrQFields[$checkFieldId]['q_field_unique_id'])) {
                                $arrInsertData['job_spouse'][$arrQFields[$checkFieldId]['q_field_unique_id']][] = $checkFieldVal;
                            } elseif (preg_match('/^qf_job_/', $arrQFields[$checkFieldId]['q_field_unique_id'])) {
                                $arrInsertData['job'][$arrQFields[$checkFieldId]['q_field_unique_id']][] = $checkFieldVal;
                            } else {
                                $arrInsertData['data'][$checkFieldId] = $checkFieldVal;
                            }

                            if ($arrQFields[$checkFieldId]['q_field_unique_id'] == 'qf_marital_status') {
                                $maritalStatus = $checkFieldVal;
                            }
                            break;
                    }
                }
            }
        }

        // Make sure that job order is passed/generated
        if (isset($arrInsertData['job']) && !isset($arrInsertData['job']['qf_job_order'])) {
            $maxCount = 0;
            foreach ($arrInsertData['job'] as $arrValues) {
                $maxCount = max($maxCount, count($arrValues));
            }

            for ($i = 0; $i < $maxCount; $i++) {
                $arrInsertData['job']['qf_job_order'][] = $i;
            }
        }

        if (isset($arrInsertData['job_spouse']) && !isset($arrInsertData['job_spouse']['qf_job_order'])) {
            $maxCount = 0;
            foreach ($arrInsertData['job_spouse'] as $arrValues) {
                $maxCount = max($maxCount, count($arrValues));
            }

            for ($i = 0; $i < $maxCount; $i++) {
                $arrInsertData['job_spouse']['qf_job_order'][] = $i;
            }
        }

        // Calculate points for categories assigning
        $arrCheckedCategories   = array();
        $arrUncheckedCategories = array();
        $booForceRefreshProfile = false;
        if (empty($strError)) {
            // Delete all spouse's info if specific options are not selected in the 'marital status' combo
            if (($method == 'prospect_profile' && !$this->getParent()->hasProspectSpouse(
                        $maritalStatus
                    )) || ($method == 'prospect_occupations' && isset($arrParams['spouse_has_experience']) && $arrParams['spouse_has_experience'] == 'no') || ($method == 'qnr' && (!$this->getParent()->hasProspectSpouse(
                            $maritalStatus
                        ) || (isset($arrParams['spouse_has_experience']) && $arrParams['spouse_has_experience'] == 'no')))) {
                $arrInsertData['job_spouse'] = array();
            }
            if ($this->_config['site_version']['version'] == 'australia') {
                $arrAssessment = array();
            } else {
                $arrAssessment = $this->getCompanyProspectsPoints()->calculatePoints(
                    $method,
                    $arrReceivedFields,
                    $arrQFields,
                    $arrQFieldsOptions,
                    $prospect_id
                );

                $skilledWorker = isset($arrAssessment['skilled_worker']['global']['total']) ? (int) $arrAssessment['skilled_worker']['global']['total'] : 0;
                $expressEntry  = isset($arrAssessment['express_entry']['global']['total']) ? (int) $arrAssessment['express_entry']['global']['total'] : 0;

                $arrInsertData['prospect']['points_skilled_worker'] = $skilledWorker;
                $arrInsertData['prospect']['points_express_entry']  = $expressEntry;
            }

            $arrInsertData['prospect']['assessment'] = serialize($arrAssessment);

            // Check if categories are correctly assigned
            if ($this->_config['site_version']['version'] == 'australia') {
                $skilledWorkerCategoryId = null;
            } else {
                $skilledWorkerCategoryId = $this->getCategoryIdByUniqueId('skilled_worker');
            }

            if (!empty($skilledWorkerCategoryId)) {
                $booSkilledWorkerQualified = (bool)$arrAssessment['skilled_worker']['global']['qualified'];


                $arrCategories = array();
                if (array_key_exists('categories', $arrInsertData)) {
                    $arrCategories = $arrInsertData['categories'];
                } elseif (in_array($method, array('prospect_profile', 'prospect_occupations', 'prospect_business', 'prospect_assessment'))) {
                    // Load saved categories info from DB
                    $arrCategories = $this->getParent()->getProspectAssignedCategories($prospect_id);
                }

                $booChecked = false;
                if (in_array($skilledWorkerCategoryId, $arrCategories)) {
                    // This category was checked by user
                    if (!$booSkilledWorkerQualified) {
                        // But our calculations tell us that it must be unchecked!
                        $key = array_search($skilledWorkerCategoryId, $arrCategories);
                        unset($arrCategories[$key]);
                        $booForceRefreshProfile = true;
                    }
                } else {
                    // This category wasn't checked by user
                    if ($booSkilledWorkerQualified) {
                        // But our calculations tell us that it must be checked!
                        $arrCategories[]        = $skilledWorkerCategoryId;
                        $booForceRefreshProfile = true;
                        $booChecked             = true;
                    }
                }


                if ($booForceRefreshProfile) {
                    // Show a confirmation message
                    if ($booChecked) {
                        $arrCheckedCategories[] = 'Skilled Worker category';
                    } else {
                        $arrUncheckedCategories[] = 'Skilled Worker category';
                    }

                    // Update categories if needed
                    $arrInsertData['categories'] = $arrCategories;

                    if (empty($arrInsertData['categories']) &&
                        empty($arrInsertData['prospect']['category_other']) &&
                        empty($arrInsertData['prospect']['category_pnp'])
                    ) {
                        // No categories were selected -
                        // mark this prospect as 'Waiting for Assessment'
                        $arrInsertData['prospect']['qualified'] = null;
                    } else {
                        $arrInsertData['prospect']['qualified'] = 'Y';
                    }
                }
            }
        }

        // Return result
        return array(
            'arrQFields'             => $arrQFields,
            'arrErrors'              => $arrErrors,
            'strError'               => implode('<br/>', $arrErrors),
            'booForceRefreshProfile' => $booForceRefreshProfile,
            'arrCheckedCategories'   => $arrCheckedCategories,
            'arrUncheckedCategories' => $arrUncheckedCategories,
            'arrInsertData'          => $arrInsertData
        );
    }

    private function getArrFieldOrganizedOnStepsAndSections($arrFields) {

        $arrSteps = [];

        foreach($arrFields as $item) {
            $stepName = $item['q_section_step'];
            
            if (!isset($arrSteps[$stepName])) {
                $arrSteps[$stepName] = [];
            }

            if (!isset($arrSteps[$stepName][$item['q_section_id']])) {
                $arrSteps[$stepName][$item['q_section_id']] = [
                    'q_section_step' => $item['q_section_step'],
                    'q_section_id' => $item['q_section_id'],
                    'q_section_name' => $item['q_section_name'] ?? '',
                    'q_section_template_name' => $item['q_section_template_name'] ?? '',
                    'original_q_section_template_name' => $item['original_q_section_template_name'] ?? '',
                    'q_section_help' => $item['q_section_help'] ?? '',
                    'q_section_help_show' => $item['q_section_help_show'],
                    'q_section_hidden' => $item['q_section_hidden'] == 'Y' && $item['q_simplified'] == 'Y'? 'Y': 'N',
                    'fields' => []
                ];
            }

            $item['q_field_hidden'] = $item['q_field_hidden'] == 'Y' && $item['q_simplified'] == 'Y'? 'Y': 'N';
            $arrSteps[$stepName][$item['q_section_id']]['fields'][] = $item;

        }
        return $arrSteps;
    }


    /**
     * Generate view for specific questionnaire
     *
     * @param $arrQInfo
     * @param bool $booAllowEdit
     * @return string generated view
     */
    public function generateQnrView($arrQInfo, $booAllowEdit = false)
    {
        $strResult = '';

        $q_id      = $arrQInfo['q_id'];
        $bgColor   = $arrQInfo['q_section_bg_color'];
        $textColor = $arrQInfo['q_section_text_color'];

        $rtl = $arrQInfo['q_rtl'] == 'Y';
        $layoutDirection = $arrQInfo['q_rtl'] == 'Y' ? 'rtl' : 'ltr';
        $headerDirection = $arrQInfo['q_rtl'] == 'Y' ? 'right' : 'left';


        // Get qnr fields
        $arrQFields = $this->getQuestionnaireFields($q_id, false, true, $booAllowEdit);
        $arrFields  = array();
        foreach ($arrQFields as $arrFieldInfo) {
            $arrFields[$arrFieldInfo['q_field_id']] = $arrFieldInfo;
        }

        $arrSteps = $this->getArrFieldOrganizedOnStepsAndSections($arrFields);

        // Load countries list
        $arrCountries = $this->_country->getCountries(true);
        $arrCountries = array('' => $arrQInfo['q_please_select']) + $arrCountries;


        // Generate result
        if (empty($arrFields)) {
            $strResult = 'There are no sections and fields created for this questionnaire';
        } else {
            $currentStep     = 0;
            $currentSection  = 0;

            foreach ($arrSteps as $keyStep => $arrSections) {

                // Open Section
                $strResult .= "<div id='step_$keyStep' class='steps'>";
                if ($booAllowEdit) {
                    $strResult .= "<h1>Page $keyStep</h1>";
                }
                
                foreach ($arrSections as $arrSection) {

                    $sectionName    = empty($arrSection['q_section_template_name']) ? $arrSection['q_section_name'] : $arrSection['q_section_template_name'];
                    $currentSection = $arrSection['q_section_id'];
                    $sectionId      = "qnr_$q_id" . "section_$currentSection";

                    if ($booAllowEdit) {

                        $sectionHidden = $arrSection['q_section_hidden'] == 'Y' ? 'uf-section-hidden': '';
                        // Open the Section to edition
                        $borderStyle = empty($sectionName) ? '' : "border: 1px solid #$bgColor;";
                        $strResult .= "<table class='qnr-section job-section-$currentSection $sectionHidden' style='$borderStyle direction: $layoutDirection; width: 100%;' cellpadding='0' cellspacing='0'>";

                        if (!empty($sectionName)) {
                            $strResult .= "<tr><th class='qnr-section-header' style='background-color: #$bgColor; text-align: $headerDirection;' colspan='2'>";
                            $strResult .= "<span id='$sectionId' style='color: #$textColor;'>$sectionName</span>";
                            $strResult .= ' <a href="#" onclick=\'changeSectionLabel(' . $currentSection . ', ' . $q_id . '); return false;\' style="margin-left: 10px;"><i class="las la-edit" title="Click to change label"></i></a>';
                            $strResult .= $this->_generateHelpIcon($sectionId, $arrSection['q_section_help'], $arrSection['q_section_help_show'] == 'Y', !$booAllowEdit);
                            $strResult .= '</th></tr>';
                        }

                        $strResult .= '<tr><td '.($rtl?'style="text-align: right;"':'').'>';
                    } else {
                        // Open the Section to questionnaire
                        $strResult .= "<div class='qnr-section job-section-$currentSection step$currentStep' style='margin-top: 20px; width:100%;'>";
                        $strResult .= "<div class='card' style='border-color: #$bgColor;'>";

                        if (!empty($sectionName)) {
                            $strResult .= "<div class='card-header' style='background-color: #$bgColor; color: #$textColor;'>";
                            $strResult .= "<h4 id='$sectionId' style='display: inline;'>$sectionName</h4>";
                            $strResult .= $this->_generateHelpIcon($sectionId, $arrSection['q_section_help'], $arrSection['q_section_help_show'] == 'Y', !$booAllowEdit);
                            $strResult .= "</div>";
                        }
                        // Open Card-Block
                        $spouseSection = ($arrSection['original_q_section_template_name'] == 'WHAT IS YOUR SPOUSE\'S OR COMMON-LAW PARTNER\'S OCCUPATION?') ? 'spouse_field' : '';
                        $strResult .= "<div class='card-block text-$headerDirection $spouseSection' dir='$layoutDirection'>";
                    }

                    // Write fields
                    if (!$booAllowEdit && in_array($arrSection['original_q_section_template_name'], ['EDUCATION', 'LANGUAGE'])) {
                        $arrRight = $arrLeft = [];
                        $strResultRight = $strResultLeft = '';
                        // Separate the Fields in 2 columns
                        switch ($arrSection['original_q_section_template_name']) {
                            case 'EDUCATION':
                                // Separate by index
                                $arrRight = array_filter($arrSection['fields'], function($v) { return strpos($v['q_field_unique_id'] ??'', '_spouse_') !== false; });
                                $arrLeft = array_filter($arrSection['fields'], function($v) { return strpos($v['q_field_unique_id'] ??'', '_spouse_') === false; });
                                break;
                            case 'LANGUAGE':
                                // Separate by field unique id
                                $arrRight = array_filter($arrSection['fields'], function($val) { return strpos($val['q_field_unique_id'] ?? '', 'qf_language_spouse') === 0; });
                                $arrLeft = array_filter($arrSection['fields'], function($val) { return !(strpos($val['q_field_unique_id'] ?? '', 'qf_language_spouse') === 0); });
                                break;
                        }

                        foreach ($arrRight as $arrFieldInfo) {
                            if (!$this->checkUseOfNocField($arrFieldInfo, $booAllowEdit)) {
                                continue;
                            }
                            $strResultRight .= $this->generateQnrViewField($arrQInfo, $arrFieldInfo, $currentSection, $booAllowEdit);
                        }

                        foreach ($arrLeft as $arrFieldInfo) {
                            if (!$this->checkUseOfNocField($arrFieldInfo, $booAllowEdit)) {
                                continue;
                            }
                            $strResultLeft .= $this->generateQnrViewField($arrQInfo, $arrFieldInfo, $currentSection, $booAllowEdit);
                        }

                        $strResult .= "<div class='row p-0 no-gutters'>
                                    <div class='col-12 col-sm-12 col-md-6'>$strResultLeft</div>
                                    <div class='col-12 col-sm-12 col-md-6'>$strResultRight</div>
                                </div>";

                    } else {
                        foreach ($arrSection['fields'] as $arrFieldInfo) {
                            if (!$this->checkUseOfNocField($arrFieldInfo, $booAllowEdit)) {
                                continue;
                            }
                            if ($booAllowEdit) {
                                if (in_array($arrFieldInfo['q_field_unique_id'], ['qf_language_spouse_label', 'qf_language_spouse_french_done'])) {
                                    $strResult .= '</td><td>';
                                }
                                if ($arrFieldInfo['q_field_unique_id'] == 'qf_language_french_done') {
                                    $strResult .= '</td></tr><tr><td>';
                                }
                            }
                            $strResult .= $this->generateQnrViewField($arrQInfo, $arrFieldInfo, $currentSection, $booAllowEdit);

                        }
                    }

                    // Close section
                    if ($booAllowEdit) {
                        // Close table of edition
                        $strResult .= '</td><tr>';
                        $strResult .= "</table>";
                    } else {
                        // Close Card-Block
                        $strResult .= "</div>";
                        $strResult .= "</div></div>";
                    }
                }

                // Close step
                $strResult .= '</div>';
            }
        }
        return $strResult;
    }

    private function checkUseOfNocField($arrFieldInfo, $booAllowEdit) {
        // Don't show 'Noc' field on qnr page
        $arrToCheck = $this->_config['site_version']['version'] == 'australia' ? array() : array('qf_job_noc', 'qf_job_spouse_noc');
        if (in_array($arrFieldInfo['q_field_unique_id'], $arrToCheck)) {
            return false;
        }
        return true;
    }

    /**
     * Generate view for specific field configuration.
     */
    private function generateQnrViewField($arrQInfo, $arrFieldInfo, $currentSection, $booAllowEdit = false)
    {
        $strResult = '';

        $q_id      = $arrQInfo['q_id'];
        $layoutDirection = $arrQInfo['q_rtl'] == 'Y' ? 'rtl' : 'ltr';
        $headerDirection = $arrQInfo['q_rtl'] == 'Y' ? 'right' : 'left';

        // Load options list and show 'please select' text as first option
        $arrQFieldsOptions = $this->getQuestionnaireFieldsOptions($q_id);

        // Load countries list
        $arrCountries = $this->_country->getCountries(true);
        $arrCountries = array('' => $arrQInfo['q_please_select']) + $arrCountries;


        // Prepare/collect field's info
        $fieldId  = 'q_' . $q_id . '_field_' . $arrFieldInfo['q_field_id'];
        $fieldId2 = 'q2_' . $q_id . '_field_' . $arrFieldInfo['q_field_id'];

        if ($arrFieldInfo['q_field_unique_id'] == 'qf_job_employer') {
            $fieldId  = 'q_' . $q_id . '_employer_field_' . $arrFieldInfo['q_field_id'];
            $fieldId2 = 'q2_' . $q_id . '_employer_field_' . $arrFieldInfo['q_field_id']; // Used for options
        } elseif ($arrFieldInfo['q_field_unique_id'] == 'qf_job_spouse_employer') {
            $fieldId  = 'q_' . $q_id . '_spouse_employer_field_' . $arrFieldInfo['q_field_id'];
            $fieldId2 = 'q2_' . $q_id . '_spouse_employer_field_' . $arrFieldInfo['q_field_id']; // Used for options
        }

        $labelFor = in_array($arrFieldInfo['q_field_type'], array('radio', 'checkbox', 'label')) ? '' : "for='$fieldId'";

        $fieldLabel = empty($arrFieldInfo['q_field_label']) ? $arrFieldInfo['original_q_field_label'] : $arrFieldInfo['q_field_label'];
        $fieldLabel = $this->_settings->getHTMLPurifier()->purify($fieldLabel);

        $label      = '<label id="' . $fieldId . '_label" ' . $labelFor . '>' . $fieldLabel . '</label>';
        if ($booAllowEdit) {
            $label .= '<a href="#" onclick=\'editField(' . $arrFieldInfo['q_field_id'] . ', ' . $q_id . ', "' . $arrFieldInfo['q_field_type'] . '"); return false;\' style="margin-left: 10px;"><i class="las la-edit" title="Click to edit this field"></i></a>';
        }
        $label .= '<div style="display: none" id="prospect_' . $fieldId . '">' . $arrFieldInfo['q_field_prospect_profile_label'] . '</div>';


        // Generate Field
        $strField  = $this->generateQnrField(false, $fieldId, $fieldId2, $q_id, $arrFieldInfo, $arrQFieldsOptions, $arrCountries, '', $layoutDirection, true, $arrQInfo['q_please_select'], 'prospects', !$booAllowEdit);
        $fieldHelp = $this->_generateHelpIcon($fieldId, $arrFieldInfo['q_field_help'], $arrFieldInfo['q_field_help_show'] == 'Y', !$booAllowEdit);


        if ($arrFieldInfo['q_field_type'] == 'label') {
            $labelContent = "<div class='row'><div class='col-xl-12 no-gutters'><div class='col-xl-12 uf-label-title text-$headerDirection' dir='$layoutDirection'>$label$fieldHelp$strField</div></div></div>";
            $labelWithHelp = "<div class='row'><div class='col-xl-12 no-gutters'><div class='col-xl-12 uf-label-title text-$headerDirection' dir='$layoutDirection'>$label$fieldHelp</div></div></div>";
            switch ($arrFieldInfo['q_field_unique_id']) {
                case 'qf_education_your_label':
                    $strResult .= "$labelContent";
                    break;

                case 'qf_education_spouse_label':
                    $strResult .= "<div class='spouse_field education'>$labelContent</div>";
                    break;

                case 'qf_language_english_ielts_scores_label':
                    $tdClass = ' qf_language_english_ielts';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_english_general_label':
                    $tdClass = ' qf_language_english_general';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_french_tef_scores_label':
                    $tdClass = ' qf_language_french_tef';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_french_general_label':
                    $tdClass = ' qf_language_french_general';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_spouse_english_ielts_scores_label':
                    $tdClass = ' spouse_field qf_language_spouse_english_ielts';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_spouse_english_general_label':
                    $tdClass = ' spouse_field qf_language_spouse_english_general';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_spouse_french_tef_scores_label':
                    $tdClass = ' spouse_field qf_language_spouse_french_tef';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_spouse_french_general_label':
                    $tdClass = ' spouse_field qf_language_spouse_french_general';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_your_label':
                    $tdClass = ' qf_language_your_label';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_spouse_label':
                    $tdClass = ' spouse_field qf_language_spouse_label';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_english_celpip_label':
                    $tdClass = ' qf_language_english_celpip_label';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_language_spouse_english_celpip_label':
                    $tdClass = ' spouse_field qf_language_spouse_english_celpip_label';
                    $strResult .= "<div class='$tdClass'>$labelContent</div>";
                    break;

                case 'qf_education_your_label_au':
                    $strResult .= "<div style='padding-left: 100px; font-weight: bold; font-style: italic;'>$labelWithHelp<br/>$strField</div>";
                    break;

                case 'qf_education_spouse_label_au':
                    $strResult .= "<div class='spouse_field' style='padding-left: 100px; font-weight: bold; font-style: italic;'>$labelWithHelp<br/>$strField</div>";
                    break;

                case 'qf_language_your_label_au':
                    $strResult .= "<div style='padding-left: 100px; font-weight: bold; font-style: italic;'>$labelContent</div>";
                    break;

                case 'qf_language_spouse_label_au':
                    $strResult .= "<div class='spouse_field' style='padding-left: 100px; font-weight: bold; font-style: italic;'>$labelContent</div>";
                    break;

                case 'qf_language_eng_label':
                    $strResult .= "<div style='padding-left: 50px; width: 145px; display: inline-block;'>$labelWithHelp</div>";
                    break;

                case 'qf_language_spouse_eng_label':
                    $strResult .= "<div class='spouse_field'><span style='padding-left: 50px; width: 145px; display: inline-block;'>$labelWithHelp</span></div>";
                    break;

                case 'qf_language_speak_label':
                case 'qf_language_spouse_speak_label':
                case 'qf_language_read_label':
                case 'qf_language_spouse_read_label':
                case 'qf_language_write_label':
                case 'qf_language_spouse_write_label':
                case 'qf_language_listen_label':
                case 'qf_language_spouse_listen_label':
                    $strClass = '';
                    if (preg_match('/^qf_(.*)spouse_/', $arrFieldInfo['q_field_unique_id'])) {
                        $strClass = "class='spouse_field'";
                    }
                    if (in_array($arrFieldInfo['q_field_unique_id'], array('qf_language_speak_label', 'qf_language_read_label', 'qf_language_write_label', 'qf_language_listen_label'))) {
                        $strResult .= '<tr>';
                    }
                    $strResult .= "<td $strClass><span style='width: 50px; display: inline-block;'>$labelWithHelp</span>";
                    break;

                case 'qf_language_fr_label':
                    $strResult .= "&nbsp;&nbsp;$labelWithHelp</td>";
                    break;

                case 'qf_language_spouse_fr_label':
                    $strResult .= "&nbsp;&nbsp;$labelWithHelp</td></tr>";
                    break;

                // Show label field in 2 columns
                default:
                    $strResult .= "<div>$labelContent</div>";
                    break;
            }

        } else {
            $tdClass = $arrFieldInfo['q_field_unique_id'];
            $trClass = '';
            switch ($arrFieldInfo['q_field_unique_id']) {
                /* CHILDREN */
                case 'qf_children_count':
                case 'qf_children_age_1':
                case 'qf_children_age_2':
                case 'qf_children_age_3':
                case 'qf_children_age_4':
                case 'qf_children_age_5':
                case 'qf_children_age_6':

                /* WORK IN CANADA */
                case 'qf_work_temporary_worker':
                case 'qf_work_years_worked':
                case 'qf_work_currently_employed':
                case 'qf_work_leave_employment':
                case 'qf_study_previously_studied':
                case 'qf_work_offer_of_employment':
                case 'qf_work_noc':

                /* FAMILY RELATIONS IN CANADA */
                case 'qf_family_have_blood_relative':
                case 'qf_family_relationship':
                case 'qf_family_relative_wish_to_sponsor':
                case 'qf_family_sponsor_age':
                case 'qf_family_employment_status':
                case 'qf_family_sponsor_financially_responsible':
                case 'qf_family_sponsor_income':
                case 'qf_family_currently_fulltime_student':
                case 'qf_family_been_fulltime_student':

                /* BUSINESS/FINANCE */
                case 'qf_cat_net_worth':
                case 'qf_cat_have_experience':
                case 'qf_cat_managerial_experience':
                case 'qf_cat_staff_number':
                case 'qf_cat_own_this_business':
                case 'qf_cat_percentage_of_ownership':
                case 'qf_cat_annual_sales':
                case 'qf_cat_annual_net_income':
                case 'qf_cat_net_assets':
                case 'qf_visa_refused_or_cancelled':
                case 'qf_applied_for_visa_before':
                case 'qf_spouse_is_resident_of_new_zealand':
                case 'qf_spouse_is_resident_of_australia':
                case 'qf_area_of_interest':

                    $trClass = $arrFieldInfo['q_field_unique_id'];
                    break;

                default:
                    $jobSectionId       = $this->getQuestionnaireSectionJobId();
                    $jobSpouseSectionId = $this->getQuestionnaireSpouseSectionJobId();
                    if (in_array($currentSection, array($jobSectionId, $jobSpouseSectionId))) {
                        $trClass = 'job_section ' . $arrFieldInfo['q_field_unique_id'];
                    } elseif ($arrFieldInfo['q_field_unique_id'] == 'qf_job_spouse_has_experience') {
                        $trClass = $arrFieldInfo['q_field_unique_id'];
                    }

                    if (preg_match('/^qf_(.*)spouse_/', $arrFieldInfo['q_field_unique_id'])) {
                        $tdClass .= ' spouse_field';
                    }

                    if (preg_match('/^qf_(.*)current_address_/', $arrFieldInfo['q_field_unique_id']) && $arrFieldInfo['q_field_unique_id'] != 'qf_current_address_visa_type') {
                        $tdClass .= ' current_address';
                    }

                    if (preg_match('/^qf_(.*)education_/', $arrFieldInfo['q_field_unique_id'])) {
                        $tdClass .= ' education';
                    }

                    if (preg_match('/^qf_(.*)education_/', $arrFieldInfo['q_field_unique_id'])) {
                        if (!strpos($tdClass, ' spouse_field')) {
                            $tdClass .= ' main';
                        }
                    }

                    if (preg_match('/^qf_(.*)language_(.*)(test)|(score)/', $arrFieldInfo['q_field_unique_id'])) {
                        if (!strpos($tdClass, ' spouse_field')) {
                            $tdClass .= ' main';
                        }
                    }

                    if (preg_match('/^qf_(.*)language_(.*)(test)|(score)/', $arrFieldInfo['q_field_unique_id'])) {
                        $tdClass .= ' language';
                    }

                    if (!$booAllowEdit) {
                        if (preg_match('/^qf_(.*)language_/', $arrFieldInfo['q_field_unique_id'])) {
                            if (!strpos($tdClass, ' spouse_field') && !strpos($tdClass, ' main')) {
                                $tdClass .= ' main';
                            }
                        }

                        if (preg_match('/^qf_(.*)language_/', $arrFieldInfo['q_field_unique_id'])) {
                            $tdClass .= ' lang';
                        }
                    }
                    break;
            }

            if ($booAllowEdit) {
                $trClass = empty($trClass) ? '' : "class='$trClass'";

                switch ($arrFieldInfo['q_field_unique_id']) {
                    case 'qf_education_level':
                    case 'qf_education_diploma_name':
                    case 'qf_education_area_of_studies':
                    case 'qf_education_country_of_studies':
                    case 'qf_education_institute_type':
                    case 'qf_education_bachelor_degree_name':
                    case 'qf_study_previously_studied':
                    case 'qf_education_studied_in_canada_period':
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_education_spouse_level':
                    case 'qf_education_spouse_diploma_name':
                    case 'qf_education_spouse_area_of_studies':
                    case 'qf_education_spouse_country_of_studies':
                    case 'qf_education_spouse_institute_type':
                    case 'qf_education_spouse_previously_studied':
                    case 'qf_education_spouse_bachelor_degree_name':
                    case 'qf_education_spouse_studied_in_canada_period':
                        $strResult .= "<div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div>";
                        break;

                    case 'qf_language_english_ielts_score_speak':
                    case 'qf_language_english_ielts_score_read':
                    case 'qf_language_english_ielts_score_write':
                    case 'qf_language_english_ielts_score_listen':
                        $tdClass .= ' qf_language_english_ielts';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_english_celpip_score_speak':
                    case 'qf_language_english_celpip_score_read':
                    case 'qf_language_english_celpip_score_write':
                    case 'qf_language_english_celpip_score_listen':
                        $tdClass .= ' qf_language_english_celpip';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_english_general_score_speak':
                    case 'qf_language_english_general_score_read':
                    case 'qf_language_english_general_score_write':
                        $tdClass .= ' qf_language_english_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_english_general_score_listen':
                        $tdClass .= ' qf_language_english_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_french_tef_score_speak':
                    case 'qf_language_french_tef_score_read':
                    case 'qf_language_french_tef_score_write':
                    case 'qf_language_french_tef_score_listen':
                        $tdClass .= ' qf_language_french_tef';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_french_general_score_speak':
                    case 'qf_language_french_general_score_read':
                    case 'qf_language_french_general_score_write':
                        $tdClass .= ' qf_language_french_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_french_general_score_listen':
                        $tdClass .= ' qf_language_french_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_english_ielts_score_speak':
                    case 'qf_language_spouse_english_ielts_score_read':
                    case 'qf_language_spouse_english_ielts_score_write':
                    case 'qf_language_spouse_english_ielts_score_listen':
                        $tdClass .= ' qf_language_spouse_english_ielts';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_english_celpip_score_speak':
                    case 'qf_language_spouse_english_celpip_score_read':
                    case 'qf_language_spouse_english_celpip_score_write':
                    case 'qf_language_spouse_english_celpip_score_listen':
                        $tdClass .= ' qf_language_spouse_english_celpip';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_english_general_score_speak':
                    case 'qf_language_spouse_english_general_score_read':
                    case 'qf_language_spouse_english_general_score_write':
                        $tdClass .= ' qf_language_spouse_english_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_english_general_score_listen':
                        $tdClass .= ' qf_language_spouse_english_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_french_tef_score_speak':
                    case 'qf_language_spouse_french_tef_score_read':
                    case 'qf_language_spouse_french_tef_score_write':
                    case 'qf_language_spouse_french_tef_score_listen':
                        $tdClass .= ' qf_language_spouse_french_tef';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_french_general_score_speak':
                    case 'qf_language_spouse_french_general_score_read':
                    case 'qf_language_spouse_french_general_score_write':
                        $tdClass .= ' qf_language_spouse_french_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_french_general_score_listen':
                        $tdClass .= ' qf_language_spouse_french_general';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_french_done':
                        $tdClass .= ' qf_language_french_done';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_french_done':
                        $tdClass .= ' qf_language_spouse_french_done';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_english_done':
                        $tdClass .= ' qf_language_english_done';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_spouse_english_done':
                        $tdClass .= ' qf_language_spouse_english_done';
                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div>$strField</div></div>";
                        break;

                    case 'qf_language_eng_proficiency_speak':
                    case 'qf_language_spouse_eng_proficiency_speak':
                    case 'qf_language_eng_proficiency_read':
                    case 'qf_language_spouse_eng_proficiency_read':
                    case 'qf_language_eng_proficiency_write':
                    case 'qf_language_spouse_eng_proficiency_write':
                    case 'qf_language_eng_proficiency_listen':
                    case 'qf_language_spouse_eng_proficiency_listen':
                        $strResult .= "$strField$label";
                        break;

                    case 'qf_language_fr_proficiency_speak':
                    case 'qf_language_fr_proficiency_read':
                    case 'qf_language_fr_proficiency_write':
                    case 'qf_language_fr_proficiency_listen':
                        $strResult .= "$strField$label";
                        break;

                    case 'qf_language_spouse_fr_proficiency_speak':
                    case 'qf_language_spouse_fr_proficiency_read':
                    case 'qf_language_spouse_fr_proficiency_write':
                    case 'qf_language_spouse_fr_proficiency_listen':
                        $strResult .= "$strField$label";
                        break;

                    //show fields with radiobuttons without word wrapping
                    case 'qf_language_have_taken_test_on_english':
                        $strResult .= "<div class='qf_language_have_taken_test_on_english'><div style='padding:5px 0 15px 0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr $trClass><td style='padding:5px;'>" .
                            "$label$fieldHelp</td><td style='padding: 5px; padding-left: 0;'" .
                            ">$strField</td></tr></table></div></div>";
                        break;
                    case 'qf_spouse_is_resident_of_australia':
                        $strResult .= "<div $trClass><div style='padding:0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr $trClass><td class='spouse_field' " .
                            "style='padding:5px;'>$label$fieldHelp</td><td class='spouse_field' " .
                            "style='padding: 5px; padding-left: 25px;'>$strField</td></tr></table></div></div>";
                        break;
                    case 'qf_spouse_is_resident_of_new_zealand':
                        $strResult .= "<div $trClass><div style='padding:12px 0 0 0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr $trClass><td class='spouse_field' style='padding:5px;'>" .
                            "$label$fieldHelp</td><td class='spouse_field' style='padding: 5px; padding-left: 0;'" .
                            ">$strField</td></tr></table></div></div>";
                        break;
                    case 'qf_language_spouse_have_taken_ielts':
                        $strResult .= "<div class='qf_language_spouse_have_taken_ielts'><div style='padding:5px 0 15px 0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr $trClass><td class='spouse_field' style='padding:5px;'>" .
                            "$label$fieldHelp</td><td class='spouse_field' style='padding: 5px; padding-left: 0;'" .
                            ">$strField</td></tr></table></div></div>";
                        break;
                    case 'qf_language_spouse_have_taken_test_on_english':
                        $strResult .= "<div class='qf_language_spouse_have_taken_test_on_english'><div style='padding:5px 0 15px 0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr $trClass><td class='spouse_field' style='padding:5px;'>" .
                            "$label$fieldHelp</td><td class='spouse_field' style='padding: 5px; padding-left: 0;'" .
                            ">$strField</td></tr></table></div></div>";
                        break;
                    case 'qf_was_the_turnover_greater':
                        $strResult .= "<div class='qf_was_the_turnover_greater'><div style='padding:0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr ><td style='padding-top:0;'>" .
                            "$label$fieldHelp</td><td style='padding-left: 18px; padding-top: 5px;'" .
                            ">$strField</td></tr></table></div></div>";
                        break;
                    case 'qf_have_you_completed_any_qualification':
                        $strResult .= "<div><div style='padding:0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr $trClass><td style='padding:5px;'>" .
                            "$label$fieldHelp</td><td style='padding: 5px 5px 0 60px;'" .
                            ">$strField</td></tr></table></div></div>";
                        break;
                    case 'qf_currently_have_a_job_offer':
                        $strResult .= "<div><div style='padding:5px 0 0 0;'><table border='0'" .
                            " cellspacing='0' cellpadding='0'><tr $trClass><td style='padding:5px;'>" .
                            "$label$fieldHelp</td><td style='padding: 5px 5px 0 89px;'" .
                            ">$strField</td></tr></table></div></div>";
                        break;
                    default:
                        $tdClass .= ' ' . $arrFieldInfo['q_field_unique_id'];

                        $strResult .= "<div $trClass><div class='first_col $tdClass'>$label$fieldHelp</div><div class='$tdClass'>$strField</div></div>";
                        break;
                }
            } else {
                switch ($arrFieldInfo['q_field_unique_id']) {
                    case 'qf_language_have_taken_test_on_english':
                    case 'qf_language_spouse_have_taken_test_on_english':
                        $tdClass = str_replace("language", "", $tdClass);
                        break;

                    case 'qf_study_previously_studied':
                        $tdClass .= ' main education';
                        break;

                    case 'qf_language_english_ielts_scores_label':
                    case 'qf_language_english_ielts_score_speak':
                    case 'qf_language_english_ielts_score_read':
                    case 'qf_language_english_ielts_score_write':
                    case 'qf_language_english_ielts_score_listen':
                        $tdClass .= ' qf_language_english_ielts';
                        break;

                    case 'qf_language_english_celpip_score_speak':
                    case 'qf_language_english_celpip_score_read':
                    case 'qf_language_english_celpip_score_write':
                    case 'qf_language_english_celpip_score_listen':
                        $tdClass .= ' qf_language_english_celpip';
                        break;

                    case 'qf_language_english_general_label':
                    case 'qf_language_english_general_score_speak':
                    case 'qf_language_english_general_score_read':
                    case 'qf_language_english_general_score_write':
                    case 'qf_language_english_general_score_listen':
                        $tdClass .= ' qf_language_english_general';
                        break;

                    case 'qf_language_french_tef_scores_label':
                    case 'qf_language_french_tef_score_speak':
                    case 'qf_language_french_tef_score_read':
                    case 'qf_language_french_tef_score_write':
                    case 'qf_language_french_tef_score_listen':
                        $tdClass .= ' qf_language_french_tef';
                        break;

                    case 'qf_language_french_general_label':
                    case 'qf_language_french_general_score_speak':
                    case 'qf_language_french_general_score_read':
                    case 'qf_language_french_general_score_write':
                    case 'qf_language_french_general_score_listen':
                        $tdClass .= ' qf_language_french_general';
                        break;

                    case 'qf_language_spouse_english_ielts_scores_label':
                    case 'qf_language_spouse_english_ielts_score_speak':
                    case 'qf_language_spouse_english_ielts_score_read':
                    case 'qf_language_spouse_english_ielts_score_write':
                    case 'qf_language_spouse_english_ielts_score_listen':
                        $tdClass .= ' qf_language_spouse_english_ielts';
                        break;

                    case 'qf_language_spouse_english_celpip_score_speak':
                    case 'qf_language_spouse_english_celpip_score_read':
                    case 'qf_language_spouse_english_celpip_score_write':
                    case 'qf_language_spouse_english_celpip_score_listen':
                        $tdClass .= ' qf_language_spouse_english_celpip';
                        break;

                    case 'qf_language_spouse_english_general_label':
                    case 'qf_language_spouse_english_general_score_speak':
                    case 'qf_language_spouse_english_general_score_read':
                    case 'qf_language_spouse_english_general_score_write':
                    case 'qf_language_spouse_english_general_score_listen':
                        $tdClass .= ' qf_language_spouse_english_general';
                        break;

                    case 'qf_language_spouse_french_tef_scores_label':
                    case 'qf_language_spouse_french_tef_score_speak':
                    case 'qf_language_spouse_french_tef_score_read':
                    case 'qf_language_spouse_french_tef_score_write':
                    case 'qf_language_spouse_french_tef_score_listen':
                        $tdClass .= ' qf_language_spouse_french_tef';
                        break;

                    case 'qf_language_spouse_french_general_label':
                    case 'qf_language_spouse_french_general_score_speak':
                    case 'qf_language_spouse_french_general_score_read':
                    case 'qf_language_spouse_french_general_score_write':
                    case 'qf_language_spouse_french_general_score_listen':
                        $tdClass .= ' qf_language_spouse_french_general';
                        break;

                    default:
                        break;
                }
                $class = $trClass . ' ' . $tdClass;
                if (!strpos($class, $arrFieldInfo['q_field_unique_id'])) {
                    $class .= ' ' . $arrFieldInfo['q_field_unique_id'];
                }
                
                $arrFieldTypeFullWidth = ['textarea', 'radio', 'checkbox', 'status', 'file'];
                $fieldContainerSubclass = '';
                if (!in_array($arrFieldInfo['q_field_type'], $arrFieldTypeFullWidth)) {
                    $fieldContainerSubclass = 'field-container-limit-width';
                }

                $strResult .= "<div class='row $class text-$headerDirection' dir='$layoutDirection' >";
                $strResult .= "<div class='col-xl-12 no-gutters field-wrapper'>";
                $strResult .= "<div class='col-xl-12 '>$label$fieldHelp</div>";
                $strResult .= "<div class='col-xl-12 no-gutters field-container $fieldContainerSubclass input-group'>$strField</div>";
                $strResult .= "<div class='col-xl-12 error-validation'></div>";
                $strResult .= "</div>";
                $strResult .= "</div>";

            }
        }

        // Add job button container
        $arrToCheck = $this->_config['site_version']['version'] == 'australia' ? array('qf_job_end_date', 'qf_job_spouse_end_date') : array('qf_job_employment_type', 'qf_job_spouse_employment_type');
        if (!$booAllowEdit && in_array($arrFieldInfo['q_field_unique_id'], $arrToCheck)) {
            $strResult .= "<div id='job_{$arrFieldInfo['q_field_unique_id']}' class='q_job_add_$q_id p-0' style='padding: 15px;'>";
            $strResult .= "</div>";
        }

        if ($booAllowEdit) {
            $fieldHidden = $arrFieldInfo['q_field_hidden'] == 'Y' ? 'class="uf-field-hidden"': '';
            $strResult = "<div $fieldHidden>$strResult</div>";
        }

        return $strResult;
    }

    private function _generateHelpIcon($id, $strHelp, $booShow, $booBootstrap = false)
    {
        $strResult = '';
        if ($booShow && !empty($strHelp)) {
            $strHelp   = str_replace('<br>', '<br/>', $strHelp);
            $helpId    = "help_$id";
            if ($booBootstrap) {
                $strHelp   = htmlspecialchars($strHelp, ENT_QUOTES);
                $strResult = "<button type='button' id='content_$helpId' class='btn btn-sm field_help' data-toggle='popover' data-trigger='hover focus' data-html='true' data-title='Help' data-placement='right' data-content='$strHelp'>
                    <a class='fas fa-question-circle' tabindex='-1' class='field-help-tip'></a>
                </button>";
            } else {
                /** @var Layout $layout */
                $layout = $this->_viewHelperManager->get('layout');
                $strResult = "<div style='display:none;'><div id='content_$helpId' class='field_help'>$strHelp</div></div>";
                $strResult .= "&nbsp;&nbsp;<img id='$helpId' src='{$layout()->getVariable('imagesUrl')}/icons/help.png' width='16' height='16' alt='help' class='field-help-tip' />";
            }
        }

        return $strResult;
    }


    public function getFieldIdByUniqueId($uniqueFieldId)
    {
        $select = (new Select())
            ->from(['cf' => 'company_questionnaires_fields'])
            ->columns(['q_field_id'])
            ->where(['cf.q_field_unique_id' => $uniqueFieldId]);

        return $this->_db2->fetchOne($select);
    }


    /**
     * Generate hash by qnr id -
     * will be used when open qnr by end user
     *
     * @param int $q_id - questionnaire id
     * @return string generated hash
     */
    public function generateHashForQnrId($q_id)
    {
        return md5($q_id . 'custom cool salt :)');
    }


    /**
     * Check if current member can access to specific qnr
     * [Checked only by company id]
     *
     * @param int $q_id - questionnaire id
     * @return bool false if member cannot access, otherwise true
     */
    public function hasAccessToQnr($q_id)
    {
        // Check if received q_id is related to specific company
        $companyId = $this->_auth->getCurrentUserCompanyId();

        $select = (new Select())
            ->from(['cq' => 'company_questionnaires'])
            ->columns(['count' => new Expression('COUNT(cq.company_id)')])
            ->where([
                'cq.company_id' => $companyId,
                'cq.q_id'       => $q_id
            ]);

        return $this->_db2->fetchOne($select) > 0;
    }

    /**
     * Check if QNR name is already used for the company
     *
     * @param int $companyId
     * @param string $qnrName
     * @return bool true if used
     */
    public function checkQnrNameUsed($companyId, $qnrName)
    {
        $select = (new Select())
            ->from(['cq' => 'company_questionnaires'])
            ->columns(['count' => new Expression('COUNT(cq.q_id)')])
            ->where([
                'cq.company_id' => (int)$companyId,
                'cq.q_name'     => $qnrName
            ]);

        return $this->_db2->fetchOne($select) > 0;
    }


    /**
     * Load all qnr ids
     * @return array of qnr ids
     */
    public function getAllQnr()
    {
        $select = (new Select())
            ->from(['cq' => 'company_questionnaires'])
            ->columns(['q_id']);

        return $this->_db2->fetchCol($select);
    }


    /**
     * Check if current member has access to specific prospect template
     * [Checked only by company id OR this is superadmin]
     *
     * @param int $prospectTemplateId - prospect template id
     * @return bool false if member cannot access, otherwise true
     */
    public function hasAccessToTemplate($prospectTemplateId)
    {
        $booHasAccess = false;

        try {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } else {
                // Check if received template id is related to specific company
                $companyId = $this->_auth->getCurrentUserCompanyId();

                $select = (new Select())
                    ->from(['ct' => 'company_prospects_templates'])
                    ->columns(['count' => new Expression('COUNT(ct.company_id)')])
                    ->where([
                        'ct.company_id'           => $companyId,
                        'ct.prospect_template_id' => $prospectTemplateId
                    ]);

                $booHasAccess = $this->_db2->fetchOne($select) > 0;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }


    /**
     * Check if prospect template is used somewhere
     * (e.g. assigned to thank you template)
     *
     * @param int $qnrProspectTemplateId - prospect id
     * @return bool true if template is used, otherwise false
     */
    public function isTemplateUsed($qnrProspectTemplateId)
    {
        $select = (new Select())
            ->from(['qt' => 'company_questionnaires_category_template'])
            ->columns(['count' => new Expression('COUNT(qt.prospect_template_id)')])
            ->where(['qt.prospect_template_id' => (int)$qnrProspectTemplateId]);

        $categoriesCount = $this->_db2->fetchOne($select);

        $select = (new Select())
            ->from(['cq' => 'company_questionnaires'])
            ->columns(['count' => new Expression('COUNT(cq.q_id)')])
            ->where([
                (new Where())
                    ->equalTo('cq.q_template_negative', (int)$qnrProspectTemplateId)
                    ->or
                    ->equalTo('cq.q_template_thank_you', $qnrProspectTemplateId)
            ]);

        $qnrCount = $this->_db2->fetchOne($select);

        return $categoriesCount > 0 || $qnrCount > 0;
    }


    /**
     * Load category id by unique string id
     *
     * @param string $strUniqueId
     * @return int category id
     */
    public function getCategoryIdByUniqueId($strUniqueId)
    {
        $select = (new Select())
            ->from('company_prospects_categories')
            ->columns(['prospect_category_id'])
            ->where(['prospect_category_unique_id' => $strUniqueId]);

        return $this->_db2->fetchOne($select);
    }


    /**
     * Load categories list
     *
     * @param bool $booIdOnly
     * @param bool $booSettingsPage
     * @return array with categories list
     */
    public function getCategories($booIdOnly = false, $booSettingsPage = false)
    {
        $select = (new Select())
            ->from('company_prospects_categories')
            ->columns([$booIdOnly ? 'prospect_category_id' : Select::SQL_STAR])
            ->order('prospect_category_order');

        if ($booSettingsPage) {
            $select->where(['prospect_category_show_in_settings' => 'Y']);
        }

        return $booIdOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }


    /**
     * Load categories list related to specific company
     * (which company will use for prospects)
     *
     * @param int $companyId
     * @return array with categories list
     */
    public function getCompanyCategories($companyId)
    {
        $select = (new Select())
            ->from(['cp' => 'company_prospects_selected_categories'])
            ->join(array('cat' => 'company_prospects_categories'), 'cat.prospect_category_id = cp.prospect_category_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where(['cp.company_id' => (int)$companyId])
            ->order('cp.order');

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load categories list (ids only) related to specific company
     *
     * @param int $companyId
     * @param bool $booOrder if true, sort by order
     * @return array with categories ids
     */
    public function getCompanyCategoriesIds($companyId, $booOrder = false)
    {
        $select = (new Select())
            ->from(['cp' => 'company_prospects_selected_categories'])
            ->columns(['prospect_category_id'])
            ->where(['cp.company_id' => (int)$companyId]);

        if ($booOrder) {
            $select->order('cp.order');
        }

        return $this->_db2->fetchCol($select);
    }


    /**
     * Load prospect templates list related to specific company
     *
     * @param int $companyId
     * @param bool $booIdOnly
     * @return array with templates list
     */
    public function getProspectTemplates($companyId, $booIdOnly = false)
    {
        $select = (new Select());
        if ($booIdOnly) {
            $select->from(array('t' => 'company_prospects_templates'))
                ->columns(['prospect_template_id']);
        } else {
            $select->from(array('t' => 'company_prospects_templates'))
                ->join(array('m' => 'members'), 'm.member_id = t.author_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->join(array('u' => 'members'), 'u.member_id = t.updated_by_id', array('update_fName' => 'fName', 'update_lName' => 'lName'), Select::JOIN_LEFT_OUTER);
        }
        $select->where(['t.company_id' => (int)$companyId]);

        return $booIdOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }


    /**
     * Load template content by id
     * @param int $templateId
     * @return array with template info
     */
    public function getTemplate($templateId)
    {
        $template = array();
        if (is_numeric($templateId) && !empty($templateId)) {
            $select = (new Select())
                ->from('company_prospects_templates')
                ->where(['prospect_template_id' => (int)$templateId]);

            $template = $this->_db2->fetchRow($select);
        }

        return $template;
    }

    public function getFields()
    {
        $firstName = $this->_company->getCurrentCompanyDefaultLabel('first_name');
        $lastName  = $this->_company->getCurrentCompanyDefaultLabel('last_name');

        $prospectMainInfo = array(
            array('name' => 'salutation', 'label' => 'Salutation'),
            array('name' => 'fName', 'label' => $firstName),
            array('name' => 'lName', 'label' => $lastName),
            array('name' => 'email', 'label' => 'Email Address')
        );

        $n = 0;
        foreach ($prospectMainInfo as &$field) {
            $field['n']     = $n;
            $field['group'] = 'Prospect information';
        }
        ++$n;

        $companyInfo = array(
            array('name' => 'company', 'label' => 'Company name'),
        );

        if (!empty($this->_config['site_version']['check_abn_enabled'])) {
            $companyInfo[] = array('name' => 'company_abn', 'label' => 'Company ABN');
        }

        foreach ($companyInfo as &$field1) {
            $field1['n']     = $n;
            $field1['group'] = 'Company Details';
        }
        ++$n;

        $arrUserInfo = array(
            array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_fName', 'label' => 'Current User ' . $firstName),
            array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_lName', 'label' => 'Current User ' . $lastName),
            array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_username', 'label' => 'Current User Username'),
            array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_email', 'label' => 'Current User Email Address'),
            array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_email_signature', 'label' => 'Current User Email signature'),
        );
        ++$n;

        $arrDateTime = array(
            array('n' => $n, 'group' => 'Date &amp; Time', 'name' => 'time', 'label' => 'Current Time (' . date('H:i') . ')'),
            array('n' => $n, 'group' => 'Date &amp; Time', 'name' => 'today_date', 'label' => 'Today Date (' . $this->_settings->formatDate(date('Y-m-d')) . ')'),
            array('n' => $n, 'group' => 'Date &amp; Time', 'name' => 'today_datetime', 'label' => 'Today Date and Time (' . $this->_settings->formatDateTime(date('Y-m-d H:i')) . ')')
        );


        return array_merge($prospectMainInfo, $companyInfo, $arrUserInfo, $arrDateTime);
    }

    public function saveTemplate($templateId, $data)
    {
        try {
            if (empty($templateId)) {
                $data['create_date'] = date('c');
                $data['author_id']   = $this->_auth->getCurrentUserId();
                $data['company_id']  = $this->_auth->getCurrentUserCompanyId();

                $templateId = $this->_db2->insert('company_prospects_templates', $data);
            } else {
                $this->_db2->update('company_prospects_templates', $data, ['prospect_template_id' => (int)$templateId]);
            }
        } catch (Exception $e) {
            $templateId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $templateId;
    }


    public function deleteTemplate($templateId)
    {
        $this->_db2->delete('company_prospects_templates', ['prospect_template_id' => $templateId]);
    }


    /**
     * Mark template as default,
     * all other company templates mark as not default
     *
     * @param $templateId - to mark as default
     * @param $arrCompanyTemplatesIds - to mark as not default
     */
    public function markAsDefaultTemplate($templateId, $arrCompanyTemplatesIds)
    {
        if (!is_array($arrCompanyTemplatesIds)) {
            $arrCompanyTemplatesIds = [$arrCompanyTemplatesIds];
        }

        $this->_db2->update(
            'company_prospects_templates',
            ['template_default' => 'N'],
            [(new Where())->in('prospect_template_id', $arrCompanyTemplatesIds)]
        );

        $this->_db2->update(
            'company_prospects_templates',
            ['template_default' => 'Y'],
            ['prospect_template_id' => (int)$templateId]
        );
    }


    /**
     * Load default template id for specific company
     *
     * @param $companyId
     * @return string
     */
    public function getCompanyDefaultTemplateId($companyId)
    {
        $select = (new Select())
            ->from('company_prospects_templates')
            ->columns(['prospect_template_id'])
            ->where([
                'template_default' => 'Y',
                'company_id'       => (int)$companyId
            ]);

        return $this->_db2->fetchOne($select);
    }


    /**
     * Update prospect categories for specific company
     *
     * @param int $companyId
     * @param array $arrCategories
     *
     * @return bool true if data was successfully created/updated
     */
    public function updateProspectCategories($companyId, $arrCategories)
    {
        try {
            $this->_db2->delete('company_prospects_selected_categories', ['company_id' => $companyId]);

            $arrCategoriesIds = array();
            foreach ($arrCategories as $arrCategoryInfo) {
                $arrCategoriesIds[] = $arrCategoryInfo['prospect_category_id'];

                $arrNewCategory = array(
                    'company_id'           => $companyId,
                    'prospect_category_id' => $arrCategoryInfo['prospect_category_id'],
                    'order'                => $arrCategoryInfo['order']
                );
                $this->_db2->insert('company_prospects_selected_categories', $arrNewCategory);
            }

            // Remove all records if such categories are not available for QNR
            $arrQnrIds = $this->getCompanyQuestionnaires($companyId, true);
            if (is_array($arrQnrIds) && count($arrQnrIds) && is_array($arrCategoriesIds) && count($arrCategoriesIds)) {
                $arrWhere                         = array();
                $arrWhere['q_id']                 = $arrQnrIds;
                $arrWhere[] = (new Where())->notIn('prospect_category_id', $arrCategoriesIds);

                $this->_db2->delete('company_questionnaires_category_template', $arrWhere);
            }

            $booResult = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booResult = false;
        }

        return $booResult;
    }


    /**
     * Load questionnaires list related to specific company
     *
     * @param int $companyId
     * @param bool $booIdsOnly
     * @return array with questionnaires list
     */
    public function getCompanyQuestionnaires($companyId, $booIdsOnly = false)
    {
        $arrSelect = $booIdsOnly ? array('q_id') : array(Select::SQL_STAR);

        $select = (new Select())
            ->from(['cq' => 'company_questionnaires'])
            ->columns($arrSelect)
            ->where(['cq.company_id' => (int)$companyId])
            ->order('cq.q_id');

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Get list of fields for advanced search
     *
     * @return array
     */
    public function getAdvancedSearchFields()
    {
        $select = (new Select())
            ->from(['cq' => 'company_questionnaires_fields'])
            ->columns(['q_field_unique_id'])
            ->where(['cq.q_field_use_in_search' => 'Y']);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load grouped list of fields that are available during advanced search
     *
     * @return array
     */
    public function getAdvancedSearchFieldsPrepared()
    {
        $defaultQnrId = $this->getDefaultQuestionnaireId();

        $arrAllProspectFields = $this->getQuestionnaireFields($defaultQnrId, false, false);

        $arrAdvancedSearchFieldIds = $this->getAdvancedSearchFields();


        $arrAdvancedSearchFields = array();
        foreach ($arrAllProspectFields as $arrProspectFieldInfo) {
            if (in_array($arrProspectFieldInfo['q_field_unique_id'], $arrAdvancedSearchFieldIds)) {
                $arrProspectFieldInfo['q_field_label']                  = rtrim($arrProspectFieldInfo['q_field_label'] ?? '', '?:');
                $arrProspectFieldInfo['q_field_prospect_profile_label'] = rtrim($arrProspectFieldInfo['q_field_prospect_profile_label'] ?? '', '?:');
                $arrProspectFieldInfo['q_section_prospect_profile']     = ucwords(strtolower($arrProspectFieldInfo['q_section_prospect_profile'] ?? ''));

                $arrAdvancedSearchFields[] = $arrProspectFieldInfo;
            }
        }

        $arrStaticFields = $this->getStaticFields(true);
        foreach ($arrStaticFields as $arrStaticFieldInfo) {
            $arrAdvancedSearchFields[] = $arrStaticFieldInfo;
        }

        return $arrAdvancedSearchFields;
    }

    /**
     * Load hardcoded/static fields that are used in prospects
     *
     * @param bool $booForAdvancedSearch
     * @return array
     */
    public function getStaticFields($booForAdvancedSearch = false)
    {
        $arrStaticFields = array(
            array(9999, 'qf_assessment_summary', 'assessment', $booForAdvancedSearch ? $this->_tr->translate('Qualified as') : $this->_tr->translate('Assessment Summary')),
            array(8888, 'qf_preferred_language', 'preferred_language', $this->_tr->translate('Preferred Language')),
            array(7777, 'qf_office', 'office', $this->_tr->translate('Office')),
            array(6666, 'qf_agent', 'agent', $this->_tr->translate('Sales Agent')),
            array(6667, 'qf_status', 'status', $this->_tr->translate('Status')),
            array(3333, 'qf_seriousness', 'seriousness', $this->_tr->translate('Level of Seriousness')),
        );

        if ($this->_config['site_version']['version'] != 'australia') {
            // languages
            $arrStaticFields[] = array(5555, 'qf_language_english_ielts', 'number', $this->_tr->translate('Language - English (IELTS)'));
            $arrStaticFields[] = array(5554, 'qf_language_english_celpip', 'language', $this->_tr->translate('Language - English (CELPIP)'));
            $arrStaticFields[] = array(5553, 'qf_language_english_general', 'language', $this->_tr->translate('Language - English (General proficiency)'));

            $arrStaticFields[] = array(4444, 'qf_language_french_tef', 'number', $this->_tr->translate('Language - French (TEF)'));
            $arrStaticFields[] = array(4443, 'qf_language_french_general', 'language', $this->_tr->translate('Language - French (General proficiency)'));

            //Points
            $arrStaticFields[] = array(4442, 'qf_points_skilled_worker', 'number', $this->_tr->translate('Skilled Worker Points'));

            if ($this->_company->isExpressEntryEnabledForCompany()) {
                $arrStaticFields[] = array(4441, 'qf_points_express_entry', 'number', $this->_tr->translate('Express Entry Points'));
            }
        }

        if ($booForAdvancedSearch) {
            $arrStaticFields[] = array(9998, 'qf_create_date', 'full_date', $this->_tr->translate('Created On'));
            $arrStaticFields[] = array(9997, 'qf_update_date', 'full_date', $this->_tr->translate('Updated On'));
            if ($this->_config['site_version']['version'] == 'australia') {
                $arrStaticFields[] = array(9996, 'qf_assessment_notes', 'textarea', $this->_tr->translate('Assessment Notes'));
            }
        }

        $arrStaticFieldsPrepared = array();
        foreach ($arrStaticFields as $f) {
            $arrStaticFieldsPrepared[] = array(
                'q_field_id'                       => $f[0],
                'q_field_unique_id'                => $f[1],
                'q_section_id'                     => 10,
                'q_field_type'                     => $f[2],
                'q_field_required'                 => 'Y',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_order'                    => 0,
                'q_field_label'                    => $f[3],
                'q_field_prospect_profile_label'   => $f[3],
                'q_field_help'                     => '',
                'q_field_help_show'                => 'N',
                'q_section_step'                   => 4,
                'q_section_template_name'          => 'Other Info',
                'q_section_prospect_profile'       => 'Other Info',
                'q_section_help'                   => '',
                'q_section_help_show'              => 'N',
            );
        }

        return $arrStaticFieldsPrepared;
    }

    /**
     * Load default questionnaires list related
     *
     * @param bool $booIdsOnly - true to load only ids
     * @return array of found questionnaires
     */
    public function getDefaultQuestionnaires($booIdsOnly = false)
    {
        return $this->getCompanyQuestionnaires($this->_company->getDefaultCompanyId(), $booIdsOnly);
    }


    /**
     * Load default questionnaire id
     * This questionnaire is main - all others are created from it
     *
     * @return int questionnaire id
     */
    public function getDefaultQuestionnaireId()
    {
        return 1;
    }

    /**
     * Load questionnaire settings
     *
     * @param int $qnrId - questionnaire id
     * @return array of questionnaire settings
     */
    public function getQuestionnaireInfo($qnrId)
    {
        $select = (new Select())
            ->from(['cq' => 'company_questionnaires'])
            ->where(['cq.q_id' => $qnrId]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Load questionnaire templates list
     *
     * @param int $qnrId - questionnaire id
     * @return array of questionnaire templates (key - category id, val - template id)
     */
    public function getQuestionnaireTemplates($qnrId)
    {
        $select = (new Select())
            ->from(['t' => 'company_questionnaires_category_template'])
            ->where(['t.q_id' => (int)$qnrId]);

        $arrTemplates = $this->_db2->fetchAll($select);

        $arrResult = array();
        foreach ($arrTemplates as $arrTemplateInfo) {
            $arrResult[$arrTemplateInfo['prospect_category_id']] = $arrTemplateInfo['prospect_template_id'];
        }

        return $arrResult;
    }


    /**
     * Load specific questionnaire sections list
     *
     * @param int $qnrId - questionnaire id
     * @return array of questionnaire sections
     */
    public function getQuestionnaireSections($qnrId)
    {
        $strWhere = new PredicateExpression('t.q_section_id = cqs.q_section_id AND t.q_id ='. $qnrId);

        $select = (new Select())
            ->from(['cqs' => 'company_questionnaires_sections'])
            ->join(array('t' => 'company_questionnaires_sections_templates'), $strWhere, array('q_section_template_name', 'q_section_prospect_profile', 'q_section_help', 'q_section_help_show'), Select::JOIN_LEFT_OUTER)
            ->order(array('cqs.q_section_step ASC', 'cqs.q_section_order ASC'));

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load specific questionnaire section info
     *
     * @param int $qnrId - questionnaire id
     * @param int $qnrSectionId - questionnaire section id
     * @return array of questionnaire section info
     */
    public function getQuestionnaireSectionInfo($qnrId, $qnrSectionId)
    {
        $select = (new Select())
            ->from(['cqs' => 'company_questionnaires_sections'])
            ->join(array('t' => 'company_questionnaires_sections_templates'), 't.q_section_id = cqs.q_section_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where([
                't.q_id'         => (int)$qnrId,
                't.q_section_id' => (int)$qnrSectionId
            ]);

        return $this->_db2->fetchRow($select);
    }

    public function getQuestionnaireSectionEducationId()
    {
        return $this->_config['site_version']['version'] == 'australia' ? 5 : 4;
    }


    public function getQuestionnaireSectionProfessionalProgramsId()
    {
        return $this->_config['site_version']['version'] == 'australia' ? 12 : 0;
    }

    public function getQuestionnaireSectionFamilyCompositionId()
    {
        return $this->_config['site_version']['version'] == 'australia' ? 4 : 0;
    }


    public function getQuestionnaireSectionLanguageId()
    {
        return $this->_config['site_version']['version'] == 'australia' ? 6 : 5;
    }


    public function getQuestionnaireSectionJobId()
    {
        return $this->_config['site_version']['version'] == 'australia' ? 7 : 9;
    }

    public function getQuestionnaireSpouseSectionJobId()
    {
        return $this->_config['site_version']['version'] == 'australia' ? 8 : 12;
    }

    public function getQuestionnaireSpouseSectionJobHeaderId()
    {
        return 11;
    }


    public function getQuestionnaireSectionBusinessId()
    {
        return $this->_config['site_version']['version'] == 'australia' ? 9 : 10;
    }


    /**
     * Load specific questionnaire fields list
     *
     * @param int $qnrId - questionnaire id
     * @param bool $booFormat
     * @param bool $booQNRFields
     * @return array of questionnaire fields
     */
    public function getQuestionnaireFields($qnrId, $booFormat = false, $booQNRFields = true, $booShowHiddenFields = true)
    {
        $qnrId        = intval($qnrId);
        $defaultQNRId = $this->getDefaultQuestionnaireId();
        $select = (new Select())
            ->from(array('f' => 'company_questionnaires_fields'))
            ->join(array('ft' => 'company_questionnaires_fields_templates'), new PredicateExpression('ft.q_field_id = f.q_field_id AND ft.q_id =' . $qnrId), array('q_field_label', 'q_field_prospect_profile_label', 'q_field_help', 'q_field_help_show', 'q_field_hidden'), Select::JOIN_LEFT_OUTER)
            ->join(array('s' => 'company_questionnaires_sections'), 's.q_section_id = f.q_section_id', array('q_section_step'), Select::JOIN_LEFT_OUTER)
            ->join(array('t' => 'company_questionnaires_sections_templates'), new PredicateExpression('t.q_section_id = s.q_section_id AND t.q_id =' . $qnrId), array('q_section_template_name', 'q_section_prospect_profile', 'q_section_help', 'q_section_help_show', 'q_section_hidden'), Select::JOIN_LEFT_OUTER)
            ->join(array('ft2' => 'company_questionnaires_fields_templates'), new PredicateExpression('ft2.q_field_id = f.q_field_id AND ft2.q_id =' . $defaultQNRId), array('original_q_field_label' => 'q_field_label'), Select::JOIN_LEFT_OUTER)
            ->join(array('t2' => 'company_questionnaires_sections_templates'), new PredicateExpression('t2.q_section_id = s.q_section_id AND t2.q_id =' . $defaultQNRId), array('original_q_section_template_name' => 'q_section_template_name'), Select::JOIN_LEFT_OUTER)
            ->join(array('q' => 'company_questionnaires'), new PredicateExpression('q.q_id = ft.q_id'), array('q_simplified'), Select::JOIN_LEFT_OUTER)
            ->order(array('s.q_section_step ASC', 's.q_section_order ASC', 'f.q_section_id ASC', 'f.q_field_order ASC'));
            
        if (!$booShowHiddenFields) {
            $select->where(new PredicateExpression("(
                q.q_simplified <> 'Y'
                OR (
                    q.q_simplified = 'Y'
                    AND
                    (
                        t.q_section_hidden <> 'Y'
                        AND t2.q_section_hidden <> 'Y' 
                        AND ft.q_field_hidden <> 'Y' 
                        AND ft2.q_field_hidden <> 'Y'
                    )
                )
            )"));
        }
        if (!$booQNRFields) {
            $select->where(['f.q_field_show_in_prospect_profile' => 'Y']);
        } else {
            $select->where(['f.q_field_show_in_qnr' => 'Y']);
        }

        $arrQFields = $this->_db2->fetchAll($select);

        if ($booFormat) {
            $arrFields = array();
            foreach ($arrQFields as $arrFieldInfo) {
                $arrFields[$arrFieldInfo['q_field_id']] = $arrFieldInfo;
            }
        } else {
            $arrFields = $arrQFields;
        }

        return $arrFields;
    }


    /**
     * Load field info
     *
     * @param $qnrId - questionnaire id
     * @param $qnrFieldId - questionnaire field id
     *
     * @return array field info
     */
    public function getQuestionnaireFieldInfo($qnrId, $qnrFieldId)
    {
        $select = (new Select())
            ->from(['f' => 'company_questionnaires_fields'])
            ->join(array('ft' => 'company_questionnaires_fields_templates'), 'ft.q_field_id = f.q_field_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where([
                'f.q_field_id' => (int)$qnrFieldId,
                'ft.q_id'      => (int)$qnrId
            ]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Get field id by unique field id
     *
     * @param string $qnrFieldUniqueId - text id which identifies the field
     * @return int field id
     */
    public function getQuestionnaireFieldIdByUniqueId($qnrFieldUniqueId)
    {
        $select = (new Select())
            ->from(['f' => 'company_questionnaires_fields'])
            ->columns(['q_field_id'])
            ->where(['f.q_field_unique_id' => $qnrFieldUniqueId]);

        return $this->_db2->fetchOne($select);
    }


    /**
     * Load questionnaire field info
     *
     * @param int $qnrFieldId - questionnaire field id
     * @return array of questionnaire fields options
     */
    public function getQuestionnaireFieldsList($qnrFieldId)
    {
        $select = (new Select())
            ->from(['f' => 'company_questionnaires_fields'])
            ->where(['f.q_field_id' => (int)$qnrFieldId]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Load default questionnaire fields options list
     *
     * @return array of questionnaire fields options
     */
    public function getDefaultQuestionnaireFieldsOptions()
    {
        $select = (new Select())
            ->from(['ot' => 'company_questionnaires_fields_options_templates'])
            ->columns(['q_field_option_id', 'q_field_option_label'])
            ->where(['ot.q_id' => $this->getDefaultQuestionnaireId()]);

        $arrOptions = $this->_db2->fetchAll($select);

        $arrResult = array();
        if (!empty($arrOptions)) {
            foreach ($arrOptions as $arrOptionInfo) {
                $arrResult[$arrOptionInfo['q_field_option_id']] = $arrOptionInfo['q_field_option_label'];
            }
        }

        return $arrResult;
    }

    /**
     * Load fields options list for specific questionnaire
     *
     * @param int $qnrId - questionnaire id
     * @param bool $booVisibleOnly - true if only visible options must be returned
     * @param bool $booFormat - true if result must be formatted in specific way
     * @param bool $booLoadCustomOptions - true if load custom options list
     *
     * @return array of fields options
     */
    public function getQuestionnaireFieldsOptions($qnrId, $booVisibleOnly = true, $booFormat = true, $booLoadCustomOptions = true)
    {
        $select = (new Select())
            ->from(['o' => 'company_questionnaires_fields_options'])
            ->join(array('ot' => 'company_questionnaires_fields_options_templates'), new PredicateExpression('ot.q_field_option_id = o.q_field_option_id AND ot.q_id =' . $qnrId), Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->join(array('ot2' => 'company_questionnaires_fields_options_templates'), new PredicateExpression('ot2.q_field_option_id = o.q_field_option_id AND ot2.q_id =' . $this->getDefaultQuestionnaireId()), array('original_q_field_option_id' => 'q_field_option_id', 'original_q_field_option_label' => 'q_field_option_label', 'original_q_field_option_visible' => 'q_field_option_visible'), Select::JOIN_LEFT_OUTER)
            ->order(array('o.q_field_option_order ASC'));

        $arrAllOptions = $this->_db2->fetchAll($select);

        foreach ($arrAllOptions as &$arrOptionInfo) {
            if (empty($arrOptionInfo['q_field_option_id'])) {
                $arrOptionInfo['q_field_option_id'] = $arrOptionInfo['original_q_field_option_id'];
            }

            if (empty($arrOptionInfo['q_field_option_label'])) {
                $arrOptionInfo['q_field_option_label'] = $arrOptionInfo['original_q_field_option_label'];
            }

            if (empty($arrOptionInfo['q_field_option_visible'])) {
                $arrOptionInfo['q_field_option_visible'] = $arrOptionInfo['original_q_field_option_visible'];
            }
        }

        if ($booVisibleOnly) {
            foreach ($arrAllOptions as $key => $arrOptionInfo2) {
                if ($arrOptionInfo2['q_field_option_visible'] != 'Y') {
                    unset($arrAllOptions[$key]);
                }
            }
        }

        $arrOptions = $arrAllOptions;
        if ($booFormat) {
            $arrResult = array();
            if (!empty($arrOptions)) {
                foreach ($arrOptions as $arrOptionInfo3) {
                    $arrResult[$arrOptionInfo3['q_field_id']][] = $arrOptionInfo3;
                }
            }
        } else {
            $arrResult = $arrOptions;
        }


        // Load custom options if needed
        $arrCustomResult = $booLoadCustomOptions ? $this->getQuestionnaireFieldsCustomOptions($qnrId, $booVisibleOnly, $booFormat) : array();

        return $arrResult + $arrCustomResult;
    }


    /**
     * Load fields options list for specific questionnaire
     *
     * @param int $qnrId - questionnaire id
     * @param bool $booVisibleOnly - true if only visible options must be returned
     * @param bool $booFormat - true if result must be formatted in specific way
     *
     * @return array of fields options
     */
    public function getQuestionnaireFieldsCustomOptions($qnrId, $booVisibleOnly = true, $booFormat = true)
    {
        $arrWhere         = [];
        $arrWhere['q_id'] = (int)$qnrId;

        if ($booVisibleOnly) {
            $arrWhere['q_field_custom_option_visible'] = 'Y';
        }

        $select = (new Select())
            ->from('company_questionnaires_fields_custom_options')
            ->where($arrWhere)
            ->order('q_field_custom_option_order ASC');

        $arrCustomOptions = $this->_db2->fetchAll($select);

        if ($booFormat) {
            $arrCustomResult = array();
            if (!empty($arrCustomOptions)) {
                foreach ($arrCustomOptions as $arrCustomOptionInfo) {
                    $arrCustomResult[$arrCustomOptionInfo['q_field_id']][] = $arrCustomOptionInfo;
                }
            }
        } else {
            $arrCustomResult = $arrCustomOptions;
        }

        return $arrCustomResult;
    }


    /**
     * Load options list for specific field in specific questionnaire
     *
     * @param int $qId - questionnaire id
     * @param int $fieldId - field id
     * @return array of options list
     */
    public function getQuestionnaireFieldOptions($qId, $fieldId)
    {
        $select = (new Select())
            ->from(['o' => 'company_questionnaires_fields_options'])
            ->join(array('ot' => 'company_questionnaires_fields_options_templates'), 'ot.q_field_option_id = o.q_field_option_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where([
                'ot.q_id'      => (int)$qId,
                'o.q_field_id' => (int)$fieldId
            ])
            ->order('q_field_option_order ASC');

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load options list for specific field in specific questionnaire
     *
     * @param int $qId - questionnaire id
     * @param int $fieldId - field id
     * @return array of options list
     */
    public function getQuestionnaireFieldCustomOptions($qId, $fieldId)
    {
        $select = (new Select())
            ->from(['o' => 'company_questionnaires_fields_custom_options'])
            ->where([
                'o.q_id'       => (int)$qId,
                'o.q_field_id' => (int)$fieldId
            ])
            ->order('o.q_field_custom_option_order ASC');

        return $this->_db2->fetchAll($select);
    }


    /**
     * Create new QNR
     *
     * @param $companyId
     * @param $userId
     * @param $qnrName
     * @param $qnrNoc
     * @param $qnrOriginalId
     * @param int $qnrOfficeId
     * @param int $qnrNegativeTemplateId
     * @param int $qnrThankYouTemplateId
     * @param bool $booAllDuplicate - true to duplicate all settings
     * @param bool $booSimplified
     * @param bool $booLogoOnTop
     * @return int qnr id, empty on error
     */
    public function createQnr($companyId, $userId, $qnrName, $qnrNoc, $qnrOriginalId, $qnrOfficeId = 0, $qnrNegativeTemplateId = 0, $qnrThankYouTemplateId = 0, $booAllDuplicate = false, $booSimplified = false, $booLogoOnTop = false)
    {
        $this->_db2->getDriver()->getConnection()->beginTransaction();
        try {
            // Load info about default selected QNR
            $arrDefaultQnrSettings = $this->getQuestionnaireInfo($qnrOriginalId);
            if (empty($qnrOfficeId) || !is_numeric($qnrOfficeId)) {
                $qnrOfficeId = $booAllDuplicate ? $arrDefaultQnrSettings['q_office_id'] : null;
            } else {
                $qnrOfficeId = (int)$qnrOfficeId;
            }

            $filter = new StripTags();
            // Create new QNR
            $arrNewQnr = array(
                'company_id'           => (int)$companyId,
                'q_noc'                => $qnrNoc,
                'q_name'               => $filter->filter($qnrName),
                'q_section_bg_color'   => $arrDefaultQnrSettings['q_section_bg_color'],
                'q_section_text_color' => $arrDefaultQnrSettings['q_section_text_color'],
                'q_button_color'       => $arrDefaultQnrSettings['q_button_color'],
                'q_preferred_language' => $booAllDuplicate ? $arrDefaultQnrSettings['q_preferred_language'] : null,
                'q_office_id'          => $qnrOfficeId,
                'q_agent_id'           => $booAllDuplicate ? $arrDefaultQnrSettings['q_agent_id'] : null,
                'q_applicant_name'     => $arrDefaultQnrSettings['q_applicant_name'],
                'q_please_select'      => $arrDefaultQnrSettings['q_please_select'],
                'q_please_answer_all'  => $arrDefaultQnrSettings['q_please_answer_all'],
                'q_please_press_next'  => $arrDefaultQnrSettings['q_please_press_next'],
                'q_next_page_button'   => $arrDefaultQnrSettings['q_next_page_button'],
                'q_prev_page_button'   => $arrDefaultQnrSettings['q_prev_page_button'],
                'q_step1'              => $arrDefaultQnrSettings['q_step1'],
                'q_step2'              => $arrDefaultQnrSettings['q_step2'],
                'q_step3'              => $arrDefaultQnrSettings['q_step3'],
                'q_step4'              => $arrDefaultQnrSettings['q_step4'],
                'q_rtl'                => $arrDefaultQnrSettings['q_rtl'],
                'q_template_negative'  => !empty($qnrNegativeTemplateId) ? (int)$qnrNegativeTemplateId : null,
                'q_template_thank_you' => !empty($qnrThankYouTemplateId) ? (int)$qnrThankYouTemplateId : null,
                'q_simplified'         => $booSimplified ? 'Y' : 'N',
                'q_logo_on_top'        => $booLogoOnTop ? 'Y' : 'N',
                'q_created_by'         => (int)$userId,
                'q_created_on'         => date('c')
            );

            $q_id = $this->_db2->insert('company_questionnaires', $arrNewQnr);

            // Create a copy of QNR sections
            $arrDefaultSections = $this->getQuestionnaireSections($qnrOriginalId);
            foreach ($arrDefaultSections as $arrSectionInfo) {
                $arrValues                               = array();
                $arrValues['q_id']                       = (int)$q_id;
                $arrValues['q_section_id']               = (int)$arrSectionInfo['q_section_id'];
                $arrValues['q_section_template_name']    = $arrSectionInfo['q_section_template_name'];
                $arrValues['q_section_prospect_profile'] = $booAllDuplicate ? $arrSectionInfo['q_section_prospect_profile'] : '';
                $arrValues['q_section_help']             = $booAllDuplicate ? $arrSectionInfo['q_section_help'] : null;
                $arrValues['q_section_help_show']        = $booAllDuplicate ? $arrSectionInfo['q_section_help_show'] : null;

                $this->_db2->insert('company_questionnaires_sections_templates', $arrValues);
            }

            // Create a copy of QNR fields (templates)
            $arrDefaultFields = $this->getQuestionnaireFields($qnrOriginalId);
            foreach ($arrDefaultFields as $arrFieldInfo) {
                if (!empty($arrFieldInfo['q_field_label']) || !empty($arrFieldInfo['original_q_field_label'])) {
                    $arrValues = array(
                        'q_id'                           => $q_id,
                        'q_field_id'                     => $arrFieldInfo['q_field_id'],
                        'q_field_label'                  => empty($arrFieldInfo['q_field_label']) ? $arrFieldInfo['original_q_field_label'] : $arrFieldInfo['q_field_label'],
                        'q_field_prospect_profile_label' => $booAllDuplicate ? (string)$arrFieldInfo['q_field_prospect_profile_label'] : '',
                        'q_field_help'                   => $arrFieldInfo['q_field_help'],
                        'q_field_help_show'              => $arrFieldInfo['q_field_help_show'],
                    );

                    $this->_db2->insert('company_questionnaires_fields_templates', $arrValues);
                }
            }

            // Create a copy of QNR fields options (templates)
            $arrDefaultFieldsOptions = $this->getQuestionnaireFieldsOptions($qnrOriginalId, false, false, false);
            foreach ($arrDefaultFieldsOptions as $arrFieldOptionInfo) {
                if (isset($arrFieldOptionInfo['q_field_option_id'])) {
                    $arrValues                           = array();
                    $arrValues['q_id']                   = (int)$q_id;
                    $arrValues['q_field_option_id']      = (int)$arrFieldOptionInfo['q_field_option_id'];
                    $arrValues['q_field_option_label']   = $arrFieldOptionInfo['q_field_option_label'];
                    $arrValues['q_field_option_visible'] = $arrFieldOptionInfo['q_field_option_visible'];

                    $this->_db2->insert('company_questionnaires_fields_options_templates', $arrValues);
                }
            }

            // Create a copy of QNR fields custom options (templates)
            $arrDefaultFieldsCustomOptions = $this->getQuestionnaireFieldsCustomOptions($qnrOriginalId, false, false);
            $this->copyCustomOptions($q_id, $arrDefaultFieldsCustomOptions);


            // Create a copy of assigned categories
            if ($booAllDuplicate) {
                $arrDefaultCategories = $this->getQuestionnaireTemplates($qnrOriginalId);
                foreach ($arrDefaultCategories as $categoryId => $templateId) {
                    $arrValues                         = array();
                    $arrValues['q_id']                 = (int)$q_id;
                    $arrValues['prospect_category_id'] = (int)$categoryId;
                    $arrValues['prospect_template_id'] = (int)$templateId;

                    $this->_db2->insert('company_questionnaires_category_template', $arrValues);
                }
            }

            $this->_db2->getDriver()->getConnection()->commit();
        } catch (Exception $e) {
            $q_id = 0;
            $this->_db2->getDriver()->getConnection()->rollback();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $q_id;
    }


    /**
     * Delete QNR by id
     *
     * @param $qId
     * @return bool true on success
     */
    public function deleteQnr($qId)
    {
        try {
            $this->_db2->delete('company_questionnaires_fields_custom_options', ['q_id' => $qId]);
            $this->_db2->delete('company_questionnaires_category_template', ['q_id' => $qId]);
            $this->_db2->delete('company_questionnaires_fields_options_templates', ['q_id' => $qId]);
            $this->_db2->delete('company_questionnaires_fields_templates', ['q_id' => $qId]);
            $this->_db2->delete('company_questionnaires_sections_templates', ['q_id' => $qId]);
            $this->_db2->delete('company_questionnaires', ['q_id' => $qId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * @param int $companyId
     * @param array $qnrFieldsOptions
     * @return array
     */
    public function getMultipleComboOptionsGrouped($companyId, $qnrFieldsOptions)
    {
        $arrFieldsToCheck = array(
            'qf_referred_by',
            'qf_did_not_arrive'
        );

        foreach ($arrFieldsToCheck as $uniqueFieldId) {
            $fieldId = $this->getFieldIdByUniqueId($uniqueFieldId);
            if (!empty($fieldId) && array_key_exists($fieldId, $qnrFieldsOptions)) {
                $arrData = array();
                switch ($uniqueFieldId) {
                    case 'qf_referred_by':
                        $arrData = $this->getParent()->getCompanyProspectsReferredBy($companyId);
                        break;

                    case 'qf_did_not_arrive':
                        $arrData = $this->getParent()->getCompanyProspectsDidNotArrive($companyId);
                        break;
                }

                $maxOrder         = 0;
                $arrAlreadyLoaded = array();
                foreach ($qnrFieldsOptions[$fieldId] as $arrOptionInfo) {
                    $arrAlreadyLoaded[] = $arrOptionInfo['q_field_custom_option_label'];
                    $maxOrder           = max($maxOrder, $arrOptionInfo['q_field_custom_option_order']);
                }

                foreach ($arrData as $item) {
                    if (!in_array($item, $arrAlreadyLoaded)) {
                        $qnrFieldsOptions[$fieldId][] = array(
                            'q_field_custom_option_id'       => '',
                            'q_id'                           => $this->getDefaultQuestionnaireId(),
                            'q_field_id'                     => $fieldId,
                            'q_field_custom_option_label'    => $item,
                            'q_field_custom_option_visible'  => 'Y',
                            'q_field_custom_option_selected' => 'N',
                            'q_field_custom_option_order'    => $maxOrder++
                        );
                    }
                }

                // Sort options by label
                $arrLabels = array();
                foreach ($qnrFieldsOptions[$fieldId] as $key => $row) {
                    $arrLabels[$key] = $row['q_field_custom_option_label'];
                }
                $arrLabels = array_map('strtolower', $arrLabels);
                array_multisort($arrLabels, SORT_ASC, SORT_STRING, $qnrFieldsOptions[$fieldId]);
            }

        }

        return $qnrFieldsOptions;
    }

    /**
     * Update field template info (e.g. label, help)
     *
     * @param $qnrId
     * @param $fieldId
     * @param $fieldLabel
     * @param $fieldHelp
     * @param $booShowHelp
     * @param string $prospectFieldLabel
     * @param bool $fieldHidden
     * @return bool true on success
     */
    public function updateFieldTemplate($qnrId, $fieldId, $fieldLabel, $fieldHelp, $booShowHelp, $prospectFieldLabel = null, $fieldHidden = false)
    {
        try {
            $select = (new Select())
                ->from(['t' => 'company_questionnaires_fields_templates'])
                ->where([
                    't.q_id'       => $qnrId,
                    't.q_field_id' => $fieldId
                ]);

            $arrRow = $this->_db2->fetchRow($select);

            $arrNewSettings = array(
                'q_field_label'                  => $fieldLabel,
                'q_field_help'                   => $fieldHelp === '' ? null : $fieldHelp,
                'q_field_help_show'              => $booShowHelp ? 'Y' : 'N',
                'q_field_hidden'                 => $fieldHidden ? 'Y': 'N',
                'q_field_prospect_profile_label' => $prospectFieldLabel
            );

            if (is_null($prospectFieldLabel)) {
                unset($arrNewSettings['q_field_prospect_profile_label']);
            }

            if (!empty($arrRow)) {
                $this->_db2->update(
                    'company_questionnaires_fields_templates',
                    $arrNewSettings,
                    [
                        'q_id'       => (int)$qnrId,
                        'q_field_id' => (int)$fieldId
                    ]
                );
            } else {
                $arrNewSettings['q_id']       = $qnrId;
                $arrNewSettings['q_field_id'] = $fieldId;

                $this->_db2->insert('company_questionnaires_fields_templates', $arrNewSettings);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}
