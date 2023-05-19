<?php

namespace Superadmin\Controller;

use Exception;
use Files\Service\Files;
use Help\Service\Help;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * FAQ Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageFaqController extends BaseController
{
    /** @var Help */
    protected $help;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->help   = $services[Help::class];
        $this->_files = $services[Files::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = 'Manage Help Categories And Articles';
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $sectionType = $this->findParam('type', 'help');
        $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

        $view->setVariable('faqArr', $this->help->getFAQList($sectionType, false, false, false));
        $view->setVariable('sectionType', $sectionType);

        return $view;
    }

    public function getFaqAction()
    {
        $faqId       = $this->params()->fromPost('faq_id');
        $sectionType = Json::decode($this->params()->fromPost('section_type'), Json::TYPE_ARRAY);
        $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

        $faq = $this->help->getFAQ($faqId, $sectionType, false);

        return new JsonModel($faq);
    }

    private function updateFaq($action)
    {
        $strError = '';

        try {
            $faqId                  = $this->params()->fromPost('faq_id');
            $faqSectionId           = $this->params()->fromPost('faq_section_id');
            $faqClientView          = $this->params()->fromPost('faq_client_view') === 'Y' ? 'Y' : 'N';
            $faqFeatured            = $this->params()->fromPost('faq_featured') === 'Y' ? 'Y' : 'N';
            $faqContentType         = $this->params()->fromPost('faq_content_type');
            $faqInlinemanualTopicId = $this->params()->fromPost('faq_inlinemanual_topic_id', '');
            $arrAssignedTags        = trim(Json::decode($this->params()->fromPost('faq_assigned_tags', ''), Json::TYPE_ARRAY));
            $arrAssignedTags        = $arrAssignedTags === '' ? array() : explode(',', $arrAssignedTags);
            $metaTags               = trim(Json::decode($this->params()->fromPost('faq_meta_tags', ''), Json::TYPE_ARRAY));
            $question               = trim(Json::decode($this->params()->fromPost('question', ''), Json::TYPE_ARRAY));
            $answer                 = trim(Json::decode($this->params()->fromPost('answer', ''), Json::TYPE_ARRAY));
            $answer                 = preg_replace('/<a[^>]*?href=[\'"](.*?)[\'"][^>]*?>(.*?)<\/a>/si', '<a href="$1" target="_blank" class="blulinkun">$2</a>', $answer);

            if (empty($strError) && empty($faqSectionId)) {
                $strError = 'Incorrectly selected category.';
            }

            if (empty($strError) && !in_array($faqFeatured, array('Y', 'N'))) {
                $strError = 'Incorrectly selected "featured" option.';
            }

            if (empty($strError) && !in_array($faqContentType, array('text', 'video', 'walkthrough'))) {
                $strError = 'Incorrectly selected type.';
            }

            if (empty($strError)) {
                if ($faqContentType === 'walkthrough') {
                    if (!strlen($faqInlinemanualTopicId)) {
                        $strError = 'Topic Id cannot be empty.';
                    } else {
                        $answer      = null;
                        $metaTags    = null;
                        $faqFeatured = 'N';
                    }
                } else {
                    $faqInlinemanualTopicId = null;
                }
            }

            if (empty($strError)) {
                $this->help->updateFAQ($action, $faqId, $faqSectionId, $question, $answer, $metaTags, $faqClientView, $faqFeatured, $faqContentType, $arrAssignedTags, $faqInlinemanualTopicId);
                $this->help->updateAllArticlesIndexes();
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    public function addAction()
    {
        $strError = $this->updateFaq('add');
        return new JsonModel(array('success' => empty($strError), 'message' => $strError));
    }

    public function editAction()
    {
        $strError = $this->updateFaq('edit');
        return new JsonModel(array('success' => empty($strError), 'message' => $strError));
    }

    public function deleteAction()
    {
        $faq_id = $this->params()->fromPost('faq_id');
        $result = $this->help->deleteFAQ($faq_id);
        $this->help->updateAllArticlesIndexes();

        return new JsonModel(array('success' => $result));
    }

    public function getFaqSectionAction()
    {
        $faqSectionId = Json::decode($this->params()->fromPost('faq_section_id'), Json::TYPE_ARRAY);
        $sectionType = Json::decode($this->params()->fromPost('section_type'), Json::TYPE_ARRAY);
        $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

        $section = $this->help->getFAQSection($faqSectionId, $sectionType);

        return new JsonModel($section);
    }

    private function updateFaqSection($action)
    {
        $strError = '';
        try {
            $faqSectionId         = $this->params()->fromPost('faq_section_id');
            $sectionType          = trim(Json::decode($this->params()->fromPost('section_type', ''), Json::TYPE_ARRAY));
            $sectionName          = trim(Json::decode($this->params()->fromPost('section_name', ''), Json::TYPE_ARRAY));
            $sectionSubtitle      = trim(Json::decode($this->params()->fromPost('section_subtitle', ''), Json::TYPE_ARRAY));
            $sectionDescription   = Json::decode($this->params()->fromPost('section_description', ''), Json::TYPE_ARRAY);
            $sectionColor         = trim(Json::decode($this->params()->fromPost('section_color', ''), Json::TYPE_ARRAY));
            $sectionClass         = trim(Json::decode($this->params()->fromPost('section_class', ''), Json::TYPE_ARRAY));
            $sectionExternalLink  = trim(Json::decode($this->params()->fromPost('section_external_link', ''), Json::TYPE_ARRAY));
            $sectionShowAsHeading = Json::decode($this->params()->fromPost('section_show_as_heading'), Json::TYPE_ARRAY);
            $sectionIsHidden      = Json::decode($this->params()->fromPost('section_is_hidden'), Json::TYPE_ARRAY);
            $clientView           = Json::decode($this->params()->fromPost('client_view'), Json::TYPE_ARRAY);
            $parentSectionId      = Json::decode($this->params()->fromPost('parent_section_id'), Json::TYPE_ARRAY);
            $beforeSectionId      = Json::decode($this->params()->fromPost('before_section_id'), Json::TYPE_ARRAY);

            $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

            $this->help->updateFAQSection($action, $faqSectionId, $sectionType, $sectionName, $sectionSubtitle, $sectionDescription, $sectionColor, $sectionClass, $sectionExternalLink, $sectionShowAsHeading, $sectionIsHidden, $clientView, $parentSectionId, $beforeSectionId);
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    public function sectionAddAction()
    {
        return new JsonModel(array('success' => $this->updateFaqSection('add')));
    }

    public function sectionEditAction()
    {
        return new JsonModel(array('success' => $this->updateFaqSection('edit')));
    }

    public function sectionDeleteAction()
    {
        $faq_section_id = $this->params()->fromPost('faq_section_id');
        $result         = $this->help->deleteFAQSection($faq_section_id);

        return new JsonModel(array('success' => $result));
    }

    public function upAction()
    {
        $faqId  = $this->params()->fromPost('faq_id');
        $result = $this->help->moveHelpArticle($faqId, true);

        return new JsonModel(array('success' => $result));
    }

    public function downAction()
    {
        $faqId  = $this->params()->fromPost('faq_id');
        $result = $this->help->moveHelpArticle($faqId, false);

        return new JsonModel(array('success' => $result));
    }

    public function sectionUpAction()
    {
        $sectionId  = $this->params()->fromPost('faq_section_id');
        $booSuccess = $this->help->moveHelpSection($sectionId, true);

        return new JsonModel(array('success' => $booSuccess));
    }

    public function sectionDownAction()
    {
        $sectionId  = $this->params()->fromPost('faq_section_id');
        $booSuccess = $this->help->moveHelpSection($sectionId, false);

        return new JsonModel(array('success' => $booSuccess));
    }

    public function manageTagsAction()
    {
        $view = new ViewModel();

        $title = "Manage Help Context ID's";
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    public function getContextIdsAction()
    {
        try {
            $arrRecords = $this->help->getContextIds();
            $arrTags    = $this->help->getTags();
        } catch (Exception $e) {
            $arrRecords = array();
            $arrTags    = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'totalCount' => count($arrRecords),
            'rows'       => $arrRecords,
            'arrTags'    => $arrTags,
        );

        return new JsonModel($arrResult);
    }

    public function saveContextIdAction()
    {
        $strError = '';

        try {
            $filter    = new StripTags();
            $oPurifier = $this->_settings->getHTMLPurifier();

            $contextId            = $this->params()->fromPost('faq_context_id');
            $contextIdText        = trim($filter->filter($this->params()->fromPost('faq_context_id_text', '')));
            $contextIdDescription = trim($filter->filter($this->params()->fromPost('faq_context_id_description', '')));
            $moduleDescription    = trim($oPurifier->purify($this->params()->fromPost('faq_context_id_module_description', '')));

            $arrContextIdTags = $this->params()->fromPost('faq_assigned_tags_ids', '');
            $arrContextIdTags = empty($arrContextIdTags) ? array() : explode(',', $arrContextIdTags);

            if (empty($strError) && !empty($contextId)) {
                $arrTagInfo = $this->help->getContextIdInfo($contextId);

                if (empty($arrTagInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected context id');
                }
            }

            if (empty($strError) && !strlen($contextIdText)) {
                $strError = $this->_tr->translate('Context Id is required');
            }

            if (empty($strError) && !$this->help->saveContextId($contextId, $contextIdText, $contextIdDescription, $moduleDescription, $arrContextIdTags)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError,
        );

        return new JsonModel($arrResult);
    }

    public function getHelpTagsAction()
    {
        try {
            $arrRecords = $this->help->getTags();
        } catch (Exception $e) {
            $arrRecords = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'totalCount' => count($arrRecords),
            'rows'       => $arrRecords,
        );

        return new JsonModel($arrResult);
    }


    public function saveHelpTagAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $tagId    = $this->findParam('faq_tag_id');
            $tagLabel = trim($filter->filter($this->findParam('faq_tag_text', '')));

            if (empty($strError) && !empty($tagId)) {
                $arrTagInfo = $this->help->getTagInfo($tagId);

                if (empty($arrTagInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected tag');
                }
            }

            if (empty($strError) && !strlen($tagLabel)) {
                $strError = $this->_tr->translate('Tag label is required');
            }

            if (empty($strError) && !$this->help->saveTag($tagId, $tagLabel)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError,
        );

        return new JsonModel($arrResult);
    }

    public function deleteHelpTagsAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit($this->_tr->translate('Insufficient access rights.'));
        }

        $strMessage = '';
        $booSuccess = false;

        try {
            /** @var array $arrTags */
            $arrTags = Json::decode($this->findParam('tags'), Json::TYPE_ARRAY);
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Incorrect incoming params');
        }

        if (!is_array($arrTags) || empty($arrTags)) {
            $strMessage = $this->_tr->translate('Incorrect incoming params');
        }

        if (empty($strMessage)) {
            foreach ($arrTags as $invoiceId) {
                if (empty($invoiceId) || !is_numeric($invoiceId)) {
                    $strMessage = $this->_tr->translate('Incorrect incoming params');
                    break;
                }
            }
        }

        if (empty($strMessage)) {
            $booSuccess = $this->help->deleteTags($arrTags);

            $strSuccess = sprintf(
                $this->_tr->translate('%d %s deleted successfully'),
                count($arrTags),
                count($arrTags) == 1 ? $this->_tr->translate('tag was') : $this->_tr->translate('tags were')
            );
            $strMessage = $booSuccess ? $strSuccess : $this->_tr->translate('Internal error');
        }

        $arrResult = array(
            'success' => $booSuccess,
            'message' => $strMessage
        );

        return new JsonModel($arrResult);
    }
}