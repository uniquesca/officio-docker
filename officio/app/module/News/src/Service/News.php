<?php

namespace News\Service;

use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Laminas\Db\Sql\Expression;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class News extends BaseService
{

    /** @var Members */
    protected $_members;

    public function initAdditionalServices(array $services)
    {
        $this->_members = $services[Members::class];
    }

    /**
     * Load the list of news for the homepage
     *
     * @param int $offset
     * @return array
     */
    public function getNewsHTML($offset)
    {
        $strResult   = '';
        $booShowMore = false;

        try {
            $arrNews = $this->getNewsList();

            if (count($arrNews)) {
                $offset      = empty($offset) ? 0 : (int)$offset;
                $length      = 10;
                $booShowMore = count($arrNews) > ($offset + 1) * $length;
                $arrNews     = array_slice($arrNews, $offset * $length, $length);
                foreach ($arrNews as $arrNewsInfo) {
                    $clsUnread = empty($arrNewsInfo['news_read']) ? 'news_unread' : '';
                    $strResult .= '<table width="100%" cellpadding="0" cellspacing="0" class="garytxt11 news ' . $clsUnread . '">
                        <tr>
                            <td class="news-title">' . $arrNewsInfo['title'] . '</td>
                        </tr>
                        <tr>
                            <td class="news-date">' . $this->_settings->formatDate($arrNewsInfo['create_date']) . '</td>
                        </tr>
                        <tr>
                            <td align="left" valign="top" class="news-content">' . $arrNewsInfo['content'] . '</td>
                        </tr>
                        </table>';
                }
            } else {
                $strResult = '<span class="message">No recent announcements.</span>';
            }
        } catch (Exception $e) {
            $strResult = '<span class="message"><i class="las la-check"></i> No News found.</span>';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return [$strResult, $booShowMore];
    }

    public function getAdminNewsHTML()
    {
        $strResult = '';
        try {
            $arrNews = $this->getNewsList(false, false);

            //show news
            if (count($arrNews)) {
                foreach ($arrNews as $arrNewsInfo) {
                    // Remove first spaces
                    $strContent = preg_replace('/^(&nbsp;)+/', '', $arrNewsInfo['content']);
                    $cls        = $arrNewsInfo['is_special_announcement'] === 'Y' ? 'news_banner' : '';

                    $strResult .= '<table width="100%" cellpadding="0" cellspacing="0" class="news ' . $cls . '">' .
                        '<tr>' .
                        '<td class="news-title">' . $arrNewsInfo['title'] . '</td>' .
                        '<td class="news-date">' . $this->_settings->formatDate($arrNewsInfo['create_date']) . '</td>' .
                        '</tr>' .

                        '<tr>' .
                        '<td colspan="2" align="left" valign="top" class="news-content">' . $strContent . '</td>' .
                        '</tr>' .

                        '<tr>' .
                        '<td colspan="2" align="right" valign="top">' .
                        '<a href="#" onclick="news({action: \'edit\', news_id: ' . $arrNewsInfo['news_id'] . '}); return false;">' .
                        '<i class="las la-edit"></i>' .
                        '</a>' .

                        '&nbsp;' .

                        '<a href="#" onclick="news({action: \'delete\', news_id: ' . $arrNewsInfo['news_id'] . '}); return false;">' .
                        '<i class="las la-trash"></i>' .
                        '</a>' .
                        '</td>' .
                        '</tr>' .
                        '</table>';
                }
            } else {
                $strResult = 'No News found.';
            }
        } catch (Exception $e) {
            $strResult = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $strResult;
    }

    /**
     * Load all or new (from a "read on date") news items
     *
     * @param bool $booNewOnly
     * @param bool $booHomepage
     * @return array
     */
    public function getNewsList($booNewOnly = false, $booHomepage = true)
    {
        $arrMemberInfo = $this->_members->getMemberInfo();
        if (isset($arrMemberInfo['news_read_on']) && !empty($arrMemberInfo['news_read_on'])) {
            $arrDataToLoad = array('*', 'news_read' => new Expression("IF (create_date <= '" . $arrMemberInfo['news_read_on'] . "', 1, 0)"));
        } else {
            $arrDataToLoad = array('*', 'news_read' => new Expression('0'));
        }

        $arrWhere = [];
        if ($booNewOnly && isset($arrMemberInfo['news_read_on']) && !empty($arrMemberInfo['news_read_on'])) {
            $arrWhere[] = (new Where())->greaterThanOrEqualTo('create_date', $arrMemberInfo['news_read_on']);
        }
        if ($booHomepage) {
            $arrWhere[] = (new Where())->equalTo('show_on_homepage', 'Y');
        }

        $select = (new Select())
            ->from('news')
            ->columns($arrDataToLoad)
            ->where($arrWhere)
            ->order(array('create_date DESC', 'news_id DESC'));

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load news item info
     *
     * @param int $newsId
     * @return array
     */
    public function getNews($newsId)
    {
        $booSuccess  = false;
        $arrNewsInfo = array();

        try {
            $select = (new Select())
                ->from('news')
                ->where(['news_id' => (int)$newsId]);

            $news = $this->_db2->fetchRow($select);

            if (!empty($news)) {
                $arrNewsInfo = array(
                    'news_id'                 => $news['news_id'],
                    'title'                   => $news['title'],
                    'content'                 => $news['content'],
                    'create_date'             => $news['create_date'],
                    'is_special_announcement' => $news['is_special_announcement'],
                    'show_on_homepage'        => $news['show_on_homepage']
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => $booSuccess, 'news' => $arrNewsInfo);
    }

    /**
     * Create/update news item
     *
     * @param $action
     * @param $newsId
     * @param $title
     * @param $content
     * @param $isSpecialAnnouncement
     * @param $showOnTheHomepage
     * @return bool
     */
    public function updateNews($action, $newsId, $title, $content, $isSpecialAnnouncement, $showOnTheHomepage)
    {
        $booSuccess = false;

        try {
            $arrData = array(
                'title'                   => $title,
                'content'                 => $content,
                'is_special_announcement' => $isSpecialAnnouncement,
                'show_on_homepage'        => $showOnTheHomepage,
            );

            // Allow only 1 banner at a time
            if ($isSpecialAnnouncement === 'Y') {
                $this->_db2->update('news', ['is_special_announcement' => 'N'], []);
            }

            if ($action == 'add') {
                $arrData['create_date'] = date('c');

                $this->_db2->insert('news', $arrData);
            } else {
                $this->_db2->update('news', $arrData, ['news_id' => $newsId]);
            }
            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete news item by specific id
     *
     * @param $newsId
     * @return int
     */
    public function deleteNews($newsId)
    {
        return $this->_db2->delete('news', ['news_id' => $newsId]);
    }

    /**
     * Load count of unread news items
     *
     * @return int
     */
    public function getCurrentMemberUnreadNewsCount()
    {
        $arrNewsCount = $this->getNewsList(true);
        return count($arrNewsCount);
    }

    /**
     * Mark all news as read for the current member
     *
     * @return bool true on success, false otherwise
     */
    public function markNewsAsReadForCurrentMember()
    {
        $memberId = $this->_auth->getCurrentUserId();
        return $this->_members->updateMemberData($memberId, array('news_read_on' => date('c')));
    }

    /**
     * Set time for the news to now
     *
     * @param $newsId
     * @return bool
     */
    public function setTime($newsId)
    {
        $booSuccess = false;
        try {
            $arrData = array(
                'create_date' => date('c')
            );

            $this->_db2->update('news', $arrData, ['news_id' => $newsId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Get a list of announcements that will be used in the Daily notifications
     *
     * @return array
     */
    public function getYesterdayNews()
    {
        $yesterday = date('Y-m-d', strtotime('-1 days'));

        $select = (new Select())
            ->from('news')
            ->where(
                [
                    (new Where())
                        ->equalTo('show_on_homepage', 'Y')
                        ->equalTo('is_special_announcement', 'N')
                        ->greaterThanOrEqualTo('create_date', $yesterday . ' 00:00:00')
                        ->lessThanOrEqualTo('create_date', $yesterday . ' 23:59:59')
                ]
            );

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load the latest message that can be showed to the user
     *
     * @param bool $booGeneratedYesterdayOnly
     * @return array
     */
    public function getLatestBannerMessage($booGeneratedYesterdayOnly = false)
    {
        $select = (new Select())
            ->from('news')
            ->where(['is_special_announcement' => 'Y'])
            ->order('create_date DESC');

        if ($booGeneratedYesterdayOnly) {
            $yesterday = date('Y-m-d', strtotime('-1 days'));
            $select->where->greaterThanOrEqualTo('create_date', $yesterday . ' 00:00:00');
            $select->where->lessThanOrEqualTo('create_date', $yesterday . ' 23:59:59');
        }

        return $this->_db2->fetchRow($select);
    }
}
