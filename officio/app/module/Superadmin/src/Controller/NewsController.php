<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use News\Service\News;
use Officio\BaseController;

/**
 * News Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class NewsController extends BaseController
{
    /** @var News */
    private $news;

    public function initAdditionalServices(array $services)
    {
        $this->news = $services[News::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Announcements');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('news', $this->news->getAdminNewsHTML());
        $view->setVariable('booSpecialAnnouncementEnabled', !empty($this->_config['site_version']['homepage']['announcements']['special_announcement_enabled']));

        return $view;
    }

    public function getNewsHtmlAction()
    {
        $view = new ViewModel(
            [
                'content' => $this->news->getAdminNewsHTML()
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function getNewsAction()
    {
        $news_id = $this->params()->fromPost('news_id');
        $news    = $this->news->getNews($news_id);

        return new JsonModel($news);
    }

    private function updateNews($action)
    {
        $strError = '';

        try {
            $filter  = new StripTags();
            $news_id = $this->params()->fromPost('news_id');
            $title   = $filter->filter(trim(Json::decode($this->params()->fromPost('title', ''), Json::TYPE_ARRAY)));
            $content = trim(Json::decode($this->params()->fromPost('content', ''), Json::TYPE_ARRAY));
            $content = $this->_settings->getHTMLPurifier(false)->purify(preg_replace('/<a[^>]*?href=[\'"](.*?)[\'"][^>]*?>(.*?)<\/a>/si', '<a href="$1" target="_blank" class="blulinkun" >$2</a>', $content));

            $isSpecialAnnouncement = $this->params()->fromPost('is_special_announcement');
            $isSpecialAnnouncement = in_array($isSpecialAnnouncement, array('Y', 'N')) ? $isSpecialAnnouncement : 'N';

            $showOnTheHomepage = $this->params()->fromPost('show_on_the_homepage');
            $showOnTheHomepage = in_array($showOnTheHomepage, array('Y', 'N')) ? $showOnTheHomepage : 'Y';

            if (!$this->news->updateNews($action, $news_id, $title, $content, $isSpecialAnnouncement, $showOnTheHomepage)) {
                $strError = $this->_tr->translate('Data was not saved');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success' => empty($strError),
            'error'   => $strError
        );
    }

    public function addAction()
    {
        return new JsonModel($this->updateNews('add'));
    }

    public function editAction()
    {
        return new JsonModel($this->updateNews('edit'));
    }

    public function deleteAction()
    {
        $news_id = $this->params()->fromPost('news_id');
        $result  = $this->news->deleteNews($news_id);

        return new JsonModel(array('success' => $result));
    }

    public function setTimeAction()
    {
        $strError = '';
        $newTime  = '';

        try {
            $newsId     = $this->params()->fromPost('news_id');
            $booSuccess = $this->news->setTime($newsId);

            if (!$booSuccess) {
                $strError = $this->_tr->translate('Internal error');
            } else {
                $news = $this->news->getNews($newsId);
                if (!$news['success']) {
                    $strError = $this->_tr->translate('Data was not loaded...');
                } else {
                    $newTime = $news['news']['create_date'];
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'     => empty($strError),
            'message'     => $strError,
            'create_date' => $newTime
        );

        return new JsonModel($arrResult);
    }
}
