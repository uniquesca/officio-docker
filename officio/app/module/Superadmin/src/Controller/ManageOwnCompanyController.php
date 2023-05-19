<?php

namespace Superadmin\Controller;

use Officio\BaseController;

/**
 * Manage Own Company Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageOwnCompanyController extends BaseController
{

    public function indexAction()
    {
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $tab = $this->params()->fromQuery('tab', '');
            $tab = $tab == 'company-packages' ? '#' . $tab : '';
            return $this->redirect()->toUrl('/superadmin/manage-company/edit?' . http_build_query(['company_id' => $this->_auth->getCurrentUserCompanyId()]) . $tab);
        }

        return $this->redirect()->toUrl('/superadmin/manage-company');
    }

    public function visaAction()
    {
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            return $this->redirect()->toUrl('/superadmin/manage-company/edit?' . http_build_query(['company_id' => $this->_auth->getCurrentUserCompanyId(), 'option' => 'visa']));
        } else {
            return $this->redirect()->toUrl('/superadmin/manage-company');
        }
    }
}