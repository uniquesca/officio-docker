<?php

namespace Superadmin\Controller;

use Clients\Service\Members;
use Laminas\Db\Sql\Select;
use Officio\Service\AuthHelper;
use Officio\BaseController;
use stdClass;

/**
 * Manage Company As Admin Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageCompanyAsAdminController extends BaseController
{

    /** @var AuthHelper */
    protected $_authHelper;

    public function initAdditionalServices(array $services)
    {
        $this->_authHelper = $services[AuthHelper::class];
    }

    public function indexAction()
    {
        $companyId = (int)$this->params('company_id', 0);
        $memberId  = (int)$this->params('member_id', 0);

        // We need to be sure that company admin cannot log in as user/admin from another company
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            if (empty($memberId)) {
                $memberId = $this->_auth->getCurrentUserId();
            }
        }

        // Load company's user/admin information
        $objCompanyAdminInfo = $this->_loadMemberInfo($companyId, $memberId, !empty($this->_config['security']['logout_user_when_login_as_company_admin']));
        if ($objCompanyAdminInfo) {
            if ($this->_auth->isCurrentUserSuperadmin() && $objCompanyAdminInfo->is_strict_admin) {
                $memberInfo                               = $this->_members->getMemberInfo();
                $objCompanyAdminInfo->superadmin_as_admin = true;
                $objCompanyAdminInfo->superadmin_name     = $memberInfo['full_name'];
            }
            $this->_auth->getStorage()->clear();
            $this->_auth->getStorage()->write($objCompanyAdminInfo);

            $this->_authHelper->switchSuperAdminAccessToken($objCompanyAdminInfo->member_id);

            return $this->redirect()->toUrl('/');
        } else {
            return $this->redirect()->toUrl('/superadmin/');
        }
    }


    private function _loadMemberInfo($companyId, $memberId, $updateLastLogin)
    {
        if (!empty($companyId)) {
            // Load first found company admin
            $arrWhere = [
                'm.company_id' => (int)$companyId
            ];

            if (!empty($memberId)) {
                $arrWhere['m.member_id'] = (int)$memberId;
            } else {
                $arrWhere['m.userType'] = Members::getMemberType('admin');
            }

            $select = (new Select())
                ->from(['m' => 'members'])
                ->where($arrWhere)
                ->limit(1)
                ->offset(0);

            $result = $this->_db2->fetchRow($select);
            if (!empty($result)) {
                $objAdminInfo = new stdClass();
                foreach ($result as $key => $val) {
                    $objAdminInfo->$key = $val;
                }

                return $this->_authHelper->prepareIdentity($objAdminInfo, $updateLastLogin);
            }
        }

        return false;
    }
}
