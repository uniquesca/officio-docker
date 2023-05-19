<?php

namespace Help\Controller;

use Exception;
use Help\Service\Help;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\Validator\EmailAddress;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * Help Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{
    /** @var  Help */
    protected $_help;

    public function initAdditionalServices(array $services)
    {
        $this->_help = $services[Help::class];
    }

    public function homeAction()
    {
        $view = new ViewModel();

        $sectionType = $this->findParam('type');
        $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

        $view->setVariable('booShowHelpLearnButton', isset($this->_config['help']['show_learn_button']) ? (bool)$this->_config['help']['show_learn_button'] : false);
        $view->setVariable('arrHelp', $this->_help->getFAQList($sectionType, true));
        $view->setVariable('sectionType', $sectionType);

        return $view;
    }


    public function indexAction()
    {
        $view = new ViewModel();

        $sectionType = $this->params()->fromQuery('type');
        $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

        $arrGroupedHelp = $this->_help->getFAQList($sectionType);

        // Get the list of featured questions and place them to the Featured Articles group
        $booIsClient          = $this->_auth->isCurrentUserClient();
        $arrFeaturedQuestions = $this->_help->getListOfQuestions($sectionType, true, $booIsClient, true, true);
        if (!empty($arrFeaturedQuestions)) {
            $arrFeaturedSection = array(
                'faq_section_id'          => 0,
                'parent_section_id'       => null,
                'section_type'            => $sectionType,
                'section_name'            => $this->_tr->translate('Featured Articles'),
                'section_subtitle'        => '',
                'section_description'     => '',
                'section_color'           => '',
                'section_class'           => 'fas fa-bolt',
                'section_external_link'   => false,
                'section_show_as_heading' => false,
                'section_is_hidden'       => false,
                'order'                   => 0,
                'client_view'             => $booIsClient ? 'Y' : 'N',
                'faq'                     => $arrFeaturedQuestions,
            );

            // Show this group at the top of the list
            array_unshift($arrGroupedHelp, $arrFeaturedSection);
        }

        $view->setVariable('faqArr', $arrGroupedHelp);
        $view->setVariable('sectionType', $sectionType);

        return $view;
    }

    public function searchViaPostAction()
    {
        $view = new JsonModel();

        $arrResult  = array();
        $booSuccess = false;
        try {
            $sectionType = $this->params()->fromPost('type');
            $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';
            $query       = trim($this->params()->fromPost('query', ''));

            if ($query != '') {
                $arrResult  = $this->_help->search($sectionType, $query);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrResult,
            'totalCount' => count($arrResult)
        );

        return $view->setVariables($arrResult);
    }

    public function getHelpContextAction()
    {
        $strError          = '';
        $moduleDescription = '';
        $arrArticles       = array();

        try {
            $contextTextId    = Json::decode($this->params()->fromPost('context_id'), Json::TYPE_ARRAY);
            $arrContextIdInfo = $this->_help->getContextIdInfoByTextId($contextTextId);

            if (empty($arrContextIdInfo)) {
                $strError = $this->_tr->translate('Incorrect context id.');
            } else {
                $arrArticles       = $this->_help->getContextTagsByTextId($arrContextIdInfo['faq_context_id']);
                $moduleDescription = $arrContextIdInfo['faq_context_id_module_description'];
            }

            if (empty($strError)) {
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'items'              => $arrArticles,
            'count'              => count($arrArticles),
            'msg'                => $strError,
            'module_description' => $moduleDescription,
        );

        return new JsonModel($arrResult);
    }

    public function getSupportRequestInfoAction()
    {
        $strError       = '';
        $arrRequestInfo = [];

        try {
            $arrRequestInfo = $this->_help->getSupportRequestInfo();
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'        => empty($strError),
            'message'        => $strError,
            'arrRequestInfo' => $arrRequestInfo,
        );

        return new JsonModel($arrResult);
    }

    public function sendSupportRequestAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $requestInfo = array(
                'email'   => trim($filter->filter($this->params()->fromPost('email', ''))),
                'company' => trim($filter->filter($this->params()->fromPost('company', ''))),
                'name'    => trim($filter->filter($this->params()->fromPost('name', ''))),
                'phone'   => trim($filter->filter($this->params()->fromPost('phone', ''))),
                'request' => trim(nl2br($filter->filter($this->params()->fromPost('description', ''))))
            );

            $validator = new EmailAddress();
            if (empty($requestInfo['email']) || !$validator->isValid($requestInfo['email'])) {
                $strError = $this->_tr->translate('Please enter a correct email address.');
            }

            if (empty($strError) && empty($requestInfo['company'])) {
                $strError = $this->_tr->translate('Please provide your Company');
            }

            if (empty($strError) && empty($requestInfo['name'])) {
                $strError = $this->_tr->translate('Please provide your Name');
            }

            if (empty($strError) && empty($requestInfo['phone'])) {
                $strError = $this->_tr->translate('Please provide your Phone No.');
            }

            if (empty($strError) && empty($requestInfo['request'])) {
                $strError = $this->_tr->translate('Your Request message is empty');
            }

            if (empty($strError)) {
                // generate subject
                $supportRequestNumber   = $this->_help->getSupportRequestCount();
                $requestInfo['subject'] = $this->_tr->translate('Officio support request ') . $supportRequestNumber;

                $requestSent = $this->_help->sendRequest($requestInfo);
                if ($requestSent !== true) {
                    $strError = $requestSent;
                }
            }

            if (empty($strError)) {
                // save request number
                $this->_help->incrementSupportRequestCount();
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return new JsonModel($arrResult);
    }
}