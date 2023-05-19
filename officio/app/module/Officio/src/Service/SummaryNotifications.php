<?php

namespace Officio\Service;

use Clients\Service\Members;
use Exception;
use News\Service\News;
use Officio\Common\Service\BaseService;
use Officio\Templates\Model\SystemTemplate;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;
use Officio\Templates\SystemTemplates;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SummaryNotifications extends BaseService
{
    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var Members */
    protected $_members;

    /** @var Company */
    protected $_company;

    /** @var Tasks */
    protected $_tasks;

    /** @var News */
    protected $_news;

    /** @var CompanyProspects */
    protected $_companyProspects;

    public function initAdditionalServices(array $services)
    {
        $this->_systemTemplates  = $services[SystemTemplates::class];
        $this->_members          = $services[Members::class];
        $this->_company          = $services[Company::class];
        $this->_tasks            = $services[Tasks::class];
        $this->_news             = $services[News::class];
        $this->_companyProspects = $services[CompanyProspects::class];
    }

    /**
     * Retrieve template replacements
     * @param array $memberTasks
     * @param int $newProspectsCount
     * @param array $announcements
     * @param string $studioNews
     * @return string[]
     */
    public function getTemplateReplacements($memberTasks = [], $newProspectsCount = 0, $announcements = [], $studioNews = '')
    {
        // Prepare Announcements created yesterday
        $strAnnouncements = '';
        if (!empty($announcements)) {
            foreach ($announcements as $arrAnnouncementInfo) {
                $strAnnouncements .= $arrAnnouncementInfo['title'] . '<br>';
                $strAnnouncements .= $arrAnnouncementInfo['content'] . '<br><br>';
            }
        }

        // Generate "today at a glance" info for each user
        $strToday      = '';
        $todayTemplate = '<td><table class="today_news_item"><tr><td class="today_news_title">%s</td></tr><tr><td class="today_news_count">%d</td></tr></table></td><td class="today_news_item_spacer">&nbsp;</td>';

        $strToday .= sprintf($todayTemplate, $this->_tr->translate('New prospects'), $newProspectsCount);

        $newTasks = count($memberTasks['tasks_other']);
        $strToday .= sprintf($todayTemplate, $this->_tr->translate('New tasks'), $newTasks);

        $clientsPaymentsDue = count($memberTasks['tasks_payment_due']);
        $strToday           .= sprintf($todayTemplate, $this->_tr->translate('Payments due'), $clientsPaymentsDue);

        $clientsUploadedDocs = count($memberTasks['tasks_uploaded_docs']);
        $strToday            .= sprintf($todayTemplate, $this->_tr->translate('Uploaded documents'), $clientsUploadedDocs);

        $clientsCompletedForms = count($memberTasks['tasks_completed_form']);
        $strToday              .= sprintf($todayTemplate, $this->_tr->translate('Completed forms'), $clientsCompletedForms);

        return [
            '{studio_news}'               => $studioNews,
            '{studio_news_block_style}'   => empty($strStudioNews) ? 'display: none;' : '',
            '{announcements}'             => $strAnnouncements,
            '{announcements_block_style}' => $strAnnouncements === '' ? 'display: none;' : '',
            '{today_news}'                => '<table cellpadding="0" cellspacing="0" style="border-collapse: collapse;"><tr>' . $strToday . '</tr></table>'
        ];
    }

    /**
     * Send notifications to all active users (that allowed this option in their profile) from all active companies
     */
    public function sendNotificationsToUsers()
    {
        try {
            $arrMembers = $this->_members->getUsersForMailingList();

            // Prepare Studio News created yesterday
            // Temporary disable, don't show anything
            // $arrStudioNews = $this->_news->getLatestBannerMessage(true);
            // $studioNews = isset($arrStudioNews['content']) ? str_replace(array('color: rgb(255, 255, 255);', 'color: rgb(255, 255, 255)', 'color="#ffffff"'), '', $arrStudioNews['content']) : '';
            $studioNews = '';

            // Prepare Announcements created yesterday
            $announcements = $this->_news->getYesterdayNews();

            // Preload information to do fewer queries to the DB
            $arrAllMembersIds       = array_map(function ($n) {
                return $n['member_id'];
            }, $arrMembers);
            $arrAllCompaniesIds     = array_unique(array_map(function ($n) {
                return $n['company_id'];
            }, $arrMembers));
            $arrAllCompaniesOffices = $this->_company->getCompaniesDivisionsIds($arrAllCompaniesIds);

            // Combining all the offices
            $arrAllMembersOfficesGrouped = array();
            $arrAllMembersOffices        = $this->_members->getMembersDivisions($arrAllMembersIds);
            foreach ($arrAllMembersOffices as $arrAllMembersOfficeInfo) {
                $arrAllMembersOfficesGrouped[$arrAllMembersOfficeInfo['member_id']][] = $arrAllMembersOfficeInfo['division_id'];
            }

            if (!empty($arrMembers)) {
                $template = SystemTemplate::loadOne(['title' => 'Officio Daily Notifications']);
                if (!$template) {
                    throw new Exception('Officio Daily Notifications system template is not created.');
                }

                $emailsSent     = 0;
                $arrSendingStat = array();
                foreach ($arrMembers as $arrMemberInfo) {
                    $arrMemberOffices = $arrAllMembersOfficesGrouped[$arrMemberInfo['member_id']] ?? $arrAllCompaniesOffices[$arrMemberInfo['company_id']] ?? array();

                    // Calculating new prospects count
                    $newProspectsCount = $this->_companyProspects->getNewProspectsCount($arrMemberInfo['company_id'], $arrMemberOffices);

                    // Getting member tasks
                    $arrExtraFilterParams = array(
                        'companyId'             => $arrMemberInfo['company_id'],
                        'memberId'              => $arrMemberInfo['member_id'],
                        'memberOffices'         => $arrMemberOffices,
                        'booLoadAccessToMember' => true,
                    );
                    $arrMemberTasks       = $this->_tasks->getTasksForMember($arrExtraFilterParams, false);

                    // Replace main user's info in the template
                    $replacements      = $this->_members->getTemplateReplacements($arrMemberInfo);
                    $replacements      += $this->_systemTemplates->getGlobalTemplateReplacements();
                    $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements);

                    $additionalReplacements      = $this->getTemplateReplacements($arrMemberTasks, $newProspectsCount, $announcements, $studioNews);
                    $processedTemplate->template = $this->_systemTemplates->processText($processedTemplate->template, $additionalReplacements, false);

                    // Make sure that there are some changes
                    // If there are no Announcements and all numbers are empty - don't send the email
                    if (!empty($announcements) || !empty($studioNews) || !empty($newProspectsCount) || !empty($arrMemberTasks['tasks_other']) || !empty($arrMemberTasks['tasks_payment_due']) || !empty($arrMemberTasks['tasks_uploaded_docs']) || !empty($arrMemberTasks['tasks_completed_form'])) {
                        $this->_systemTemplates->sendTemplate($processedTemplate, [], 'mass_mail');

                        $now                  = date('Y-m-d H:i:s');
                        $arrSendingStat[$now] = isset($arrSendingStat[$now]) ? $arrSendingStat[$now] + 1 : 1;
                        $emailsSent++;
                    }
                }

                $strOutput  = sprintf(
                    $this->_tr->translatePlural('%d email was sent.', '%d emails were sent.', $emailsSent),
                    $emailsSent
                );

                $sendingRate = empty($arrSendingStat) ? 0 : max($arrSendingStat);
                $strOutput   .= sprintf(
                    $this->_tr->translatePlural(' Max sending rate: %d email per sec.', ' Max sending rate: %d emails per sec.', $sendingRate),
                    $sendingRate
                );
            } else {
                $strOutput = $this->_tr->translate('No users found.');
            }

            $this->_log->saveToCronLog('Mass users notifications: ' . $strOutput);
        } catch (Exception $e) {
            $strOutput = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strOutput;
    }
}