<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Filter\StripTags;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Default Searches Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class DefaultSearchesController extends BaseController
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var StripTags */
    private $_filter;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_filter  = new StripTags();
    }

    public function indexAction()
    {
        $title = $this->_tr->translate('Default Searches');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $searchType = $this->_filter->filter($this->params()->fromQuery('search_type', 'clients'));
        $searchType = in_array($searchType, ['clients', 'contacts']) ? $searchType : 'clients';

        return new ViewModel([
            'searchType' => $searchType
        ]);
    }

    public function getSearchesAction()
    {
        try {
            $searchType         = $this->_filter->filter($this->params()->fromPost('search_type', 'clients'));
            $searchType         = in_array($searchType, array('clients', 'contacts')) ? $searchType : 'clients';
            $arrDefaultSearches = $this->_clients->getSearch()->getCompanySearches(0, array('search_id', 'title'), [$searchType]);

            //strip slashes
            foreach ($arrDefaultSearches as &$search) {
                $search['title'] = stripslashes($search['title'] ?? '');
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess         = false;
            $arrDefaultSearches = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrDefaultSearches,
            'totalCount' => count($arrDefaultSearches)
        );
        return new JsonModel($arrResult);
    }

    public function getViewAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Default Searches');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $searchType = $this->_filter->filter($this->params()->fromQuery('search_type', 'clients'));
        $searchType = in_array($searchType, array('clients', 'contacts')) ? $searchType : 'clients';

        $searchId = $this->params()->fromQuery('search_id', 0);
        $searchId = is_numeric($searchId) ? $searchId : 0;
        $view->setVariable('searchId', (int)$searchId);

        if (!empty($searchId) && !$this->_clients->getSearch()->hasAccessToSavedSearch($searchId)) {
            $searchId = 0;
        }

        $searchName = '';
        if (!empty($searchId)) {
            $arrSavedSearch = $this->_clients->getSearch()->getSearchInfo($searchId);
            $searchName     = $arrSavedSearch['title'];
            $searchType     = $arrSavedSearch['search_type'];
        }
        $view->setVariable('searchName', $searchName);
        $view->setVariable('searchType', $searchType);

        $view->setVariable('arrApplicantsSettings', $this->_clients->getSettings(0, 0, 0));

        $advancedSearchRowsMaxCount = $this->_company->getCompanyDetailsInfo($this->_auth->getCurrentUserCompanyId());
        $advancedSearchRowsMaxCount = (int)$advancedSearchRowsMaxCount['advanced_search_rows_max_count'];

        $view->setVariable('advancedSearchRowsMaxCount', $advancedSearchRowsMaxCount);

        $allowedPages[] = 'templates-view';

        $view->setVariable('allowedPages', $allowedPages);
        $view->setVariable('is_superadmin', $this->_auth->isCurrentUserSuperadmin());

        return $view;
    }

    public function deleteAction()
    {
        $booSuccess = false;
        try {
            $searchId = (int)$this->params()->fromPost('search_id');
            if ($this->_clients->getSearch()->hasAccessToSavedSearch($searchId)) {
                $booSuccess = $this->_clients->getSearch()->delete($searchId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => $booSuccess));
    }
}