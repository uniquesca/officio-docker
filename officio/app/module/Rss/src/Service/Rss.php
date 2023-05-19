<?php

namespace Rss\Service;

use Exception;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\Db\Sql\Select;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Reader\StandaloneExtensionManager;
use Laminas\Http\Client;
use Laminas\Http\Client\Adapter\Proxy;
use Laminas\Uri\UriFactory;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Settings;
use Officio\Common\ServiceContainerHolder;
use Rss\Reader\Extension\LMS\Entry;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Rss extends BaseService
{

    use ServiceContainerHolder;

    public function init()
    {
        // Setup caching
        /** @var StorageAdapterFactoryInterface $cacheFactory */
        $cacheFactory = $this->_serviceContainer->get(StorageAdapterFactoryInterface::class);
        $cache        = $cacheFactory->createFromArrayConfiguration($this->_config['rss']['cache']);
        Reader::setCache($cache);

        // Allow to load content even if SSL certificate isn't ok
        // Can be removed later or a new switch can be added to the config file
        $arrClientOptions = [
            'adapter'       => Client\Adapter\Socket::class,
            'ssltransport'  => 'ssl',
            'sslverifypeer' => false,
        ];

        // Bind proxy to RSS feed reader if configured
        if ($this->_config['outbound_proxy']['use']) {
            $arrClientOptions = array_merge(
                $arrClientOptions,
                [
                    'adapter'    => Proxy::class,
                    'proxy_host' => $this->_config['outbound_proxy']['host'],
                    'proxy_port' => $this->_config['outbound_proxy']['port'],
                    'proxy_user' => $this->_config['outbound_proxy']['login'],
                    'proxy_pass' => $this->_config['outbound_proxy']['pass'],
                ]
            );
        }

        $httpClient = new Client(null, $arrClientOptions);
        Reader::setHttpClient($httpClient);

        // Add Reader extensions
        /** @var StandaloneExtensionManager $standalone */
        $standalone = Reader::getExtensionManager();
        $standalone->add('Media\Entry', \Rss\Reader\Extension\Media\Entry::class);
        $standalone->add('LMS\Entry', Entry::class);
        Reader::registerExtension('Media');
        Reader::registerExtension('LMS');
    }

    /**
     * Load rss black list
     *
     * @return array
     */
    private function _getBlackList()
    {
        $select = (new Select())
            ->from('rss_black_list')
            ->columns(['domain'])
            ->order('domain');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Scrap rss data and prepare it to html output
     *
     * @return string
     */
    public function generateHtml()
    {
        $strResult = '';
        try {
            $arrFeedUrls = $this->_config['rss']['urls'];
            $arrFeedUrls = empty($arrFeedUrls) || !is_array($arrFeedUrls) ? array() : $arrFeedUrls;

            $blackList = $this->_getBlackList();

            $arrParsedFeeds = array();
            $bad = '<br /><div style="padding-top:0.8em;"><img alt="" height="1" width="1" /></div>';
            foreach ($arrFeedUrls as $provider => $feedUrl) {
                try {
                    $feed = Reader::import($feedUrl);
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                    continue;
                }

                /** @var EntryInterface | \Rss\Reader\Extension\Media\Entry $entry */
                foreach ($feed as $entry) {
                    $link        = $entry->getLink();
                    $source      = $entry->getAuthor() ? $entry->getAuthor()['name'] : '';
                    $content     = $entry->getTitle();
                    $description = $entry->getContent();
                    $image       = $entry->getMediaContentImage();
                    $date        = $entry->getDateModified() ? $entry->getDateModified()->getTimestamp() : '';

                    // We need extract date and beautify the look of the item
                    switch ($provider) {
                        case 'google':
                            $content = $description;
                            if (!empty($content) && preg_match('%^<a.*>(.*)</a>.*<font.*>(.*)</font>$%', $content, $regs)) {
                                $content = $regs[1];
                                $source  = $regs[2];
                            }
                            break;

                        default:
                            break;
                    }


                    $booBlackListAlert = false;
                    foreach ($blackList as $b) {
                        if (strstr($content, $b) !== false || strstr($description, $b) !== false || strstr($link, $b) !== false) {
                            $booBlackListAlert = true;
                            break;
                        }
                    }

                    if (!$booBlackListAlert) {
                        if ($image) {
                            $content = '<img src="' . $image . '" alt="News Image" />' . $content;
                        }

                        $arrParsedFeeds[] = array(
                            'content' => str_replace(array($bad, '<nobr>', '</nobr>'), '', $content),
                            'date'    => $date,
                            'source'  => empty($source) ? '' : $source,
                            'link'    => empty($link) ? '' : $link
                        );
                    }
                }
            }

            if (!empty($arrParsedFeeds)) {
                // Sort by date
                $arrDates = array();
                foreach ($arrParsedFeeds as $key => $row) {
                    $arrDates[$key] = $row['date'];
                }
                array_multisort($arrDates, SORT_DESC, $arrParsedFeeds);

                foreach ($arrParsedFeeds as $arrFeedInfo) {
                    $strResult .= '<a class="news" href="' . $arrFeedInfo['link'] . '" target="_blank">';
                    $strResult .= '<span class="content">' . $arrFeedInfo['content'] . '</span>';
                    $strResult .= '<span class="source">' . $arrFeedInfo['source'] . '</span>';
                    $strResult .= '<span class="date">' . Settings::getDateDiff(time(), $arrFeedInfo['date'], 1, ' ') . ' ago</span>';
                    $strResult .= '</a>';
                }
            } else {
                $strResult = $this->_tr->translate('No immigration news to show.');
            }
        } catch (Exception $e) {
            $strResult = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strResult;
    }

    /**
     * Scrap LMS rss data
     *
     * @return array
     */
    public function getLMSNews($booLMSEnabled)
    {
        $strError    = '';
        $arrRssItems = array();

        try {
            if ($booLMSEnabled) {
                try {
                    $booValid       = false;
                    $rssUrl         = $this->_config['lms']['rss_url'];
                    $maxItemsToShow = (int)$this->_config['lms']['rss_max_items'];
                    if (!empty($rssUrl)) {
                        $validator = UriFactory::factory($rssUrl);
                        $booValid  = $validator->isValid();
                    }

                    if ($booValid) {
                        $feed = Reader::import($rssUrl);

                        $count = 0;
                        /** @var EntryInterface | Entry $entry */
                        foreach ($feed as $entry) {
                            $arrRssItems[] = array(
                                'image'     => $entry->getEnclosure()->url,
                                'title'     => $entry->getTitle(),
                                'message'   => $entry->getDescription(),
                                'link'      => $entry->getLink(),
                                'cpd_hours' => $entry->getLmsCpdHours(),
                            );

                            $count++;

                            // Don't show more than X items
                            if (!empty($maxItemsToShow) && $count >= $maxItemsToShow) {
                                break;
                            }
                        }
                    } else {
                        $strError = $this->_tr->translate('RSS url for LMS is not valid or not set in the config file.');
                    }
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $arrRssItems);
    }
}
