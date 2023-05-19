<?php

namespace Superadmin\Controller;

use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * LastLoggedIn Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class LastLoggedInController extends BaseController {

    private function _getRecords($booTrialOnly = false) {
        $select = (new Select())
            ->from(['m' => 'members'])
            ->columns(['member_id', 'fName', 'lName', 'username', 'lastLogin'])
            ->join(['c' => 'company'], 'c.company_id = m.company_id', ['company_id', 'companyName'], Select::JOIN_LEFT_OUTER)
            ->where(['m.userType' => [2, 4]])
            ->order('c.company_id ASC');

        if($booTrialOnly) {
            $select->join(['d' => 'company_details'], 'c.company_id = d.company_id', [],Select::JOIN_LEFT_OUTER)
                   ->where(['d.trial' => 'Y']);
        }
        $result = $this->_db2->fetchAll($select);

        //get companies list
        $companies = array();
        foreach($result as &$p) {
            $company_id = (int) $p['company_id'];
            $companies[$company_id] = (!isset($companies[$company_id]) || $p['lastLogin'] > $companies[$company_id]) ? $p['lastLogin'] : $companies[$company_id];
        }

        foreach($result as &$p) {

            //gen name
            $p['name'] = $p['lName'] . ' ' . $p['fName'];

            //format last login
            $p['lastLogin'] = empty($p['lastLogin']) ? '' : date('Y-m-d', $p['lastLogin']);

            //save last activity
            $lastActivity = $companies[$p['company_id']];
            $p['lastActivity'] = empty($lastActivity) ? '-' : date('m/d/Y', $lastActivity);

            unset($p['fName']);
            unset($p['lName']);
            unset($p['company_id']);
        }

        return array('data' => $result, 'companies_count' => count($companies));
    }

    public function indexAction() {
        $view = new ViewModel();

        $title = $this->_tr->translate('Last Logged In Information');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    public function loadAction() {
        if(!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel();
            $view->setVariable('content', null);
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $booShowOnlyTrial = Json::decode($this->findParam('booShowOnlyTrial'), Json::TYPE_ARRAY);
        $arrResult = $this->_getRecords($booShowOnlyTrial);
        
        return $view->setVariables($arrResult);
    }
}
