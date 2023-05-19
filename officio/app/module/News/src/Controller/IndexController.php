<?php

namespace News\Controller;

use Exception;
use Laminas\View\Model\JsonModel;
use News\Service\News;
use Officio\BaseController;

/**
 * News page Index Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{
    /** @var  News */
    private $_news;

    public function initAdditionalServices(array $services)
    {
        $this->_news = $services[News::class];
    }

    public function getTopBannerMessageAction()
    {
        session_write_close();
        $strNews = '';

        try {
            $booIsClient   = $this->_auth->isCurrentUserClient();
            $arrMemberInfo = $this->_members->getMemberInfo();

            // Don't show if this is a client or a user/admin turned off notifications
            if (!$booIsClient && isset($arrMemberInfo['show_special_announcements']) && $arrMemberInfo['show_special_announcements'] === 'Y') {
                $arrNewsInfo = $this->_news->getLatestBannerMessage();
                // If there is a banner message - continue
                if (isset($arrNewsInfo['content']) && !empty($arrNewsInfo['content'])) {
                    // If a user didn't see the message (detected by the "viewed on time") - show it
                    if (empty($arrMemberInfo['special_announcements_viewed_on']) || strtotime($arrMemberInfo['special_announcements_viewed_on']) < strtotime($arrNewsInfo['create_date'])) {
                        $strNews = $arrNewsInfo['content'];
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => strlen($strNews),
            'news'    => $strNews
        );

        return new JsonModel($arrResult);
    }

    public function saveBannerLastViewedTimeAction()
    {
        session_write_close();
        $strError = '';

        try {
            if (!$this->_members->updateBannerLastViewedTime($this->_auth->getCurrentUserId())) {
                $strError = $this->_tr->translate('Internal error.');
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

    public function markNewsAsReadAction()
    {
        $strError = '';

        try {
            if (!$this->_news->markNewsAsReadForCurrentMember()) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }
}