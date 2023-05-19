<?php

namespace Prospects\Service;

use Clients\Service\Clients;
use DateTime;
use DateTimeZone;
use Exception;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Laminas\Filter\StripTags;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Email\Models\MailAccount;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Settings;
use Officio\Common\Service\Encryption;
use Officio\Service\SystemTriggers;
use Officio\Common\SubServiceOwner;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Tasks\Service\Tasks;
use Officio\Templates\SystemTemplates;
use Uniques\Php\StdLib\FileTools;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyProspects extends SubServiceOwner
{

    public const TEMPLATE_PROSPECT_CONVERSION = 'prospect-converion';

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    /** @var Company */
    protected $_company;

    /** @var Pdf */
    protected $_pdf;

    /** @var Country */
    protected $_country;

    /** @var SystemTriggers */
    protected $_triggers;

    public static $exportProspectsLimit = 1000;

    /** @var CompanyProspectOffices */
    protected $_companyProspectOffices;

    /** @var CompanyQnr */
    protected $_companyQnr;

    /** @var Tasks */
    protected $_tasks;

    /** @var PhpRenderer */
    protected $_renderer;

    /** @var Encryption */
    protected $_encryption;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    public function initAdditionalServices(array $services)
    {
        $this->_clients         = $services[Clients::class];
        $this->_files           = $services[Files::class];
        $this->_company         = $services[Company::class];
        $this->_country         = $services[Country::class];
        $this->_pdf             = $services[Pdf::class];
        $this->_triggers        = $services[SystemTriggers::class];
        $this->_tasks           = $services[Tasks::class];
        $this->_renderer        = $services[PhpRenderer::class];
        $this->_encryption      = $services[Encryption::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
    }

    public function init()
    {
        $this->_triggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_DELETE, [$this, 'onDeleteCompany']);

        // TODO Rework this and place event handler in this class, and from there call getCompanyQnr(), so it doesn't initialized too early
        $companyQnr = $this->getCompanyQnr();
        $this->_triggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_DELETE, [$companyQnr, 'onDeleteCompany']);
        $this->_triggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_ENABLE_PROSPECTS, [$companyQnr, 'onEnableCompanyProspects']);

        $this->_systemTemplates->getEventManager()->attach(SystemTemplates::EVENT_GET_AVAILABLE_FIELDS, [$this, 'getSystemTemplateFields']);
    }

    /**
     * @return CompanyQnr
     */
    public function getCompanyQnr()
    {
        if (is_null($this->_companyQnr)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyQnr = $this->_serviceContainer->build(CompanyQnr::class, ['parent' => $this]);
            } else {
                $this->_companyQnr = $this->_serviceContainer->get(CompanyQnr::class);
                $this->_companyQnr->setParent($this);
            }
        }

        return $this->_companyQnr;
    }

    /**
     * @return CompanyProspectOffices
     */
    public function getCompanyProspectOffices()
    {
        if (is_null($this->_companyProspectOffices)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyProspectOffices = $this->_serviceContainer->build(CompanyProspectOffices::class, ['parent' => $this]);
            } else {
                $this->_companyProspectOffices = $this->_serviceContainer->get(CompanyProspectOffices::class);
                $this->_companyProspectOffices->setParent($this);
            }
        }

        return $this->_companyProspectOffices;
    }

    public function onDeleteCompany(EventInterface $event)
    {
        $companyId = $event->getParam('id');
        if (!is_array($companyId)) {
            $companyId = array($companyId);
        }

        //collect company prospects info
        $select = (new Select())
            ->from('company_prospects')
            ->columns(['prospect_id'])
            ->where(['company_id' => $companyId]);

        $arrProspectIds = $this->_db2->fetchCol($select);

        if (is_array($arrProspectIds) && count($arrProspectIds) > 0) {
            // delete prospects
            $this->deleteProspects($arrProspectIds);
        }
    }

    /**
     * Check if folder is located in prospect's folder
     * And if current user has access to this client
     * @param $companyId - company ID
     * @param $prospectId - prospect's id which is checked
     * @param $checkFolder - ftp folder which is checked
     * @return bool true if has access, otherwise false
     */
    public function checkProspectFolderAccess($companyId, $prospectId, $checkFolder)
    {
        $booCorrect  = false;
        $checkFolder = str_replace('\\', '/', $checkFolder ?? '');

        $booIsLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

        $pathToClientDocs = $this->getPathToProspect($prospectId, $companyId, $booIsLocal) ?? '';
        $pathToClientDocs = str_replace('\\', '/', $pathToClientDocs);

        $parsedPath = substr($checkFolder, 0, strlen($pathToClientDocs));

        if (($parsedPath == $pathToClientDocs) && $this->allowAccessToProspect($prospectId, false, $companyId)) {
            $booCorrect = true;
        }

        return $booCorrect;
    }

    /**
     * Check if current user can access prospect
     *
     * @param int|array $arrProspectIds
     * @param bool $booForDelete true to check if prospect can be deleted (allowed for company's prospects only, not for MP)
     * @param int|null $companyId
     * @return bool true if current user can access prospect
     */
    public function allowAccessToProspect($arrProspectIds, $booForDelete = false, $companyId = null)
    {
        $booHasAccess   = false;
        $arrProspectIds = (array)$arrProspectIds;

        if (!count($arrProspectIds)) {
            return false;
        }

        foreach ($arrProspectIds as $prospectId) {
            if (empty($prospectId) || !is_numeric($prospectId)) {
                return false;
            }
        }

        $select = (new Select())
            ->from('company_prospects')
            ->columns(['prospect_id', 'company_id'])
            ->where(['prospect_id' => $arrProspectIds]);

        $arrProspectsInfo = $this->_db2->fetchAll($select);


        $arrProspectsWithAccess    = array();
        $memberCompanyId           = $companyId ?? $this->_auth->getCurrentUserCompanyId();
        $booHasAccessToMarketplace = $this->_company->getCompanyMarketplace()->isMarketplaceModuleEnabledToCompany($memberCompanyId);
        foreach ($arrProspectsInfo as $arrProspectInfo) {
            if ($booForDelete) {
                if (!empty($arrProspectInfo['company_id']) && $arrProspectInfo['company_id'] == $memberCompanyId) {
                    $arrProspectsWithAccess[] = $arrProspectInfo['prospect_id'];
                }
            } else {
                if ((empty($arrProspectInfo['company_id']) && $booHasAccessToMarketplace) || (!empty($arrProspectInfo['company_id']) && $arrProspectInfo['company_id'] == $memberCompanyId)) {
                    $arrProspectsWithAccess[] = $arrProspectInfo['prospect_id'];
                }
            }
        }

        return count($arrProspectIds) == count($arrProspectsWithAccess);
    }

    /**
     * Check if current member has access to specific note
     *
     * @param int $noteId
     * @return bool
     */
    public function hasAccessToNote($noteId)
    {
        $booHasAccess = false;

        try {
            if (!empty($noteId)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();

                $select = (new Select())
                    ->from('company_prospects_notes')
                    ->columns(['company_id'])
                    ->where(['note_id' => (int)$noteId]);

                $noteCompanyId = $this->_db2->fetchOne($select);
                $booHasAccess  = $companyId == $noteCompanyId;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Generate prospect's name based on prospect's data
     *
     * @param array $prospect
     * @return string
     */
    public function generateProspectName($prospect)
    {
        return trim($prospect['fName'] . ' ' . $prospect['lName']);
    }

    /**
     * Calculate new prospects count for the current user's company
     *
     * @param int|null $companyId
     * @param array|null $arrMemberOffices
     * @return int
     */
    public function getNewProspectsCount($companyId = null, $arrMemberOffices = null)
    {
        $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        $select = (new Select())
            ->from(array('cp' => 'company_prospects'))
            ->columns(['count' => new Expression('COUNT(cp.prospect_id)')])
            ->join(array('cpd' => 'company_prospects_divisions'), 'cpd.prospect_id = cp.prospect_id', [], Join::JOIN_LEFT)
            ->join(array('cps' => 'company_prospects_settings'), new PredicateExpression('cps.prospect_id = cp.prospect_id AND cps.company_id = ' . $companyId), ['viewed'], Join::JOIN_LEFT)
            ->where([
                (new Where())
                    ->equalTo('cp.company_id', (int)$companyId)
                    ->nest()
                    ->equalTo('cps.viewed', 'N')
                    ->or
                    ->isNull('cps.viewed')
                    ->unnest()
            ])
            ->group('cp.prospect_id');

        // Show prospects only for allowed divisions
        $arrMemberOffices = is_null($arrMemberOffices) ? $this->_clients->getDivisions(true, $companyId) : $arrMemberOffices;
        if (empty($arrMemberOffices)) {
            $select->where([(new Where())->isNull('cpd.office_id')]);
        } else {
            $select->where([
                (new Where())
                    ->nest()
                    ->isNull('cpd.office_id')
                    ->or
                    ->in('cpd.office_id', $arrMemberOffices)
                    ->unnest()
            ]);
        }

        return $this->_db2->fetchOne($select);
    }

    /**
     * Get the list of prospects' ids for a specific contact/agent
     *
     * @param int $agentId
     * @return array
     */
    public function getProspectsIdsByAgentId($agentId)
    {
        $select = (new Select())
            ->from(array('cp' => 'company_prospects'))
            ->columns(['prospect_id'])
            ->where(['cp.agent_id' => (int)$agentId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load prospects list for current member
     *
     * @param $panelType
     * @param int $start - number from which load prospects list
     * @param int $limit - how many prospects we need show
     * @param string $type - which prospects need to show (e.g. Qualified)
     * @param string $filter - if prospect's info contains this parameter - load it
     * @param array $arrAdvancedSearchParams - array of filters thrown by advanced search form
     * @param string $sort - sorting field
     * @param string $dir - sorting direction
     * @param int $companyId - if set, load prospects for this company
     * @param null $divisionGroupId
     * @param array $arrAdditionalFields
     * @param null $arrReturnFields
     * @param bool $booLoadAllIds true to load all ids of prospects found in the first query
     * @param null $booActiveProspectsChecked
     * @param bool $booExport
     * @return array array rows - of found prospects
     * array rows - of found prospects
     * int totalCount - count of total prospects
     */
    public function getProspectsList($panelType, $start, $limit, $type, $filter, $arrAdvancedSearchParams = null, $sort = 'cp.create_date', $dir = 'DESC', $companyId = null, $divisionGroupId = null, $arrAdditionalFields = null, $arrReturnFields = null, $booLoadAllIds = false, $booActiveProspectsChecked = null, $booExport = false)
    {
        try {
            if (is_null($companyId)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            if (is_null($divisionGroupId)) {
                $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
            }

            $select = (new Select())
                ->from(['cp' => 'company_prospects'])
                ->join(array('cpi' => 'company_prospects_invited'), new PredicateExpression('cpi.prospect_id = cp.prospect_id AND cpi.company_id =' . $companyId), 'invited_on', Select::JOIN_LEFT_OUTER)
                ->join(array('cps' => 'company_prospects_settings'), new PredicateExpression('cps.prospect_id = cp.prospect_id AND cps.company_id =' . $companyId), array('viewed', 'email_sent'), Select::JOIN_LEFT_OUTER)
                ->order($sort . ' ' . $dir)
                ->group('cp.prospect_id');

            if (!empty($limit)) {
                $start = $start < 0 || $start > 1000000 ? 0 : $start;
                $limit = $limit < 0 || $limit > 1000000 ? 0 : $limit;
                $select->limit($limit)->offset($start);
            }


            if ($panelType == 'prospects') {
                $select->join(array('cpo' => 'company_prospects_divisions'), 'cpo.prospect_id = cp.prospect_id', [], Select::JOIN_LEFT_OUTER);
                $select->where(['cp.company_id' => (int)$companyId]);

                // Show prospects only for allowed divisions
                if (isset($arrAdvancedSearchParams['arrMemberOffices'])) {
                    $select->where->addPredicate((new Where())->in('cpo.office_id', $arrAdvancedSearchParams['arrMemberOffices']));
                    $arrAdvancedSearchParams = null;
                } else {
                    $arrMemberOffices = $this->_clients->getDivisions(true, $companyId, $divisionGroupId);
                    if (empty($arrMemberOffices)) {
                        $select->where->addPredicate((new Where())->isNull('cpo.office_id'));
                    } else {
                        if (!is_array($arrMemberOffices)) {
                            $arrMemberOffices = [$arrMemberOffices];
                        }
                        $select->where->addPredicate((new Where())->nest()->isNull('cpo.office_id')->or->in('cpo.office_id', $arrMemberOffices)->unnest());
                    }
                }
            } else {
                $select->where->addPredicate((new Where())->isNull('cp.company_id'));
            }

            if (is_array($arrAdvancedSearchParams)) {
                // Advanced search params - let's apply!
                $cpj_joined      = $cpd_joined = false;
                $cpdcTablesCount = 0;

                // Calculate total search rows
                $maxCount = 1;
                foreach (array_keys($arrAdvancedSearchParams) as $checkKey) {
                    if (preg_match('/^field_(\d+)$/', $checkKey, $regs)) {
                        $maxCount = max($regs[1], $maxCount);
                    }
                }

                $arrCustomFields = $this->getCompanyQnr()->getQuestionnaireFieldsCustomOptions($this->getCompanyQnr()->getDefaultQuestionnaireId(), true, false);

                // define DB table and field where we have to search this field
                $arrAllStaticFields = $this->getCompanyQnr()->getStaticFields(true);
                $static_fields      = self::getStaticFieldsMapping();
                $now                = date('Y-m-d H:i:s'); //PHP Date Stamp

                $compiledCondition = (new Where())->nest();
                for ($i = 1; $i <= $maxCount; $i++) {
                    if (!array_key_exists('field_' . $i, $arrAdvancedSearchParams)) {
                        continue;
                    }

                    $q_field_unique_id = '';
                    if (is_numeric($arrAdvancedSearchParams['field_' . $i])) {
                        foreach ($arrAllStaticFields as $arrAllStaticFieldInfo) {
                            if ($arrAllStaticFieldInfo['q_field_id'] == $arrAdvancedSearchParams['field_' . $i]) {
                                $q_field_unique_id = $arrAllStaticFieldInfo['q_field_unique_id'];
                                break;
                            }
                        }
                    }

                    if (empty($q_field_unique_id)) {
                        $select2 = (new Select())
                            ->from('company_questionnaires_fields')
                            ->columns(['q_field_unique_id'])
                            ->where(['q_field_id' => $arrAdvancedSearchParams['field_' . $i]]);

                        $q_field_unique_id = $this->_db2->fetchOne($select2);
                    }

                    $languageExtraExp = false;
                    if (array_key_exists($q_field_unique_id, $static_fields)) {
                        $tbl        = 'cp'; // company_prospects
                        $fieldName    = $static_fields[$q_field_unique_id];

                        if ($q_field_unique_id == 'qf_referred_by' || $q_field_unique_id == 'qf_did_not_arrive') {
                            foreach ($arrCustomFields as $arrCustomFieldInfo) {
                                if (isset($arrAdvancedSearchParams['options_' . $i]) && $arrCustomFieldInfo['q_field_custom_option_id'] == $arrAdvancedSearchParams['options_' . $i]) {
                                    $arrAdvancedSearchParams['text_' . $i] = $arrCustomFieldInfo['q_field_custom_option_label'];
                                    break;
                                }
                            }

                            if (isset($arrAdvancedSearchParams['options_' . $i]) && empty($arrAdvancedSearchParams['text_' . $i])) {
                                $arrAdvancedSearchParams['text_' . $i] = $arrAdvancedSearchParams['options_' . $i];
                            }
                        }
                    } elseif ($arrAdvancedSearchParams['field_type_' . $i] == 'assessment') {
                        // Assessment Summary
                        $cpdcTablesCount++;
                        $tbl       = 'cpdc' . $cpdcTablesCount;
                        $fieldName = 'prospect_category_id';
                        $select->join(array($tbl => 'company_prospects_data_categories'), new PredicateExpression($tbl . '.prospect_id = cp.prospect_id'), [], Select::JOIN_LEFT_OUTER);

                        // make this combo
                        $arrAdvancedSearchParams['field_type_' . $i] = 'combo';
                    } elseif ($arrAdvancedSearchParams['field_type_' . $i] == 'language') {
                        // Language
                        $tbl                                      = 'cpd'; // company_prospects
                        $fieldName                                = 'q_value';
                        $arrAdvancedSearchParams['options_' . $i] = array_filter(explode(',', $arrAdvancedSearchParams['options_' . $i] ?? ''));

                        if (!$cpd_joined) {
                            $select->join(array('cpd' => 'company_prospects_data'), 'cpd.prospect_id=cp.prospect_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER);
                            $cpd_joined = true;
                        }

                        // return distinct prospect_id!
                        $select->group('cp.prospect_id');

                        // make this combo
                        $arrAdvancedSearchParams['field_type_' . $i] = 'combo';

                        $arrCheckIds = array();
                        $fieldId     = null;
                        $optionId    = null;
                        switch($q_field_unique_id) {
                            case 'qf_language_english_celpip':
                                $arrCheckIds = array(
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_celpip_score_speak'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_celpip_score_read'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_celpip_score_write'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_celpip_score_listen')
                                );

                                $masterFieldId = $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_done');
                                $arrs          = $this->getCompanyQnr()->getQuestionnaireFieldOptions($this->getCompanyQnr()->getDefaultQuestionnaireId(), $masterFieldId);
                                foreach ($arrs as $arr) {
                                    if ($arr['q_field_option_unique_id'] == 'celpip') {
                                        $fieldId  = $arr['q_field_id'];
                                        $optionId = $arr['q_field_option_id'];
                                    }
                                }
                                break;

                            case 'qf_language_english_general':
                                $arrCheckIds = array(
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_general_score_speak'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_general_score_read'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_general_score_write'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_general_score_listen')
                                );

                                $masterFieldId = $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_done');
                                $arrs          = $this->getCompanyQnr()->getQuestionnaireFieldOptions($this->getCompanyQnr()->getDefaultQuestionnaireId(), $masterFieldId);
                                foreach ($arrs as $arr) {
                                    if ($arr['q_field_option_unique_id'] == 'no') {
                                        $fieldId  = $arr['q_field_id'];
                                        $optionId = $arr['q_field_option_id'];
                                    }
                                }
                                break;

                            case 'qf_language_french_general':
                                $arrCheckIds = array(
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_general_score_speak'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_general_score_read'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_general_score_write'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_general_score_listen')
                                );

                                $masterFieldId = $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_done');
                                $arrs          = $this->getCompanyQnr()->getQuestionnaireFieldOptions($this->getCompanyQnr()->getDefaultQuestionnaireId(), $masterFieldId);

                                foreach ($arrs as $arr) {
                                    if (in_array($arr['q_field_option_unique_id'], array('no', 'not_sure'))) {
                                        $fieldId  = $arr['q_field_id'];
                                        $optionId = $arr['q_field_option_id'];
                                    }
                                }
                                break;
                        }

                        if (!empty($arrCheckIds)) {
                            $subQuery         = (new Select('company_prospects_data'))
                                ->columns(['prospect_id'])
                                ->where(
                                    [
                                        'q_field_id' => $fieldId,
                                        'q_value'    => $optionId
                                    ]
                                );
                            if (!is_array($arrCheckIds)) {
                                $arrCheckIds = [$arrCheckIds];
                            }
                            $languageExtraExp = (new Where())
                                ->nest()
                                ->in("$tbl.q_field_id", $arrCheckIds)
                                ->and
                                ->in("cp.prospect_id", $subQuery)
                                ->unnest();
                        }
                    } elseif ($arrAdvancedSearchParams['field_type_' . $i] == 'preferred_language') {
                        // Preferred language
                        $tbl        = 'cp'; // company_prospects
                        $fieldName    = 'preferred_language';

                        // make this textfield
                        $arrAdvancedSearchParams['field_type_' . $i] = 'textfield';
                    } elseif ($arrAdvancedSearchParams['field_type_' . $i] == 'seriousness') {
                        // seriousness
                        $tbl        = 'cp'; // company_prospects
                        $fieldName    = 'seriousness';

                        // make this textfield
                        $arrAdvancedSearchParams['field_type_' . $i] = 'combo';
                    } elseif ($arrAdvancedSearchParams['field_type_' . $i] == 'office') {
                        // Office
                        $tbl        = 'cpo'; // company_prospects_divisions
                        $fieldName    = 'office_id';

                        // make this combo
                        $arrAdvancedSearchParams['field_type_' . $i] = 'combo';
                    } elseif ($arrAdvancedSearchParams['field_type_' . $i] == 'agent') {
                        // Agent
                        $tbl        = 'cp'; // company_prospects
                        $fieldName    = 'agent_id';

                        // make this combo
                        $arrAdvancedSearchParams['field_type_' . $i] = 'combo';
                    } elseif (preg_match('/^qf_job_/', $q_field_unique_id)) {
                        $tbl        = 'cpj'; // company_prospects_job
                        $fieldName    = $q_field_unique_id;

                        if (!$cpj_joined) {
                            $select->join(array('cpj' => 'company_prospects_job'), 'cpj.prospect_id=cp.prospect_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER);
                            $cpj_joined = true;
                        }
                    } elseif (in_array($q_field_unique_id, array('qf_points_skilled_worker', 'qf_points_express_entry'))) {
                        $tbl       = 'cp'; // company_prospects
                        $fieldName = $q_field_unique_id == 'qf_points_skilled_worker' ? 'points_skilled_worker' : 'points_express_entry';
                    } else {
                        $tbl       = 'cpd'; // company_prospects_data
                        $fieldName = "q_value";

                        if (!in_array($q_field_unique_id, array('qf_language_french_tef', 'qf_language_english_ielts'))) {
                            $select->where(['cpd.q_field_id' => $arrAdvancedSearchParams['field_' . $i]]);
                        }

                        if (!$cpd_joined) {
                            $select->join(array('cpd' => 'company_prospects_data'), 'cpd.prospect_id=cp.prospect_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER);
                            $cpd_joined = true;
                        }
                    }

                    $fullFieldName  = "$tbl.$fieldName";
                    $condition      = new Where();
                    $booCheckIsNull = false;
                    switch ($arrAdvancedSearchParams['field_type_' . $i]) {
                        case 'combo':
                        case 'radio':
                        case 'seriousness':
                        case 'agent':
                            $searchVal = $arrAdvancedSearchParams['options_' . $i];
                            $fltr      = $arrAdvancedSearchParams['filter_' . $i];
                        if (!is_array($searchVal)) {
                            $searchVal = [$searchVal];
                        }
                            if (is_array($searchVal)) {
                                if ($fltr == 'is') {
                                    $condition->in($fullFieldName, $searchVal);
                                } else {
                                    $condition->notIn($fullFieldName, $searchVal);
                                }
                            } else {
                                if ($fltr == 'is') {
                                    $condition->equalTo($fullFieldName, $searchVal);
                                } else {
                                    $condition->notEqualTo($fullFieldName, $searchVal);
                                }
                            }

                            if ($fltr != 'is') {
                                $booCheckIsNull = true;
                            }
                            break;

                        case 'number':
                            $fullFieldName = new PredicateExpression("IFNULL($tbl.$fieldName, 0)");
                            $searchVal     = (float)$arrAdvancedSearchParams['text_' . $i];
                            switch ($arrAdvancedSearchParams['filter_' . $i]) {
                                case 'not_equal':
                                    $condition->notEqualTo($fullFieldName, $searchVal);
                                    break;

                                case 'less':
                                    $condition->lessThan($fullFieldName, $searchVal);
                                    break;

                                case 'less_or_equal':
                                    $condition->lessThanOrEqualTo($fullFieldName, $searchVal);
                                    break;

                                case 'more':
                                    $condition->greaterThan($fullFieldName, $searchVal);
                                    break;

                                case 'more_or_equal':
                                    $condition->greaterThanOrEqualTo($fullFieldName, $searchVal);
                                    break;

                                case 'equal':
                                default:
                                $condition->equalTo($fullFieldName, $searchVal);
                                    break;
                            }

                            $fieldId  = null;
                            $optionId = null;
                            if (isset($arrAdvancedSearchParams['field_' . $i]) && $q_field_unique_id == 'qf_language_english_ielts') {
                                unset($arrAdvancedSearchParams['field_' . $i]);
                                $arrCheckIds = array(
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_ielts_score_speak'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_ielts_score_read'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_ielts_score_write'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_ielts_score_listen')
                                );


                                $masterFieldId = $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_english_done');
                                $arrs          = $this->getCompanyQnr()->getQuestionnaireFieldOptions($this->getCompanyQnr()->getDefaultQuestionnaireId(), $masterFieldId);

                                foreach ($arrs as $arr) {
                                    if ($arr['q_field_option_unique_id'] == 'ielts') {
                                        $fieldId  = $arr['q_field_id'];
                                        $optionId = $arr['q_field_option_id'];
                                    }
                                }

                                $subQuery = (new Select('company_prospects_data'))
                                    ->columns(['prospect_id'])
                                    ->where(
                                        [
                                            'q_field_id' => $fieldId,
                                            'q_value'    => $optionId
                                        ]
                                    );

                                if (!is_array($arrCheckIds)) {
                                    $arrCheckIds = [$arrCheckIds];
                                }
                                $condition
                                    ->and
                                    ->in("$tbl.q_field_id", $arrCheckIds)
                                    ->and
                                    ->in('cp.prospect_id', $subQuery);
                            }

                            if (isset($arrAdvancedSearchParams['field_' . $i]) && $q_field_unique_id == 'qf_language_french_tef') {
                                unset($arrAdvancedSearchParams['field_' . $i]);
                                $arrCheckIds = array(
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_tef_score_speak'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_tef_score_read'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_tef_score_write'),
                                    $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_tef_score_listen')
                                );

                                $masterFieldId = $this->getCompanyQnr()->getQuestionnaireFieldIdByUniqueId('qf_language_french_done');
                                $arrs          = $this->getCompanyQnr()->getQuestionnaireFieldOptions($this->getCompanyQnr()->getDefaultQuestionnaireId(), $masterFieldId);

                                foreach ($arrs as $arr) {
                                    if ($arr['q_field_option_unique_id'] == 'yes') {
                                        $fieldId  = $arr['q_field_id'];
                                        $optionId = $arr['q_field_option_id'];
                                    }
                                }

                                $subQuery = (new Select('company_prospects_data'))
                                    ->columns(['prospect_id'])
                                    ->where(
                                        [
                                            'q_field_id' => $fieldId,
                                            'q_value'    => $optionId
                                        ]
                                    );

                                if (!is_array($arrCheckIds)) {
                                    $arrCheckIds = [$arrCheckIds];
                                }
                                $condition
                                    ->and
                                    ->in("$tbl.q_field_id", $arrCheckIds)
                                    ->and
                                    ->in('cp.prospect_id', $subQuery);
                            }
                            break;

                        case 'date':
                        case 'full_date':
                        $searchVal = isset($arrAdvancedSearchParams['date_' . $i]) ? date('Y-m-d', strtotime($arrAdvancedSearchParams['date_' . $i])) : '';
                            switch ($arrAdvancedSearchParams['filter_' . $i]) {
                                case 'is_not':
                                    $condition->notEqualTo($fullFieldName, $searchVal);
                                    break;

                                case 'is_before':
                                    $condition->lessThan($fullFieldName, $searchVal);
                                    break;

                                case 'is_after':
                                    $condition->greaterThan($fullFieldName, $searchVal);
                                    break;

                                case 'is_empty':
                                    $condition->equalTo($fullFieldName, '');
                                    break;

                                case 'is_not_empty':
                                    $condition->notEqualTo($fullFieldName, '');
                                    break;

                                case 'is_between_today_and_date':
                                    $condition->between($fullFieldName, $now, $searchVal);
                                    break;

                                case 'is_between_date_and_today':
                                    $condition->between($fullFieldName, $searchVal, $now);
                                    break;

                                case 'is_since_start_of_the_year_to_now':
                                    $condition->between($fullFieldName, date('Y-01-01'), $now);
                                    break;

                                case 'is_from_today_to_the_end_of_year':
                                    $condition->between($fullFieldName, $now, date('Y-12-31'));
                                    break;

                                case 'is_in_this_month':
                                    $condition->between(
                                        $fullFieldName,
                                        date('Y-m-d', strtotime(date('m') . '/1/' . date('Y'))),
                                        date('Y-m-d', strtotime('next month', strtotime(date('m/01/y'))) - 1)
                                    );
                                    break;

                                case 'is_in_this_year':
                                    $condition->between(
                                        $fullFieldName,
                                        date('Y-01-01'),
                                        date('Y-12-31')
                                    );
                                    break;

                                case 'is':
                                default:
                                $condition->equalTo($fullFieldName, $searchVal);
                                    break;
                            }
                            break;

                        case 'textfield':
                        case 'age':
                        default:
                            if (!$fieldName) {
                                $fieldName     = self::getStaticFieldNameInDB($q_field_unique_id);
                                $fieldName     = empty($fieldName) ? 'fieldName' : $fieldName;
                                $fullFieldName = "$tbl.$fieldName";
                            }
                            $searchVal = $arrAdvancedSearchParams['text_' . $i] ?? '';
                            switch ($arrAdvancedSearchParams['filter_' . $i]) {
                                case 'is':
                                    $condition->equalTo($fullFieldName, $searchVal);
                                    break;

                                case 'is_not':
                                    $condition->notEqualTo($fullFieldName, $searchVal);
                                    break;

                                case 'starts_with':
                                    $condition->like($fullFieldName, $searchVal . '%');
                                    break;

                                case 'ends_with':
                                    $condition->like($fullFieldName, '%' . $searchVal);
                                    break;

                                case 'is_empty':
                                    $condition->equalTo($fullFieldName, '');
                                    break;

                                case 'is_not_empty':
                                    $condition->notEqualTo($fullFieldName, '');
                                    break;

                                case 'does_not_contain':
                                    $condition->notLike($fullFieldName, "%$searchVal%");
                                    break;

                                case 'contains':
                                default:
                                    $condition->like($fullFieldName, "%$searchVal%");
                                    break;
                            }
                            break;
                    }

                    if ($languageExtraExp) {
                        $condition->addPredicate($languageExtraExp);
                    }

                    if ($booCheckIsNull) {
                        $conditionWrapper = (new Where())
                            ->nest()
                            ->addPredicate($condition)
                            ->or
                            ->isNull("$tbl.$fieldName")
                            ->unnest();
                    } else {
                        $conditionWrapper = $condition;
                    }

                    $op = PredicateSet::OP_AND;
                    if ($arrAdvancedSearchParams['operator_' . $i] != 'and') {
                        $op = PredicateSet::OP_OR;
                    }

                    $compiledCondition->addPredicate($conditionWrapper, $op);
                }
                $compiledCondition = $compiledCondition->unnest();

                if ($compiledCondition->count() > 0) {
                    $select->where->addPredicate($compiledCondition);
                }
            } else {
                // Not advanced search
                // Apply filter
                if (!empty($filter)) {
                    $filter = substr($filter, 0, 1000);

                    // Remove not needed chars
                    $arrInvalidChars = array(
                        './',
                        '../',
                        chr(47),
                        chr(92),
                        '|',
                        "\r",
                        "\n",
                        "\t",
                        '<!--',
                        '-->',
                        '<',
                        '>',
                        '&',
                        '$',
                        '#',
                        '*',
                        '(',
                        ')',
                        '{',
                        '}',
                        '[',
                        ']',
                        '=',
                        '+',
                        '-',
                        '%',
                        '~',
                        ':',
                        ',',
                        ';',
                        '?',
                        '!',
                        '  '
                    );

                    do {
                        $old    = $filter;
                        $filter = str_replace($arrInvalidChars, ' ', $filter);
                    } while ($old !== $filter);

                    $arrWords  = explode(' ', $filter);
                    $arrFilter = array();
                    foreach ($arrWords as $word) {
                        $word = trim($word);
                        if (strlen($word)) {
                            $arrFilter[] = (new Where())
                                ->nest()
                                ->like('cp.fName', "%$word%")
                                ->or
                                ->like('cp.lName', "%$word%")
                                ->or
                                ->like('cp.email', "%$word%")
                                ->unnest();
                        }
                    }
                    $select->where->addPredicates($arrFilter);
                } else {
                    // Filter prospects by assigned categories
                    switch ($type) {
                        case 'qualified-prospects':
                            $select->where(['cp.qualified' => 'Y']);
                            break;

                        case 'unqualified-prospects':
                            $select->where(['cp.qualified' => 'N']);
                            break;

                        case 'all-prospects':
                            // Show all prospects
                            break;

                        case 'invited':
                            // Show invited prospects
                            $select->where([(new Where())->isNotNull('cpi.invited_on')]);
                            break;

                        case 'waiting-for-assessment':
                        default:
                            $select->where([(new Where())->isNull('cp.qualified')]);
                            break;
                    }
                }
            }

            if ((isset($arrAdvancedSearchParams['active-prospects']) && !empty($arrAdvancedSearchParams['active-prospects'])) || !empty($booActiveProspectsChecked) ) {
                $select->where(['cp.status' => 'active']);
            }

            $arrRows    = $this->_db2->fetchAll($select);
            $totalCount = $this->_db2->fetchResultsCount($select);

            // Load all found prospect ids - will be used during mass emailing
            $allProspectIds = array();
            if ($booLoadAllIds) {
                if (!empty($limit)) {
                    // We cannot reset only "select", so need to reset it manually
                    $select->reset(Select::COLUMNS)
                        ->reset(Select::LIMIT)
                        ->reset(Select::OFFSET);
                    $select->columns(['prospect_id']);

                    $allProspectIds = $this->_db2->fetchCol($select);
                } else {
                    foreach ($arrRows as $arrProspectRow) {
                        $allProspectIds[] = $arrProspectRow['prospect_id'];
                    }
                }
            }

            // Load additional info for each of the prospect
            if (count($arrRows) && !is_null($arrAdditionalFields)) {
                $arrCategories           = $this->getCompanyQnr()->getCategories();
                $arrFieldIdsToResetValue = $this->getCompanyQnr()->getFieldIdsWithUnreadableValue($panelType);

                $arrIds = array();
                foreach ($arrRows as $arrProspectInfoMain) {
                    $arrIds[] = $arrProspectInfoMain['prospect_id'];
                }

                $arrDynamicFieldIds    = array();
                $booSearchForJobFields = false;
                foreach ($arrAdditionalFields as $additionalFieldId) {
                    $staticFieldId = self::getStaticFieldNameInDB($additionalFieldId);
                    if (empty($staticFieldId)) {
                        $arrDynamicFieldIds[] = $additionalFieldId;
                    }

                    if (preg_match('/^qf_job_/', $additionalFieldId)) {
                        $booSearchForJobFields = true;
                    }
                }

                $arrProspectsCategories        = $this->getProspectsAssignedCategories($arrIds);
                $arrProspectsCategoriesGrouped = array();
                foreach ($arrProspectsCategories as $prospectCategoryInfo) {
                    $arrProspectsCategoriesGrouped[$prospectCategoryInfo['prospect_id']][] = $prospectCategoryInfo['prospect_category_id'];
                }

                $arrDefaultCategories = $this->_clients->getCaseCategories()->getCompanyCaseCategories($this->_auth->getCurrentUserCompanyId());
                $arrAllCompanyOffices = $this->_company->getDivisions($this->_auth->getCurrentUserCompanyId(), $this->_auth->getCurrentUserDivisionGroupId());
                $arrAgents            = $this->_clients->getAgentsListFormatted();

                // Search for jobs data
                $arrProspectsJobs = array();
                if ($booSearchForJobFields) {
                    $arrProspectsJobs = $this->getProspectsMainJobs($arrIds);
                }

                foreach ($arrRows as &$arrProspectInfo) {
                    $prospectId = $arrProspectInfo['prospect_id'];

                    $arrProspectCategories = array_key_exists($prospectId, $arrProspectsCategoriesGrouped)
                        ? $arrProspectsCategoriesGrouped[$prospectId]
                        : array();

                    $arrReadableCategories = $this->getProspectReadableAssignedCategories(
                        $prospectId,
                        $arrProspectInfo,
                        true,
                        $arrProspectCategories,
                        $arrCategories
                    );

                    $arrProspectInfo['qualified_as'] = implode(', ', $arrReadableCategories);

                    // Show 'assigned category' instead of assigned categories
                    if (!empty($arrProspectInfo['visa'])) {
                        foreach ($arrDefaultCategories as $arrDefaultCategoryInfo) {
                            if ($arrDefaultCategoryInfo['client_category_id'] == $arrProspectInfo['visa']) {
                                $arrProspectInfo['qualified_as'] = $arrDefaultCategoryInfo['client_category_name'];
                                break;
                            }
                        }
                    }
                    $arrProspectInfo['qualified_as'] = $arrProspectInfo['qualified_as'] ?: 'not assessed';

                    foreach ($arrAdditionalFields as $additionalFieldId) {
                        $staticFieldId = self::getStaticFieldNameInDB($additionalFieldId);
                        if (!empty($staticFieldId) && array_key_exists($staticFieldId, $arrProspectInfo)) {
                            switch ($additionalFieldId) {
                                case 'qf_agent':
                                    if (!empty($arrProspectInfo[$staticFieldId])) {
                                        foreach ($arrAgents as $agentId => $agentName) {
                                            if ($arrProspectInfo[$staticFieldId] == $agentId) {
                                                $arrProspectInfo[$additionalFieldId] = $agentName;
                                                break;
                                            }
                                        }
                                    }
                                    break;

                                case 'qf_status':
                                    if (isset($arrProspectInfo[$staticFieldId])) {
                                        $arrProspectInfo[$additionalFieldId] = ($arrProspectInfo[$staticFieldId] == '1') ? 'Active' : '';
                                    }
                                    break;

                                default:
                                    $arrProspectInfo[$additionalFieldId] = $arrProspectInfo[$staticFieldId];
                                    break;
                            }
                        }
                    }

                    if (count($arrDynamicFieldIds)) {
                        $arrProspectData = $this->getProspectDetailedData($prospectId, false);

                        foreach ($arrDynamicFieldIds as $strId) {
                            switch ($strId) {
                                case 'qf_office':
                                    $arrProspectOffices = $this->getCompanyProspectOffices()->getProspectOffices($prospectId);

                                    if (count($arrProspectOffices)) {
                                        foreach ($arrAllCompanyOffices as $arrCompanyOfficeInfo) {
                                            foreach ($arrProspectOffices as $prospectOfficeId) {
                                                if ($arrCompanyOfficeInfo['division_id'] == $prospectOfficeId) {
                                                    if (!isset($arrProspectInfo[$strId])) {
                                                        $arrProspectInfo[$strId] = $arrCompanyOfficeInfo['name'];
                                                    } else {
                                                        $arrProspectInfo[$strId] .= ', ' . $arrCompanyOfficeInfo['name'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    break;

                                case 'qf_assessment_summary':
                                    $arrProspectInfo[$strId] = $arrProspectInfo['qualified_as'];
                                    break;

                                default:
                                    if (isset($arrProspectData[$strId])) {
                                        $arrProspectInfo[$strId] = $arrProspectData[$strId];
                                    } elseif (preg_match('/^qf_job_/', $strId)) {
                                        $arrProspectInfo[$strId] = $arrProspectsJobs[$arrProspectInfo['prospect_id']][$strId] ?? '';
                                    }
                                    break;
                            }
                        }
                    }

                    // Return only allowed fields, not all
                    foreach ($arrProspectInfo as $key => $val) {
                        $booUnset = false;
                        if (!is_null($arrReturnFields)) {
                            $booUnset = !in_array($key, $arrReturnFields);
                        }

                        if (($booUnset && !in_array($key, $arrAdditionalFields)) || $val == '0000-00-00') {
                            unset($arrProspectInfo[$key]);
                        } elseif(is_array($arrFieldIdsToResetValue) && in_array($key, $arrFieldIdsToResetValue)) {
                            $arrProspectInfo[$key] = $this->getCompanyQnr()->getUnreadableValue($val);
                            if (!is_null($arrReturnFields) && in_array($key . 'Readable', $arrReturnFields)) {
                                $arrProspectInfo[$key . 'Readable'] = $val;
                            }
                        }
                    }
                }
            }

            if (count($arrRows) && is_array($arrAdvancedSearchParams) && !$booExport) {
                $arrAdvancedSearchFields = $this->getCompanyQnr()->getAdvancedSearchFieldsPrepared();
                unset($arrProspectInfo);
                foreach ($arrRows as $rowKey => $arrProspectInfo) {
                    foreach ($arrProspectInfo as $fieldUniqueId => $fieldValue) {
                        foreach ($arrAdvancedSearchFields as $advancedSearchFieldInfo) {
                            if ($fieldUniqueId == $advancedSearchFieldInfo['q_field_unique_id'] &&
                                $advancedSearchFieldInfo['q_field_type'] == 'textarea' && strlen($fieldValue) > 20) {
                                $arrRows[$rowKey][$fieldUniqueId] = substr($fieldValue, 0, 20) . '...';
                                break;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $arrRows        = array();
            $totalCount     = 0;
            $allProspectIds = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $this->_log->debugErrorToFile('Request', $_REQUEST);
        }


        // Return collected data from DB
        return array(
            'rows'           => $arrRows,
            'totalCount'     => $totalCount,
            'allProspectIds' => $allProspectIds
        );
    }

    /**
     * Calculate prospects count based on panel type, company and filter
     *
     * @param string $panelType
     * @param int $companyId
     * @param int $divisionGroupId
     * @param array $arrFilter
     * @return string
     */
    public function getProspectsCount($panelType, $companyId, $divisionGroupId, $arrFilter = array())
    {
        try {
            $select = (new Select())
                ->from(array('cp' => 'company_prospects'))
                ->columns(['row_count' => new Expression('COUNT(DISTINCT cp.prospect_id)')]);

            if ($panelType == 'prospects') {
                $select->where(['cp.company_id' => (int)$companyId]);

                $select->join(array('cpo' => 'company_prospects_divisions'), 'cpo.prospect_id = cp.prospect_id', [], Select::JOIN_LEFT_OUTER);

                // Show prospects only for allowed divisions
                $arrMemberOffices = $this->_clients->getDivisions(true, $companyId, $divisionGroupId);
                if (empty($arrMemberOffices)) {
                    $select->where->addPredicate((new Where())->isNull('cpo.office_id'));
                } else {
                    if (!is_array($arrMemberOffices)) {
                        $arrMemberOffices = [$arrMemberOffices];
                    }
                    $select->where(
                        [
                            (new Where())
                                ->nest()
                                ->isNull('cpo.office_id')
                                ->or
                                ->in('cpo.office_id', $arrMemberOffices)
                                ->unnest()
                        ]
                    );
                }
            } else {
                $select->where(['cp.status' => 'active']);
                $select->where->addPredicate((new Where())->isNull('cp.company_id'));
            }

            // Filter by "viewed" / "not viewed"
            if (isset($arrFilter['viewed'])) {
                $select->join(array('cps' => 'company_prospects_settings'), new PredicateExpression('cps.prospect_id = cp.prospect_id AND cps.company_id =' . $companyId), [], Select::JOIN_LEFT_OUTER);
                if ($arrFilter['viewed'] == 'Y') {
                    $select->where(['cps.viewed' => 'Y']);
                } else {
                    $select->where->addPredicate(
                        (new Where())
                            ->nest()
                            ->equalTo('cps.viewed', 'N')
                            ->or
                            ->isNull('cps.viewed')
                            ->unnest()
                    );
                }
            }

            // Filter prospects by assigned categories
            $type = $arrFilter['type'] ?? 'all-prospects';
            switch ($type) {
                case 'qualified-prospects':
                    $select->where(['cp.qualified' => 'Y']);
                    break;

                case 'unqualified-prospects':
                    $select->where(['cp.qualified' => 'N']);
                    break;

                case 'invited':
                    // Show invited prospects
                    $select->join(array('cpi' => 'company_prospects_invited'), new PredicateExpression('cpi.prospect_id = cp.prospect_id AND cpi.company_id =' . $companyId), [], Select::JOIN_LEFT_OUTER)
                        ->where(
                            [
                                (new Where())->isNotNull('cpi.invited_on')
                            ]
                        );
                    break;

                case 'waiting-for-assessment':
                    $select->where(['cp.qualified' => null]);
                    break;

                case 'all-prospects':
                default:
                    // Show all prospects
                    break;
            }

            // Filter by period of time
            $when = $arrFilter['when'] ?? '';
            switch ($when) {
                case 'today':
                    $select->where->addPredicate((new Where())->greaterThanOrEqualTo('cp.create_date', date('Y-m-d 00:00:00')));
                    $select->where->addPredicate((new Where())->lessThanOrEqualTo('cp.create_date', date('Y-m-d 23:59:59')));
                    break;

                case 'yesterday':
                    $select->where->addPredicate((new Where())->greaterThanOrEqualTo('cp.create_date', date('Y-m-d 00:00:00', strtotime('yesterday'))));
                    $select->where->addPredicate((new Where())->lessThanOrEqualTo('cp.create_date', date('Y-m-d 23:59:59', strtotime('yesterday'))));
                    break;

                case 'last_7_days':
                    $select->where->addPredicate((new Where())->greaterThanOrEqualTo('cp.create_date', date('Y-m-d 00:00:00', strtotime('-7 days'))));
                    $select->where->addPredicate((new Where())->lessThanOrEqualTo('cp.create_date',  date('Y-m-d 23:59:59')));
                    break;

                case 'before_last_7_days':
                    $select->where->addPredicate((new Where())->greaterThanOrEqualTo('cp.create_date', date('Y-m-d 00:00:00', strtotime('-14 days'))));
                    $select->where->addPredicate((new Where())->lessThanOrEqualTo('cp.create_date',date('Y-m-d 23:59:59', strtotime('-7 days'))));
                    break;

                default:
                    break;
            }

            $count = $this->_db2->fetchOne($select);
        } catch (Exception $e) {
            $count = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $count;
    }

    /**
     * Load list of unread prospects for specific categories
     *
     * @param string $panelType prospects or marketplace
     * @param int $companyId
     * @param int $divisionGroupId
     * @param array $arrTypes
     * @return array
     */
    public function getProspectsUnreadCounts($panelType, $companyId, $divisionGroupId, $arrTypes)
    {
        $arrTypeTotalCount = array();

        try {
            foreach ($arrTypes as $type) {
                $arrTypeTotalCount[$type] = $this->getProspectsCount($panelType, $companyId, $divisionGroupId, array('type' => $type, 'viewed' => 'N'));
            }
        } catch (Exception $e) {
            $arrTypeTotalCount = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $this->_log->debugErrorToFile('Request', $_REQUEST);
        }

        return $arrTypeTotalCount;
    }

    /**
     * Calculate prospects count for the specific company
     *
     * @param int $companyId
     * @return int|string
     */
    public function getProspectsCountForCompany($companyId)
    {
        try {
            $arrWhere = [];
            $arrWhere['cp.company_id'] = (int)$companyId;

            // Load prospects only for allowed divisions
            $arrMemberOffices = $this->_clients->getDivisions(true, $companyId, $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId));
            if (empty($arrMemberOffices)) {
                $arrWhere['cpo.office_id'] = null;
            } else {
                if (!is_array($arrMemberOffices)) {
                    $arrMemberOffices = [$arrMemberOffices];
                }
                $arrWhere[] = (new Where())->nest()->isNull('cpo.office_id')->or->in('cpo.office_id', $arrMemberOffices)->unnest();
            }

            $select = (new Select())
                ->from(['cp' => 'company_prospects'])
                ->columns(['row_count' => new Expression('COUNT(cp.prospect_id)')])
                ->join(array('cpo' => 'company_prospects_divisions'), 'cpo.prospect_id = cp.prospect_id', [], Select::JOIN_LEFT_OUTER)
                ->where($arrWhere);

            $count = $this->_db2->fetchOne($select);
        } catch (Exception $e) {
            $count = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $count;
    }


    /**
     * Load company prospects data
     *
     * @param array|int $arrProspectsIds
     * @return array
     */
    public function getProspectsInfo($arrProspectsIds)
    {
        $select = (new Select())
            ->from('company_prospects')
            ->where(['prospect_id' => $arrProspectsIds]);

        return (is_array($arrProspectsIds)) ? $this->_db2->fetchAll($select) : $this->_db2->fetchRow($select);
    }

    /**
     * Load specific prospect info
     *
     * @param int $prospectId
     * @param string $panelType
     * @param bool $booHideData
     * @return array
     */
    public function getProspectInfo($prospectId, $panelType = 'prospects', $booHideData = true)
    {
        $select = (new Select())
            ->from(['p' => 'company_prospects'])
            ->join(array('c' => 'company'), 'c.company_id = p.company_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where(['p.prospect_id' => (int)$prospectId]);

        if (!is_null($panelType)) {
            if ($panelType == 'prospects') {
                $select->where([(new Where())->isNotNull('p.company_id')]);
            } else {
                $select->where([(new Where())->isNull('p.company_id')]);
            }
        }

        $arrData = $this->_db2->fetchRow($select);

        if ($booHideData) {
            if (is_null($panelType)) {
                $panelType = isset($arrData['company_id']) && !empty($arrData['company_id']) ? 'prospects' : 'marketplace';
            }

            $arrFieldIdsToResetValue = $this->getCompanyQnr()->getFieldIdsWithUnreadableValue($panelType);
            if (is_array($arrFieldIdsToResetValue) && count($arrFieldIdsToResetValue)) {
                foreach ($arrData as $key => $val) {
                    if (in_array($key, $arrFieldIdsToResetValue)) {
                        $arrData[$key] = $this->getCompanyQnr()->getUnreadableValue($val);
                    }
                }
            }
        }

        return $arrData;
    }

    /**
     * Load assigned categories list to specific prospect
     *
     * @param int $prospectId
     * @return array
     */
    public function getProspectAssignedCategories($prospectId)
    {
        $select = (new Select())
            ->from('company_prospects_data_categories')
            ->columns(['prospect_category_id'])
            ->where(['prospect_id' => (int)$prospectId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load assigned categories list to prospects
     *
     * @param array $arrProspectIds
     * @return array
     */
    public function getProspectsAssignedCategories($arrProspectIds)
    {
        $select = (new Select())
            ->from('company_prospects_data_categories')
            ->columns(['prospect_id', 'prospect_category_id'])
            ->where(['prospect_id' => $arrProspectIds]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load NOC details by job title
     *
     * @param string $jobTitle
     * @return array
     */
    public function getNocDetails($jobTitle)
    {
        $arrResult = array();
        if (!empty($jobTitle)) {
            $select = (new Select())
                ->from(['j' => 'company_prospects_noc_job_titles'])
                ->join(array('n' => 'company_prospects_noc'), 'j.noc_code = n.noc_code', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->where(['j.noc_job_title' => $jobTitle]);

            $arrResult = $this->_db2->fetchRow($select);
        }

        return $arrResult;
    }

    /**
     * Load all job titles (noc code, job title and their union)
     * @return array
     */
    public function getAllJobTitles()
    {
        $select = (new Select())
            ->from(['j' => 'company_prospects_noc_job_titles'])
            ->columns(['noc_job_and_code' => new Expression('SQL_CALC_FOUND_ROWS j.*, CONCAT(j.noc_code, " - ", j.noc_job_title)')])
            ->order(['noc_job_title']);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Search for job records by title or code
     *
     * @param string $searchName - search string
     * @param bool $booSearchNoc - true to search by NOC code only
     * @param bool $booSearchByCodeAndJob - true to run search by code or by job
     * @param int $start
     * @param int $limit
     * @param string $language
     * @return array
     */
    public function searchJobTitle($searchName, $booSearchNoc, $booSearchByCodeAndJob, $start, $limit, $language = null)
    {
        $arrResult    = array();
        $arrSearch    = array();
        $totalRecords = 0;

        try {
            if (!is_numeric($start) || $start <= 0) {
                $start = 0;
            }

            if (!is_numeric($limit) || $limit <= 0 || $limit > 50) {
                $limit = 10;
            }

            if (!empty($searchName)) {
                $select = (new Select())
                    ->from(array('j' => 'company_prospects_noc_job_titles'))
                    ->columns(array('noc_job_and_code' => new Expression('CONCAT(j.noc_code, " - ", j.noc_job_title)'), Select::SQL_STAR))
                    ->order(array('noc_job_title'))
                    ->limit($limit)
                    ->offset($start);

                if ($language) {
                    $select->where(['j.noc_language' => $language]);
                }

                if ($booSearchNoc) {
                    $searchName = substr($searchName, 0, 5);
                    $select->where(
                        [
                            (new Where())->nest()
                                ->like('noc_code', "%$searchName%")
                                ->or
                                ->like(new PredicateExpression('CONCAT(j.noc_code, " - ", j.noc_job_title) '), "%$searchName%")
                                ->unnest()
                        ]
                    );
                    $arrSearch = array($searchName);
                } else {
                    $searchName = substr($searchName, 0, 1000);
                    $arrSearch  = Settings::generateWordsCombinations($searchName);
                    if (count($arrSearch) > 24) {
                        $arrSearch = array($searchName);
                    }

                    $where = (new Where())
                        ->nest()
                        ->like(new PredicateExpression('CONCAT(j.noc_code, " - ", j.noc_job_title) '), "%$searchName%");
                    foreach ($arrSearch as $strSearch) {
                        $where->or->like('noc_job_title', "%$strSearch%");
                        if ($booSearchByCodeAndJob && is_numeric($strSearch)) {
                            $where->or->like('noc_code', "%$strSearch%");
                        }
                    }
                    $where = $where->unnest();
                    $select->where([$where]);
                }

                $arrResult    = $this->_db2->fetchAll($select);
                $totalRecords = $this->_db2->fetchResultsCount($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($totalRecords, $arrResult, $arrSearch);
    }

    /**
     * Load list of prospects jobs (use the first row only)
     *
     * @param array $arrProspectIds
     * @return array
     */
    public function getProspectsMainJobs($arrProspectIds)
    {
        $arrProspectsJobs = array();
        if (count($arrProspectIds)) {
            $select = (new Select())
                ->from(['j' => 'company_prospects_job'])
                ->where([
                    'j.prospect_type' => 'main',
                    'j.prospect_id'   => $arrProspectIds,
                    'j.qf_job_order'  => 0
                ]);

            $arrSavedJobs = $this->_db2->fetchAll($select);

            foreach ($arrSavedJobs as $arrSavedJobInfo) {
                $arrProspectsJobs[$arrSavedJobInfo['prospect_id']] = $arrSavedJobInfo;
            }
        }

        return $arrProspectsJobs;
    }


    /**
     * Load job info for specific prospect
     *
     * @param $prospectId
     * @param bool $booUseTextLabels
     * @param string $prospectType (main or spouse)
     * @return array
     */
    public function getProspectAssignedJobs($prospectId, $booUseTextLabels = false, $prospectType = 'main')
    {
        if (!in_array($prospectType, array('main', 'spouse'))) {
            $prospectType = 'main';
        }

        $select = (new Select())
            ->from(['j' => 'company_prospects_job'])
            ->join(array('p' => 'company_prospects'), 'p.prospect_id = j.prospect_id', 'company_id', Select::JOIN_LEFT_OUTER)
            ->where([
                'j.prospect_type' => $prospectType,
                'j.prospect_id'   => (int)$prospectId
            ])
            ->order('j.qf_job_order ASC');

        $arrProspectJobs = $this->_db2->fetchAll($select);

        $arrResult = array();
        if (!$booUseTextLabels) {
            $arrResult = $arrProspectJobs;
        } elseif (is_array($arrProspectJobs) && count($arrProspectJobs)) {
            $arrOptions     = $this->getCompanyQnr()->getDefaultQuestionnaireFieldsOptions();
            $arrCountries   = $this->_country->getCountries(true);
            $dateFormatFull = $this->_settings->variable_get('dateFormatFull');
            foreach ($arrProspectJobs as $arrJobInfo) {
                if (array_key_exists($arrJobInfo['qf_job_duration'], $arrOptions)) {
                    $arrJobInfo['qf_job_duration'] = $arrOptions[$arrJobInfo['qf_job_duration']];
                }

                if (array_key_exists($arrJobInfo['qf_job_location'], $arrOptions)) {
                    $arrJobInfo['qf_job_location'] = $arrOptions[$arrJobInfo['qf_job_location']];
                }

                if (array_key_exists($arrJobInfo['qf_job_province'], $arrOptions)) {
                    $arrJobInfo['qf_job_province'] = $arrOptions[$arrJobInfo['qf_job_province']];
                }

                if (array_key_exists($arrJobInfo['qf_job_presently_working'], $arrOptions)) {
                    $arrJobInfo['qf_job_presently_working'] = $arrOptions[$arrJobInfo['qf_job_presently_working']];
                }

                if (array_key_exists($arrJobInfo['qf_job_qualified_for_social_security'], $arrOptions)) {
                    $arrJobInfo['qf_job_qualified_for_social_security'] = $arrOptions[$arrJobInfo['qf_job_qualified_for_social_security']];
                }

                if (isset($arrJobInfo['qf_job_start_date']) && !empty($arrJobInfo['qf_job_start_date']) && $arrJobInfo['qf_job_start_date'] != '0000-00-00') {
                    $arrJobInfo['qf_job_start_date'] = $this->_settings->reformatDate($arrJobInfo['qf_job_start_date'], Settings::DATE_UNIX, $dateFormatFull);
                }

                if (isset($arrJobInfo['qf_job_end_date']) && !empty($arrJobInfo['qf_job_end_date']) && $arrJobInfo['qf_job_end_date'] != '0000-00-00') {
                    $arrJobInfo['qf_job_end_date'] = $this->_settings->reformatDate($arrJobInfo['qf_job_end_date'], Settings::DATE_UNIX, $dateFormatFull);
                }

                if (isset($arrJobInfo['qf_job_country_of_employment']) && array_key_exists($arrJobInfo['qf_job_country_of_employment'], $arrCountries)) {
                    $arrJobInfo['qf_job_country_of_employment'] = $arrCountries[$arrJobInfo['qf_job_country_of_employment']];
                }

                $arrResult[] = $arrJobInfo;
            }
        }

        // Check if resume file exists
        // if not - don't show in GUI
        if (is_array($arrResult) && count($arrResult)) {
            $booStorageLocal = $this->_company->isCompanyStorageLocationLocal($arrResult[0]['company_id']);
            $prospectPath    = $this->getPathToCompanyProspectJobFiles($prospectId);
            foreach ($arrResult as $key => $arrProspectJobInfo) {
                if (!empty($arrProspectJobInfo['qf_job_resume'])) {
                    $resumePath    = $prospectPath . '/' . $arrProspectJobInfo['qf_job_id'];
                    $booFileExists = $booStorageLocal ? file_exists($resumePath) : $this->_files->getCloud()->checkObjectExists($resumePath);

                    if (!$booFileExists) {
                        $arrResult[$key]['qf_job_resume'] = '';
                    }
                }
            }
        }

        return $arrResult;
    }

    /**
     * Load prospect data (grouped as field id -> field value)
     *
     * @param int $prospectId
     * @return array
     */
    public function getProspectData($prospectId)
    {
        $select = (new Select())
            ->from(['d' => 'company_prospects_data'])
            ->columns(['q_field_id', 'q_value'])
            ->where(['d.prospect_id' => (int)$prospectId]);

        $arrProspectData = $this->_db2->fetchAll($select);

        $arrResult = array();
        foreach ($arrProspectData as $arrData) {
            $arrResult[$arrData['q_field_id']] = $arrData['q_value'];
        }

        return $arrResult;
    }


    /**
     * Get prospect assigned categories in readable format
     *
     * @param int $prospectId
     * @param array $arrProspectInfo
     * @param bool $booShort
     * @param null $arrProspectCategories
     * @param null $arrCategories
     * @return array
     */
    public function getProspectReadableAssignedCategories($prospectId, $arrProspectInfo, $booShort = false, $arrProspectCategories = null, $arrCategories = null)
    {
        $arrAssignedProspectCategories = array();
        $booUnQualified                = isset($arrProspectInfo['qualified']) ? $arrProspectInfo['qualified'] == 'N' : false;
        if ($booUnQualified) {
            $arrAssignedProspectCategories[] = $booShort ? 'NQ' : 'Unqualified';
        } else {
            if (is_null($arrProspectCategories)) {
                $arrProspectCategories = $this->getProspectAssignedCategories($prospectId);
            }

            if (count($arrProspectCategories)) {
                if (!is_array($arrCategories)) {
                    $arrCategories = $this->getCompanyQnr()->getCategories();
                }

                foreach ($arrCategories as $arrCategoryInfo) {
                    if (isset($arrCategoryInfo['prospect_category_id']) && in_array($arrCategoryInfo['prospect_category_id'], $arrProspectCategories)) {
                        $key                             = $booShort ? 'prospect_category_short_name' : 'prospect_category_name';
                        $arrAssignedProspectCategories[] = $arrCategoryInfo[$key];
                    }
                }
            }

            if (!empty($arrProspectInfo['category_other'])) {
                $arrAssignedProspectCategories[] = 'Other: ' . $arrProspectInfo['category_other'];
            }

            if (!empty($arrProspectInfo['category_pnp'])) {
                $arrAssignedProspectCategories[] = 'PNP: ' . $arrProspectInfo['category_pnp'];
            }
        }

        return $arrAssignedProspectCategories;
    }

    /**
     * Get detailed information about the prospect
     *
     * @param int $prospectId
     * @param bool $booFormatDate
     * @return array
     */
    public function getProspectDetailedData($prospectId, $booFormatDate = true)
    {
        $arrResult       = array();
        $arrProspectData = array();
        if (!empty($prospectId)) {
            $select = (new Select())
                ->from(['d' => 'company_prospects_data'])
                ->columns(['q_field_id', 'q_value'])
                ->join(array('f' => 'company_questionnaires_fields'), 'f.q_field_id = d.q_field_id', array('q_field_unique_id', 'q_field_type'), Select::JOIN_LEFT_OUTER)
                ->where(['d.prospect_id' => (int)$prospectId]);

            $arrProspectData = $this->_db2->fetchAll($select);
        }

        if (is_array($arrProspectData) && count($arrProspectData)) {
            $arrOptions = $this->getCompanyQnr()->getDefaultQuestionnaireFieldsOptions();
            foreach ($arrProspectData as $arrData) {
                switch ($arrData['q_field_type']) {
                    case 'country':
                        $value = $this->_country->getCountryName($arrData['q_value']);
                        break;

                    case 'money':
                        $value = $this->_clients->getAccounting()::formatPrice($arrData['q_value'], 'usd');
                        break;

                    case 'date':
                        if ($booFormatDate) {
                            $dateFormatFull = $this->_settings->variable_get('dateFormatFull');
                            $value          = $this->_settings->reformatDate($arrData['q_value'], Settings::DATE_UNIX, $dateFormatFull);
                        } else {
                            $value = $arrData['q_value'];
                        }
                        break;

                    case 'combo':
                    case 'radio':
                        $value = array_key_exists($arrData['q_value'], $arrOptions) ? $arrOptions[$arrData['q_value']] : $arrData['q_value'];
                        break;

                    case 'combo_custom':
                        $value = $arrData['q_value'];
                        if (is_numeric($value) && !empty($value)) {
                            $savedLabel = $this->getCompanyQnr()->getCustomOptionLabelById($value);
                            if (!empty($savedLabel)) {
                                $value = $savedLabel;
                            }
                        }
                        break;

                    case 'checkbox':
                        $arrParsedValues = array();
                        $arrValues       = explode(',', $arrData['q_value'] ?? '');
                        foreach ($arrValues as $optionId) {
                            if (array_key_exists($optionId, $arrOptions)) {
                                $arrParsedValues[] = $arrOptions[$optionId];
                            }
                        }
                        $value = implode(', ', $arrParsedValues);
                        break;

                    case 'textarea':
                        $value = array_key_exists($arrData['q_value'], $arrOptions) ? $arrOptions[$arrData['q_value']] : $arrData['q_value'];
                        break;

                    default:
                        $value = $arrData['q_value'];
                        break;
                }
                $arrResult[$arrData['q_field_unique_id']] = $value;
            }
        }

        return $arrResult;
    }

    /**
     * Load list of notes for all prospects from a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getAllCompanyProspectsNotes($companyId)
    {
        $arrRecords = array();
        try {
            $tz = $this->_auth->getCurrentMemberTimezone();

            $select = (new Select())
                ->from(array('pn' => 'company_prospects_notes'))
                ->join(array('p' => 'company_prospects'), 'p.prospect_id = pn.prospect_id', array('fName', 'lName'))
                ->join(array('m' => 'members'), 'm.member_id = pn.author_id', array('authorFirstName' => 'fName', 'authorLastName' => 'lName'), Select::JOIN_LEFT_OUTER)
                ->where(['pn.company_id' => (int)$companyId])
                ->order(array('p.lName ASC', 'p.fName ASC','pn.create_date DESC'));

            $arrNotes = $this->_db2->fetchAll($select);

            foreach ($arrNotes as $note) {
                $date = new DateTime($note['create_date']);
                $dt   = new DateTime('@' . $date->getTimestamp());
                if ($tz instanceof DateTimeZone) {
                    $dt->setTimezone($tz);
                }

                $author = $this->_clients::generateMemberName(array('fName' => $note['authorFirstName'], 'lName' => $note['authorLastName']));

                $arrRecords[] = array(
                    'prospect' => trim($note['lName'] . ' ' . $note['fName']),
                    'note'     => $note['note'],
                    'date'     => $dt->format('Y-m-d H:i:s'),
                    'author'   => $author['full_name'],
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrRecords;
    }


    /**
     * Get notes list for specific prospect
     *
     * @param int $companyId
     * @param int $prospectId
     * @param bool $booForExport
     * @param int $start
     * @param int $limit
     * @param string $sort
     * @param string $dir
     * @return array
     */
    public function getNotes($companyId, $prospectId, $booForExport = false, $start = 0, $limit = 20, $sort = '', $dir = '')
    {
        $tz = $this->_auth->getCurrentMemberTimezone();

        $select = (new Select())
            ->from(['pn' => 'company_prospects_notes'])
            ->join(array('m' => 'members'), 'm.member_id = pn.author_id', array('fName', 'lName'), Select::JOIN_LEFT_OUTER)
            ->where([
                'pn.prospect_id' => (int)$prospectId,
                'pn.company_id'  => (int)$companyId
            ]);

        $arrNotes = $this->_db2->fetchAll($select);

        $booLocal        = $this->_company->isCompanyStorageLocationLocal($companyId);
        $attachmentsPath = $this->_files->getProspectNoteAttachmentsPath($companyId, $prospectId, $booLocal);

        $arrRecords = array();
        foreach ($arrNotes as $note) {
            $date = new DateTime($note['create_date']);
            $dt   = new DateTime('@' . $date->getTimestamp());
            if ($tz instanceof DateTimeZone) {
                $dt->setTimezone($tz);
            }

            if ($booForExport) {
                $arrRecords[] = array(
                    'author_id'   => $note['author_id'],
                    'note_id'     => $note['note_id'],
                    'prospect_id' => $note['prospect_id'],
                    'note'        => $note['note'],
                    'create_date' => $note['create_date']
                );
            } else {
                $author          = $this->_clients::generateMemberName($note);
                $noteAttachments = $this->getNoteAttachments($note['note_id']);

                $arrResultFileAttachments = array();
                foreach ($noteAttachments as $fileAttachment) {
                    $fileId   = $this->_encryption->encode($attachmentsPath . '/' . $note['note_id'] . '/' . $fileAttachment['id']);
                    $fileSize = Settings::formatSize($fileAttachment['size'] / 1024);

                    $arrResultFileAttachments[] = array(
                        'id'      => $fileAttachment['id'],
                        'file_id' => $fileId,
                        'size'    => $fileSize,
                        'link'    => '#',
                        'name'    => $fileAttachment['name']
                    );
                }

                $arrRecords[] = array(
                    'rec_type'            => 'note',
                    'rec_additional_info' => '',
                    'rec_id'              => $note['note_id'],
                    'message'             => nl2br($note['note'] ?? ''),
                    'real_note'           => $note['note'],
                    'date'                => $dt->format('Y-m-d H:i:s'),
                    'real_date'           => $dt->getTimestamp(),
                    'author'              => $author['full_name'],
                    'visible_to_clients'  => '-',
                    'has_attachment'      => !empty($arrResultFileAttachments),
                    'file_attachments'    => $arrResultFileAttachments,
                    'rtl'                 => false,
                    'allow_edit'          => true
                );
            }
        }
        $totalCount = count($arrRecords);

        // Load tasks list for this prospect
        // @NOTE: The code is very similar to what we have in getNotes method in the Notes class
        if (!$booForExport && $this->_acl->isAllowed('clients-tasks-view')) {
            $arrTasks = $this->_tasks->getMemberTasks(
                'prospect',
                array(
                    'clientId' => $prospectId
                )
            );

            foreach ($arrTasks as $arrTaskInfo) {
                $date = new DateTime($arrTaskInfo['task_create_date']);
                $dt   = new DateTime('@' . $date->getTimestamp());
                if ($tz instanceof DateTimeZone) {
                    $dt->setTimezone($tz);
                }

                $arrRecords[] = array_merge(
                    $arrTaskInfo,

                    // This info is general, same as for Note
                    array(
                        'rec_type'            => $arrTaskInfo['task_completed'] == 'Y' ? 'task_complete' : 'task',
                        'rec_additional_info' => '',
                        'rec_id'              => $arrTaskInfo['task_id'],
                        'message'             => $arrTaskInfo['task_subject'],
                        'real_note'           => $arrTaskInfo['task_subject'],
                        'date'                => $dt->format('Y-m-d H:i:s'),
                        'real_date'           => $dt->getTimestamp(),
                        'author'              => $arrTaskInfo['task_created_by'],
                        'visible_to_clients'  => '-',
                        'has_attachment'      => false,
                        'file_attachments'    => [],
                        'rtl'                 => false,
                        'allow_edit'          => false
                    )
                );
            }

            $totalCount = count($arrRecords);

            // Sort collected data
            $dir     = strtoupper($dir) == 'ASC' ? SORT_ASC : SORT_DESC;
            $sort    = empty($sort) ? 'real_date' : $sort;
            $sort    = $sort == 'note' ? 'real_note' : $sort;
            $sort    = $sort == 'date' ? 'real_date' : $sort;
            $arrSort = array();
            foreach ($arrRecords as $key => $row) {
                $arrSort[$key] = strtolower($row[$sort] ?? '');
            }
            array_multisort($arrSort, $dir, SORT_STRING, $arrRecords);

            // Return only one page
            $arrRecords = array_slice($arrRecords, $start, $limit);

            // Apply sorting for messages too
            $tasksDir = 'DESC';
            if ($sort == 'real_date' && $dir == SORT_ASC) {
                $tasksDir = 'ASC';
            }

            foreach ($arrRecords as &$arrRecInfo) {
                // Load messages for the task
                if ($arrRecInfo['rec_type'] == 'task' || $arrRecInfo['rec_type'] == 'task_complete') {
                    $arrMessages                     = $this->_tasks->getTaskMessages($arrRecInfo['rec_id'], 'timestamp', $tasksDir, false);
                    $arrRecInfo['rec_tasks_content'] = $this->_tasks->formatThreadContent($arrMessages);
                    $arrRecInfo['rec_tasks_author']  = $this->_tasks->formatThreadAuthor($arrMessages);
                    $arrRecInfo['rec_tasks_date']    = $this->_tasks->formatThreadDate($arrMessages);
                }

                // Don't return temp data to js
                unset($arrRecInfo['real_note'], $arrRecInfo['real_date']);
            }
        }


        return array($arrRecords, $totalCount);
    }

    public function getNote($noteId)
    {
        $select = (new Select())
            ->from('company_prospects_notes')
            ->where(['note_id' => (int)$noteId]);

        $note = $this->_db2->fetchRow($select);

        $fileAttachments = $this->getNoteAttachments($noteId);

        $companyId = $this->_auth->getCurrentUserCompanyId();
        $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);
        $filePath  = $this->_files->getProspectNoteAttachmentsPath($companyId, $note['prospect_id'], $booLocal) . '/' . $noteId;

        $arrResultFileAttachments = array();
        foreach ($fileAttachments as $fileAttachment) {
            $fileId   = $this->_encryption->encode($filePath . '/' . $fileAttachment['id']);
            $fileSize = Settings::formatSize($fileAttachment['size'] / 1024);

            $arrResultFileAttachments[] = array(
                'id'      => $fileAttachment['id'],
                'file_id' => $fileId,
                'size'    => $fileSize,
                'link'    => '#',
                'name'    => $fileAttachment['name']
            );
        }

        return array(
            'note_id'            => $note['note_id'],
            'prospect_id'        => $note['prospect_id'],
            'note'               => $note['note'],
            'date'               => $note['create_date'],
            'visible_to_clients' => false,
            'rtl'                => false,
            'file_attachments'   => $arrResultFileAttachments
        );
    }


    /**
     * Create/update note for the prospect
     *
     * @param $action
     * @param $noteId
     * @param $prospectId
     * @param $note
     * @param $attachments
     * @return bool true if all was ok
     */
    public function updateNotes($action, $noteId, $prospectId, $note, $attachments = array())
    {
        try {
            $arrData = array('note' => $note);

            if ($action == 'add') {
                if (!empty($prospectId)) {
                    $arrData['prospect_id'] = (int)$prospectId;
                    $arrData['author_id']   = $this->_auth->getCurrentUserId();
                    $arrData['company_id']  = $this->_auth->getCurrentUserCompanyId();
                    $arrData['create_date'] = date('c');

                    $noteId = $this->_db2->insert('company_prospects_notes', $arrData);
                }
            } else {
                $this->_db2->update('company_prospects_notes', $arrData, ['note_id' => (int)$noteId]);
            }

            $config     = $this->_config['directory'];
            $targetPath = $config['tmp'] . '/uploads/';
            $companyId  = $this->_auth->getCurrentUserCompanyId();
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);
            $filePath   = $this->_files->getProspectNoteAttachmentsPath($companyId, $prospectId, $booLocal) . '/' . $noteId;

            $arrSavedFileAttachments = $this->getNoteAttachments($noteId);

            foreach ($arrSavedFileAttachments as $savedFileAttachment) {
                $booFound = false;
                foreach ($attachments as $attachment) {
                    if ($savedFileAttachment['id'] == $attachment['attach_id']) {
                        $booFound = true;
                    }
                }
                if (!$booFound) {
                    $this->_db2->delete('company_prospect_notes_attachments', ['id' => (int)$savedFileAttachment['id']]);
                    $this->_files->deleteFile($filePath . '/' . $savedFileAttachment['id'], $booLocal);
                }
            }

            $filter = new StripTags();
            foreach ($attachments as $attachment) {
                if (array_key_exists('file_id', $attachment)) {
                    continue;
                }
                $arrInsertAttachmentInfo = array(
                    'note_id'     => (int)$noteId,
                    'prospect_id' => (int)$prospectId,
                    'name'        => $filter->filter($attachment['name']),
                    'size'        => empty($attachment['size']) ? null : (int)$attachment['size']
                );

                $attachmentId = $this->_db2->insert('company_prospect_notes_attachments', $arrInsertAttachmentInfo);

                $attachment['tmp_name'] = $this->_encryption->decode($attachment['tmp_name']);
                // File path is in such format: path/to/file#check_id
                if (preg_match('/(.*)#(\d+)/', $attachment['tmp_name'], $regs)) {
                    $attachment['tmp_name'] = $regs[1];
                }

                $tmpPath = str_replace('//', '/', $targetPath) . $attachment['tmp_name'];

                if (file_exists($tmpPath)) {
                    // Get correct path to the file in the cloud
                    $pathToFile = $filePath . '/' . $attachmentId;
                    if ($booLocal) {
                        $this->_files->moveLocalFile($tmpPath, $this->_files->fixFilePath($pathToFile));
                    } else {
                        $this->_files->getCloud()->uploadFile($tmpPath, $this->_files->getCloud()->fixFilePath($pathToFile));
                    }
                    unlink($tmpPath);
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if current user has access to provided note ids and delete them
     *
     * @param array $arrNoteIds
     * @return bool
     */
    public function deleteNotes($arrNoteIds)
    {
        $booSuccess = false;

        try {
            $arrNoteIds = (array) $arrNoteIds;
            if (count($arrNoteIds)) {
                // Check if current user can delete each note
                $booCheckedSuccess = true;
                foreach ($arrNoteIds as $noteId) {
                    if (empty($noteId) || !is_numeric($noteId) || !$this->hasAccessToNote($noteId)) {
                        $booCheckedSuccess = false;
                        break;
                    }
                }

                // If can delete - delete them!
                if ($booCheckedSuccess) {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                    $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);

                    foreach ($arrNoteIds as $noteId) {
                        $noteInfo = $this->getNote($noteId);

                        if (isset($noteInfo['prospect_id']) && !empty($noteInfo['prospect_id'])) {
                            $attachmentsPath = $this->_files->getProspectNoteAttachmentsPath($companyId, $noteInfo['prospect_id'], $booLocal);

                            $folderPath = $attachmentsPath . '/' . $noteId;
                            $this->_files->deleteFolder($folderPath, $booLocal);
                        }
                    }

                    $this->_db2->delete('company_prospects_notes', ['note_id' => $arrNoteIds]);
                    $booSuccess = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booSuccess;
    }

    /**
     * Load path to company prospects
     *
     * @param int $companyId
     * @param bool $booLocal
     * @return string
     */
    public function getPathToCompanyProspects($companyId = null, $booLocal = null)
    {
        $config    = $this->_config['directory'];
        $booLocal  = is_null($booLocal) ? $this->_auth->isCurrentUserCompanyStorageLocal() : $booLocal;
        $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        $root = $booLocal ? $config['companyfiles'] : '';

        return $root . '/' . $companyId . '/.prospects';
    }

    /**
     * Get path to the company prospects job files location
     * @param int $prospectId
     * @param int $companyId
     * @param bool $booLocal
     * @return string
     */
    public function getPathToCompanyProspectJobFiles($prospectId, $companyId = null, $booLocal = null)
    {
        $config   = $this->_config['directory'];
        $booLocal = is_null($booLocal) ? $this->_auth->isCurrentUserCompanyStorageLocal() : $booLocal;
        if (is_null($companyId)) {
            $arrProspectInfo = $this->getProspectInfo($prospectId, null);
            // Is this "marketplace" prospect?
            if (isset($arrProspectInfo['prospect_id']) && empty($arrProspectInfo['company_id'])) {
                $companyId = 0;
            } else {
                // Not "marketplace" prospect - relates to the current user's company
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }
        }

        $root = $booLocal ? $config['companyfiles'] : '';

        return $root . '/' . $companyId . '/.prospects_files/' . $prospectId;
    }

    /**
     * Load path to prospect's ftp folder
     *
     * @param $prospectId
     * @param $companyId
     * @param $booLocal
     * @return string
     */
    public function getPathToProspect($prospectId, $companyId = null, $booLocal = null)
    {
        return !empty($prospectId) && is_numeric($prospectId) ? $this->getPathToCompanyProspects($companyId, $booLocal) . '/' . $prospectId : '';
    }

    /**
     * Generate array with folder properties (used in GUI)
     *
     * @param $path
     * @param $file
     * @param $arrChildren
     * @return array
     */
    private function generateFolderArray($path, $file, $arrChildren)
    {
        return array(
            'el_id'             => $this->_encryption->encode($path),
            'filename'          => $file,
            'path_hash'         => $this->_files->getHashForThePath($path),
            'uiProvider'        => 'col',
            'iconCls'           => $this->_files->getFolderIconCls('CD'),
            'allowChildren'     => true,
            'expanded'          => empty($arrChildren),
            'leaf'              => false,
            'folder'            => true,
            'isDefaultFolder'   => false,
            'checked'           => false,
            'allowDeleteFolder' => true,
            'allowRW'           => true,
            'allowEdit'         => true,
            'children'          => $arrChildren
        );
    }

    /**
     * Load folders and files list for a specific prospect
     *
     * @param bool $booLocal
     * @param int $prospectId
     * @return array
     */
    public function loadFoldersAndFilesList($booLocal, $prospectId)
    {
        $arrFolders = false;

        $booAlive = $booLocal ? true : $this->_files->getCloud()->isAlive();
        if ($booAlive) {
            $strPathToProspect = $this->getPathToProspect($prospectId);
            if ($booLocal) {
                $this->_files->createFTPDirectory($strPathToProspect);
                $arrFolders = $this->_files->_loadFTPFoldersAndFilesList($strPathToProspect, array(), 'D', $prospectId, true);
            } else {
                $arrFolders = $this->_files->_loadMemberCloudFoldersAndFilesList($strPathToProspect, array(), 'D', $prospectId, true);
            }
            // Make sure that root path has "/" in the end of the path -
            // Is used in checks that it is a dir in the Cloud
            $arrFolders              = array($this->generateFolderArray($strPathToProspect . '/', 'Prospect Documents', $arrFolders));
            $arrFolders[0]['isRoot'] = true;
        }

        return $arrFolders;
    }

    /**
     * Load int field id by its text field id
     * @param $arrFields
     * @param string $companyFieldId
     * @param bool $booCase
     * @return int field id
     */
    private function _getFieldId($arrFields, $companyFieldId, $booCase = true)
    {
        $fieldId          = 0;
        $fieldIdKey       = $booCase ? 'field_id' : 'applicant_field_id';
        $uniqueFieldIdKey = $booCase ? 'company_field_id' : 'applicant_field_unique_id';
        foreach ($arrFields as $fieldInfo) {
            if ($fieldInfo[$uniqueFieldIdKey] == $companyFieldId) {
                $fieldId = $fieldInfo[$fieldIdKey];
                break;
            }
        }

        return $fieldId;
    }


    /**
     * Convert prospect(s) to client(s)
     * At the end of process - prospect's record will be deleted
     *
     * @param array $arrProspectIds
     * @param $arrProspectOffices
     * @param $companyInvoiceId
     * @param int $caseTypeId
     * @param $clientTypeId
     * @return array First item is bool true on success, otherwise false
     */
    public function convertToClient($arrProspectIds, $arrProspectOffices, $companyInvoiceId, $caseTypeId, $clientTypeId)
    {
        $booSuccess                   = false;
        $strError                     = '';
        $password                     = '';
        $booShowWelcomeMessage        = false;
        $caseId                       = 0;
        $caseReferenceNumberToRelease = false;

        try {
            $companyId        = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId  = $this->_auth->getCurrentUserDivisionGroupId();
            $currentMemberId  = $this->_auth->getCurrentUserId();
            $companyAdminId   = $this->_company->getCompanyAdminId($companyId);
            $arrCompanyFields = $this->_clients->getFields()->getCompanyFields($companyId);

            $applicantTypeId       = 0;
            $arrParentClientFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $clientTypeId);

            // Load options for relationship status field -
            // will be used to identify option id during data saving
            $arrRelationshipOptions  = array();
            $arrDisabledLoginOptions = array();
            foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                switch ($arrParentClientFieldInfo['applicant_field_unique_id']) {
                    case 'relationship_status':
                        $arrRelationshipOptions = $this->_clients->getApplicantFields()->getFieldsOptions($arrParentClientFieldInfo['applicant_field_id']);
                        break;

                    case 'disable_login':
                        $arrDisabledLoginOptions = $this->_clients->getApplicantFields()->getFieldsOptions($arrParentClientFieldInfo['applicant_field_id']);
                        break;

                    default:
                        break;
                }
            }

            $view = new ViewModel();
            foreach ($arrProspectIds as $prospectId) {
                // Load prospect info
                $arrProspectInfo = $this->getProspectInfo($prospectId, null, false);

                if ($arrProspectInfo) {
                    $this->_db2->getDriver()->getConnection()->beginTransaction();

                    // Load additional info
                    $arrProspectDetailedData  = $this->getProspectDetailedData($prospectId);
                    $arrProspectOffices       = is_null($arrProspectOffices) ? $this->getCompanyProspectOffices()->getProspectOffices($prospectId) : $arrProspectOffices;
                    $arrProspectJobData       = $this->getProspectAssignedJobs($prospectId, true);
                    $arrProspectSpouseJobData = $this->getProspectAssignedJobs($prospectId, true, 'spouse');

                    // Notes
                    list($arrNotes,) = $this->getNotes($companyId, $prospectId, true);

                    // Client form fields + data
                    $arrCaseInfo = array(
                        'client_type_id'     => $caseTypeId,
                        'added_by_member_id' => $currentMemberId
                    );

                    if (!empty($arrProspectInfo['agent_id'])) {
                        $arrCaseInfo['agent_id'] = $arrProspectInfo['agent_id'];
                    }

                    $arrCaseData = array(
                        $this->_getFieldId($arrCompanyFields, 'Client_file_status')       => 'Active',
                        $this->_getFieldId($arrCompanyFields, 'accounting')               => 'user:all',
                        $this->_getFieldId($arrCompanyFields, 'processing')               => 'user:all',
                        $this->_getFieldId($arrCompanyFields, 'sales_and_marketing')      => 'user:all',
                        $this->_getFieldId($arrCompanyFields, 'registered_migrant_agent') => $companyAdminId,
                        $this->_getFieldId($arrCompanyFields, 'date_client_signed')       => date('Y-m-d'),

                        // Custom fields, can be absent for companies
                        $this->_getFieldId($arrCompanyFields, 'title')                    => array_key_exists('qf_salutation', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_salutation'] : '',
                        $this->_getFieldId($arrCompanyFields, 'phone_w')                  => array_key_exists('qf_phone', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_phone'] : '',
                        $this->_getFieldId($arrCompanyFields, 'fax_w')                    => array_key_exists('qf_fax', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_fax'] : '',
                        $this->_getFieldId($arrCompanyFields, 'country_of_citizenship')   => array_key_exists('qf_country_of_citizenship', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_country_of_citizenship'] : '',
                        $this->_getFieldId($arrCompanyFields, 'country_of_residence')     => array_key_exists('qf_country_of_residence', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_country_of_residence'] : '',
                        $this->_getFieldId($arrCompanyFields, 'date_of_english_test')     => array_key_exists('qf_language_date_of_test', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_language_date_of_test'] : '',
                    );

                    // Use 'visa subclass' only if it was identified
                    $subclassFieldVal = '';
                    if (!empty($arrProspectInfo['visa'])) {
                        $subclassFieldVal = $arrCaseData[$this->_getFieldId($arrCompanyFields, 'visa_subclass')] = $arrProspectInfo['visa'];
                    }

                    // Use the first job title for 'main applicant'
                    foreach ($arrProspectJobData as $arrProspectJobInfo) {
                        if ($arrProspectJobInfo['prospect_type'] == 'main' && empty($arrProspectJobInfo['qf_job_order'])) {
                            $arrCaseData[$this->_getFieldId($arrCompanyFields, 'assessment_occupation')] = $arrProspectJobInfo['qf_job_title'];
                            break;
                        }
                    }


                    // Remove all not existing fields
                    // This can be if company field id was renamed or not found
                    foreach ($arrCaseData as $key => $val) {
                        if (empty($key)) {
                            unset($arrCaseData[$key]);
                        }
                    }

                    // Prepare client data
                    $arrInternalContacts = array();
                    $arrClientData       = array();

                    $booAustralia          = $this->_config['site_version']['version'] == 'australia';
                    $strClientLastNameKey  = $booAustralia ? 'family_name' : 'last_name';
                    $strClientFirstNameKey = $booAustralia ? 'given_names' : 'first_name';

                    $arrFieldsMapping = array(
                        $strClientLastNameKey  => $arrProspectInfo['lName'],
                        $strClientFirstNameKey => $arrProspectInfo['fName'],
                        'DOB'                  => Settings::isDateEmpty($arrProspectInfo['date_of_birth']) ? '' : $arrProspectInfo['date_of_birth'],
                        'email'                => $arrProspectInfo['email'],
                        'office'               => $arrProspectOffices,
                        'phone_main'           => array_key_exists('qf_phone', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_phone'] : '',
                        'country_of_passport'  => array_key_exists('qf_country_of_citizenship', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_country_of_citizenship'] : '',
                        'country_of_residence' => array_key_exists('qf_country_of_residence', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_country_of_residence'] : '',
                        'address_1'            => array_key_exists('qf_current_address_street_address_1', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_current_address_street_address_1'] : '',
                        'address_2'            => array_key_exists('qf_current_address_street_address_2', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_current_address_street_address_2'] : '',
                        'city'                 => array_key_exists('qf_current_address_suburb', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_current_address_suburb'] : '',
                        'state'                => array_key_exists('qf_current_address_state', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_current_address_state'] : '',
                        'zip_code'             => array_key_exists('qf_current_address_postal_code', $arrProspectDetailedData) ? $arrProspectDetailedData['qf_current_address_postal_code'] : ''
                    );

                    $dateFormatFull = $this->_settings->variableGet('dateFormatFull');

                    if (array_key_exists('qf_initial_interview_date', $arrProspectDetailedData) && !Settings::isDateEmpty($arrProspectDetailedData['qf_initial_interview_date'])) {
                        $arrFieldsMapping['initial_interview_date'] = $this->_settings->reformatDate($arrProspectDetailedData['qf_initial_interview_date'], $dateFormatFull, Settings::DATE_UNIX);
                    } else {
                        $arrFieldsMapping['initial_interview_date'] = '';
                    }

                    if (!empty($arrProspectInfo['email'])) {
                        $email  = $arrProspectInfo['email'];
                        $number = 1;
                        while ($this->_clients->isUsernameAlreadyUsed($email)) {
                            $email = $arrProspectInfo['email'] . '_' . $number++;
                        }
                        $arrFieldsMapping['username'] = $email;
                        $arrFieldsMapping['password'] = $password = $this->_clients->generatePass();
                        $booShowWelcomeMessage        = $this->_company->getPackages()->canCompanyClientLogin($companyId);
                    }

                    foreach ($arrDisabledLoginOptions as $arrDisabledLoginOptionInfo) {
                        if ($arrDisabledLoginOptionInfo['value'] == 'Enabled') {
                            $arrFieldsMapping['disable_login'] = $arrDisabledLoginOptionInfo['applicant_form_default_id'];
                            break;
                        }
                    }

                    // Use relationship status field only if option was found
                    $booMarried = false;
                    if (!empty($arrProspectDetailedData['qf_marital_status'])) {
                        if (in_array($arrProspectDetailedData['qf_marital_status'], $this->_clients->getFields()->getMaritalStatuses())) {
                            $booMarried = true;
                        }

                        foreach ($arrRelationshipOptions as $arrRelationshipOptionInfo) {
                            if ($arrRelationshipOptionInfo['value'] == $arrProspectDetailedData['qf_marital_status']) {
                                $arrFieldsMapping['relationship_status'] = $arrRelationshipOptionInfo['applicant_form_default_id'];
                                break;
                            }
                        }
                    }

                    $contactTypeId = $this->_clients->getMemberTypeIdByName('internal_contact');
                    foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                        foreach ($arrFieldsMapping as $uniqueFieldId => $fieldVal) {
                            if ($arrParentClientFieldInfo['applicant_field_unique_id'] == $uniqueFieldId) {
                                // Group fields by parent client type
                                // i.e. internal contact info and main client info
                                if ($arrParentClientFieldInfo['member_type_id'] == $contactTypeId || $arrParentClientFieldInfo['contact_block'] == 'Y') {
                                    if (!array_key_exists($arrParentClientFieldInfo['applicant_block_id'], $arrInternalContacts)) {
                                        $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']] = array(
                                            'parent_group_id' => array(),
                                            'data'            => array()
                                        );
                                    }

                                    if (!in_array($arrParentClientFieldInfo['group_id'], $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'])) {
                                        $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'][] = $arrParentClientFieldInfo['group_id'];
                                    }

                                    $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['data'][] = array(
                                        'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                        'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                        'value'           => $fieldVal,
                                        'row'             => 0,
                                        'row_id'          => 0
                                    );
                                } else {
                                    $arrClientData[] = array(
                                        'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                        'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                        'value'           => $fieldVal,
                                        'row'             => 0,
                                        'row_id'          => 0
                                    );
                                }
                            }
                        }
                    }

                    // Add one dependent record if needed
                    $arrDependents = array();
                    if ($booMarried) {
                        if (Settings::isDateEmpty($arrProspectDetailedData['qf_spouse_date_of_birth'])) {
                            $arrProspectDetailedData['qf_spouse_date_of_birth'] = null;
                        } else {
                            $arrProspectDetailedData['qf_spouse_date_of_birth'] = $this->_settings->reformatDate($arrProspectDetailedData['qf_spouse_date_of_birth'], $dateFormatFull);
                        }

                        $arrDependents[] = array(
                            'relationship' => 'spouse',
                            'line'         => 0,
                            'fName'        => $arrProspectDetailedData['qf_spouse_first_name'],
                            'lName'        => $arrProspectDetailedData['qf_spouse_last_name'],
                            'DOB'          => $arrProspectDetailedData['qf_spouse_date_of_birth'],
                            'migrating'    => 'yes',
                        );
                    }

                    // Prepare all info
                    $arrNewClientInfo = array(
                        'createdBy'  => $currentMemberId,
                        'arrParents' => array(
                            array(
                                // Parent client info
                                'arrParentClientInfo' => array(
                                    'emailAddress'     => $arrProspectInfo['email'],
                                    'fName'            => $arrProspectInfo['fName'],
                                    'lName'            => $arrProspectInfo['lName'],
                                    'createdBy'        => $currentMemberId,
                                    'memberTypeId'     => $clientTypeId,
                                    'applicantTypeId'  => $applicantTypeId,
                                    'arrApplicantData' => $arrClientData,
                                    'arrOffices'       => $arrProspectOffices,
                                ),

                                // Internal contact(s) info
                                'arrInternalContacts' => $arrInternalContacts,
                            )
                        ),

                        // Case info
                        'case'       => array(
                            'members' => array(
                                'company_id'   => $companyId,
                                'emailAddress' => $arrProspectInfo['email'],
                                'userType'     => $this->_clients->getMemberTypeIdByName('case'),
                                'regTime'      => strtotime($arrProspectInfo['create_date']),
                                'status'       => 1
                            ),

                            'members_divisions' => $arrProspectOffices,

                            'clients' => $arrCaseInfo,

                            'client_form_data' => $arrCaseData,

                            'client_form_dependents' => $arrDependents,

                            'u_notes' => $arrNotes,
                        )
                    );

                    // Create new client (with internal contact(s)) + case
                    $arrResult           = $this->_clients->createClient($arrNewClientInfo, $companyId, $divisionGroupId, true);
                    $arrCreatedClientIds = $arrResult['arrCreatedClientIds'];
                    $strError            = $arrResult['strError'];
                    $caseId              = $arrResult['caseId'];
                    $clientId            = $arrCreatedClientIds[0];

                    if (empty($strError)) {
                        // Create new client folders
                        $this->_files->mkNewMemberFolders(
                            $caseId,
                            $companyId,
                            $this->_company->isCompanyStorageLocationLocal($companyId)
                        );

                        // Update tasks owner
                        $this->_tasks->convertTaskOwner($prospectId, $caseId);

                        // Move all documents to new place
                        $src      = $this->getPathToProspect($prospectId ?? '');
                        $src      = rtrim($src, '/') . '/';
                        $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
                        $dst      = $this->_files->getClientCorrespondenceFTPFolder($companyId, $caseId, $booLocal);
                        if ($booLocal) {
                            $this->_files->moveFTPFolder($src, $dst);
                        } else {
                            $this->_files->moveCloudFolder($src, $dst);
                        }

                        //Check if CV is attached to Job
                        if (!empty($arrProspectJobData[0]['qf_job_resume'])) {
                            //Move Resume to Correspondence
                            $prospectPath = $this->getPathToCompanyProspectJobFiles($prospectId);
                            $prospectPath = str_replace('\\', '/', $prospectPath);
                            $resumePath   = $prospectPath . '/' . $arrProspectJobData[0]['qf_job_id'];
                            $this->_files->copyFile($resumePath, $dst . '/' . FileTools::cleanupFileName($arrProspectJobData[0]['qf_job_resume']), $booLocal);
                        }

                        // Generate PDF file with all data not imported to client

                        if (array_key_exists('date_of_birth', $arrProspectInfo) && (!empty($arrProspectInfo['date_of_birth']) || $arrProspectInfo['date_of_birth'] != '')) {
                            $arrProspectInfo['date_of_birth'] = $this->_settings->reformatDate($arrProspectInfo['date_of_birth'], Settings::DATE_UNIX, $dateFormatFull);
                        }


                        // Load Assessment info
                        $arrAssignedProspectCategories = $this->getProspectReadableAssignedCategories($prospectId, $arrProspectInfo);

                        // Get saved assessment data
                        $arrAssessmentInfo = array();
                        if (array_key_exists('assessment', $arrProspectInfo) && !empty($arrProspectInfo['assessment'])) {
                            $arrAssessmentInfo = unserialize($arrProspectInfo['assessment']);
                        }

                        // Make sure that we identified if there is a spouse
                        // This is used in points calculation
                        $booHasProspectSpouse = false;
                        $maritalStatusFieldId = $this->getCompanyQnr()->getFieldIdByUniqueId('qf_marital_status');
                        $arrProspectData      = $this->getProspectData($prospectId);
                        if ($arrProspectData) {
                            $booHasProspectSpouse = $this->hasProspectSpouse((int)$arrProspectData[$maritalStatusFieldId]);
                        }
                        $view->setVariable('booHasProspectSpouse', $booHasProspectSpouse);
                        $view->setVariable('booOnlyPointsTable', true);
                        $view->setVariable('arrAssessmentInfo', $arrAssessmentInfo);
                        $view->setVariable('booExpressEntryEnabledForCompany', $this->_company->isExpressEntryEnabledForCompany());
                        $view->setTemplate('prospects/index/prospect-assessment.phtml');

                        $strAssessmentPointsTable = $this->_renderer->render($view);
                        // For pdf we need update some html things... :S
                        $strAssessmentPointsTable = str_replace('cellpadding="0"', 'cellpadding="2"', $strAssessmentPointsTable);
                        $strAssessmentPointsTable = str_replace("'", '"', $strAssessmentPointsTable);


                        // Generate content once and save in needed formats
                        $strHtmlContent = $this->_pdf->exportProspectDataToHtml(
                            array(
                                'main'       => $arrProspectInfo,
                                'data'       => $arrProspectDetailedData,
                                'job'        => $arrProspectJobData,
                                'job_spouse' => $arrProspectSpouseJobData,
                                'categories' => $arrAssignedProspectCategories,
                                'points'     => $strAssessmentPointsTable
                            ),
                            $this->_clients->getCaseCategories()->getCompanyCaseCategories($this->_auth->getCurrentUserCompanyId())
                        );

                        // Save in pdf file
                        $booSuccess = $this->_pdf->generatePDFFromHtml(
                            'Questionnaire Summary for ' . $arrProspectInfo['fName'] . ' ' . $arrProspectInfo['lName'],
                            $dst . '/Questionnaire Summary.pdf',
                            $strHtmlContent
                        );

                        // Save to html file
                        if ($booSuccess) {
                            $path           = $dst . '/Questionnaire Summary.html';
                            $header         = '<!DOCTYPE HTML><html><head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"><title>' . 'Questionnaire Summary' . '</title></head><body>';
                            $footer         = '</body></html>';
                            $strHtmlContent = $header . $strHtmlContent . $footer;

                            if ($booLocal) {
                                $this->_files->createFTPDirectory(dirname($path));
                                $booSuccess = $this->_files->createFile($path, $strHtmlContent);
                            } else {
                                $booSuccess = $this->_files->getCloud()->createObject($path, $strHtmlContent);
                            }
                        }

                        // Generate case number
                        if ($booSuccess && $this->_clients->getCaseNumber()->isAutomaticTurnedOn($companyId) && $this->_config['site_version']['clients']['generate_case_number_on'] === 'default') {
                            // Find visa abbreviation, use it during case number generation
                            $subclassAbbreviation = '';
                            if (!empty($subclassFieldVal)) {
                                $arrAllOptions = $this->_clients->getCaseCategories()->getCompanyCaseCategories($companyId);
                                foreach ($arrAllOptions as $arrOptionInfo) {
                                    if ($arrOptionInfo['client_category_id'] == $subclassFieldVal) {
                                        $subclassAbbreviation = $arrOptionInfo['client_category_abbreviation'];
                                        break;
                                    }
                                }
                            }

                            $boolIsReserved      = false;
                            $intMaxAttempts      = 20;
                            $intAttempt          = 0;
                            $generatedCaseNumber = false;
                            $startCaseNumberFrom = '';
                            $arrCompanySettings  = $this->_clients->getCaseNumber()->getCompanyCaseNumberSettings($companyId);

                            while (!$boolIsReserved && ($intAttempt < $intMaxAttempts)) {
                                $intAttempt++;
                                list($strError, $generatedCaseNumber, $startCaseNumberFrom, $increment) = $this->_clients->getCaseNumber()->generateNewCaseNumber(
                                    $companyId,
                                    $clientId,
                                    0,
                                    $caseId,
                                    $caseTypeId,
                                    $subclassAbbreviation,
                                    true,
                                    $intAttempt
                                );
                                if (!empty($strError)) {
                                    break;
                                }

                                $booBasedOnCaseType = array_key_exists('cn-global-or-based-on-case-type', $arrCompanySettings) && $arrCompanySettings['cn-global-or-based-on-case-type'] === 'case-type';

                                // do not reserve a case number if it is based on the Immigration Program
                                if (!$booBasedOnCaseType) {
                                    $boolIsReserved = $this->_clients->getCaseNumber()->reserveFileNumber($companyId, $generatedCaseNumber, $increment);
                                } else {
                                    $boolIsReserved = true;
                                }
                            }

                            if (empty($strError)) {
                                if (!$generatedCaseNumber) {
                                    $this->_log->debugErrorToFile(
                                        sprintf('Could not generate new unique file number - reached maximum number of attempts. companyId = %s, applicantId = %s, caseId = %s', $companyId, $clientId, $caseId),
                                        null,
                                        'case_number'
                                    );
                                } else {
                                    $caseReferenceNumberToRelease = $generatedCaseNumber;
                                }

                                $caseNumber = empty($generatedCaseNumber) ? 'please_change' : $generatedCaseNumber;

                                if (!empty($generatedCaseNumber) && !empty($startCaseNumberFrom)) {
                                    $arrCompanySettings['cn-start-number-from-text'] = $startCaseNumberFrom;
                                    $this->_clients->getCaseNumber()->saveCaseNumberSettings($companyId, $arrCompanySettings);
                                }

                                // Update ase number for the already created case
                                $booSuccess = $this->_clients->updateClientInfo($caseId, array('fileNumber' => $caseNumber));
                            } else {
                                $strError   = $this->_tr->translate('Case file number was not generated: ') . $strError;
                                $booSuccess = false;
                            }
                        }

                        // Remove this prospect record from DB + delete files
                        if ($booSuccess) {
                            if (!empty($arrProspectInfo['company_id'])) {
                                $booSuccess = $this->deleteProspects($prospectId);
                            } else {
                                // Mark the prospect as converted
                                $booSuccess = $this->saveConvertedProspectLink($prospectId, $caseId, $companyInvoiceId);
                            }
                        }
                    } else {
                        $booSuccess = false;
                    }

                    // If all is okay - apply changes
                    if ($booSuccess) {
                        $this->_db2->getDriver()->getConnection()->commit();
                    } else {
                        $this->_db2->getDriver()->getConnection()->rollback();

                        // TODO Need to understand why file reference number reservation is not just rolled back
                        if ($caseReferenceNumberToRelease && !empty($companyId)) {
                            // If case file number was reserved, we try to release it
                            $this->_clients->getCaseNumber()->releaseFileNumber($companyId, $caseReferenceNumberToRelease);
                        }
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_db2->getDriver()->getConnection()->rollback();

            // TODO Need to understand why case file number reservation is not just rolled back
            if ($caseReferenceNumberToRelease && !empty($companyId)) {
                // If case file number was reserved, we try to release it
                $this->_clients->getCaseNumber()->releaseFileNumber($companyId, $caseReferenceNumberToRelease);
            }

            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success'                  => $booSuccess,
            'error'                    => $strError,
            'show_welcome_message'     => $booShowWelcomeMessage,
            'applicantEncodedPassword' => !empty($password) ? $this->_encryption->encode($password) : '',
            'case_id'                  => $caseId
        );
    }

    /**
     * Clear resume record for specific job id
     * @param $prospectJobId
     */
    public function clearProspectJobResume($prospectJobId)
    {
        $this->_db2->update('company_prospects_job', ['qf_job_resume' => ''], ['qf_job_id' => (int)$prospectJobId]);
    }

    /**
     * Select Prospect job by Employment number, Prospect ID and Prospect Type
     * @param int $prospectId
     * @param string $prospectType
     * @param int $jobOrder - number of employment section (from 0) for main and spouse employers separately
     * @return array
     */
    public function getProspectJobByProspectIdAndProspectType($prospectId, $prospectType, $jobOrder)
    {
        $select = (new Select())
            ->from('company_prospects_job')
            ->where([
                'qf_job_order'  => (int)$jobOrder,
                'prospect_id'   => (int)$prospectId,
                'prospect_type' => $prospectType
            ]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load job info by its id
     * @param int $prospectId
     * @param int $prospectJobId
     * @return array
     */
    public function getProspectJobById($prospectId, $prospectJobId)
    {
        $select = (new Select())
            ->from('company_prospects_job')
            ->where([
                'prospect_id' => (int)$prospectId,
                'qf_job_id'   => (int)$prospectJobId
            ]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Remove all previously created jobs where qf_job_resume IS NULL
     * If prospect's job doesn't already exist in DB, insert prospect's job data into DB, else update data
     * Save file (resume)
     * @param array $arrUpdateJobData
     * @param int $prospectId
     * @param string $prospectType
     * @param int $companyId
     * @param bool $booDeleteResume
     * @return array $fileFieldsToUpdate - for showing buttons "change", "remove", if file is uploaded
     */
    public function saveProspectJob($arrUpdateJobData, $prospectId, $prospectType = 'main', $companyId = null, $booDeleteResume = false)
    {
        if (!in_array($prospectType, array('main', 'spouse'))) {
            $prospectType = 'main';
        }

        // Remove all previously created jobs (except of the first one)
        $arrSpouseJob = array();
        $arrWhere = array();
        $arrWhere['prospect_id'] = (int)$prospectId;
        $arrWhere['prospect_type'] = $prospectType;
        if (count($arrUpdateJobData) > 0) {
            $arrWhere[] = (new Where())->greaterThan('qf_job_order', 0);
        } else {
            $arrSpouseJob = $this->getProspectJobByProspectIdAndProspectType($prospectId, $prospectType, 0);
        }

        $this->_db2->delete('company_prospects_job', $arrWhere);

        // If no jobs were selected for "spouse" section -
        // delete previously created resume file
        $booForceDelete = false;
        if (!count($arrUpdateJobData) && $prospectType == 'spouse' && is_array($arrSpouseJob) && count($arrSpouseJob) && !empty($arrSpouseJob['qf_job_resume'])) {
            $this->_files->deleteProspectResume($prospectId, $arrSpouseJob['qf_job_id']);
            $booForceDelete = true;
        }

        $fileFieldsToUpdate = array();

        if (count($arrUpdateJobData) > 0) {
            unset($arrUpdateJobData['qf_job_spouse_has_experience']);

            $arrJobData = array();
            foreach ($arrUpdateJobData as $strFieldId => $arrValues) {
                if ($prospectType == 'spouse') {
                    $strFieldId = str_replace('qf_job_spouse_', 'qf_job_', $strFieldId);
                }

                foreach ($arrValues as $valueId => $value) {
                    $arrJobData[$valueId][$strFieldId] = $value;
                }
            }

            if (is_null($companyId)) {
                $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
            } else {
                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
            }
            $clientFolder = $this->getPathToCompanyProspectJobFiles($prospectId, $companyId, $booLocal) . '/';

            foreach ($arrJobData as $arrJobDataInsert) {
                $intCheckOptionId = $prospectType == 'main' ? 175 : 343;
                if (!isset($arrJobDataInsert['qf_job_location']) || $arrJobDataInsert['qf_job_location'] != $intCheckOptionId) {
                    $arrJobDataInsert['qf_job_province'] = null;
                }
                $arrFileInfo = array();
                if (!empty($arrJobDataInsert['qf_job_resume'])) {
                    $arrFileInfo                       = $arrJobDataInsert['qf_job_resume'];
                    $arrJobDataInsert['qf_job_resume'] = $arrFileInfo['name'];
                } else {
                    unset($arrJobDataInsert['qf_job_resume']);
                }

                // Use NULL for date fields if empty
                if (array_key_exists('qf_job_start_date', $arrJobDataInsert) && Settings::isDateEmpty($arrJobDataInsert['qf_job_start_date'])) {
                    $arrJobDataInsert['qf_job_start_date'] = null;
                }

                if (array_key_exists('qf_job_end_date', $arrJobDataInsert) && Settings::isDateEmpty($arrJobDataInsert['qf_job_end_date'])) {
                    $arrJobDataInsert['qf_job_end_date'] = null;
                }

                // Save/Update Job records
                $arrJobDataInsert['prospect_id']   = $prospectId;
                $arrJobDataInsert['prospect_type'] = $prospectType;
                $jobOrder                          = array_key_exists('qf_job_order', $arrJobDataInsert) ? $arrJobDataInsert['qf_job_order'] : null;
                $prospectJob                       = is_null($jobOrder) ? 0 : $this->getProspectJobByProspectIdAndProspectType($prospectId, $prospectType, $jobOrder);
                if (!is_array($prospectJob) || !count($prospectJob)) {
                    $prospectJobId = $this->_db2->insert('company_prospects_job', $arrJobDataInsert);
                } else {
                    $prospectJobId = $prospectJob['qf_job_id'];
                    $this->_db2->update('company_prospects_job', $arrJobDataInsert, ['qf_job_id' => (int)$prospectJobId]);
                }

                // Save resume file to correct location
                $tmpFilePath = isset($arrFileInfo['id']) && isset($_FILES[$arrFileInfo['id']]['tmp_name']) && !empty($_FILES[$arrFileInfo['id']]['tmp_name']) ? $_FILES[$arrFileInfo['id']]['tmp_name'] : '';
                if (!empty($arrJobDataInsert['qf_job_resume']) && !empty($tmpFilePath)) {
                    $uploadFile = $clientFolder . $prospectJobId;
                    if ($booLocal) {
                        $this->_files->createFTPDirectory($clientFolder);
                        move_uploaded_file($tmpFilePath, $uploadFile);
                    } else {
                        $this->_files->createCloudDirectory($clientFolder);
                        $this->_files->getCloud()->uploadFile($tmpFilePath, $uploadFile);
                    }

                    $fileFieldsToUpdate[] = array(
                        'prospect_id'   => $prospectId,
                        'field_id'      => $prospectJobId,
                        'full_field_id' => $arrFileInfo['id'],
                        'filename'      => $arrFileInfo['name']
                    );
                }
            }
        }

        $arrJob = $this->getProspectJobByProspectIdAndProspectType($prospectId, $prospectType, 0);
        if($booDeleteResume && !$booForceDelete && !empty($arrJob)) {
            $this->_files->deleteProspectResume($prospectId, $arrJob['qf_job_id']);
            $this->clearProspectJobResume($arrJob['qf_job_id']);
        }

        return $fileFieldsToUpdate;
    }

    /**
     * Save calculated points for specific prospect
     *
     * @param string $strPoints
     * @param int $prospectId
     */
    public function saveProspectPoints($strPoints, $prospectId)
    {
        $this->_db2->update('company_prospects', ['assessment' => $strPoints], ['prospect_id' => $prospectId]);
    }


    /**
     * Save prospect's categories
     *
     * @param array $arrCategories
     * @param int $prospectId
     * @return bool
     */
    public function saveProspectCategories($arrCategories, $prospectId)
    {
        try {
            $arrWhere = array();
            $arrWhere['prospect_id'] = $prospectId;
            // Update prospect's categories
            if (isset($arrCategories) && !empty($arrCategories)) {
                foreach ($arrCategories as $categoryId) {
                    $query = "INSERT INTO `company_prospects_data_categories` (`prospect_id`, `prospect_category_id`) 
                        VALUES (?, ?) ON DUPLICATE KEY UPDATE prospect_category_id=VALUES(prospect_category_id)";
                    $this->_db2->query($query, [(int)$prospectId, (int)$categoryId]);
                }

                $arrWhere[] = (new Where())->notIn('prospect_category_id', $arrCategories);
            }

            // Remove previously assigned categories
            $this->_db2->delete('company_prospects_data_categories', $arrWhere);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booSuccess = false;
        }

        return $booSuccess;
    }


    /**
     * Create a new Prospect
     *
     * @param $arrInsertData
     * @param bool $booFromMarketplace
     * @return array string with error description,
     * empty if prospect was created successfully
     */
    public function createProspect($arrInsertData, $booFromMarketplace = false)
    {
        $prospectId = 0;
        $strError   = '';

        try {
            // Add additional info and create the prospect
            if (!isset($arrInsertData['prospect']['company_id'])) {
                $arrInsertData['prospect']['company_id'] = $this->_auth->getCurrentUserCompanyId();
            }
            $companyId = $arrInsertData['prospect']['company_id'];

            // Reset company id if prospect was created from MP
            if ($booFromMarketplace) {
                $arrInsertData['prospect']['company_id'] = null;
                unset($arrInsertData['prospect_offices']);
            }

            if (!isset($arrInsertData['prospect']['create_date'])) {
                $arrInsertData['prospect']['create_date'] = date('c');
            }

            $prospectId = $this->_db2->insert('company_prospects', $arrInsertData['prospect']);


            // Save job data
            if (isset($arrInsertData['job'])) {
                $this->saveProspectJob($arrInsertData['job'], $prospectId, 'main', $companyId);
            }

            if (isset($arrInsertData['job_spouse'])) {
                $this->saveProspectJob($arrInsertData['job_spouse'], $prospectId, 'spouse', $companyId);
            }

            // Create prospect's categories - with one query!
            if (isset($arrInsertData['categories'])) {
                $this->saveProspectCategories($arrInsertData['categories'], $prospectId);
            }


            // Save additional data - with one query!
            if (isset($arrInsertData['data'])) {
                $values = array();
                foreach ($arrInsertData['data'] as $fieldId => $fieldVal) {
                    $fieldVal = is_array($fieldVal) ? implode(',', $fieldVal) : $fieldVal;
                    $values['prospect_id'] = (int)$prospectId;
                    $values['q_field_id'] = (int)$fieldId;
                    $values['q_value'] = $fieldVal;
                    if (!empty($values)) {
                        $this->_db2->insert('company_prospects_data', $values);
                    }
                }
            }

            if (isset($arrInsertData['prospect_offices'])) {
                $this->getCompanyProspectOffices()->updateProspectOffices($prospectId, $arrInsertData['prospect_offices']);
            }
        } catch (Exception $e) {
            // Try remove just created prospect and all related info
            if (!empty($prospectId)) {
                $this->deleteProspects($prospectId);
            }

            // Return error message
            $strError = 'Internal Server Error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return array(
            'strError'   => $strError,
            'prospectId' => $prospectId
        );
    }

    /**
     * Check if specific category is assigned to prospect
     *
     * @param int $companyId
     * @param int|array $categoryId
     * @return bool
     */
    public function isCategoryUsedByProspect($companyId, $categoryId)
    {
        $select = (new Select())
            ->from(['p' => 'company_prospects'])
            ->columns(['prospects_count' => new Expression('COUNT(p.prospect_id)')])
            ->join(array('c' => 'company_prospects_data_categories'), 'p.prospect_id = c.prospect_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where([
                'company_id'           => $companyId,
                'prospect_category_id' => $categoryId
            ])
            ->group('p.prospect_id');

        return $this->_db2->fetchOne($select) > 0;
    }

    /**
     * Create/Update prospects settings
     *
     * @param $companyId
     * @param $prospectId
     * @param $arrUpdateData
     */
    public function updateProspectSettings($companyId, $prospectId, $arrUpdateData)
    {
        try {
            if (!empty($prospectId)) {
                $select = (new Select())
                    ->from(['s' => 'company_prospects_settings'])
                    ->where([
                        's.prospect_id' => (int)$prospectId,
                        's.company_id'  => (int)$companyId
                    ]);

                $arrData = $this->_db2->fetchRow($select);

                if (!empty($arrData)) {
                    $this->_db2->update(
                        'company_prospects_settings',
                        $arrUpdateData,
                        [
                            'company_id'  => (int)$companyId,
                            'prospect_id' => (int)$prospectId
                        ]
                    );
                } else {
                    $arrUpdateData['company_id']  = (int)$companyId;
                    $arrUpdateData['prospect_id'] = (int)$prospectId;
                    $this->_db2->insert('company_prospects_settings', $arrUpdateData);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }


    /**
     * Update prospect's info
     *
     * @param array $arrUpdateData
     * @param int $prospectId
     * @param bool $booUpdateOnlyData
     * @return string error description, empty on success
     */
    public function updateProspect($arrUpdateData, $prospectId, $booUpdateOnlyData = false)
    {
        $strError = '';

        try {
            if (!$booUpdateOnlyData) {

                // Update main prospect's data
                if (isset($arrUpdateData['prospect'])) {
                    if (isset($arrUpdateData['prospect']['visa']) && empty($arrUpdateData['prospect']['visa'])) {
                        $arrUpdateData['prospect']['visa'] = null;
                    }

                    $arrUpdateData['prospect']['update_date'] = date('c');
                    $this->_db2->update('company_prospects', $arrUpdateData['prospect'], ['prospect_id' => (int)$prospectId]);
                }

                // Update prospect's categories
                if (isset($arrUpdateData['categories'])) {
                    $this->saveProspectCategories($arrUpdateData['categories'], $prospectId);
                }

                if (isset($arrUpdateData['prospect_offices'])) {
                    $this->getCompanyProspectOffices()->updateProspectOffices($prospectId, $arrUpdateData['prospect_offices']);
                }
            }

            // Update additional data - with one request!
            if (isset($arrUpdateData['data'])) {
                foreach ($arrUpdateData['data'] as $fieldId => $fieldVal) {
                    $qValue = is_array($fieldVal) ? implode(',', $fieldVal) : $fieldVal;
                    $query  = "INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`) 
                        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE q_value=VALUES(q_value)";
                    $this->_db2->query($query, [(int)$prospectId, (int)$fieldId, $qValue]);
                }
            }
        } catch (Exception $e) {
            // Return error message
            $strError = 'Internal Server Error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }


    /**
     * Delete one or several prospects
     *
     * @param $prospects
     * @return bool true on success, otherwise false
     */
    public function deleteProspects($prospects)
    {
        $booSuccess = true; // I'm optimist
        if (!empty($prospects)) {
            try {
                $prospects        = (array) $prospects;
                $arrProspectsInfo = $this->getProspectsInfo($prospects);

                // Clear all these tables,
                // one by one
                $arrTables = array(
                    'company_prospects_notes',
                    'company_prospects_job',
                    'company_prospects_data_categories',
                    'company_prospects_data',
                    'company_prospects'
                );

                foreach ($arrProspectsInfo as $arrProspectInfo) {
                    $prospectId = $arrProspectInfo['prospect_id'];
                    $companyId  = $arrProspectInfo['company_id'];
                    $booLocal   = $this->_company->isCompanyStorageLocationLocal($arrProspectInfo['company_id']);

                    // Delete general folders/files
                    $this->_files->deleteFolder($this->getPathToProspect($prospectId, $companyId, $booLocal), $booLocal);

                    // Delete job resume files
                    $this->_files->deleteFolder($this->getPathToCompanyProspectJobFiles($prospectId, $companyId, $booLocal), $booLocal);

                    // Delete note attachments folder
                    $this->_files->deleteFolder($this->_files->getProspectNoteAttachmentsPath($companyId, $prospectId, $booLocal), $booLocal);

                    // Delete data from DB
                    foreach ($arrTables as $strTable) {
                        $this->_db2->delete($strTable, ['prospect_id' => $prospectId]);
                    }

                    $this->_tasks->deleteMemberTasks($prospectId, true);
                }
            } catch (Exception $e) {
                $booSuccess = false;
            }
        }

        return $booSuccess;
    }


    /**
     * Check if spouse section must be showed
     *
     * @param array $arrProspectData
     * @param mixed $maritalStatusFieldId
     * @return bool true if must be showed
     */
    public function showSpouseSection($arrProspectData, $maritalStatusFieldId)
    {
        $booShow = false;
        if (array_key_exists($maritalStatusFieldId, $arrProspectData)) {
            $booShow = $this->hasProspectSpouse($arrProspectData[$maritalStatusFieldId]);
        }

        return $booShow;
    }

    /**
     * Check if married or common_law option is selected
     *
     * @param $value
     * @return bool true if married or common_law option is selected
     */
    public function hasProspectSpouse($value)
    {
        // married or common_law is selected
        $arrOptionsIds = $this->_config['site_version']['version'] == 'australia' ? array(7, 8, 356) : array(7, 12);

        return in_array($value, $arrOptionsIds);
    }

    /**
     * Check if business fields must be showed
     * @param array $arrProspectData
     * @param mixed $areaOfInterestFieldId
     * @return bool true if must be showed
     */
    public function showBusinessFields($arrProspectData, $areaOfInterestFieldId)
    {
        $booShow = false;
        if (array_key_exists($areaOfInterestFieldId, $arrProspectData)) {
            $arrAreaOfInterestOptions = explode(',', $arrProspectData[$areaOfInterestFieldId] ?? '');
            $booShow                  = $this->hasProspectBusinessFields($arrAreaOfInterestOptions);
        }

        return $booShow;
    }

    /**
     * Check if skilled independent or business visa is selected
     * @param $arrOptionsIds
     * @return bool
     */
    public function hasProspectBusinessFields($arrOptionsIds)
    {
        // Skilled... or Business... checkbox is checked
        return in_array('14', $arrOptionsIds) || in_array('9', $arrOptionsIds);
    }

    /**
     * Mark prospect as read/unread
     *
     * @param int|array $arrProspectIds
     * @param bool $booViewed
     */
    public function toggleProspectViewed($arrProspectIds, $booViewed = true)
    {
        $companyId      = $this->_auth->getCurrentUserCompanyId();
        $arrProspectIds = is_array($arrProspectIds) ? $arrProspectIds : array($arrProspectIds);
        foreach ($arrProspectIds as $prospectId) {
            $this->updateProspectSettings(
                $companyId,
                $prospectId,
                array('viewed' => $booViewed ? 'Y' : 'N')
            );
        }
    }

    /**
     * Export prospect's info to excel
     *
     * @param array $cmArr
     * @param array $data
     * @param string $title
     * @param array $cmArrJobs
     * @param array $dataJobs
     * @return Spreadsheet
     * @throws Exception
     */
    public function exportToExcel($cmArr, $data, $title = null, $cmArrJobs = [], $dataJobs = [])
    {
        // Turn off warnings - issue when generate xls file
        error_reporting(E_ERROR);

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        //title
        $title = (empty($title) ? 'Search Result' : $title);

        $worksheetName = $this->_files::checkPhpExcelFileName($title);
        $worksheetName = empty($worksheetName) ? 'Export Result' : $worksheetName;

        $abc     = array('A');
        $current = 'A';
        while ($current != 'ZZZ') {
            $abc[] = ++$current;
        }

        // Creating an object
        $objPHPExcel = new Spreadsheet();

        // Set properties
        $objPHPExcel->getProperties()->setTitle($worksheetName);
        $objPHPExcel->getProperties()->setSubject($worksheetName);

        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();

        // column sizes
        foreach ($cmArr as $key => $c) {
            $sheet->getColumnDimension($abc[$key])->setWidth(ceil($c['width'] / 6));
        }

        // all cells styles
        $bottom_right_cell = $abc[count($cmArr) - 1] . (count($data) + 2);

        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setName('Arial');
        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setSize(10);
        $sheet->getStyle('A1:' . $bottom_right_cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // header styles
        $sheet->mergeCells("A1:" . $abc[count($cmArr) - 1] . "1"); // colspan

        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $color = new Color();
        $color->setRGB('0000FF');
        $sheet->getStyle('A1')->getFont()->setColor($color);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // headers styles
        $sheet->getStyle('A2:' . $abc[count($cmArr) - 1] . '2')->getFont()->setBold(true);
        $sheet->getStyle('A2:' . $abc[count($cmArr) - 1] . '2')->getFont()->setSize(11);
        $sheet->getStyle('A2:' . $abc[count($cmArr) - 1] . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // output header
        $sheet->setCellValue('A1', $worksheetName);

        // output headers
        foreach ($cmArr as $key => $h) {
            $sheet->setCellValue($abc[$key] . '2', $h['name']);
        }

        // output data
        foreach ($data as $row_key => $row) {
            foreach ($cmArr as $col_key => $cm) {
                $sheet->setCellValueExplicit($abc[$col_key] . ($row_key + 3), $row[$cm['id']], DataType::TYPE_STRING);
            }
        }

        // Rename sheet
        $sheet->setTitle($worksheetName);


        if (count($cmArrJobs) && count($dataJobs)) {
            //Jobs exporting
            $objPHPExcel->createSheet(1); //Setting index when creating
            $objPHPExcel->setActiveSheetIndex(1);
            $sheetJobs = $objPHPExcel->getActiveSheet();
            $sheetJobs->setTitle('Jobs Export');

            foreach ($cmArrJobs as $key => $c) {
                $sheetJobs->getColumnDimension($abc[$key])->setWidth(ceil($c['width'] / 6));
            }

            // all cells styles
            $bottom_right_cell = $abc[count($cmArrJobs) - 1] . (count($dataJobs) + 2);

            $sheetJobs->getStyle('A1:' . $bottom_right_cell)->getFont()->setName('Arial');
            $sheetJobs->getStyle('A1:' . $bottom_right_cell)->getFont()->setSize(10);
            $sheetJobs->getStyle('A1:' . $bottom_right_cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // header styles
            $sheetJobs->mergeCells("A1:" . $abc[count($cmArrJobs) - 1] . "1"); // colspan

            $sheetJobs->getStyle('A1')->getFont()->setSize(16);
            $sheetJobs->getStyle('A1')->getFont()->setBold(true);
            $sheetJobs->getStyle('A1')->getFont()->setColor($color);
            $sheetJobs->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // headers styles
            $sheetJobs->getStyle('A2:' . $abc[count($cmArrJobs) - 1] . '2')->getFont()->setBold(true);
            $sheetJobs->getStyle('A2:' . $abc[count($cmArrJobs) - 1] . '2')->getFont()->setSize(11);
            $sheetJobs->getStyle('A2:' . $abc[count($cmArrJobs) - 1] . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheetJobs->setCellValue('A1', 'Jobs Export');

            // output headers
            foreach ($cmArrJobs as $key => $h) {
                $sheetJobs->setCellValue($abc[$key] . '2', $h['name']);
            }

            // output data
            foreach ($dataJobs as $row_key => $row) {
                foreach ($cmArrJobs as $col_key => $cm) {
                    $sheetJobs->setCellValueExplicit($abc[$col_key] . ($row_key + 3), $row[$cm['id']], DataType::TYPE_STRING);
                }
            }

            $col = 0;
            foreach ($cmArrJobs as $arrColumnInfo) {
                $sheetJobs->getColumnDimension($abc[$col++])->setAutoSize(true);
            }

            //First page should be "Export results"
            $objPHPExcel->setActiveSheetIndex(0);
        }

        return $objPHPExcel;
    }

    /**
     * Load unique 'referred by' options already saved in DB for specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyProspectsReferredBy($companyId)
    {
        $select = (new Select())
            ->from('company_prospects')
            ->columns(['referred_by'])
            ->where([
                'company_id' => (int)$companyId,
                (new Where())->isNotNull('referred_by'),
                (new Where())->notEqualTo('referred_by', '')
            ])
            ->group('referred_by')
            ->order('referred_by');

        return $this->_db2->fetchCol($select);
    }

    public function getCompanyProspectsDidNotArrive($companyId)
    {
        $select = (new Select())
            ->from('company_prospects')
            ->columns(['did_not_arrive'])
            ->where([
                'company_id' => (int)$companyId,
                (new Where())->isNotNull('did_not_arrive'),
                (new Where())->notEqualTo('did_not_arrive', '')
            ])
            ->group('did_not_arrive')
            ->order('did_not_arrive');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load prospects list for specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyProspectsList($companyId)
    {
        $select = (new Select())
            ->from('company_prospects')
            ->where(['company_id' => (int)$companyId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of static fields (some of them are used in QNR)
     *
     * @return array
     */
    public static function getStaticFieldsMapping()
    {
        return array(
            'qf_first_name'            => 'fName',
            'qf_last_name'             => 'lName',
            'qf_email'                 => 'email',
            'qf_age'                   => 'date_of_birth',
            'qf_spouse_age'            => 'spouse_date_of_birth',
            'qf_referred_by'           => 'referred_by',
            'qf_status'                => 'status',
            'qf_did_not_arrive'        => 'did_not_arrive',
            'qf_agent'                 => 'agent_id',
            'qf_seriousness'           => 'seriousness',
            'qf_create_date'           => 'create_date',
            'qf_update_date'           => 'update_date',
            'qf_assessment_notes'      => 'notes',
            'qf_points_skilled_worker' => 'points_skilled_worker',
            'qf_points_express_entry'  => 'points_express_entry',
        );
    }

    /**
     * Load name of the field that is used in DB by QNR name of field
     *
     * @param mixed $staticFieldId
     * @return string
     */
    public static function getStaticFieldNameInDB($staticFieldId)
    {
        $strName = '';

        $arrFields = self::getStaticFieldsMapping();
        if (array_key_exists($staticFieldId, $arrFields)) {
            $strName = $arrFields[$staticFieldId];
        }

        return $strName;
    }

    /**
     * Mark prospect as invited for specific company(ies)
     *
     * @param int $prospectId
     * @param array $arrCompaniesIds
     * @param bool $booClearPreviousInvites true to clear previous invites
     * @return bool true on success
     */
    public function inviteProspect($prospectId, $arrCompaniesIds, $booClearPreviousInvites)
    {
        try {
            if ($booClearPreviousInvites) {
                $this->_db2->delete('company_prospects_invited', ['prospect_id' => (int)$prospectId]);
            }

            if (count($arrCompaniesIds)) {
                foreach ($arrCompaniesIds as $companyId) {
                    $query = "INSERT IGNORE INTO `company_prospects_invited` (`company_id`, `prospect_id`, `invited_on`) 
                        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE invited_on = VALUES(invited_on)";
                    $this->_db2->query($query, [$companyId, $prospectId, date('c')]);
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if prospect was invited by currently logged in user's company
     *
     * @param $prospectId
     * @return bool true if invited
     */
    public function isProspectInvited($prospectId)
    {
        $select = (new Select())
            ->from('company_prospects_invited')
            ->columns(['prospect_id'])
            ->where([
                'company_id'  => $this->_auth->getCurrentUserCompanyId(),
                'prospect_id' => (int)$prospectId
            ]);

        $foundProspectId = $this->_db2->fetchOne($select);

        return $foundProspectId == $prospectId;
    }

    /**
     * Check if prospect was converted by any company
     *
     * @param $prospectId
     * @return bool true if invited
     */
    public function isProspectConverted($prospectId)
    {
        $arrConverted = $this->getProspectConvertedCount($prospectId);
        return isset($arrConverted[$prospectId]) && $arrConverted[$prospectId] > 0;
    }

    /**
     * Return mapper prospectId - conversion count
     *
     * @param $prospectId
     * @return array
     */
    public function getProspectConvertedCount($prospectId)
    {
        $select = (new Select())
            ->from('company_prospects_converted')
            ->columns(['prospect_id', 'res_count' => new Expression('COUNT(*)')])
            ->where(['prospect_id' => $prospectId])
            ->group('prospect_id');

        $arrSavedData = $this->_db2->fetchAll($select);

        $arrMapped = array();
        foreach ($arrSavedData as $arrData) {
            $arrMapped[$arrData['prospect_id']] = $arrData['res_count'];
        }

        return $arrMapped;
    }

    /**
     * Return mapper prospectId - activity count
     *
     * @param $prospectId
     * @param string $activity
     * @return array
     */
    public function getProspectActivitiesCount($prospectId, $activity = 'email')
    {
        $select = (new Select())
            ->from('company_prospects_activities')
            ->columns(['prospect_id', 'res_count' => new Expression('COUNT(DISTINCT company_id)')])
            ->where([
                'activity'    => $activity,
                'prospect_id' => $prospectId
            ])
            ->group('prospect_id');

        $arrSavedData = $this->_db2->fetchAll($select);

        $arrMapped = array();
        foreach ($arrSavedData as $arrData) {
            $arrMapped[$arrData['prospect_id']] = $arrData['res_count'];
        }

        return $arrMapped;
    }

    /**
     * Calculate count of invited prospects for currently logged in user's company
     * @param bool $booActiveOnly
     * @return string
     */
    public function getCompanyInvitedProspectsCount($booActiveOnly = true)
    {
        $select = (new Select())
            ->from(['i' => 'company_prospects_invited'])
            ->columns(['res_count' => new Expression('COUNT(*)')])
            ->where(['i.company_id' => $this->_auth->getCurrentUserCompanyId()]);

        if ($booActiveOnly) {
            $select->join(array('p' => 'company_prospects'), 'p.prospect_id = i.prospect_id', [])
                ->where(['p.status' => 'active']);
        }

        return $this->_db2->fetchOne($select);
    }


    /**
     * Save link between the prospect and converted client + save link to invoice
     *
     * @param int $prospectId
     * @param int $memberId
     * @param int $companyInvoiceId
     * @return bool true on success
     */
    public function saveConvertedProspectLink($prospectId, $memberId, $companyInvoiceId)
    {
        try {
            $query = "INSERT IGNORE INTO `company_prospects_converted` (`prospect_id`, `member_id`, `company_invoice_id`, `converted_on`) 
                        VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE converted_on = VALUES(converted_on)";
            $this->_db2->query($query, [$prospectId, $memberId, $companyInvoiceId, date('c')]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load prospect id by MP prospect id
     *
     * @param $mpProspectId
     * @return string
     */
    public function getProspectIdByMPProspectId($mpProspectId)
    {
        $prospectId = 0;

        if (!empty($mpProspectId) && is_numeric($mpProspectId)) {
            $select = (new Select())
                ->from('company_prospects')
                ->columns(['prospect_id'])
                ->where(['mp_prospect_id' => (int)$mpProspectId]);

            $prospectId = $this->_db2->fetchOne($select);
        }

        return $prospectId;
    }

    /**
     * add prospects activity
     *
     * @param $companyId
     * @param $prospectId
     * @param $memberId
     * @param $activity
     */
    public function addProspectActivity($companyId, $prospectId, $memberId, $activity)
    {
        try {
            $arrInsertData = array(
                'company_id'  => $companyId,
                'prospect_id' => $prospectId,
                'member_id'   => $memberId,
                'activity'    => $activity,
                'date'        => date('c')
            );

            $this->_db2->insert('company_prospects_activities', $arrInsertData);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function getNoteAttachments($noteId)
    {
        $select = (new Select())
            ->from('company_prospect_notes_attachments')
            ->where(['note_id' => (int)$noteId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of recently viewed prospects
     *
     * @param bool $booMarketplace if true - prospects from the Marketplace will be loaded only, otherwise for the current company
     * @return array
     */
    public function getRecentlyOpenedProspects($booMarketplace)
    {
        $arrWhere                = [];
        $arrWhere['a.member_id'] = $this->_auth->getCurrentUserId();

        if ($booMarketplace) {
            $arrWhere['company_id'] = null;
        } else {
            $arrWhere['company_id'] = $this->_auth->getCurrentUserCompanyId();
        }

        $select = (new Select())
            ->from(['a' => 'company_prospects_last_access'])
            ->columns(['prospect_id'])
            ->join(array('p' => 'company_prospects'), 'a.prospect_id = p.prospect_id', [], Select::JOIN_LEFT_OUTER)
            ->where($arrWhere)
            ->order('a.access_date DESC');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Save the prospects as recently viewed
     *
     * @param int $prospectId
     * @return bool true on success, false otherwise
     */
    public function saveRecentlyOpenedProspect($prospectId)
    {
        try {
            $query = "INSERT IGNORE INTO `company_prospects_last_access` (`member_id`, `prospect_id`, `access_date`) 
                        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE access_date = VALUES(access_date)";
            $this->_db2->query($query, [$this->_auth->getCurrentUserId(), $prospectId, date('c')]);
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Get replacements for template processing
     * @param array|int $data
     * @param string $templateType
     * @return array
     */
    public function getTemplateReplacements($data, $templateType = 'default')
    {
        if ($templateType === self::TEMPLATE_PROSPECT_CONVERSION) {
            $rate         = $this->_settings->variable_get('price_marketplace_prospect_convert');
            $replacements = [
                '{company prospect: conversion rate}'     => Clients\Accounting::formatPrice($rate),
                '{company prospect: conversion quantity}' => $data['prospect_count'],
            ];
        } else {
            $data                 = (is_numeric($data)) ? $this->getProspectsInfo((int)$data) : $data;
            $prospectInfoDetailed = $this->getProspectDetailedData($data['prospect_id']);

            $currentMemberId      = $this->_auth->getCurrentUserId();
            $arrCurrentMemberInfo = $this->_clients->getMemberInfo($currentMemberId, true);
            $arrDefAccount        = MailAccount::getDefaultAccount($currentMemberId);
            $signature            = $arrDefAccount['signature'] ?? '';

            // Prospect info
            $replacements = [
                '{salutation}'  => $prospectInfoDetailed['qf_salutation'] ?? '',
                '{fName}'       => $data['fName'] ?? '',
                '{lName}'       => $data['lName'] ?? '',
                '{email}'       => $data['email'] ?? '',
                '{company}'     => $data['companyName'] ?? '',
                '{company ABN}' => $data['company_abn'] ?? '',
                '{company_abn}' => $data['company_abn'] ?? '',
            ];

            // Current user info
            if ($arrCurrentMemberInfo) {
                $replacements['{current_user_fName}']           = $arrCurrentMemberInfo['fName'];
                $replacements['{current_user_lName}']           = $arrCurrentMemberInfo['lName'];
                $replacements['{current_user_email}']           = $arrCurrentMemberInfo['emailAddress'];
                $replacements['{current_user_username}']        = $arrCurrentMemberInfo['username'];
                $replacements['{current_user_email_signature}'] = $signature;
            }

            // Date & Time
            // Use another date format for date fields in the template
            $dateFormatFull     = $this->_settings->variableGet('dateFormatFull');
            $dateFormatExtended = $this->_settings->variableGet('dateFormatFullExtended', $dateFormatFull);

            $replacements['{time}']           = date('H:i');
            $replacements['{today_date}']     = $this->_settings->formatDate(date('Y-m-d'), true, $dateFormatExtended);
            $replacements['{today_date_day}'] = date('l');
            $replacements['{today_datetime}'] = $this->_settings->formatDate(date('Y-m-d'), true, $dateFormatExtended) . date(' H:i');
        }

        return $replacements;
    }

    /**
     * Provides list of fields available for system templates.
     * @param EventInterface $e
     * @return array
     */
    public function getSystemTemplateFields(EventInterface $e)
    {
        $templateType = $e->getParam('templateType');
        if ($templateType == 'mass_email') {
            return [];
        }

        // Company prospects
        $arrCompanyProspectsFields = array(
            array('name' => 'company prospect: conversion rate', 'label' => 'Prospect to client conversion rate'),
            array('name' => 'company prospect: conversion quantity', 'label' => 'Prospects count'),
        );

        foreach ($arrCompanyProspectsFields as &$field) {
            $field['n']     = 7;
            $field['group'] = 'Company Prospect details';
        }

        return $arrCompanyProspectsFields;
    }

}
