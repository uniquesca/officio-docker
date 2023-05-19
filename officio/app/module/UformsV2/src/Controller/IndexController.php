<?php


namespace UformsV2\Controller;

use Exception;
use Laminas\Db\Sql\Select;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Session\SessionManager;
use Laminas\View\Model\ViewModel;
use Officio\Api2\Model\AccessToken;
use Officio\BaseController;
use Officio\Service\AngularApplicationHost;
use Files\Service\Files;
use Forms\Service\Forms;

/**
 * UformsV2 Controller
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var AngularApplicationHost */
    protected $_angularApplicationHost;

    /** @var SessionManager */
    protected $_session;

    /** @var ModuleManager */
    protected $_moduleManager;

    /** @var Forms */
    protected $_forms;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_angularApplicationHost = $services[AngularApplicationHost::class];
        $this->_session                = $services[SessionManager::class];
        $this->_moduleManager          = $services[ModuleManager::class];
        $this->_forms                  = $services[Forms::class];
        $this->_files                  = $services[Files::class];
    }

    public function isModuleEnabled()
    {
        return $this->_config['site_version']['version'] == 'australia' && $this->_moduleManager->getModule('Officio\\AuForms');
    }

    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        if (!$this->isModuleEnabled()) {
            // Officio AuForms module is not loaded
            return $view->setVariables(
                [
                    'uformEnabled' => false
                ]
            );
        }

        $baseUrl                = rtrim($this->layout()->getVariable('baseUrl'), '/');
        $uformsV2ApplicationUrl = $baseUrl . '/uformsv2/new-prototype';

        $html = '<iframe id="angular-application" frameBorder="0" src="' . $uformsV2ApplicationUrl . '" style="flex-grow:1;z-index:110;position:relative;"></iframe>';

        $memberId = $this->_auth->getCurrentUserId();
        return $view->setVariables(
            [
                'uformEnabled' => true,
                'uid'          => $memberId,
                'protocol'     => $this->_config['urlSettings']['protocol'],
                'proto'        => str_replace('://', '', $this->_config['urlSettings']['protocol']),
                'content'      => $html
            ]
        );
    }

    public function newPrototypeAction()
    {
        $assignedId = (int)$this->params()->fromQuery('assignedId', 0);
        $view       = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        if (!$this->isModuleEnabled()) {
            // Officio AuForms module is not loaded
            throw new Exception('AuForms module is not installed.');
        }

        if (!$assignedId) {
            throw new Exception('Select one assigned form.');
        }

        $appPath    = 'public/assets/au-forms';
        $baseUrl    = rtrim($this->layout()->getVariable('baseUrl'), '/');
        $appBaseUrl = $baseUrl . '/assets/au-forms';
        $html       = $this->_angularApplicationHost->getEntryHtml($appPath, $appBaseUrl);

        $accessTokens = AccessToken::loadBySessionId($this->_session->getId());
        if (!$accessTokens) {
            throw new Exception('Could not find access token for this application.');
        }
        $accessToken = reset($accessTokens);

        $select = (new Select())
            ->from('form_assigned')
            ->columns(['form_settings'])
            ->where(['form_assigned_id' => $assignedId]);

        $formSettings = json_decode($this->_db2->fetchOne($select), true);
        $formSettings = array_merge($formSettings, ['uform_id' => $assignedId, 'form_id' => $assignedId]);
        $formSettings = json_encode($formSettings);

        $filePath = $this->getFilePath($assignedId);

        $config = $this->_angularApplicationHost->renderConfigurationScript(
            [
                'access_token'      => $accessToken ? $accessToken->access_token : false,
                'form_assigned_id'  => $assignedId,
                'file_path'         => $filePath,
                'au-config-general' => $formSettings,
                'api_url'           => $baseUrl . '/api2'
            ]
        );

        return $view->setVariables(
            [
                'content' => $config . $html
            ]
        );
    }

    private function getFilePath($assignedId)
    {
        $select = (new Select())
            ->from('form_assigned')
            ->where(['form_assigned_id' => $assignedId]);

        $formAssigned = $this->_db2->fetchRow($select);

        return $this->_files->getClientJsonFilePath(
            $formAssigned['client_member_id'],
            $formAssigned['family_member_id'],
            $formAssigned['form_assigned_id']
        );
    }

}
