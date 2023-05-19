<?php

namespace Websites;

use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\Log;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class BuilderLayout
{

    /** @var array */
    protected $_config;

    /** @var DbAdapterWrapper */
    protected $_db2;

    /** @var Log */
    protected $_log;

    /** @var BuilderPage */
    private $_builderPage;

    private $_pages;

    public function __construct(array $config, DbAdapterWrapper $db, Log $log)
    {
        $this->_config = $config;
        $this->_db2    = $db;
        $this->_log    = $log;
    }

    public function init()
    {
        $this->_builderPage = new BuilderPage($this->_config, $this->_db2, $this->_log);
        $this->_pages       = [
            'homepage',
            'about',
            'canada',
            'immigration',
            'assessment',
            'contact'
        ];
    }

    /**
     * Update layout and page records
     *
     * @param int $companyId
     * @param array $data
     * @param string $pageName
     * @return bool true on success
     */
    public function updateLayout($companyId, $data = [], $pageName = null)
    {
        try {
            $builderRow = $this->getBuilderRow($companyId);
            if (count($builderRow) < 1) {
                return false;
            }

            $res = $this->_builderPage->updatePage($builderRow[$pageName], $data['content']);

            if ($res) {
                $this->_db2->update(
                    'company_websites_builder_template',
                    $data['main'],
                    ['id' => (int)$builderRow['id']]
                );

                $booSuccess = true;
            } else {
                $booSuccess = false;
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $booSuccess;
    }

    /**
     * Update builder record
     *
     * @param int $id
     * @param array $data
     * @return bool true on success
     */
    public function updateBuilder($id, $data)
    {
        try {
            $this->_db2->update(
                'company_websites_builder',
                $data,
                ['id' => (int)$id]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $booSuccess;
    }

    /**
     * save default layout
     *
     * @param int $companyId
     * @param string $entranceName
     * @param array $oldBuilder
     * @return bool true on success
     */
    public function saveDefLayout($companyId, $entranceName, $oldBuilder = [])
    {
        $builderRow = $this->getBuilderRow($companyId);
        $templates = $this->getTemplates(true);
        $oldBuilderPages = $this->oldBuilderAvailabilityToArray($oldBuilder);

        try {
            if (count($builderRow) < 1) {
                $arrInsert['company_id'] = $companyId;
                $arrInsert['entrance_name'] = $entranceName;
                $arrInsert['template_id'] = 1;
                $arrInsert['visible'] = 1;
                if (!empty($oldBuilder) && count($oldBuilder) > 0) {
                    $arrInsert['company_name']   = $oldBuilder['company_name'];
                    $arrInsert['title']          = $oldBuilder['title'];
                    $arrInsert['address']        = $oldBuilder['contact_text'];
                    $arrInsert['phone']          = $oldBuilder['company_phone'];
                    $arrInsert['fb_script']      = $oldBuilder['script_google_analytics'];
                    $arrInsert['google_script']  = $oldBuilder['script_facebook_pixel'];
                    $arrInsert['assessment_url'] = $oldBuilder['assessment_url'];
                }

                $newBuilderId = $this->_db2->insert('company_websites_builder', $arrInsert, 0);

                foreach ($templates as $template) {
                    $pagesId = $this->_builderPage->savePage($builderRow, $oldBuilderPages, $template);
                    //               if (empty($pagesId) || (is_array($pagesId && count($pagesId) < 1))) {
                    //                  continue;
                    //               }
                    $pages                  = $pagesId;
                    $pagesId['builder_id']  = $newBuilderId;
                    $pagesId['template_id'] = $template['id'];
                    $pages['entrance_name'] = $entranceName;
                    $pagesId['header'] = $this->generateDefNav($pages);
                    $pagesId['footer'] = $template['footer'];
                    $pagesId['css'] = $template['css'];

                    $this->_db2->insert('company_websites_builder_template', $pagesId);
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
     * get templates data
     *
     * @param bool $fullData
     * @return array
     */
    public function getTemplates($fullData = false)
    {
        try {
            if ($fullData) {
                $select = (new Select())
                    ->from('company_websites_template_default');
            } else {
                $select = (new Select())
                    ->from('company_websites_template_default')
                    ->columns(['id', 'template_name']);
            }

            $res = $this->_db2->fetchAll($select);
        } catch (Exception $e) {
            $res = [];
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $res;
    }

    /**
     * get templates data
     *
     * @param int $id
     * @return array
     */
    public function getTemplateById($id)
    {
        try {
            $select = (new Select())
                ->from('company_websites_template_default')
                ->where(['id' => $id]);
            $res = $this->_db2->fetchRow($select);
        } catch (Exception $e) {
            $res = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $res;
    }

    /**
     * switch template
     *
     * @param int $companyId
     * @param int $templateId
     * @return int
     */
    public function switchTemplate($companyId, $templateId)
    {
        try {
            $select = (new Select())
                ->from('company_websites_builder')
                ->columns(['template_id'])
                ->where(['company_id' => $companyId]);

            $builder = $this->_db2->fetchOne($select);

            if ($builder !== $templateId) {
                $res = $this->_db2->update(
                    'company_websites_builder',
                    ['template_id' => (int)$templateId],
                    ['company_id' => (int)$companyId]
                );
            } else {
                $res = true;
            }
        } catch (Exception $e) {
            $res = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $res;
    }

    /**
     * get builder data and specific page
     *
     * @param string $entranceName
     * @param string $pageName
     * @return array
     */
    public function getBuilderContentByEntranceName($entranceName, $pageName)
    {
        try {
            $builderRow = $this->getBuilderRow($entranceName);
            if (count($builderRow) < 1) {
                return [];
            }

            $pageRow = $this->_builderPage->getPageById($builderRow[$pageName], true);
            $res     = array_merge($builderRow, $pageRow);
        } catch (Exception $e) {
            $res = [];
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $res;
    }

    /**
     * get builder data by company id
     *
     * @param int $companyId
     * @return array
     */
    public function getBuilderByCompanyId($companyId)
    {
        try {
            $builderRow = $this->getBuilderRow($companyId);
            if (count($builderRow) < 1) {
                return [];
            }
            foreach ($this->_pages as $value) {
                $select = (new Select())
                    ->from('company_websites_pages')
                    ->columns(['id', 'name', 'available'])
                    ->where(['id' => $builderRow[$value]]);

                $builderRow[$value] = $this->_db2->fetchRow($select);
            }
            $res = $builderRow;
        } catch (Exception $e) {
            $res = [];
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $res;
    }

    /**
     * generate entrance name
     *
     * @param int $companyId
     * @param string $name
     * @return string
     */
    public function generateEntranceName($companyId, $name = '')
    {
        try {
            // format name
            $name = self::seoUrl($name);
            if (empty($name)) {
                $name = 'company' . $companyId;
            }

            //  check if company name is available
            $counter = 1;
            while ($this->isEntranceNameAlreadyUsed($name, $companyId)) {
                $name = 'company' . $companyId . '_' . $counter++;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $name;
    }

    /**
     * check if entrance name already used
     *
     * @param string $entranceName
     * @param int $companyId
     * @return bool true on success
     */
    private function isEntranceNameAlreadyUsed($entranceName, $companyId = 0)
    {
        $select = (new Select())
            ->from('company_websites_builder')
            ->columns(['count' => new Expression('COUNT(id)')])
            ->where([
                'entrance_name' => $entranceName,
                (new Where())->notEqualTo('company_id', (int)$companyId)
            ]);

        $count = $this->_db2->fetchOne($select);

        return !empty($count);
    }

    /**
     * @static
     * @param $string
     * @param int $limit
     * @return string
     */
    private static function seoUrl($string, $limit = 100)
    {
        if ($limit > 0) {
            $string = substr($string ?? '', 0, $limit);
        }
        //Unwanted:  {UPPERCASE} ; / ? : @ & = + $ , . ! ~ * ' ( )
        $string = strtolower($string ?? '');

        //Strip any unwanted characters
        $string = preg_replace("/[^a-z0-9_\s-]/", "", $string);

        //Clean multiple dashes or whitespaces
        $string = preg_replace("/[\s-]+/", " ", $string);

        //Convert whitespaces and underscore to dash
        return preg_replace("/[\s_]/", "-", $string);
    }

    /**
     * @param string $entranceName
     * @return int on success
     */
    public function getCompanyIdByEntrance($entranceName)
    {
        try {
            // get company ID by entrance name
            $select = (new Select())
                ->from('company_websites_builder')
                ->columns(['company_id'])
                ->where(['entrance_name' => $entranceName]);

            $res = $this->_db2->fetchOne($select);
        } catch (Exception $e) {
            $res = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $res;
    }

    /**
     * @param int|string $param
     * @return array
     */
    public function getBuilderRow($param)
    {
        if (empty($param) && $param !== "0") {
            return [];
        }
        try {
            $where = [];
            if (is_numeric($param)) {
                $where['b.company_id'] = $param;
            } else {
                if (is_string($param)) {
                    $where['b.entrance_name'] = $param;
                } else {
                    return [];
                }
            }

            $select = (new Select())
                ->from(['b' => 'company_websites_builder'])
                ->join(array('tmpl' => 'company_websites_builder_template'),
                       new PredicateExpression('b.id = tmpl.builder_id AND b.template_id = tmpl.template_id'),
                       Select::SQL_STAR,
                       Select::JOIN_LEFT_OUTER)
                ->where($where);

            $res = $this->_db2->fetchRow($select);
        } catch (Exception $e) {
            $res = [];
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $res;
    }

    /**
     * @param mixed $builder
     * @param string $entranceName
     *
     * @return array of pages
     */
    public function getActivePages($builder = false, $entranceName = '')
    {
        try {
            if (empty($builder)) {
                $builder = $this->getBuilderRow($entranceName);
            }

            $pages = [];
            foreach ($this->_pages as $page) {
                //if has page id - get page data
                if (!empty($builder[$page])) {
                    if (is_array($builder[$page])) {
                        $pages[$page] = $builder[$page];
                    } else {
                        $pages[$page] = $this->_builderPage->getPageById($builder[$page]);
                    }
                }
            }
            $res = $pages;
        } catch (Exception $e) {
            $res = [];
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $res;
    }

    /**
     * @param array $builder old builder row
     *
     * @return array $pages (availability, name)
     */
    public function oldBuilderAvailabilityToArray($builder)
    {
        try {
            $pages = [];
            if (empty($builder)) {
                return $pages;
            }
            foreach ($this->_pages as $pageName) {
                $pages[$pageName] = [
                    'name' => $builder[$pageName . '_name'],
                    'available' => $builder[$pageName . '_on'] === 'Y' ? 1 : 0
                ];
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $pages;
    }

    /**
     * @param array $builder old builder row
     * @param array $activePages of pages(name, available)
     *
     * @return bool true on success
     */
    public function updateFromOldBuilderAvailability($builder, $activePages)
    {
        try {
            foreach ($this->_pages as $pageName) {
                $updateData = [];
                $needUpdate = false;
                if (isset($activePages[$pageName]['available'])) {
                    if ($builder[$pageName . "_on"] === 'Y' && (int)$activePages[$pageName]['available'] === 0) {
                        //update available 1
                        $updateData['available'] = 1;
                        $needUpdate = true;
                    } else {
                        if ($builder[$pageName . "_on"] === 'N' && (int) $activePages[$pageName]['available'] === 1) {
                            //update available 0
                            $updateData['available'] = 0;
                            $needUpdate = true;
                        }
                    }
                }
                if ($builder[$pageName . "_name"] != $activePages[$pageName]['name']) {
                    $updateData["name"] = $builder[$pageName . "_name"];
                    $needUpdate = true;
                }
                if ($needUpdate) {
                    $this->_builderPage->updatePage(
                        $activePages[$pageName]['id'],
                        $updateData
                    );
                }
            }
            return true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }

    /**
     * @param int $id of new builder
     * @param array $data
     *
     * @return bool true on success
     */
    public function updateNewBuilderData($id, $data)
    {
        $newBuilderFormat = array();
        if (isset($data['contact_text'])) {
            $newBuilderFormat['address'] = $data['contact_text'];
        }
        if (isset($data['script_facebook_pixel'])) {
            $newBuilderFormat['fb_script'] = $data['script_facebook_pixel'];
        }
        if (isset($data['script_google_analytics'])) {
            $newBuilderFormat['google_script'] = $data['script_google_analytics'];
        }
        if (isset($data['company_phone'])) {
            $newBuilderFormat['phone'] = $data['company_phone'];
        }
        if (isset($data['company_name'])) {
            $newBuilderFormat['company_name'] = $data['company_name'];
        }
        if (isset($data['title'])) {
            $newBuilderFormat['title'] = $data['title'];
        }
        if (isset($data['assessment_url'])) {
            $newBuilderFormat['assessment_url'] = $data['assessment_url'];
        }
        if (empty($newBuilderFormat)) {
            return false;
        }

        try {
            $this->_db2->update(
                'company_websites_builder',
                $newBuilderFormat,
                ['id' => (int)$id]
            );
            return true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }

    /**
     * update navigation in builder
     *
     * @param array $builder row
     * @return bool true on success
     */
    public function updateNav($builder)
    {
        try {
            if (!empty($builder)) {
                $activePages = $this->getActivePages($builder);
                $pageLinks = [];
                foreach ($activePages as $pageName => $page) {
                    if ($page['available'] && $page['available'] != 0) {
                        $pageLinks[] = '<li name="' . $pageName . '" class="nav-item"> <a class="nav-link" href="/webs/' .
                            $builder['entrance_name'] . '/' . $pageName . '">' . $page['name'] . '</a> </li>';
                    }
                }
                $html_links = join('', $pageLinks);

                if (isset($builder['header']) && !empty($builder['header'])) {
                    $start = strpos($builder['header'], '<li');
                    $end = strrpos($builder['header'], '</li>');
                    $begin = substr($builder['header'], 0, $start);
                    $ending = substr($builder['header'], $end + 5);

                    if ($start) {
                        $header = $begin . $html_links . $ending;
                        $this->_db2->update(
                            'company_websites_builder_template',
                            ['header' => $header],
                            ['id' => (int)$builder['id']]
                        );
                    }
                }
                $res = true;
            } else {
                $res = false;
            }
        } catch (Exception $e) {
            $res = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $res;
    }

    /**
     * generate navigation in builder
     *
     * @param array $builder row
     * @return string
     */
    public function generateDefNav($builder)
    {
        try {
            if (!empty($builder)) {
                $activePages = $this->getActivePages($builder);
                $pageLinks = [];
                foreach ($activePages as $pageName => $page) {
                    if ($page['available'] && $page['available'] != 0) {
                        $pageLinks[] = '<li name="' . $pageName . '" class="nav-item"> <a class="nav-link" href="/webs/' .
                            $builder['entrance_name'] . '/' . $pageName . '">' . $page['name'] . '</a> </li>';
                    }
                }
                $html_links = join('', $pageLinks);

                $header = '<nav class="navbar navbar-expand-lg c2380">
                    <a href="#" class="navbar-brand nav_title">Navbar</a>
                    <button type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation" class="navbar-toggler">
                        <span class="navbar-toggler-icon c2409"></span>
                    </button>
                    <div id="navbarSupportedContent" class="collapse navbar-collapse c2419">
                        <ul class="navbar-nav ml-auto c2429">' . $html_links . '</ul>
                    </div>
                </nav>';

                $res = $header;
            } else {
                $res = '';
            }
        } catch (Exception $e) {
            $res = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $res;
    }
}
