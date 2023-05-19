<?php

namespace Homepage\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\View\Model\JsonModel;
use Links\Service\Links;
use News\Service\News;
use Notes\Service\Notes;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Service\Users;
use Prospects\Service\CompanyProspects;
use Rss\Service\Rss;
use Tasks\Service\Tasks;

/**
 * Home page Index Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var Notes */
    protected $_notes;

    /** @var Clients */
    protected $_clients;

    /** @var Users */
    protected $_users;

    /** @var Company */
    protected $_company;

    /** @var Links */
    protected $_links;

    /** @var News */
    protected $_news;

    /** @var Rss */
    protected $_rss;

    /** @var Tasks */
    protected $_tasks;

    /** @var CompanyProspects */
    protected $_companyProspects;

    public function initAdditionalServices(array $services)
    {
        $this->_clients          = $services[Clients::class];
        $this->_users            = $services[Users::class];
        $this->_notes            = $services[Notes::class];
        $this->_company          = $services[Company::class];
        $this->_links            = $services[Links::class];
        $this->_news             = $services[News::class];
        $this->_rss              = $services[Rss::class];
        $this->_tasks            = $services[Tasks::class];
        $this->_companyProspects = $services[CompanyProspects::class];
    }

    public function getDashboardBlockItemsAction()
    {
        // Close session for writing - so next requests can be done
        session_write_close();

        $strError         = '';
        $booIsHtml        = false;
        $booShowRightTbar = false;
        $booCheckedToggle = false;
        $booShowMore      = false;
        $arrItems         = array();

        try {
            $blockId   = $this->params()->fromPost('block_id', '');
            $companyId = $this->_auth->getCurrentUserCompanyId();

            switch ($blockId) {
                case 'prospects':
                case 'marketplace':
                    if ($this->_acl->isAllowed('prospects-view')) {
                        $panelType       = $blockId;
                        $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();


                        // Waiting for assessment
                        $arrItems[] = array(
                            'id'        => 'waiting-for-assessment',
                            'when'      => '',
                            'number'    => $this->_companyProspects->getProspectsCount($panelType, $companyId, $divisionGroupId, array('type' => 'waiting-for-assessment', 'viewed' => 'N')),
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translate('Waiting for assessment (unread)')
                        );


                        // Qualified prospects
                        $qualifiedProspectsCount = $this->_companyProspects->getProspectsCount($panelType, $companyId, $divisionGroupId, array('type' => 'qualified-prospects', 'viewed' => 'N'));

                        $arrItems[] = array(
                            'id'        => 'qualified-prospects',
                            'when'      => '',
                            'number'    => $qualifiedProspectsCount,
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translatePlural('Qualified prospect (unread)', 'Qualified prospects (unread)', $qualifiedProspectsCount)
                        );


                        // Unqualified prospects
                        $UnqualifiedProspectsCount = $this->_companyProspects->getProspectsCount($panelType, $companyId, $divisionGroupId, array('type' => 'unqualified-prospects', 'viewed' => 'N'));

                        $arrItems[] = array(
                            'id'        => 'unqualified-prospects',
                            'when'      => '',
                            'number'    => $UnqualifiedProspectsCount,
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translatePlural('Unqualified prospect (unread)', 'Unqualified prospects (unread)', $UnqualifiedProspectsCount)
                        );


                        // New Prospects
                        $todayNewProspectsCount = $this->_companyProspects->getProspectsCount($panelType, $companyId, $divisionGroupId, array('viewed' => 'N'));

                        $arrItems[] = array(
                            'id'        => 'all-prospects',
                            'when'      => '',
                            'number'    => $todayNewProspectsCount,
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translatePlural('New Prospect', 'All New Prospects', $todayNewProspectsCount)
                        );
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                    break;

                case 'clients':
                    if ($this->_acl->isAllowed('clients-view')) {
                        /** @var array $arrIdsNow */
                        list($arrIdsNow,) = $this->_clients->getTodayNewClientsCount();
                        $countNow = count($arrIdsNow);

                        $arrItems[] = array(
                            'id'        => 'today_clients',
                            'when'      => $this->_tr->translate('Today'),
                            'number'    => $countNow,
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translatePlural('New Client', 'New Clients', $countNow)
                        );

                        if ($this->_acl->isAllowed('clients-tasks-view')) {
                            // Getting member tasks
                            $arrMemberTasks = $this->_tasks->getTasksForMember([
                                'companyId'             => $companyId,
                                'memberId'              => $this->_auth->getCurrentUserId(),
                                'memberOffices'         => $this->_members->getMembersDivisions([$this->_auth->getCurrentUserId()]),
                                'booLoadAccessToMember' => true,
                            ], false);


                            $countPayments = count($arrMemberTasks['tasks_payment_due']);

                            $arrItems[] = array(
                                'id'        => 'clients_have_payments_due',
                                'when'      => '',
                                'number'    => $countPayments,
                                'direction' => 'unknown',
                                'what'      => $this->_tr->translatePlural('Client has payment due', 'Clients have payments due', $countPayments)
                            );


                            $countUploadedDocs = count($arrMemberTasks['tasks_uploaded_docs']);

                            $arrItems[] = array(
                                'id'        => 'clients_uploaded_documents',
                                'when'      => '',
                                'number'    => $countUploadedDocs,
                                'direction' => 'unknown',
                                'what'      => $this->_tr->translatePlural('Client uploaded document', 'Clients uploaded documents', $countUploadedDocs)
                            );


                            $countCompletedForms = count($arrMemberTasks['tasks_completed_form']);

                            $arrItems[] = array(
                                'id'        => 'clients_completed_forms',
                                'when'      => '',
                                'number'    => $countCompletedForms,
                                'direction' => 'unknown',
                                'what'      => $this->_tr->translatePlural('Client completed form', 'Clients completed forms', $countCompletedForms)
                            );
                        }

                        // Search for Categories field by type - can be named in other way
                        $categoriesFieldId     = 0;
                        $categoriesFieldTypeId = $this->_clients->getFieldTypes()->getFieldTypeId('categories');
                        $arrAllFields          = $this->_clients->getFields()->getCompanyFields($companyId);
                        foreach ($arrAllFields as $arrCompanyFieldInfo) {
                            if ($arrCompanyFieldInfo['type'] == $categoriesFieldTypeId) {
                                $categoriesFieldId = $arrCompanyFieldInfo['field_id'];
                                break;
                            }
                        }

                        if (!empty($categoriesFieldId)) {
                            // 1- Number of registrations, by category (weekly & calendar year to date)
                            // 2- Number of applications, by category (weekly & calendar year to date) Number of decisions (of that week to date)
                            //  2.1 Also cumulative, calendar year to date Number of nominations (of that week to date)
                            //  2.2. Also cumulative, calendar year to date
                            // 3- We’d love the ability to show progress towards our annual targets but that’s something that would require more development I expect

                            list($arrRecordsThisYear,) = $this->_clients->getTodayNewClientsCount('calendar_year', $categoriesFieldId);

                            $arrCompanyCaseTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId);
                            $arrCompanyCategories    = $this->_clients->getCaseCategories()->getCompanyCaseCategories($companyId);

                            // TODO: fix here
                            $arrFilters = array(
                                array(
                                    'case_template_name' => 'Skills Immigration Registration',
                                    'categories'         => array(
                                        'entry-level::registration'           => 'Entry-Level and Semi-Skilled Worker (including Northeast)',
                                        'express-intl-grad::registration'     => 'Express Entry – International Graduate',
                                        'express-intl-postgrad::registration' => 'Express Entry – International Post-Graduate',
                                        'express-skilled::registration'       => 'Express Entry – Skilled Worker',
                                        'health-care::registration'           => 'Health Care Professional',
                                        'intl-grad::registration'             => 'International Graduate',
                                        'intl-postgrad::registration'         => 'International Post-Graduate',
                                        'northeast::registration'             => 'Northeast Pilot',
                                        'skilled::registration'               => 'Skilled Worker',
                                        'express-health-care::registration'   => 'Express Entry – Health Care Professional'
                                    )
                                ),

                                array(
                                    'case_template_name' => 'Business Immigration Registration',
                                    'categories'         => array(
                                        'basic::registration'          => 'Base Category',
                                        'regional-pilot::registration' => 'Regional Pilot'
                                    )
                                ),

                                array(
                                    'case_template_name' => 'Skills Immigration Application',
                                    'categories'         => array(
                                        'entry-level::application'           => 'Entry-Level and Semi-Skilled Worker (including Northeast)',
                                        'express-intl-grad::application'     => 'Express Entry – International Graduate',
                                        'express-intl-postgrad::application' => 'Express Entry – International Post-Graduate',
                                        'express-skilled::application'       => 'Express Entry – Skilled Worker',
                                        'health-care::application'           => 'Health Care Professional',
                                        'intl-grad::application'             => 'International Graduate',
                                        'intl-postgrad::application'         => 'International Post-Graduate',
                                        'northeast::application'             => 'Northeast Pilot',
                                        'skilled::application'               => 'Skilled Worker',
                                        'express-health-care::application'   => 'Express Entry – Health Care Professional'
                                    )
                                ),

                                array(
                                    'case_template_name' => 'Business Immigration Application',
                                    'categories'         => array(
                                        'basic::application'          => 'Base Category',
                                        'regional-pilot::application' => 'Regional Pilot'
                                    )
                                ),
                            );


                            $arrGroupedWeeklyByCategory = array();
                            $arrGroupedYearlyByCategory = array();
                            $arrGroupedWeeklyByCaseTypeAndCategory = array();
                            $arrGroupedYearlyByCaseTypeAndCategory = array();
                            foreach ($arrRecordsThisYear as $arrRecordInfo) {
                                $caseTypeId = $arrRecordInfo['client_type_id'];
                                $categoryId = empty($arrRecordInfo['value']) ? 0 : (int)$arrRecordInfo['value'];

                                if (!isset($arrGroupedYearlyByCaseTypeAndCategory[$caseTypeId][$categoryId])) {
                                    $arrGroupedYearlyByCaseTypeAndCategory[$caseTypeId][$categoryId] = 1;
                                } else {
                                    $arrGroupedYearlyByCaseTypeAndCategory[$caseTypeId][$categoryId] += 1;
                                }

                                if (!isset($arrGroupedYearlyByCategory[$categoryId])) {
                                    $arrGroupedYearlyByCategory[$categoryId] = 1;
                                } else {
                                    $arrGroupedYearlyByCategory[$categoryId] += 1;
                                }

                                if($arrRecordInfo['regTime'] >= strtotime('monday this week') && $arrRecordInfo['regTime'] <= time()) {
                                    if (!isset($arrGroupedWeeklyByCaseTypeAndCategory[$caseTypeId][$categoryId])) {
                                        $arrGroupedWeeklyByCaseTypeAndCategory[$caseTypeId][$categoryId] = 1;
                                    } else {
                                        $arrGroupedWeeklyByCaseTypeAndCategory[$caseTypeId][$categoryId] += 1;
                                    }

                                    if (!isset($arrGroupedWeeklyByCategory[$categoryId])) {
                                        $arrGroupedWeeklyByCategory[$categoryId] = 1;
                                    } else {
                                        $arrGroupedWeeklyByCategory[$categoryId] += 1;
                                    }
                                }
                            }

//                            foreach ($arrCompanyCategories as $arrCompanyCategoryInfo) {
//                                $arrItems[] = array(
//                                    'when'      => $this->_tr->translate('Calendar Year'),
//                                    'number'    => isset($arrGroupedYearlyByCategory[$arrCompanyCategoryInfo['option_id']]) ? $arrGroupedYearlyByCategory[$arrCompanyCategoryInfo['option_id']] : 0,
//                                    'direction' => 'unknown',
//                                    'what'      => $this->_tr->translate('Registrations in ') . $arrCompanyCategoryInfo['option_name']
//                                );
//
//                                $arrItems[] = array(
//                                    'when'      => $this->_tr->translate('Weekly'),
//                                    'number'    => isset($arrGroupedWeeklyByCategory[$arrCompanyCategoryInfo['option_id']]) ? $arrGroupedWeeklyByCategory[$arrCompanyCategoryInfo['option_id']] : 0,
//                                    'direction' => 'unknown',
//                                    'what'      => $this->_tr->translate('Registrations in ') . $arrCompanyCategoryInfo['option_name']
//                                );
//                            }

//                            if (isset($arrGroupedYearlyByCategory[0])) {
//                                $arrItems[] = array(
//                                    'when'      => $this->_tr->translate('Calendar Year'),
//                                    'number'    => $arrGroupedYearlyByCategory[0],
//                                    'direction' => 'unknown',
//                                    'what'      => $this->_tr->translate('Registrations without category')
//                                );
//                            }

//                            if (isset($arrGroupedWeeklyByCategory[0])) {
//                                $arrItems[] = array(
//                                    'when'      => $this->_tr->translate('Weekly'),
//                                    'number'    => $arrGroupedWeeklyByCategory[0],
//                                    'direction' => 'unknown',
//                                    'what'      => $this->_tr->translate('Registrations without category')
//                                );
//                            }

                            foreach ($arrCompanyCaseTemplates as $arrCompanyCaseTemplateInfo) {
                                $caseTypeId = $arrCompanyCaseTemplateInfo['case_template_id'];

                                $booShow = false;
                                $arrCategoriesInThisCaseTemplate = array();
                                foreach ($arrFilters as $arrFilter) {
                                    if ($arrFilter['case_template_name'] == $arrCompanyCaseTemplateInfo['case_template_name']) {
                                        $booShow = true;
                                        $arrCategoriesInThisCaseTemplate = $arrFilter['categories'];
                                        break;
                                    }
                                }

                                if (!$booShow) {
                                    continue;
                                }

                                foreach ($arrCompanyCategories as $arrCompanyCategoryInfo) {
                                    if (!in_array($arrCompanyCategoryInfo['client_category_name'], array_keys($arrCategoriesInThisCaseTemplate))) {
                                        continue;
                                    }

                                    $arrItems[] = array(
                                        'when'      => $this->_tr->translate('Calendar Year'),
                                        'number'    => $arrGroupedYearlyByCaseTypeAndCategory[$caseTypeId][$arrCompanyCategoryInfo['client_category_id']] ?? 0,
                                        'direction' => 'unknown',
                                        'what'      => $arrCompanyCaseTemplateInfo['case_template_name'] . '<br/>' . $arrCategoriesInThisCaseTemplate[$arrCompanyCategoryInfo['client_category_name']]
                                    );

//                                    $arrItems[] = array(
//                                        'when'      => $this->_tr->translate('Weekly'),
//                                        'number'    => isset($arrGroupedWeeklyByCaseTypeAndCategory[$caseTypeId][$arrCompanyCategoryInfo['option_id']]) ? $arrGroupedWeeklyByCaseTypeAndCategory[$caseTypeId][$arrCompanyCategoryInfo['option_id']] : 0,
//                                        'direction' => 'unknown',
//                                        'what'      => $arrCompanyCaseTemplateInfo['case_template_name'] . ': ' . $arrCompanyCategoryInfo['option_name']
//                                    );
                                }

                                if (isset($arrGroupedYearlyByCaseTypeAndCategory[$caseTypeId][0])) {
                                    $arrItems[] = array(
                                        'when'      => $this->_tr->translate('Calendar Year'),
                                        'number'    => $arrGroupedYearlyByCaseTypeAndCategory[$caseTypeId][0],
                                        'direction' => 'unknown',
                                        'what'      => $arrCompanyCaseTemplateInfo['case_template_name'] . ': ' . $this->_tr->translate('category not set')
                                    );
                                }

                                if (isset($arrGroupedWeeklyByCaseTypeAndCategory[$caseTypeId][0])) {
//                                    $arrItems[] = array(
//                                        'when'      => $this->_tr->translate('Weekly'),
//                                        'number'    => $arrGroupedWeeklyByCaseTypeAndCategory[$caseTypeId][0],
//                                        'direction' => 'unknown',
//                                        'what'      => $arrCompanyCaseTemplateInfo['case_template_name'] . ': ' . $this->_tr->translate('category not set')
//                                    );
                                }
                            }
                        }
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                    break;

                case 'announcements':
                    if ($this->_acl->isAllowed('news-view')) {
                        $booIsHtml = true;
                        list($arrItems, $booShowMore) = $this->_news->getNewsHTML($this->params()->fromPost('start', 0));

                        $arrUnread        = $this->_news->getCurrentMemberUnreadNewsCount();
                        $booShowRightTbar = !empty($arrUnread);

                        $booCheckedToggle = $this->_clients->areDailyNotificationsEnabledToMember();
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                    break;

                case 'rss':
                    if ($this->_acl->isAllowed('rss-view')) {
                        $booIsHtml = true;
                        $arrItems  = $this->_rss->generateHtml();
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                    break;

                case 'tasks':
                    if ($this->_acl->isAllowed('clients-tasks-view')) {
                        $booCheckedToggle = $this->_clients->areDailyNotificationsEnabledToMember();

                        list($countDueToday, $countDueTomorrow, $countDueIn7Days) = $this->_tasks->getTasksCountForHomepage();

                        $arrItems[] = array(
                            'id'        => 'due_today',
                            'when'      => '',
                            'number'    => $countDueToday,
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translate('Due as of Today')
                        );

                        $arrItems[] = array(
                            'id'        => 'due_tomorrow',
                            'when'      => '',
                            'number'    => $countDueTomorrow,
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translate('Due Tomorrow')
                        );

                        $arrItems[] = array(
                            'id'        => 'due_next_7_days',
                            'when'      => '',
                            'number'    => $countDueIn7Days,
                            'direction' => 'unknown',
                            'what'      => $this->_tr->translate('Due over the next 7 days')
                        );
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                    break;

                case 'lms':
                    list($strError, $arrItems) = $this->_rss->getLMSNews($this->_users->isLmsEnabled(true));
                    break;

                case 'client_accounting':
                    if ($this->_acl->isAllowed('trust-account-view')) {
                        $arrCompanyTA              = $this->_clients->getAccounting()->getCompanyTA($companyId);
                        $arrCompanyTAIdsWithAccess = $this->_clients->getAccounting()->getCompanyTAIdsWithAccess();
                        if (empty($arrCompanyTA)) {
                            $strError = sprintf(
                                $this->_tr->translate('There are no %ss created.'),
                                $this->_company->getCurrentCompanyDefaultLabel('trust_account')
                            );
                        } elseif (empty($arrCompanyTAIdsWithAccess)) {
                            $strError = sprintf(
                                $this->_tr->translate('There is no access to %ss.'),
                                $this->_company->getCurrentCompanyDefaultLabel('trust_account')
                            );
                        } else {
                            foreach ($arrCompanyTA as $arrTAInfo) {
                                if (in_array($arrTAInfo['company_ta_id'], $arrCompanyTAIdsWithAccess)) {
                                    $lastReconciled = $this->_clients->getAccounting()->getLastReconcileDate($arrTAInfo['company_ta_id'], true);
                                    if (Settings::isDateEmpty($lastReconciled)) {
                                        $lastReconciled = $this->_tr->translate('Not Reconciled');
                                    } else {
                                        $lastReconciled = '<span class="number_small">' . $this->_settings->formatDate($lastReconciled) . '</span>' . ' ' . $this->_tr->translate('Last Reconciled');
                                    }

                                    $arrUnverifiedRecords = $this->_clients->getAccounting()->getTrustAccount()->getUnverifiedTransactions($arrTAInfo['company_ta_id']);
                                    $unverifiedRecordsCount = count($arrUnverifiedRecords);

                                    $details = sprintf(
                                        '<div><span>%s</span><br><span><span class="number_small">%d</span> %s</span></div>',
                                        $lastReconciled,
                                        $unverifiedRecordsCount,
                                        $this->_tr->translatePlural('Payment not assigned', 'Payments not assigned', $unverifiedRecordsCount)
                                    );

                                    $arrItems[] = array(
                                        'id'           => $arrTAInfo['company_ta_id'],
                                        'when'         => '',
                                        'number'       => '',
                                        'direction'    => 'hidden',
                                        'what'         => $arrTAInfo['name'] . ' (' . $arrTAInfo['currencyLabel'] . ')',
                                        'what_cls'     => 'blue',
                                        'what_details' => $details
                                    );
                                }
                            }
                        }
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                    break;

                default:
                    $strError = $this->_tr->translate('Unsupported yet...');
                    break;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'         => empty($strError),
            'msg'             => $strError,
            'is_html'         => $booIsHtml,
            'show_right_tbar' => $booShowRightTbar,
            'checked_toggle'  => $booCheckedToggle,
            'show_more'       => $booShowMore,
            'block_items'     => $arrItems
        );

        return new JsonModel($arrResult);
    }
}
