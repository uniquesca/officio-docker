<?php

namespace Help\Service;

use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Nadar\Stemming\Stemm;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Service\Users;
use Officio\Templates\Model\SystemTemplate;
use Officio\Templates\SystemTemplates;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Help extends BaseService
{
    /** @var Users */
    protected $_users;

    /** @var Company */
    protected $_company;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    public function initAdditionalServices(array $services)
    {
        $this->_users           = $services[Users::class];
        $this->_company         = $services[Company::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
    }

    public function init()
    {
        $this->_systemTemplates->getEventManager()->attach(SystemTemplates::EVENT_GET_AVAILABLE_FIELDS, [$this, 'getSystemTemplateFields']);
    }

    /**
     * Load the list of questions for categories with the provided type
     *
     * @param string $sectionType
     * @param bool $booLoadOnlyFeatured
     * @param bool $booIsClient
     * @param bool $booExceptOfWalkthrough
     * @return array
     */
    public function getListOfQuestions($sectionType, $booLoadOnlyFeatured, $booIsClient, $booExceptOfWalkthrough, $booOnlyVisible)
    {
        $select = (new Select())
            ->from(array('f' => 'faq'))
            ->join(array('s' => 'faq_sections'), 's.faq_section_id = f.faq_section_id', [], Join::JOIN_LEFT)
            ->where([
                (new Where())
                    ->nest()
                    ->isNull('s.section_type')
                    ->or
                    ->equalTo('s.section_type', $sectionType)
                    ->unnest()
            ])
            ->order('f.order');

        if ($booLoadOnlyFeatured) {
            $select->where(['f.featured' => 'Y']);
        }

        if ($booIsClient) {
            $select->where(['f.client_view' => 'Y']);
        }

        if ($booExceptOfWalkthrough) {
            $select->where(['f.content_type' => array('text', 'video')]);
        }

        if ($booOnlyVisible) {
            $select->where(['s.faq_section_id' => $this->getVisibleSectionIds()]);
        }

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of sections and articles in them
     *
     * @param string $sectionType
     * @param bool $booLoadOnlyFeatured true to load only articles that were marked as featured
     * @param bool $booExceptOfWalkthrough
     * @param bool $booOnlyVisible
     * @return array
     */
    public function getFAQList($sectionType, $booLoadOnlyFeatured = false, $booExceptOfWalkthrough = true, $booOnlyVisible = true)
    {
        $booIsClient = $this->_auth->isCurrentUserClient();

        // get sections
        $select = (new Select())
            ->from('faq_sections')
            ->where(
                [
                    'section_type' => $sectionType
                ]
            )
            ->order('order');

        if ($booIsClient) {
            $select->where->equalTo('client_view', 'Y');
        }

        if ($booOnlyVisible) {
            $select->where->equalTo('section_is_hidden', 'N');
        }

        $arrFAQSections = $this->_db2->fetchAssoc($select);

        // get FAQs
        $faqs = $this->getListOfQuestions($sectionType, $booLoadOnlyFeatured, $booIsClient, $booExceptOfWalkthrough, $booOnlyVisible);

        // merge
        foreach ($arrFAQSections as &$section) {
            foreach ($faqs as $faq) {
                if ($faq['faq_section_id'] == $section['faq_section_id']) {
                    $section['faq'][] = $faq;
                }
            }
        }
        unset($section);

        // add subcategories to top-level categories
        foreach ($arrFAQSections as &$section) {
            // skip subcategories
            if ($section['parent_section_id'] !== null) {
                continue;
            }


            $select = (new Select())
                ->from('faq_sections')
                ->order('order');

            if (is_null($section['faq_section_id'])) {
                $select->where->isNull('parent_section_id');
            } else {
                $select->where->equalTo('parent_section_id', (int)$section['faq_section_id']);
            }

            if ($booIsClient) {
                $select->where->equalTo('client_view', 'Y');
            }

            if ($booOnlyVisible) {
                $select->where->equalTo('section_is_hidden', 'N');
            }

            $arrSubcategoriesIds = $this->_db2->fetchCol($select);

            if (count($arrSubcategoriesIds)) {
                foreach ($arrSubcategoriesIds as $subcategoryId) {
                    $section['subcategories'][] = $arrFAQSections[$subcategoryId];
                }
            }
        }
        unset($section);

        // only now we can remove subcategories from top level
        return array_filter(
            $arrFAQSections,
            function ($s) {
                return $s['parent_section_id'] === null;
            }
        );
    }

    /**
     * Load list of sections with all sub sections
     *
     * @param string $sectionType
     * @param int $level
     * @param null|int $parentSectionId
     * @param bool $booOnlyVisible
     * @param bool $parentSectionIsVisible
     * @return array
     */
    private function getGroupedSections($sectionType = '', $level = 0, $parentSectionId = null, $booOnlyVisible = true, $parentSectionIsVisible = true)
    {
        $arrSectionsGrouped = array();
        if ($booOnlyVisible && !$parentSectionIsVisible) {
            return $arrSectionsGrouped;
        }

        $select = (new Select())
            ->from('faq_sections')
            ->columns(array('faq_section_id', 'section_name', 'section_is_hidden'))
            ->order('order');

        if (!empty($sectionType)) {
            $select->where->equalTo('section_type', $sectionType);
        }

        if (!empty($parentSectionId)) {
            $select->where->equalTo('parent_section_id', (int)$parentSectionId);
        } else {
            $select->where->isNull('parent_section_id');
        }

        if ($booOnlyVisible) {
            $select->where->equalTo('section_is_hidden', 'N');
        }

        $arrSections = $this->_db2->fetchAll($select);

        foreach ($arrSections as $arrSectionInfo) {
            $arrSectionInfo['level'] = $level;
            $arrSectionsGrouped[]    = $arrSectionInfo;
            $arrSubSections          = $this->getGroupedSections($sectionType, $level + 1, $arrSectionInfo['faq_section_id'], $booOnlyVisible, $arrSectionInfo['section_is_hidden'] === 'N');
            if (!empty($arrSubSections)) {
                foreach ($arrSubSections as $arrSubSectionInfo) {
                    $arrSubSectionInfo['level'] = $level + 1;
                    $arrSectionsGrouped[]       = $arrSubSectionInfo;
                }
            }
        }
        return $arrSectionsGrouped;
    }

    /**
     * Load FAQ (article) detailed info by id
     *
     * @param int $faqId
     * @param string $sectionType
     * @return array
     */
    public function getFAQ($faqId, $sectionType, $booOnlyVisible = true)
    {
        if ($faqId) { //edit
            $arrResult = $this->getFAQInfo($faqId);
        } else { //add
            $arrResult = array(
                'question' => '',
                'answer'   => ''
            );
        }

        $arrResult['parent_sections']   = $this->getGroupedSections($sectionType, 0, null, $booOnlyVisible);
        $arrResult['faq_assigned_tags'] = $this->getFAQAssignedTags($faqId);
        $arrResult['tags']              = array_merge(array(array('faq_tag_id' => 0, 'faq_tag_text' => 'Check/Uncheck All')), $this->getTags());

        return $arrResult;
    }

    /**
     * Load list of assigned tags for a specific help article
     *
     * @param int $faqId
     * @return array
     */
    public function getFAQAssignedTags($faqId)
    {
        $arrAssignedTags = array();
        if (!empty($faqId)) {
            $select = (new Select())
                ->from('faq_assigned_tags')
                ->columns(['faq_tag_id'])
                ->where(['faq_id' => (int)$faqId]);

            $arrAssignedTags = $this->_db2->fetchCol($select);
        }

        return $arrAssignedTags;
    }

    /**
     * Get FAQ (article) detailed info
     *
     * @param int $faqId
     * @return array
     */
    public function getFAQInfo($faqId)
    {
        $arrInfo = array();
        if (!empty($faqId)) {
            $select = (new Select())
                ->from('faq')
                ->where(['faq_id' => (int)$faqId]);

            $arrInfo = $this->_db2->fetchRow($select);
        }

        return $arrInfo;
    }

    /**
     * Load help section info
     *
     * @param int $faqSectionId
     * @return array
     */
    public function getFAQSectionInfo($faqSectionId)
    {
        $select = (new Select())
            ->from('faq_sections')
            ->where(['faq_section_id' => $faqSectionId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load section/category detailed info (depends on the add/edit dialog)
     * Plus the list of all root sections
     *
     * @param int $faqSectionId
     * @param string $sectionType
     * @return array
     */
    public function getFAQSection($faqSectionId, $sectionType)
    {
        if ($faqSectionId) {
            // edit
            $result = $this->getFAQSectionInfo($faqSectionId);
        } else {
            // add
            $result = array('name' => '');
        }

        $faqSectionId = empty($faqSectionId) ? 0 : $faqSectionId;

        $select = (new Select())
            ->from('faq_sections')
            ->columns(array('faq_section_id', 'section_name'))
            ->where(
                [
                    (new Where())
                        ->notEqualTo('faq_section_id', (int)$faqSectionId)
                        ->equalTo('section_type', $sectionType)
                        ->isNull('parent_section_id')
                ]
            )
            ->order('order');

        $arrParentSections = $this->_db2->fetchAll($select);

        $result['parent_sections'] = $arrParentSections;

        return $result;
    }

    /**
     * Create/update FAQ (article) record
     *
     * @param string $action
     * @param int $faqId
     * @param int $faqSectionId
     * @param string $question
     * @param string $answer
     * @param string $metaTags
     * @param string $faqClientView
     * @param string $faqFeatured
     * @param string $faqContentType
     * @param array $arrAssignedTags
     * @return string|int article id
     */
    public function updateFAQ($action, $faqId, $faqSectionId, $question, $answer, $metaTags, $faqClientView, $faqFeatured, $faqContentType, $arrAssignedTags, $faqInlinemanualTopicId)
    {
        $arrToUpdate = array(
            'faq_section_id'        => $faqSectionId,
            'question'              => $question,
            'answer'                => is_null($answer) ? $answer : $this->_settings->getHTMLPurifier(false)->purify($answer),
            'meta_tags'             => $metaTags,
            'client_view'           => $faqClientView,
            'featured'              => $faqFeatured,
            'content_type'          => $faqContentType,
            'inlinemanual_topic_id' => $faqInlinemanualTopicId,
        );

        if ($action == 'add') {
            $booCalculateOrder = true;
        } else {
            $arrFAQInfo        = $this->getFAQInfo($faqId);
            $booCalculateOrder = $arrFAQInfo['faq_section_id'] != $faqSectionId;
        }

        if ($booCalculateOrder) {
            $select = (new Select())
                ->from('faq')
                ->columns(['max' => new Expression('MAX(`order`)')])
                ->where(['faq_section_id' => (int)$faqSectionId]);

            $arrToUpdate['order'] = (int)$this->_db2->fetchOne($select) + 1;
        }

        // add/update FAQ
        if ($action == 'add') {
            $faqId     = $this->_db2->insert('faq', $arrToUpdate);
        } else {
            $this->_db2->update('faq', $arrToUpdate, ['faq_id' => (int)$faqId]);
            $this->_db2->delete('faq_assigned_tags', ['faq_id' => (int)$faqId]);
        }
        foreach ($arrAssignedTags as $tagId) {
            $this->_db2->insert(
                'faq_assigned_tags',
                [
                    'faq_id' => $faqId,
                    'faq_tag_id' => $tagId,
                ]
            );
        }

        return $faqId;
    }

    public function deleteFAQ($faqId)
    {
        return $this->_db2->delete('faq', ['faq_id' => $faqId]);
    }

    /**
     * Created / Update help section
     *
     * @param string $action
     * @param int $faqSectionId
     * @param string $sectionType
     * @param string $sectionName
     * @param string $sectionSubtitle
     * @param string $sectionDescription
     * @param string $sectionColor
     * @param string $sectionClass
     * @param string $sectionExternalLink
     * @param bool $sectionShowAsHeading
     * @param bool $sectionIsHidden
     * @param bool $clientView
     * @param int $parentSectionId
     * @param null $beforeSectionId
     * @return string $faqSectionId created or updated
     */
    public function updateFAQSection(
        $action,
        $faqSectionId,
        $sectionType,
        $sectionName,
        $sectionSubtitle,
        $sectionDescription,
        $sectionColor,
        $sectionClass,
        $sectionExternalLink,
        $sectionShowAsHeading,
        $sectionIsHidden,
        $clientView,
        $parentSectionId,
        $beforeSectionId = null
    ) {
        $arr = array(
            'section_type'            => $sectionType,
            'section_name'            => $sectionName,
            'section_subtitle'        => empty($sectionSubtitle) ? null : $sectionSubtitle,
            'section_description'     => empty($sectionDescription) ? null : $sectionDescription,
            'section_color'           => empty($sectionColor) ? null : $sectionColor,
            'section_class'           => empty($sectionClass) ? null : $sectionClass,
            'section_external_link'   => empty($sectionExternalLink) ? null : $sectionExternalLink,
            'section_show_as_heading' => $sectionShowAsHeading ? 'Y' : 'N',
            'section_is_hidden'       => $sectionIsHidden ? 'Y' : 'N',
            'client_view'             => $clientView ? 'Y' : 'N',
            'parent_section_id'       => $parentSectionId,
        );

        if ($action == 'add') {
            // add/update FAQ

            //get order
            $select = (new Select())
                ->from('faq_sections')
                ->columns(array('order' => new Expression('MAX(`order`)')));

            if (is_null($parentSectionId)) {
                $select->where->isNull('parent_section_id');
            } else {
                $select->where->equalTo('parent_section_id', (int)$parentSectionId);
            }

            $order = (int)$this->_db2->fetchOne($select);

            $arr['order'] = empty($order) ? 1 : ($order + 1);

            $faqSectionId = $this->_db2->insert('faq_sections', $arr);

            if ($beforeSectionId && !$parentSectionId) {
                $select = (new Select())
                    ->from('faq_sections')
                    ->columns(['order'])
                    ->where(
                        [
                            (new Where())
                                ->equalTo('faq_section_id', (int)$beforeSectionId)
                                ->isNull('parent_section_id')// allow "insert before" only for top level
                        ]
                    );

                $beforeSectionOrder = (int)$this->_db2->fetchOne($select);

                if ($beforeSectionOrder) {
                    // inc order for $before_section_id and next categories
                    $this->_db2->update(
                        'faq_sections',
                        ['order' => new Expression('`order`+1')],
                        [(new Where())->greaterThanOrEqualTo('order', $beforeSectionOrder)]
                    );

                    // set new cat order to $before_section_order
                    $this->_db2->update(
                        'faq_sections',
                        ['order' => $beforeSectionOrder],
                        ['faq_section_id' => (int)$faqSectionId]
                    );
                }
            }
        } else // edit
        {
            $this->_db2->update('faq_sections', $arr, ['faq_section_id' => (int)$faqSectionId]);
        }

        $this->updateAllArticlesIndexes();

        return $faqSectionId;
    }

    /**
     * Delete FAQ/Help category
     *
     * If category has subcategories or questions - don't allow to delete it
     *
     * @param int $faqSectionId - the id of the section to delete
     * @return bool true if category was successfully deleted, false otherwise
     */
    public function deleteFAQSection($faqSectionId)
    {
        try {
            $select = (new Select())
                ->from('faq_sections')
                ->columns(['count' => new Expression('COUNT(*)')])
                ->where(['parent_section_id' => (int)$faqSectionId]);

            $subsectionsCount = $this->_db2->fetchOne($select);
            if ($subsectionsCount > 0) {
                return false;
            }

            $select = (new Select())
                ->from('faq')
                ->columns(['count' => new Expression('COUNT(*)')])
                ->where(['faq_section_id' => (int)$faqSectionId]);

            $questionsCount = $this->_db2->fetchOne($select);
            if ($questionsCount > 0) {
                return false;
            }

            $select = (new Select())
                ->from('faq_sections')
                ->where(['faq_section_id' => (int)$faqSectionId]);

            $deletedSection = $this->_db2->fetchRow($select);

            // delete section
            $this->_db2->delete('faq_sections', ['faq_section_id' => $faqSectionId]);

            // dec order for categories after $faq_section_id
            $arrWhere = [
                (new Where())->greaterThan('order', $deletedSection['order'])
            ];

            if (is_null($deletedSection['parent_section_id'])) {
                $arrWhere[] = (new Where())->isNull('parent_section_id');
            } else {
                $arrWhere['parent_section_id'] = $deletedSection['parent_section_id'];
            }

            $this->_db2->update(
                'faq_sections',
                ['order' => new Expression('`order`-1')],
                $arrWhere
            );

            $this->updateAllArticlesIndexes();

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Move array element up/down
     *
     * @param array $array
     * @param int|string $itemKey
     * @param bool $booMoveUp
     * @return array
     */
    public static function arrayShove(array $array, $itemKey, $booMoveUp)
    {
        if (empty($array) || count($array) == 1) {
            return $array;
        }

        $newArray = array();
        $arrLast  = array();
        foreach ($array as $key => $value) {
            if ($key !== $itemKey) {
                $newArray["$key"] = $value;

                $arrLast = array('key' => $key, 'value' => $value);
                unset($array["$key"]);
            } else {
                if (!$booMoveUp) {
                    // Value of next, moves pointer
                    $nextValue = next($array);

                    // Key of next
                    $nextKey = key($array);

                    // Check if $next_key is null,
                    // indicating there is no more elements in the array
                    if ($nextKey !== null) {
                        // Add -next- to $new_array, keeping -current- in $array
                        $newArray["$nextKey"] = $nextValue;
                        unset($array["$nextKey"]);
                    }
                } else {
                    if (isset($arrLast['key'])) {
                        unset($newArray["{$arrLast['key']}"]);
                    }
                    // Add current $array element to $new_array
                    $newArray["$key"] = $value;
                    // Re-add $last to $new_array
                    $newArray["{$arrLast['key']}"] = $arrLast['value'];
                }

                // Merge new and old array
                return $newArray + $array;
            }
        }

        return $array;
    }

    /**
     * Move section up/down
     *
     * @param int $sectionId
     * @param bool $booUp
     * @return bool true on success
     */
    public function moveHelpSection($sectionId, $booUp)
    {
        $booSuccess = false;
        try {
            $arrSectionInfo = $this->getFAQSectionInfo($sectionId);

            if (!empty($arrSectionInfo)) {
                $parentSectionId = $arrSectionInfo['parent_section_id'];
                if (empty($parentSectionId)) {
                    $arrWhere[] = (new Where())->isNull('parent_section_id');
                    $arrWhere['section_type'] = $arrSectionInfo['section_type'];
                } else {
                    $arrWhere['parent_section_id'] = $parentSectionId;
                }

                $select = (new Select())
                    ->from('faq_sections')
                    ->where($arrWhere)
                    ->order('order');

                $arrSectionsToSort = $this->_db2->fetchAll($select);

                // Find the key of this section + resort the array
                foreach ($arrSectionsToSort as $key => $arrSectionToSortInfo) {
                    if ($arrSectionToSortInfo['faq_section_id'] == $sectionId) {
                        $arrSectionsToSort = self::arrayShove($arrSectionsToSort, $key, $booUp);
                        break;
                    }
                }

                // Update the order
                $order = 0;
                foreach ($arrSectionsToSort as $arrSectionToSortInfo) {
                    $this->_db2->update(
                        'faq_sections',
                        ['order' => $order++],
                        ['faq_section_id' => $arrSectionToSortInfo['faq_section_id']]
                    );
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Move help article up/down
     *
     * @param int $articleId
     * @param bool $booUp
     * @return bool true on success
     */
    public function moveHelpArticle($articleId, $booUp)
    {
        $booSuccess = false;
        try {
            $arrArticleInfo = $this->getFAQInfo($articleId);

            if (!empty($arrArticleInfo)) {
                $parentSectionId = $arrArticleInfo['faq_section_id'];
                if (empty($parentSectionId)) {
                    $arrWhere[] = (new Where())->isNull('faq_section_id');
                } else {
                    $arrWhere['faq_section_id'] = $parentSectionId;
                }

                $select = (new Select())
                    ->from('faq')
                    ->where($arrWhere)
                    ->order('order');

                $arrArticlesToSort = $this->_db2->fetchAll($select);

                // Find the key of this article + resort the array
                foreach ($arrArticlesToSort as $key => $arrArticleToSortInfo) {
                    if ($arrArticleToSortInfo['faq_id'] == $articleId) {
                        $arrArticlesToSort = self::arrayShove($arrArticlesToSort, $key, $booUp);
                        break;
                    }
                }

                // Update the order
                $order = 0;
                foreach ($arrArticlesToSort as $arrArticleToSortInfo) {
                    $this->_db2->update(
                        'faq',
                        ['order' => $order++],
                        ['faq_id' => $arrArticleToSortInfo['faq_id']]
                    );
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Search for articles based on the string query
     *
     * @param string $sectionType
     * @param string $query
     * @return array
     */
    public function search($sectionType, $query)
    {
        $arrRecords = array();

        if (strlen($query)) {
            $query = substr($query, 0, 1000);

            // Reload indexes if they were not created yet
            if (!($arrIndexes = $this->_cache->getItem($this->getCacheSearchIndex()))) {
                $this->updateAllArticlesIndexes();

                $arrIndexes = $this->_cache->getItem($this->getCacheSearchIndex());
            }

            // Search for the articles in the specific "help type"
            if (isset($arrIndexes[$sectionType])) {
                // Extract words from the query
                $arrQueryWords = $this->getGroupedWords($query);

                // Search for maxim from all words from all articles
                $arrFoundArticles = array();
                foreach ($arrQueryWords as $word => $count) {
                    if (isset($arrIndexes[$sectionType][$word])) {
                        foreach ($arrIndexes[$sectionType][$word] as $arrIndex) {
                            $arrFoundArticles[$arrIndex['id']] = isset($arrFoundArticles[$arrIndex['id']]) ? $arrFoundArticles[$arrIndex['id']] + $arrIndex['rank'] : $arrIndex['rank'];
                        }
                    }
                }
                arsort($arrFoundArticles);

                // Found something? Return!
                if (count($arrFoundArticles)) {
                    // Return only 5 results - the same as it is set in the typeahead settings
                    $select = (new Select())
                        ->from('faq')
                        ->columns(array('id' => 'faq_id', 'value' => 'question', 'type' => new Expression("'article'")))
                        ->join(array('s' => 'faq_sections'), 's.faq_section_id = faq.faq_section_id', 'section_type', Select::JOIN_LEFT)
                        ->where(
                            [
                                (new Where())
                                    ->equalTo('s.section_type', $sectionType)
                                    ->in('s.faq_section_id', $this->getVisibleSectionIds())
                                    ->in('faq.faq_id', array_keys($arrFoundArticles))
                            ]
                        )
                        ->limit(5);

                    $arrRecords = $this->_db2->fetchAll($select);
                }
            }
        }

        return $arrRecords;
    }

    /**
     * Sends support request.
     * @param array $requestInfo
     * @return true|string True if request sent, error message otherwise
     */
    public function sendRequest($requestInfo)
    {
        try {
            $template          = SystemTemplate::loadOne(['title' => 'Support Email']);
            $processedTemplate = $this->_systemTemplates->processTemplate(
                $template,
                [
                    '{support request: name}'    => $requestInfo['name'] ?? '',
                    '{support request: company}' => $requestInfo['company'] ?? '',
                    '{support request: email}'   => $requestInfo['email'] ?? '',
                    '{support request: phone}'   => $requestInfo['phone'] ?? '',
                    '{support request: request}' => $requestInfo['request'] ?? '',
                    '{support email counter}'    => $this->getSupportRequestCount()
                ],
                ['to', 'subject', 'template', 'from']
            );
            $processedEmail    = $this->_systemTemplates->sendTemplate($processedTemplate);
            $result            = $processedEmail['sent'] ?? false;
            if (!$result) {
                return 'Unable to send email.';
            }

            // Increment support count
            $this->incrementSupportRequestCount();
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    public function getSupportRequestInfo()
    {
        $requestInfo = array();

        try {
            $userInfo    = $this->_users->getUserInfo();
            $companyInfo = $this->_company->getCompanyInfo($userInfo['company_id']);

            $requestInfo['email']   = $userInfo['emailAddress'];
            $requestInfo['company'] = $companyInfo['companyName'];
            $requestInfo['name']    = $userInfo['full_name'];

            //get phones
            $phones = array();
            if (!empty($userInfo['workPhone'])) {
                $phones[] = $userInfo['workPhone'] . ' (W)';
            }
            if (!empty($companyInfo['phone1'])) {
                $phones[] = $companyInfo['phone1'] . ' (Office)';
            } else {
                if (!empty($companyInfo['phone2'])) {
                    $phones[] = $companyInfo['phone2'] . ' (Office)';
                }
            }
            $requestInfo['phone'] = implode(' or ', $phones);

            //get subject
            $supportRequestCount    = (int)$this->getSupportRequestCount() + 1;
            $requestInfo['subject'] = 'Officio support request ' . $supportRequestCount;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $requestInfo;
    }

    public function getSupportRequestCount()
    {
        return $this->_settings->getSystemVariables()->getVariable('support_request_count');
    }

    public function incrementSupportRequestCount()
    {
        $count = (int)$this->getSupportRequestCount();
        return $this->_settings->getSystemVariables()->setVariable('support_request_count', $count + 1);
    }

    private function getCacheSearchIndex()
    {
        return 'help_search_indexes';
    }

    /**
     * Regenerate cache of indexes for help articles
     */
    public function updateAllArticlesIndexes()
    {
        $select = (new Select())
            ->from('faq')
            ->join(array('s' => 'faq_sections'), 's.faq_section_id = faq.faq_section_id', 'section_type', Select::JOIN_LEFT)
            ->where(['s.faq_section_id' => $this->getVisibleSectionIds()]);

        $arrAllArticles = $this->_db2->fetchAll($select);

        $arrGroupedIndexes = array();
        foreach ($arrAllArticles as $arrAllArticleInfo) {
            $arrArticleWords = $this->indexArticle(array($arrAllArticleInfo['question'], $arrAllArticleInfo['answer'], $arrAllArticleInfo['meta_tags']));

            foreach ($arrArticleWords as $word => $count) {
                if (!isset($arrGroupedIndexes[$arrAllArticleInfo['section_type']][$word])) {
                    $arrGroupedIndexes[$arrAllArticleInfo['section_type']][$word] = array();
                }

                $arrGroupedIndexes[$arrAllArticleInfo['section_type']][$word][] = array(
                    'id'   => $arrAllArticleInfo['faq_id'],
                    'rank' => $count,
                );
            }
        }

        $this->_cache->setItem($this->getCacheSearchIndex(), $arrGroupedIndexes);
    }

    /**
     * Generate grouped list of words from several strings
     *
     * @param array $arrStrings
     * @return array
     */
    public function indexArticle($arrStrings)
    {
        $arrGroupedResults = array();
        foreach ($arrStrings as $string) {
            $arrWords = $this->getGroupedWords($string);

            foreach ($arrWords as $word => $count) {
                if (isset($arrGroupedResults[$word])) {
                    $arrGroupedResults[$word] += $count;
                } else {
                    $arrGroupedResults[$word] = $count;
                }
            }
        }

        arsort($arrGroupedResults);

        return $arrGroupedResults;
    }

    /**
     * Generate list of words from the string + how often they are used
     *
     * @param string $string
     * @return array
     */
    public function getGroupedWords($string)
    {
        $string = strtolower(strip_tags(html_entity_decode($string)));
        $string = preg_replace('/[^\w]+/u', ' ', $string);
        $string = trim(preg_replace('/\s+/', ' ', Settings::removeCommonWords(($string))));

        $arrAllWords     = explode(' ', $string);
        $arrGroupedWords = array();
        foreach ($arrAllWords as $word) {
            // Convert the word to the "original"
            // E.g. working => work
            $word = Stemm::stem($word, 'en');

            $arrGroupedWords[$word] = isset($arrGroupedWords[$word]) ? $arrGroupedWords[$word] + 1 : 1;
        }
        unset($arrGroupedWords['']);

        return $arrGroupedWords;
    }

    /**
     * Load list of context ids and list of assigned articles + tags
     *
     * @return array
     */
    public function getContextIds()
    {
        $select = (new Select())
            ->from(array('c' => 'faq_context_ids'))
            ->join(array('ct' => 'faq_context_ids_tags'), 'c.faq_context_id = ct.faq_context_id', 'faq_tag_id', Select::JOIN_LEFT)
            ->join(array('t' => 'faq_tags'), 't.faq_tag_id = ct.faq_tag_id', 'faq_tag_text', Select::JOIN_LEFT)
            ->join(array('ft' => 'faq_assigned_tags'), 't.faq_tag_id = ft.faq_tag_id', [], Select::JOIN_LEFT)
            ->join(array('f' => 'faq'), 'ft.faq_id = f.faq_id', 'question', Select::JOIN_LEFT)
            ->order('c.faq_context_id_text');

        $arrData = $this->_db2->fetchAll($select);

        $arrGroupedData = array();
        foreach ($arrData as $arrContextIdData) {
            if (!isset($arrGroupedData[$arrContextIdData['faq_context_id']])) {
                $arrGroupedData[$arrContextIdData['faq_context_id']] = array(
                    'faq_context_id'                    => $arrContextIdData['faq_context_id'],
                    'faq_context_id_text'               => $arrContextIdData['faq_context_id_text'],
                    'faq_context_id_description'        => $arrContextIdData['faq_context_id_description'],
                    'faq_context_id_module_description' => $arrContextIdData['faq_context_id_module_description'],
                    'faq_assigned_tags'                 => array(),
                    'faq_assigned_tags_ids'             => array(),
                    'faq_assigned_articles'             => array(),
                );
            }

            if (!empty($arrContextIdData['faq_tag_text']) && !in_array($arrContextIdData['faq_tag_id'], $arrGroupedData[$arrContextIdData['faq_context_id']]['faq_assigned_tags_ids'])) {
                $arrGroupedData[$arrContextIdData['faq_context_id']]['faq_assigned_tags_ids'][] = $arrContextIdData['faq_tag_id'];
                $arrGroupedData[$arrContextIdData['faq_context_id']]['faq_assigned_tags'][]     = $arrContextIdData['faq_tag_text'];

                sort($arrGroupedData[$arrContextIdData['faq_context_id']]['faq_assigned_tags']);
            }

            if (!empty($arrContextIdData['question'])) {
                $arrGroupedData[$arrContextIdData['faq_context_id']]['faq_assigned_articles'][] = $arrContextIdData['question'];

                sort($arrGroupedData[$arrContextIdData['faq_context_id']]['faq_assigned_articles']);
            }
        }

        // Kill keys for js
        return array_values($arrGroupedData);
    }

    /**
     * Load list of tags and count of articles assigned to them
     *
     * @return array
     */
    public function getTags()
    {
        $select = (new Select())
            ->from(array('t' => 'faq_tags'))
            ->join(array('at' => 'faq_assigned_tags'), 't.faq_tag_id = at.faq_tag_id', array('assigned_articles_count' => new Expression('COUNT(at.faq_tag_id)')), Select::JOIN_LEFT)
            ->group('t.faq_tag_id')
            ->order('t.faq_tag_text');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load information about context id record by int id
     *
     * @param int $contextId
     * @return array
     */
    public function getContextIdInfo($contextId)
    {
        $arrContextIdInfo = array();

        if (!empty($contextId)) {
            $select = (new Select())
                ->from('faq_context_ids')
                ->where(['faq_context_id' => (int)$contextId]);

            $arrContextIdInfo = $this->_db2->fetchRow($select);
        }

        return $arrContextIdInfo;
    }

    /**
     * Load information about context id record by text id
     *
     * @param string $contextTextId
     * @return array
     */
    public function getContextIdInfoByTextId($contextTextId)
    {
        $arrContextIdInfo = array();

        if (!empty($contextTextId)) {
            $select = (new Select())
                ->from('faq_context_ids')
                ->where(['faq_context_id_text' => $contextTextId]);

            $arrContextIdInfo = $this->_db2->fetchRow($select);
        }

        return $arrContextIdInfo;
    }

    /**
     * Load a list of ids of visible sections
     *
     * @return array|int[]
     */
    public function getVisibleSectionIds()
    {
        $arrGroupedSectionsIds = array();

        $arrGroupedSections = $this->getGroupedSections();
        foreach ($arrGroupedSections as $arrGroupedSectionInfo) {
            $arrGroupedSectionsIds[] = $arrGroupedSectionInfo['faq_section_id'];
        }

        return empty($arrGroupedSectionsIds) ? array(0) : Settings::arrayUnique($arrGroupedSectionsIds);
    }

    /**
     * Load list of tags assigned to the specific context id record
     *
     * @param int $contextId
     * @return array
     */
    public function getContextTagsByTextId($contextId)
    {
        $arrArticles = array();

        if (!empty($contextId)) {
            $select = (new Select())
                ->from(array('ct' => 'faq_context_ids_tags'))
                ->columns([])
                ->join(array('ft' => 'faq_assigned_tags'), 'ct.faq_tag_id = ft.faq_tag_id', [], Select::JOIN_LEFT)
                ->join(array('f' => 'faq'), 'ft.faq_id = f.faq_id', array('faq_id', 'question', 'content_type'), Select::JOIN_LEFT)
                ->join(array('s' => 'faq_sections'), 's.faq_section_id = f.faq_section_id', 'section_type', Select::JOIN_LEFT)
                ->where(
                    [
                        (new Where())
                            ->equalTo('ct.faq_context_id', (int)$contextId)
                            ->in('s.faq_section_id', $this->getVisibleSectionIds())
                            ->isNotNull('f.faq_id')
                    ]
                )
                ->order('f.question')
                ->limit(10);

            $arrArticles = $this->_db2->fetchAll($select);
        }

        return $arrArticles;
    }

    /**
     * Create/update context id record and assign list of tags to it
     *
     * @param int $contextId
     * @param string $contextIdText
     * @param string $contextIdDescription
     * @param string $moduleDescription
     * @param array $arrContextIdTags
     * @return bool true on success
     */
    public function saveContextId($contextId, $contextIdText, $contextIdDescription, $moduleDescription, $arrContextIdTags)
    {
        try {
            if (empty($contextId)) {
                $contextId = $this->_db2->insert(
                    'faq_context_ids',
                    [
                        'faq_context_id_text' => $contextIdText,
                        'faq_context_id_description' => $contextIdDescription,
                    ]
                );
            } else {
                $this->_db2->update(
                    'faq_context_ids',
                    [
                        'faq_context_id_text'               => $contextIdText,
                        'faq_context_id_description'        => $contextIdDescription,
                        'faq_context_id_module_description' => $moduleDescription
                    ],
                    ['faq_context_id' => (int)$contextId]
                );

                $this->_db2->delete('faq_context_ids_tags', ['faq_context_id' => (int)$contextId]);
            }

            foreach ($arrContextIdTags as $tagId) {
                $this->_db2->insert(
                    'faq_context_ids_tags',
                    [
                        'faq_context_id' => $contextId,
                        'faq_tag_id'     => $tagId,
                    ]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load tag record info by its id
     *
     * @param int $tagId
     * @return array
     */
    public function getTagInfo($tagId)
    {
        $arrTagInfo = array();

        if (!empty($tagId)) {
            $select = (new Select())
                ->from('faq_tags')
                ->where(['faq_tag_id' => (int)$tagId]);

            $arrTagInfo = $this->_db2->fetchRow($select);
        }

        return $arrTagInfo;
    }

    /**
     * Create/update tag record
     *
     * @param int $tagId
     * @param string $tagLabel
     * @return bool true on success
     */
    public function saveTag($tagId, $tagLabel)
    {
        try {
            if (empty($tagId)) {
                $this->_db2->insert('faq_tags', ['faq_tag_text' => $tagLabel]);
            } else {
                $this->_db2->update(
                    'faq_tags',
                    ['faq_tag_text' => $tagLabel],
                    ['faq_tag_id' => (int)$tagId]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete tags
     *
     * @param array $arrTagsIds
     * @return bool true on success
     */
    public function deleteTags($arrTagsIds)
    {
        $booSuccess = false;

        try {
            if (count($arrTagsIds)) {
                $this->_db2->delete('faq_tags', ['faq_tag_id' => $arrTagsIds]);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
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

        // Support request info
        $arrSupportRequestFields = array(
            array('name' => 'support request: name', 'label' => 'Name'),
            array('name' => 'support request: company', 'label' => 'Company'),
            array('name' => 'support request: email', 'label' => 'Email'),
            array('name' => 'support request: phone', 'label' => 'Phone No.'),
            array('name' => 'support request: request', 'label' => 'My Request')
        );

        foreach ($arrSupportRequestFields as &$field5) {
            $field5['n']     = 4;
            $field5['group'] = 'Support Request Details';
        }
        unset($field5);

        return $arrSupportRequestFields;
    }

}
