<?php

namespace Links\Service;

use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\BaseService;
use Officio\Service\Roles;
use Officio\View\Helper\ImgUrl;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Links extends BaseService
{

    /** @var Members */
    protected $_members;

    /** @var Roles */
    protected $_roles;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    public function initAdditionalServices(array $services)
    {
        $this->_members           = $services[Members::class];
        $this->_roles             = $services[Roles::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
    }

    /**
     * Check if current user has access to the specific link
     * Note that company admin has access to all shared links of the own company
     *
     * @param int $linkId
     * @return bool true if has access
     */
    public function hasAccessToLink($linkId)
    {
        $booHasAccess = false;

        if (!empty($linkId)) {
            $arrLinkInfo = $this->getLink($linkId);

            if (!empty($arrLinkInfo)) {
                if ($arrLinkInfo['member_id'] != $this->_auth->getCurrentUserId()) {
                    if ($this->_auth->isCurrentUserAdmin()) {
                        $arrSharedLinks = $this->getCompanySharedLinks();
                        if (in_array($linkId, $arrSharedLinks)) {
                            $booHasAccess = true;
                        }
                    }
                } else {
                    $booHasAccess = true;
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Load list of company shared links
     * If this is company admin - all links assigned to all "user" roles
     * If this is company user - all links assigned to current user's roles
     *
     * @return array
     */
    private function getCompanySharedLinks()
    {
        if ($this->_auth->isCurrentUserAdmin()) {
            $arrRolesIds = $this->_roles->getCompanyRoles(
                $this->_auth->getCurrentUserCompanyId(),
                $this->_auth->getCurrentUserDivisionGroupId(),
                true,
                true,
                array('user')
            );
        } else {
            $arrRolesIds = $this->_members->getMemberRoles();
        }

        $arrSharedLinksIds = array();
        if (!empty($arrRolesIds)) {
            $select = (new Select())
                ->from('u_links_sharing')
                ->columns(['link_id'])
                ->where(['role_id' => $arrRolesIds]);

            $arrSharedLinksIds = $this->_db2->fetchCol($select);
        }

        return $arrSharedLinksIds;
    }

    /**
     * Generate html (rows only) from the provided array of links
     *
     * @param array $linksList
     * @param bool $booShared
     * @param bool $booCanManage
     * @return string
     */
    public function generateLinksList($linksList, $booShared, $booCanManage)
    {
        /** @var ImgUrl $imgUrl */
        $imgUrl = $this->_viewHelperManager->get('imgUrl');

        $str = '';
        foreach ($linksList as $link) {
            $brgrbtm = 'brgrbtm';

            $str .= '<tr>';
            $str .= '<td align="left" valign="middle" class="footertxt padtopbtm3 ' . $brgrbtm . '"><a href="' . $link['url'] . '" target="_blank" class="blulinkun">' . $link['title'] . '</a></td>';

            if ($booShared) {
                $str .= '<td class="' . $brgrbtm . '" style="font-size: 11px; color: grey; text-align: right; width: 90px" ' . ($booCanManage ? '' : 'colspan="3"') . '>Shared Bookmark</td>';
            } else {
                $str .= '<td class="' . $brgrbtm . '" ' . ($booCanManage ? '' : 'colspan="3"') . '></td>';
            }

            if ($booCanManage) {
                $str .= '<td class="' . $brgrbtm . '" style="text-align: center; width: 20px"><a href="#" onclick="qLinks({action: \'edit\', link_id: ' . $link['link_id'] . ', shareable: ' . ($this->_auth->isCurrentUserAdmin() ? '1' : '0') . '}); return false;"><img src="' . $imgUrl('editicon.gif') . '" alt="Edit Link" title="Edit Link" width="12" height="12" /></a></td>';
                $str .= '<td class="' . $brgrbtm . '" style="text-align: right; width: 12px"><a href="#" onclick="qLinks({action: \'delete\', link_id: ' . $link['link_id'] . '}); return false;"><img src="' . $imgUrl(
                        'deleteicon.gif'
                    ) . '" alt="Delete Link" title="Delete Link" width="11" height="12" /></a></td>';
            }

            $str .= '</tr>';
        }

        return $str;
    }

    /**
     * Generate html table of links for the current user
     *
     * @return string
     */
    public function getLinksList()
    {
        $arrSharedLinks           = array();
        $arrCompanySharedLinksIds = $this->getCompanySharedLinks();
        if (!empty($arrCompanySharedLinksIds)) {
            $select = (new Select())
                ->from('u_links')
                ->where(['link_id' => $arrCompanySharedLinksIds])
                ->order('title');

            $arrSharedLinks = $this->_db2->fetchAll($select);
        }

        $arrWhere              = [];
        $arrWhere['member_id'] = $this->_auth->getCurrentUserId();

        if (!empty($arrCompanySharedLinksIds)) {
            $arrWhere[] = (new Where())->notIn('link_id', $arrCompanySharedLinksIds);
        }

        $select = (new Select())
            ->from('u_links')
            ->where($arrWhere)
            ->order('title');

        $currentUserLinks = $this->_db2->fetchAll($select);

        $str = '<table cellpadding="0" cellspacing="0" width="100%">';
        if (empty($currentUserLinks) && empty($arrSharedLinks)) {
            $str .= '<tr><td align="left" valign="middle" class="footertxt" style="padding-top:2px; padding-bottom:2px;">No Bookmarks found.</td></tr>';
        } else {
            if (!empty($arrSharedLinks)) {
                $str .= $this->generateLinksList($arrSharedLinks, true, $this->_auth->isCurrentUserAdmin());
            }

            if (!empty($currentUserLinks)) {
                $str .= $this->generateLinksList($currentUserLinks, false, true);
            }
        }

        $str .= '</table>';

        return $str;
    }

    /**
     * Load information about the specific link
     *
     * @param int $linkId
     * @return array
     */
    public function getLink($linkId)
    {
        $arrLinkInfo = array();
        if (!empty($linkId)) {
            $select = (new Select())
                ->from('u_links')
                ->where(['link_id' => (int)$linkId]);

            $arrLinkInfo = $this->_db2->fetchRow($select);
        }

        return $arrLinkInfo;
    }

    /**
     * Create/update link
     *
     * @param string $action add/edit
     * @param int $linkId
     * @param string $title
     * @param string $url
     * @param array $arrSharedToRoles
     * @return bool true on success
     */
    public function updateLink($action, $linkId, $title, $url, $arrSharedToRoles)
    {
        if (!preg_match('%^(http|https|ftp)://(.*)$%', $url)) {
            $url = 'http://' . $url;
        }

        $arrLinkInfo = array(
            'title' => $title,
            'url'   => $url
        );

        $memberId = $this->_auth->getCurrentUserId();
        switch ($action) {
            case 'add':
                $arrLinkInfo['member_id'] = $memberId;
                $linkId                   = $this->_db2->insert('u_links', $arrLinkInfo);

                $this->updateLinkSharing($linkId, $arrSharedToRoles);
                $booResult = true;
                break;

            case 'edit':
                $this->_db2->update('u_links', $arrLinkInfo, ['link_id' => (int)$linkId]);

                $this->updateLinkSharing($linkId, $arrSharedToRoles);
                $booResult = true;
                break;

            default:
                $booResult = false;
                break;
        }

        return $booResult;
    }

    /**
     * Delete link
     *
     * @param int $linkId
     * @return bool true on success
     */
    public function deleteLink($linkId)
    {
        try {
            $this->_db2->delete('u_links', ['link_id' => (int)$linkId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
        }

        return $booSuccess;
    }

    /**
     * Update list of roles that link will be shared to
     *
     * @param int $linkId
     * @param array $arrRoles
     */
    public function updateLinkSharing($linkId, $arrRoles)
    {
        $this->_db2->delete('u_links_sharing', ['link_id' => (int)$linkId]);

        foreach ($arrRoles as $roleId) {
            $this->_db2->insert(
                'u_links_sharing',
                [
                    'link_id' => $linkId,
                    'role_id' => $roleId,
                ]
            );
        }
    }

    /**
     * Load list of shared roles' ids for a specific link
     * @param int $linkId
     * @return array
     */
    public function getSharedToRoles($linkId)
    {
        $arrRoleIds = array();

        if (!empty($linkId)) {
            $select = (new Select())
                ->from('u_links_sharing')
                ->columns(['role_id'])
                ->where(['link_id' => (int)$linkId]);

            $arrRoleIds = $this->_db2->fetchCol($select);
        }

        return $arrRoleIds;
    }
}
