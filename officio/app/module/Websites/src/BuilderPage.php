<?php

namespace Websites;


use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\Log;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class BuilderPage
{

    /** @var array */
    protected $_config;

    /** @var DbAdapterWrapper */
    protected $_db2;

    /** @var Log */
    protected $_log;

    public function __construct(array $config, DbAdapterWrapper $db, Log $log)
    {
        $this->_config = $config;
        $this->_db2    = $db;
        $this->_log    = $log;
    }

    /**
     * Update page record
     *
     * @param int $id
     * @param array $data
     * @return bool true on success
     */
    public function updatePage($id, $data)
    {
        try {
            $this->_db2->update('company_websites_pages', $data, ['id' => (int)$id]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $booSuccess;
    }

    /**
     * insert page record
     *
     * @param array $builder
     * @param array $oldBuilderData
     * @param array $template
     * @return array|false of page ids on success
     * @throws
     */
    public function savePage($builder, $oldBuilderData, $template)
    {
        $booIsAustralia = $this->_config['site_version']['version'] == 'australia';
        $builderPages   = [];
        $arr            = [
            'homepage',
            'about',
            'canada',
            'immigration',
            'assessment',
            'contact'
        ];

        if (!$builder) {
            $builder = [];
        }
        try {
            foreach ($arr as $value) {
                if (isset($builder[$value]) && (!empty($builder[$value]) || $builder[$value] === 0)) {
                    continue;
                } else {
                    $name = null;
                    $available = 1;
                    if (!empty($oldBuilderData[$value]) && !empty($oldBuilderData[$value]['name'])) {
                        $name = $oldBuilderData[$value]['name'];
                        $available = $oldBuilderData[$value]['available'];
                    } else {
                        switch ($value) {
                            case 'homepage':
                                $name = 'Home';
                                break;
                            case 'about':
                                $name = 'About us';
                                break;
                            case 'canada':
                                $name = $booIsAustralia ? 'About Australia' : 'About Canada';
                                break;
                            case 'immigration':
                                $name = 'Immigration';
                                break;
                            case 'assessment':
                                $name = 'Free assessment';
                                break;
                            case 'contact':
                                $name = 'Contact us';
                                break;
                        }
                    }

                    if (!empty($name)) {
                        //save and receive page id
                        $builderPages[$value] = $this->_db2->insert(
                            'company_websites_pages',
                            [
                                'name'      => $name,
                                'available' => $available,
                                'html'      => $template[$value . '_html'],
                                'css'       => $template[$value . '_css']
                            ],
                            0
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        if (count($builderPages) > 0) {
            $res = $builderPages;
        } else {
            $res = false;
        }
        return $res;
    }

    /**
     * get page by id
     *
     * @param int $id
     * @param bool $onlyContent
     * @return array|false (page row) on success
     */
    public function getPageById($id, $onlyContent = false)
    {
        try {
            if ($onlyContent) {
                $pageData = array('content' => 'html', 'contentCss' => 'css');
            } else {
                $pageData = array('name', 'available', 'id');
            }
            $select = (new Select())
                ->from(['pages' => 'company_websites_pages'])
                ->columns($pageData)
                ->where(['pages.id' => $id]);

            return $this->_db2->fetchRow($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }
}
