<?php

namespace Superadmin\Controller;

use Laminas\Filter\StripTags;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Superadmin\Service\SuperadminSearch;

/**
 * Superadmin advanced search
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class AdvancedSearchController extends BaseController
{
    /** @var SuperadminSearch */
    private $_search;

    public function initAdditionalServices(array $services) {
        $this->_search = $services[SuperadminSearch::class];
    }

    public function indexAction() {
    }

    public function getListAction() {

        $view = new JsonModel();
        $arrSavedSearches = $this->_search->getSavedSearches();
        $arrSearchesParsed = array();
        $arrSearchesParsed[] = array(
            'savedSearchId' => 0,
            'savedSearchName' => 'Save as new search'
        );
        if (is_array($arrSavedSearches) && count($arrSavedSearches)) {
            foreach ($arrSavedSearches as $searchInfo) {
                $arrSearchesParsed [] = array(
                    'savedSearchId' => $searchInfo ['search_id'],
                    'savedSearchName' => $searchInfo ['search_title'],
                    'savedSearchQuery' => $searchInfo ['search_query'],
                );
            }
        }

        $arrResult = array('rows' => $arrSearchesParsed, 'totalCount' => count($arrSearchesParsed));

        return $view->setVariables($arrResult);
    }

    public function saveAction() {
        $view = new JsonModel();
        $booSuccess = false;
        $strMsg = '';
        $filter      = new StripTags();
        $searchId    = (int)$this->findParam('search_id', 0);
        $searchName  = $filter->filter(trim($this->findParam('search_name', '')));
        $searchQuery = $filter->filter(trim($this->findParam('search_query', '')));

        if(!is_numeric($searchId) && !empty($searchId)) {
            $strMsg = $this->_tr->translate('Incorrectly selected search.');
        }

        if(empty($strMsg) && empty($searchName)) {
            $strMsg = $this->_tr->translate('Please enter search name.');
        }

        if(empty($strMsg) && empty($searchQuery)) {
            $strMsg = $this->_tr->translate('Please select fields.');
        }

        if(empty($strMsg)) {
            $booSuccess = $this->_search->saveSearch($searchId, $searchName, $searchQuery);
            $strMsg = $booSuccess ? $this->_tr->translate('Saved successfully.') : $this->_tr->translate('Internal error.');
        }

        $arrResult = array('success' => $booSuccess, 'msg' => $strMsg);
        return $view->setVariables($arrResult);
    }

    public function renameAction() {
        $view = new JsonModel();
        $booSuccess = false;
        $strMsg = '';
        $filter = new StripTags();
        $searchId = (int)$this->findParam('search_id', 0);
        $searchName  = $filter->filter(trim($this->findParam('search_name', '')));

        if(!is_numeric($searchId) || empty($searchId)) {
            $strMsg = $this->_tr->translate('Incorrectly selected search.');
        }

        if(empty($strMsg) && empty($searchName)) {
            $strMsg = $this->_tr->translate('Please enter search name.');
        }

        if(empty($strMsg)) {
            $booSuccess = $this->_search->renameSearch($searchId, $searchName);
            $strMsg = $booSuccess ? $this->_tr->translate('Renamed successfully.') : $this->_tr->translate('Internal error.');
        }

        $arrResult = array('success' => $booSuccess, 'msg' => $strMsg);
        return $view->setVariables($arrResult);
    }

    public function deleteAction() {
        $view = new JsonModel();
        $booSuccess = false;
        $strMsg = '';
        $searchId = (int)$this->findParam('search_id', 0);

        if(!is_numeric($searchId) || empty($searchId)) {
            $strMsg = $this->_tr->translate('Incorrectly selected search.');
        }

        if(empty($strMsg)) {
            $booSuccess = $this->_search->deleteSearch($searchId);
            $strMsg = $booSuccess ? $this->_tr->translate('Deleted successfully.') : $this->_tr->translate('Internal error.');
        }

        $arrResult = array('success' => $booSuccess, 'msg' => $strMsg);
        return $view->setVariables($arrResult);
    }
}