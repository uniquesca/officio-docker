<?php

namespace Superadmin\Controller;

use Laminas\Filter\StripTags;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Encryption;

/**
 * SMTP Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SmtpController extends BaseController
{

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_encryption = $services[Encryption::class];
    }


    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Mail Server Settings');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $filter = new StripTags();

        $on       = $this->findParam('smtp_on');
        $server   = $this->findParam('server');
        $port     = $this->findParam('port');
        $username = $this->findParam('username');
        $password = $this->findParam('password');
        $ssl      = $this->findParam('ssl');

        if (is_array($server) && !empty($server))
        {
            foreach (array_keys($server) as $smtpId)
            {
                $data = array(
                    'smtp_on'       => (isset($on[$smtpId]) && $on[$smtpId] == 'on') ? 'Y' : 'N',
                    'smtp_server'   => $filter->filter($server[$smtpId]),
                    'smtp_port'     => (int)$filter->filter($port[$smtpId]),
                    'smtp_username' => $filter->filter($username[$smtpId]),
                    'smtp_password' => $this->_encryption->encode($filter->filter($password[$smtpId])),
                    'smtp_use_ssl'  => $filter->filter($ssl[$smtpId])
                );

                if(empty($data['smtp_password'])) {
                    unset($data['smtp_password']);
                }

                $this->_settings->updateSuperadminSMTPSettings($smtpId, $data);
            }
        }

        $smtp             = $this->_settings->getSuperadminSMTPSettings(0, false);
        $smtp[0]['title'] = 'SMTP Mail Server Settings for Officio Business Use (e.g. support email, Sign up confirmation etc) ';
        $smtp[1]['title'] = 'SMTP Mail Server Settings for User’s Temporary Use';
        $smtp[2]['title'] = 'SMTP Mail Server Settings for Users Daily Notifications';

        $view->setVariable('smtp', $smtp);

        return $view;
    }
}