<?php

namespace Officio;

use Clients\Service\Members;
use Forms\Controller\Plugin\XfdfProcessor;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\AuthenticationService;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\Response\Stream;
use Laminas\I18n\Translator\Translator;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\ViewModel;
use Officio\Controller\Plugin\ParamsFromPostOrGet;
use Officio\Common\Service\Acl;
use Officio\Common\Service\Log;
use Officio\Common\Service\Settings;
use SimpleXMLElement;

/**
 * BaseController - The default controller class that is used as a parent for all controllers
 * @package Officio
 * @method ResponseInterface file(string $strFileContent, string $fileName, string $fileMime = '', bool $booEnableCache = false, bool $booAsAttachment = true, bool $booReturnFileDownloadCookie = false)
 * @method Stream downloadFile(string $filePath, string $fileName = '', string $fileMime = '', bool $booEnableCache = false, bool $booAsAttachment = true, bool $booReturnFileDownloadCookie = false)
 * @method ResponseInterface fileNotFound()
 * @method mixed|ParamsFromPostOrGet paramsFromPostOrGet($param = null, $default = null) DEPRECATED: Use either POST or GET method, not both in the new code
 * @method array urlChecker(string $url)
 * @method int|array xfdfPreprocessor(SimpleXMLElement &$XMLData, bool $print = false, $pdfId = false)
 * @method XfdfProcessor xfdfProcessor()
 * @author Uniques Software Corp.
 * @copyright Uniques
 */
class BaseController extends AbstractActionController
{

    /** @var array */
    protected $_config;

    /** @var DbAdapterWrapper */
    protected $_db2;

    /** @var AuthenticationService */
    protected $_auth;

    /** @var Acl */
    protected $_acl;

    /** @var Translator */
    protected $_tr;

    /** @var Log */
    protected $_log;

    /** @var StorageInterface */
    protected $_cache;

    /** @var ServiceManager */
    protected $_serviceManager;

    /** @var Settings */
    protected $_settings;

    /** @var Members */
    protected $_members;

    /**
     * Looks for param in POST and if not found - in GET
     * @param $param
     * @param null $default
     * @return mixed
     * @deprecated Withdraw from using this method in the new code - pass params either via GET or POST explicitly
     */
    protected function findParam($param, $default = null) {
        return $this->paramsFromPostOrGet($param, $default);
    }

    /**
     * Looks for all params in GET and POST
     * @return array
     * @deprecated Withdraw from using this method in the new code
     */
    protected function findParams() {
        return array_merge($this->params()->fromPost(), $this->params()->fromQuery());
    }

    /**
     * BaseController constructor.
     * @param array $config
     * @param ServiceManager $serviceManager
     * @param DbAdapterWrapper $db2
     * @param AuthenticationService $auth
     * @param Acl $acl
     * @param StorageInterface $cache
     * @param Log $log
     * @param Translator $translator
     * @param Settings $settings
     * @param Members $members
     */
    public function __construct(
        array $config,
        ServiceManager $serviceManager,
        DbAdapterWrapper $db2,
        AuthenticationService $auth,
        Acl $acl,
        StorageInterface $cache,
        Log $log,
        Translator $translator,
        Settings $settings,
        Members $members
    ) {
        $this->_config         = $config;
        $this->_serviceManager = $serviceManager;
        $this->_db2            = $db2;
        $this->_auth           = $auth;
        $this->_acl            = $acl;
        $this->_cache          = $cache;
        $this->_log            = $log;
        $this->_tr             = $translator;
        $this->_settings       = $settings;
        $this->_members        = $members;
    }

    /**
     * This is a helper method which is basically part of __construct(), however easier to inherit from.
     * Note: this method is called from the fabric, not the constructor itslef, therefore we avoid listing all
     * the basic service dependencies, all additional ones.
     */
    public function initAdditionalServices(array $services) {
        // Use this method for initializing additional controller properties
    }

    /**
     * This is a helper method which is basically part of __construct(), however easier to inherit from.
     * Note: this method is called from the fabric, not the constructor itslef, therefore we avoid listing all
     * the basic service dependencies, all additional ones.
     */
    public function init() {

    }

    /**
     * Helper method for rendering error in a plain template
     * @param string $error
     * @return ViewModel
     */
    public function renderError($error)
    {
        $view = new ViewModel(['content' => $error]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    /**
     * Returns server manager
     * @return ServiceManager
     */
    public function getServiceManager() {
        return $this->_serviceManager;
    }

}
