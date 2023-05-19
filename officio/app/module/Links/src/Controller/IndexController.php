<?php

namespace Links\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Links\Service\Links;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Service\Roles;

/**
 * Links Index Controller - this controller is used in several cases
 * in Ajax requests
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{
    /** @var Links */
    protected $_links;

    /** @var Company */
    protected $_company;

    /** @var Roles */
    protected $_roles;

    public function initAdditionalServices(array $services)
    {
        $this->_links = $services[Links::class];
        $this->_company = $services[Company::class];
        $this->_roles = $services[Roles::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function getLinksListAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $links = $this->_links->getLinksList();
        $view->setVariable('content', $links);

        return $view;
    }

    public function getLinkAction()
    {
        $view = new JsonModel();

        $booShowSharingBlock = false;

        try {
            $booSuccess = true;

            $linkId = (int)$this->findParam('link_id');
            if (!empty($linkId) && $this->_links->hasAccessToLink($linkId)) {
                $arrLinkInfo = $this->_links->getLink($linkId);
                if (empty($arrLinkInfo)) {
                    $booSuccess = false;
                } else {
                    $arrLinkInfo['shared_to_roles'] = implode(',', $this->_links->getSharedToRoles($linkId));
                }
            } else {
                $arrLinkInfo = array(
                    'link_id' => 0,
                    'title' => '',
                    'url' => 'https://',
                    'shared_to_roles' => ''
                );
            }

            $booShowWithoutGroup = $this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
            if (!$booShowWithoutGroup) {
                // Current user is system - don't show
                $arrDivisionGroupInfo = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
                if (isset($arrDivisionGroupInfo['division_group_is_system'])) {
                    $booShowWithoutGroup = $arrDivisionGroupInfo['division_group_is_system'] == 'N';
                }
            }

            $arrAllRoles = $this->_roles->getCompanyRoles(
                $this->_auth->getCurrentUserCompanyId(),
                $this->_auth->getCurrentUserDivisionGroupId(),
                $booShowWithoutGroup,
                false,
                array('user')
            );

            $arrRoles = array();
            foreach ($arrAllRoles as $arrRoleInfo) {
                if ($arrRoleInfo['role_status'] == 1) {
                    $arrRoles[] = array(
                        'role_id' => $arrRoleInfo['role_id'],
                        'role_name' => $arrRoleInfo['role_name'],
                    );
                }
            }

            $booShowSharingBlock = $this->_auth->isCurrentUserAdmin();
        } catch (Exception $e) {
            $booSuccess = false;
            $arrLinkInfo = array();
            $arrRoles = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'link' => $arrLinkInfo,
            'arrRoles' => $arrRoles,
            'showSharingBlock' => $booShowSharingBlock,
        );

        return $view->setVariables($arrResult);
    }

    private function updateLink($action)
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $linkId = (int)$this->findParam('link_id');
            $title = $filter->filter(trim(Json::decode($this->findParam('title', ''), Json::TYPE_ARRAY)));
            $url = $filter->filter(trim(Json::decode($this->findParam('url', ''), Json::TYPE_ARRAY)));
            $strSharedToRoles = $filter->filter(trim(Json::decode($this->findParam('shared_to_roles', ''), Json::TYPE_ARRAY)));

            if (empty($strError) && empty($title)) {
                $strError = $this->_tr->translate('Label is a required field.');
            }

            if (empty($strError) && empty($url)) {
                $strError = $this->_tr->translate('URL is a required field.');
            }

            $arrSharedToRoles = array();

            // Only company admin can change/assign roles
            if (empty($strError) && !empty($strSharedToRoles) && $this->_auth->isCurrentUserAdmin()) {
                $arrSharedToRoles = explode(',', $strSharedToRoles);


                $arrAllRolesIds = $this->_roles->getCompanyRoles(
                    $this->_auth->getCurrentUserCompanyId(),
                    $this->_auth->getCurrentUserDivisionGroupId(),
                    true,
                    true,
                    array('user')
                );

                foreach ($arrSharedToRoles as $checkRoleId) {
                    if (!in_array($checkRoleId, $arrAllRolesIds)) {
                        $strError = $this->_tr->translate('Incorrectly selected role.');
                        break;
                    }
                }
            }

            // Finally check access rights
            // Company admin can edit shared links
            if (empty($strError) && !empty($linkId) && !$this->_links->hasAccessToLink($linkId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError) && !$this->_links->updateLink($action, $linkId, $title, $url, $arrSharedToRoles)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => empty($strError), 'msg' => $strError);
    }

    public function addAction()
    {
        $view = new JsonModel();

        return $view->setVariables($this->updateLink('add'));
    }

    public function editAction()
    {
        $view = new JsonModel();

        return $view->setVariables($this->updateLink('edit'));
    }

    public function deleteAction()
    {
        $view = new JsonModel();

        $booResult = false;

        try {
            $linkId = (int)$this->findParam('link_id');
            if ($this->_links->hasAccessToLink($linkId)) {
                $booResult = $this->_links->deleteLink($linkId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booResult));
    }
}
