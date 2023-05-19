<?php

namespace Officio;

use Clients\Service\BusinessHours;
use Clients\Service\Clients;
use Clients\Service\Clients\Accounting;
use Clients\Service\Members;
use csrfProtector;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\EventManager\EventInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\ModuleManager\Feature\BootstrapListenerInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Helper\HeadLink;
use Laminas\View\Helper\HeadScript;
use Laminas\View\HelperPluginManager;
use Officio\Common\AssetsProviderInterface;
use Officio\Common\ComposerEventProviderInterface;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\InitializableListener;
use Officio\Common\MigrationsProviderInterface;
use Officio\Common\Service\Acl;
use Officio\Common\Service\AuthenticationService;
use Officio\Service\AuthHelper;
use Officio\Service\AutomaticReminders;
use Officio\Common\Service\Log;
use Officio\Common\Service\Settings;
use Officio\Service\Company;
use Officio\Service\Statistics;
use Officio\Service\SystemTriggersListener;
use Officio\Service\Users;
use Officio\Service\ZohoKeys;
use Officio\Templates\SystemTemplates;
use Uniques\Php\StdLib\FileTools;

class Module implements
    ConfigProviderInterface,
    BootstrapListenerInterface,
    SystemTriggersListener,
    ComposerEventProviderInterface,
    InitializableListener
{

    /**
     * Register a listener for the mergeConfig event.
     * @param ModuleManager $moduleManager
     */
    public function init(ModuleManager $moduleManager)
    {
        $events = $moduleManager->getEventManager();
        $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, [$this, 'onMergeConfig']);
    }


    /**
     * @inheritdoc
     */
    public function getListeners(string $class)
    {
        $listeners = [
            SystemTemplates::class => [
                SystemTemplates::EVENT_GET_AVAILABLE_FIELDS => [Company::class, Users::class]
            ]
        ];
        return $listeners[$class] ?? [];
    }

    /**
     * @param ModuleEvent $e
     */
    public function onMergeConfig(ModuleEvent $e)
    {
        $configListener = $e->getConfigListener();
        $config         = $configListener->getMergedConfig(false);
        if (!isset($config['phinx'])) {
            return;
        }

        /** @var ServiceManager $serviceManager */
        $serviceManager = $e->getParam('ServiceManager');

        // Adding phinx migration paths
        /** @var ModuleManager $moduleManager */
        $moduleManager   = $serviceManager->get('ModuleManager');
        $migrationsPaths = $this->aggregateMigrationPaths($moduleManager);
        $path            = (isset($config['phinx']['migrations_path'])) ? $config['phinx']['migrations_path'] : '';
        if (!is_array($path)) {
            $path = [$path];
        }
        $path                               = array_merge($path, $migrationsPaths);
        $path                               = array_filter(array_map('realpath', $path));
        $config['phinx']['migrations_path'] = $path;

        // Filtering out API2 routes if API2 module is not loaded
        // Otherwise router will throw an error
        $api2ModuleLoaded = $moduleManager->getModule('Officio\\Api2');
        if (!$api2ModuleLoaded) {
            unset($config['router']['routes']['api2']);
        }

        // Adding URL settings
        $booSecure = false;
        if ($config['site_version']['always_secure']) {
            $booSecure = true;
        } elseif ($config['site_version']['proxy']['enabled']) {
            $proxiedProto = !empty($config['site_version']['proxy']['forwarded_proto']) ? $config['site_version']['proxy']['forwarded_proto'] : false;
            if (!$proxiedProto) {
                $proxiedProto = !empty($_SERVER[$config['site_version']['proxy']['forwarded_proto_header']]) ? $_SERVER[$config['site_version']['proxy']['forwarded_proto_header']] : 'http';
            }

            $booSecure = $proxiedProto == 'https';
        } elseif (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') {
            $booSecure = true;
        } elseif (isset($_SERVER['SERVER_PORT']) && in_array($_SERVER['SERVER_PORT'], array(443, 444))) {
            $booSecure = true;
        }

        $topUrl   = $config['site_version']['officio_domain_secure'];
        $protocol = $booSecure ? 'https://' : 'http://';
        $baseUrl  = $protocol . $topUrl;

        // Fix issue when index.php is in the url
        $baseUrl               = str_replace('/index.php', '', $baseUrl);
        $config['urlSettings'] = array('protocol' => $protocol, 'topUrl' => $topUrl, 'baseUrl' => $baseUrl);

        $configListener->setMergedConfig($config);
    }

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /**
     * Fills phinx config with migration paths gathered from modules
     * @param ModuleManager $moduleManager
     * @return string[] List of migration paths
     */
    public function aggregateMigrationPaths(ModuleManager $moduleManager)
    {
        $paths   = [];
        $modules = $moduleManager->getLoadedModules();
        foreach ($modules as $module) {
            if (!$module instanceof MigrationsProviderInterface) {
                continue;
            }
            $migrationPath = $module->getMigrationsPath();
            if (is_string($migrationPath)) {
                $migrationPath = [$migrationPath];
            }
            $paths = array_merge($paths, $migrationPath);
        }
        return $paths;
    }

    public function onBootstrap(EventInterface $e)
    {
        // $e is an instance of MvcEvent always
        /** @var MvcEvent $e */
        $application  = $e->getApplication();
        $eventManager = $application->getEventManager();

        // Attach render errors
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'onError'], 100);

        // Attach dispatch errors
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'onError'], 100);

        // Attach dispatch event
        // These must be processed first (the priority is from highest to the lowest)
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'checkIsSSL'], 560);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'debugMemory'], 550);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'checkIsOffline'], 540);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'checkLastLoginTime'], 530);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'toggleCsrfProtection'], 520);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'checkTimeoutHandler'], 510);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'checkBusinessHours'], 505);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'checkAccessRights'], 500);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'initLayout'], 110);

        // Initializing various components
        $this->initPhpSettings($e);
        $this->initTimeZone($e);
        $this->initLanguage($e);
        $this->initMainLayoutVariables($e);
    }

    /**
     * Initialize layout and variables
     * @param MvcEvent $e
     */
    public function initLayout(MvcEvent $e)
    {
        // TODO Not all the services here are initialized
        $serviceManager = $e->getApplication()->getServiceManager();

        /** @var Acl $acl */
        $acl = $serviceManager->get('acl');
        /** @var AuthenticationService $auth */
        $auth = $serviceManager->get('auth');
        /** @var array $config */
        $config = $serviceManager->get('config');
        /** @var Settings $settings */
        $settings = $serviceManager->get(Settings::class);
        /** @var Clients $clients */
        $clients = $serviceManager->get(Clients::class);
        /** @var ZohoKeys $zohoKeys */
        $zohoKeys = $serviceManager->get(ZohoKeys::class);

        $layout = $e->getViewModel();

        $baseUrl   = $layout->getVariable('baseUrl');
        $staticUrl = $layout->getVariable('staticUrl');


        $layout->setVariable('zoho_enabled', $zohoKeys->isZohoEnabled());
        $layout->setVariable('inline_manual_api_key', $config['inline_manual']['api_key']);
        $layout->setVariable('calendly_enabled', $config['calendly']['enabled']);
        $layout->setVariable('dropbox_app_id', $config['dropbox']['app_id']);
        $layout->setVariable('google_drive_app_id', $config['google_drive']['app_id']);
        $layout->setVariable('google_drive_client_id', $config['google_drive']['client_id']);
        $layout->setVariable('google_drive_api_key', $config['google_drive']['api_key']);

        $arrFroalaSettings = array(
            'key'               => isset($config['html_editor']['froala_license_key']) && !empty($config['html_editor']['froala_license_key']) ? $config['html_editor']['froala_license_key'] : '',
            'supported_formats' => Files::SUPPORTED_IMAGE_FROMATS,
            'image_max_size'    => Files::MAX_UPLOAD_IMAGE_SIZE
        );
        $layout->setVariable('froala_settings', $arrFroalaSettings);

        $layout->setVariable('acl', $acl);
        $layout->setVariable('config', $config);
        $layout->setVariable('settings', $settings);
        $layout->setVariable('auth', $auth);

        list($module, $controller, $action) = Settings::getModuleControllerAction($e);
        if ($module == 'superadmin') {
            $baseUrl   .= '/superadmin';
            $staticUrl .= '/superadmin';

            // Overwriting default paths
            $layout->setVariable('baseUrl', $baseUrl);
            $layout->setVariable('jsUrl', $baseUrl . '/js');
            $layout->setVariable('cssUrl', $baseUrl . '/styles');
            $layout->setVariable('imagesUrl', $baseUrl . '/images');
            $layout->setVariable('staticUrl', $staticUrl);
        }

        // Used in the webmail
        defined('SITEROOTURL') or define('SITEROOTURL', $baseUrl);
        defined('SITEROOTPATHADMIN') or define("SITEROOTPATHADMIN", $baseUrl);

        $layout->setVariable('date_format_short', $settings->variableGet("dateFormatShort"));
        $layout->setVariable('date_format_full', $settings->variableGet("dateFormatFull"));
        $layout->setVariable('today_date', date($settings->variableGet("dateFormatShort")));

        $layout->setVariable('company_timezone', $auth->getCurrentUserCompanyTimezone());
        $layout->setVariable('current_member_company_name', $auth->getCurrentUserCompanyName());
        $layout->setVariable('current_member_id', $auth->getCurrentUserId());
        $layout->setVariable('curr_member_name', $clients->getCurrentMemberName());
        $layout->setVariable('is_administrator', $auth->isCurrentUserAdmin() ? 1 : 0);

        $logoFileName = 'logo';
        if ($module === 'officio') {
            $serviceManager = $e->getApplication()->getServiceManager();
            /** @var HelperPluginManager $viewHelper */
            $viewHelper = $serviceManager->get('ViewHelperManager');
            $headScript = $viewHelper->get('headScript');
            $headLink   = $viewHelper->get('headLink');
            $headMeta   = $viewHelper->get('headMeta');

            // Setting doc type
            $viewHelper->get('Doctype')->setDoctype('XHTML5');

            // Setting content type and character set
            $headMeta->appendHttpEquiv('Content-Type', 'text/html; charset=UTF-8');
            $headMeta->appendHttpEquiv('Content-Language', 'en-US');
            $headMeta->appendHttpEquiv('X-UA-Compatible', 'IE=Edge');

            // Setting links in a view script:
            $headLink->appendStylesheet($layout->getVariable('cssUrl') . '/main.css');
            $headLink->appendStylesheet($layout->getVariable('topBaseUrl') . '/assets/plugins/line-awesome/dist/line-awesome/css/line-awesome.min.css');
            $headLink->appendStylesheet($layout->getVariable('cssUrl') . '/themes/' . $layout->getVariable('theme') . '.css');

            $headLink->appendStylesheet($layout->getVariable('cssUrl') . '/ie_fix.css', 'screen', 'IE');
            $headLink->appendStylesheet($layout->getVariable('topBaseUrl') . '/assets/plugins/jquery-ui/themes/base/theme.css');

            if ($auth->isCurrentUserClient()) {
                $headLink->appendStylesheet($layout->getVariable('topCssUrl') . '/client.css');
            }

            $headScript->appendFile($layout->getVariable('jsUrl') . '/leftpanecontrol.js');
            $headScript->appendFile($layout->getVariable('jsUrl') . '/main.js');
            $headScript->appendFile($layout->getVariable('topJsUrl') . '/iframe.js');
            $headScript->appendFile($layout->getVariable('jsUrl') . '/user-main.js');

            if (!empty($config['dropbox']['app_id'])) {
                $headScript->appendFile('https://www.dropbox.com/static/api/2/dropins.js', 'text/javascript', ['minify_disabled' => true, 'weight' => 50]);
            }

            if (!empty($config['google_drive']['api_key'])) {
                $headScript->appendScript("window.___gcfg = {parsetags: 'explicit'};");
                $headScript->appendFile('https://apis.google.com/js/platform.js', 'text/javascript', ['minify_disabled' => true, 'weight' => 50]);
            }

            /** @var Response $response */
            $response = $e->getResponse();
            if ($response->getStatusCode() === Response::STATUS_CODE_200) {
                switch ($controller) {
                    case 'auth':
                        if ($action == 'oauth-callback') {
                            $layout->setTemplate('layout/api');
                        } else {
                            $layout->setTemplate('auth/login');
                        }
                        $logoFileName = 'logo_login';
                        break;

                    case 'error':
                        $layout->setTemplate('layout/error');
                        break;

                    default:
                        $layout->setTemplate('layout/layout');
                        break;
                }
            } else {
                $layout->setTemplate('layout/error');
            }
        } elseif ($module === 'mailer' && $controller === 'settings' && $action === 'oauth-callback') {
            $layout->setTemplate('layout/api');
        } elseif ($module === 'superadmin') {
            $logoFileName = 'logo_superadmin';
        }

        switch (date('m')) {
            case 1:
                // New Year, ho-ho-ho!!!
                if (date('d') >= 1 && date('d') <= 10) {
                    $logoFileName .= '_new_year';
                }
                break;

            case 12:
                // Xmas
                if (date('d') >= 10 && date('d') <= 31) {
                    $logoFileName .= '_xmas';
                }
                break;

            default:
                break;
        }

        $logoFileName .= '.png';

        $layout->setVariable('logoFileName', $logoFileName);
    }

    /**
     * Initialize main layout/view variables
     *
     * @param MvcEvent $e
     */
    private function initMainLayoutVariables(MvcEvent $e)
    {
        $serviceManager = $e->getApplication()->getServiceManager();
        $config         = $serviceManager->get('config');
        $layout         = $e->getViewModel();

        /** @var HelperPluginManager $viewHelper */
        $viewHelper = $serviceManager->get('ViewHelperManager');
        /** @var Settings $settings */
        $settings = $serviceManager->get(Settings::class);
        /** @var HeadScript $headScript */
        $headScript = $viewHelper->get('headScript');
        /** @var HeadLink $headLink */
        $headLink = $viewHelper->get('headLink');

        $protocol = $config['urlSettings']['protocol'];
        $baseUrl  = $topBaseUrl = $config['urlSettings']['baseUrl'];

        $layout->setVariable('jsParentUrl', $baseUrl . '/js');

        $faviconPath = $baseUrl . '/images/favicon.ico';
        $headLink->headLink(
            array(
                'rel'  => 'shortcut icon',
                'type' => 'image/x-icon',
                'href' => $faviconPath
            ),
            'PREPEND'
        );

        $headLink->headLink(
            array(
                'rel'  => 'icon',
                'type' => 'image/x-icon',
                'href' => $faviconPath
            ),
            'PREPEND'
        );

        // Different site versions settings
        $layout->setVariable('site_version', $config['site_version']['version']);
        $layout->setVariable('site_company_name', $config['site_version']['company_name']);
        $layout->setVariable('site_company_phone', $config['site_version']['company_phone']);
        $layout->setVariable('site_currency', $config['site_version']['currency']);
        $layout->setVariable('site_currency_label', Accounting::getCurrencyLabel($config['site_version']['currency'], false));
        $layout->setVariable('site_password_regex', $settings->getPasswordRegex());
        $layout->setVariable('site_password_regex_message', $settings->getPasswordRegex(true));
        $layout->setVariable('officio_domain', $config['site_version']['officio_domain']);
        $layout->setVariable('officio_domain_secure', $config['site_version']['officio_domain_secure']);
        $layout->setVariable('site_top_warning_message', $config['site_version']['top_warning_message']);

        // Url for static content
        // e.g. static1.officio.ca
        $staticUrl = $protocol . $config['site_version']['officio_domain_static'];

        $booUseGeneralImgUrl = false;

        //theming
        $theme = $config['theme'];
        $theme = empty($theme) ? 'default' : $theme;
        $layout->setVariable('theme', $theme);
        $layout->setVariable('imgThemeUrl', $baseUrl . '/images/' . $theme . '/');

        // @NOTE: now for https is turned off because related certificates must be created
        $booUseStatic = (bool)$config['site_version']['officio_domain_use_static'];
        if (!$booUseStatic || $protocol == 'https://') {
            $staticUrl           = $baseUrl;
            $booUseGeneralImgUrl = true;
        }

        // Default paths
        $layout->setVariable('baseUrl', $baseUrl);
        $layout->setVariable('jsUrl', $baseUrl . '/js');
        $layout->setVariable('cssUrl', $baseUrl . '/styles');
        $layout->setVariable('imagesUrl', $baseUrl . '/images');

        $layout->setVariable('staticUrl', $staticUrl);
        $layout->setVariable('booUseGeneralImgUrl', $booUseGeneralImgUrl);

        // Related to server settings,
        // Now there are 3 created virtual hosts:
        // static1.officio.ca, static2.officio.ca, static3.officio.ca
        $layout->setVariable('staticAliasesCount', 3);

        $layout->setVariable('topBaseUrl', $topBaseUrl);
        $layout->setVariable('topJsUrl', $topBaseUrl . '/js');
        $layout->setVariable('topCssUrl', $topBaseUrl . '/styles');
        $layout->setVariable('topImagesUrl', $topBaseUrl . '/images');
        $layout->setVariable('extJsUrl', $topBaseUrl . '/js/ext');
        $layout->setVariable('jqueryUrl', $topBaseUrl . '/js/jquery');
        $layout->setVariable('minUrl', $topBaseUrl . '/min');
        $layout->setVariable('assetsUrl', $topBaseUrl . '/assets/plugins/');

        $headScript->prependFile($layout->getVariable('topJsUrl') . '/gettext.js');

        // Froala editor + plugins
        $headScript->prependFile($topBaseUrl . '/assets/plugins/html2pdf.js/dist/html2pdf.bundle.min.js');
        $headScript->prependFile($topBaseUrl . '/assets/plugins/froala-editor/js/third_party/font_awesome.min.js');
        $headLink->prependStylesheet($topBaseUrl . '/assets/plugins/froala-editor/css/froala_editor.pkgd.min.css');
        $headScript->prependFile($topBaseUrl . '/assets/plugins/froala-editor/js/froala_editor.pkgd.min.js');

        // Setting title
        $siteTitle = $config['site_version']['title'];
        $layout->setVariable('siteTitle', $siteTitle);
        $viewHelper->get('HeadTitle')->setSeparator(' :: ')->append($siteTitle);

        $officioBaseUrl = $topBaseUrl . '/officio';
        $layout->setVariable('officioImagesUrl', $officioBaseUrl . '/images');
        $layout->setVariable('officioCssUrl', $officioBaseUrl . '/css');
        $layout->setVariable('officioJsUrl', $officioBaseUrl . '/js');
        $layout->setVariable('officioBaseUrl', 'https://' . $config['site_version']['officio_domain']);
    }

    /**
     * Initialize Cross Site Request Forgery protection
     * Don't enable if:
     *  - setting isn't enabled in the config file
     *  - the script was run from the CLI
     *  - our custom header was passed (e.g. GV API calls)
     *
     * @param MvcEvent $e
     * @return void
     */
    public function toggleCsrfProtection(MvcEvent $e)
    {
        $booEnable = true;

        $serviceManager = $e->getApplication()->getServiceManager();

        $config = $serviceManager->get('config');
        if (!$config['security']['csrf_protection']['enabled']) {
            // Don't enable if CSRF isn't enabled in the config
            $booEnable = false;
        }

        if ($booEnable && FileTools::isCli()) {
            // Don't enable if called from the CLI (not in browser)
            $booEnable = false;
        }

        if ($booEnable) {
            /** @var Request $request */
            $request = $e->getRequest();
            $header  = $request->getHeader('X-Officio');

            // Don't enable if there is X-Officio header (e.g. when GOV API is used)
            $booEnable = empty($header);
        }

        if ($booEnable) {
            /** @var AuthenticationService $auth */
            $auth = $serviceManager->get('auth');
            if ($auth->isCurrentUserSuperadminMaskedAsAdmin()) {
                // Don't enable for the superadmin logged in as admin/user
                // Otherwise, the logged in admin/user will be logged out automatically
                $booEnable = false;
            }
        }

        list($module, $controller, $action) = Settings::getModuleControllerAction($e);
        if ($booEnable) {
            // Don't enable for specific urls
            if (($module == 'documents' && $controller == 'index' && $action == 'save-file') ||
                ($module == 'prospects' && $controller == 'index' && $action == 'save-file') ||
                ($module == 'templates' && $controller == 'index' && $action == 'save-letter-template-file') ||
                ($module == 'qnr' && $controller == 'index' && $action == 'index') ||
                ($module == 'qnr' && $controller == 'index' && $action == 'get-noc-url-by-code') ||
                ($module == 'qnr' && $controller == 'index' && $action == 'search') ||
                ($module == 'qnr' && $controller == 'index' && $action == 'save') ||
                ($module == 'signup' && $controller == 'index' && $action == 'step3') ||
                ($module == 'signup' && $controller == 'index' && $action == 'payment') ||
                ($module == 'signup' && $controller == 'index' && $action == 'payment-submit') ||
                ($module == 'api' && $controller == 'index' && $action == 'get-prices') ||
                ($module == 'help' && $controller == 'public')) {
                $booEnable = false;
            }
        }

        if ($booEnable) {
            require __DIR__ . '/../../../config/csrf.config.php';
            $csrfConfig = generateCsrfConfig($config);

            // Regenerate CSRF token on the login page + index (home page) for both superadmin and default modules
            if (($module == 'officio' && $controller == 'auth') ||
                ($module == 'officio' && $controller == 'index' && $action == 'index') ||
                ($module == 'superadmin' && $controller == 'auth') ||
                ($module == 'superadmin' && $controller == 'index' && $action == 'home')) {
                $csrfConfig['refreshTokenOnSuccess'] = true;
            } else {
                $csrfConfig['refreshTokenOnSuccess'] = false;
            }

            csrfProtector::init($csrfConfig);
        }
    }

    /**
     * Initialize Time Zone
     *
     * @param MvcEvent $e
     * @return void
     */
    private function initTimeZone(MvcEvent $e)
    {
        $config = $e->getApplication()->getServiceManager()->get('config');
        // TODO Move this to config initialization
        date_default_timezone_set($config['translator']['timezone']);
    }

    /**
     * Initialize PHP settings
     *
     * @param MvcEvent $e
     * @return void
     */
    private function initPhpSettings(MvcEvent $e)
    {
        $config = $e->getApplication()->getServiceManager()->get('config');

        if (isset($config['settings']['error_reporting'])) {
            error_reporting($config['settings']['error_reporting']);
        }
        ini_set('display_startup_errors', $config['settings']['display_startup_errors']);
        ini_set('display_errors', $config['settings']['display_errors']);
    }

    private function _langToLocale($lang)
    {
        $map = [
            'en' => 'en_US',
            'fr' => 'fr_FR'
        ];

        return $map[$lang] ?? $map['en'];
    }

    /**
     * Initialize Language
     * @param MvcEvent $e
     */
    private function initLanguage(MvcEvent $e)
    {
        $serviceManager = $e->getApplication()->getServiceManager();
        $config         = $serviceManager->get('config');
        $fallbackLocale = $config['translator']['locale'];
        $locale         = isset($_COOKIE['lang']) ? $this->_langToLocale($_COOKIE['lang']) : $fallbackLocale;

        // Save current lang and locale
        $view = $e->getViewModel();
        $view->setVariable('current_locale', $locale);

        /** @var HelperPluginManager $viewHelper */
        $viewHelper   = $serviceManager->get('ViewHelperManager');
        $pluralHelper = $viewHelper->get('Plural');
        // "Plural rule" can be different for each language and must be set
        // Check here: https://docs.laminas.dev/laminas-i18n/view-helpers/plural/
        switch ($locale) {
            case 'fr_FR':
                $pluralHelper->setPluralRule('nplurals=2; plural=(n==0 || n==1 ? 0 : 1)');
                break;

            default:
                $pluralHelper->setPluralRule('nplurals=2; plural=(n==1 ? 0 : 1)');
                break;
        }
    }

    /**
     * Check access rights via ACL
     *
     * @param MvcEvent $e
     * @return ResponseInterface
     * @throws Exception
     */
    public function checkAccessRights(MvcEvent $e)
    {
        $serviceManager = $e->getApplication()->getServiceManager();
        /** @var Acl $acl */
        $acl    = $serviceManager->get('acl');
        $result = $acl->preDispatch($e);
        if ($result) {
            return $result;
        }
    }

    /**
     * Check if "business hours" allow access for the current user
     * @param MvcEvent $e
     * @return ResponseInterface
     */
    public function checkBusinessHours(MvcEvent $e)
    {
        $serviceManager = $e->getApplication()->getServiceManager();

        /** @var Members $members */
        $members = $serviceManager->get(Members::class);

        /** @var BusinessHours $businessHours */
        $businessHours = $serviceManager->get(BusinessHours::class);

        /** @var AuthenticationService $auth */
        $auth = $serviceManager->get('auth');

        if ($auth->hasIdentity()) {
            list($module, $controller, $action) = Settings::getModuleControllerAction($e);

            // Make sure that user was logged out
            $memberId = $auth->getIdentity()->member_id;
            if (!$businessHours->areUserBusinessHoursNow($memberId)) {
                $members->checkMemberAndLogout($memberId, 'business hours');
                $auth->clearIdentity();
            }

            // If the user has no identity here, there has either been a time-out or the user has
            // not logged in yet.
            if (!$auth->hasIdentity()) {
                $routeName = $module == 'superadmin' ? 'superadmin_login' : 'login';

                $arrRouteParams = [
                    'logged_out'      => 'business_hours',
                    'logged_out_from' => $module . '_' . $controller . '_' . $action
                ];

                // Redirect the user to the "Login" page.
                /** @var Response $response */
                $response = $e->getResponse();
                $response->getHeaders()->addHeaderLine('Location', $e->getRouter()->assemble($arrRouteParams, array('name' => $routeName)));
                $response->setStatusCode(302);
                $e->stopPropagation();

                return $response;
            }
        }
    }

    /**
     * Check session timeout and prolong on page refresh
     * @param MvcEvent $e
     * @return ResponseInterface
     */
    public function checkTimeoutHandler(MvcEvent $e)
    {
        /** @var Request $request */
        $request = $e->getRequest();

        /** @var Members $members */
        $members = $e->getApplication()->getServiceManager()->get(Members::class);

        /** @var array $config */
        $config = $e->getApplication()->getServiceManager()->get('config');

        /** @var AuthHelper $autgh */
        $auth = $e->getApplication()->getServiceManager()->get('auth');

        if ($config['security']['session_timeout'] > 0 && $auth->hasIdentity()) {
            list($module, $controller, $action) = Settings::getModuleControllerAction($e);

            // Clear the identity of a user who has not accessed a controller for
            // longer than a timeout period (only if cookie is bound to the session)
            $identity = $auth->getIdentity();
            if (isset($identity->timeout) && ($identity->timeout > 0) && (time() > $identity->timeout)) {
                // Make sure that user was logged out
                $members->checkMemberAndLogout($identity->member_id, 'session expired');
                $auth->clearIdentity();
            } elseif (!($module == 'api' && $controller == 'remote' && $action == 'isonline')) {
                // Don't update during our ping
                $members->updateLastAccessTime($identity->member_id);

                // User is still active - update the timeout time if it's not bound to session
                $identity->timeout = time() + $config['security']['session_timeout'];
                // Store the request URI so that an authentication after a timeout
                // can be directed back to the pre-timeout display.
                $identity->requestUri = $request->getRequestUri();
            }

            // If the user has no identity here, there has either been a time-out or the user has
            // not logged in yet.
            if (!$auth->hasIdentity()) {
                $routeName = $module == 'superadmin' ? 'superadmin_login' : 'login';

                $arrRouteParams = [
                    'logged_out'      => 'timeout',
                    'logged_out_from' => $module . '_' . $controller . '_' . $action
                ];

                // Redirect the user to the "Login" page.
                /** @var Response $response */
                $response = $e->getResponse();
                $response->getHeaders()->addHeaderLine('Location', $e->getRouter()->assemble($arrRouteParams, array('name' => $routeName)));
                $response->setStatusCode(302);
                $e->stopPropagation();

                return $response;
            }
        }
    }

    /**
     * Check if another session was started for the current user
     * and if this is not the latest - automatically log the user out
     * @param MvcEvent $e
     * @return Response
     */
    public function checkLastLoginTime(MvcEvent $e)
    {
        /** @var AuthenticationService $auth */
        $auth = $e->getApplication()->getServiceManager()->get('auth');

        /** @var Members $members */
        $members = $e->getApplication()->getServiceManager()->get(Members::class);

        // Check if current user's last login timestamp is same as it is saved in db -
        // allow to log in only from one pc/browser
        if ($auth->hasIdentity() && ($identity = $auth->getIdentity()) && isset($identity->member_id) && isset($identity->lastLogin)) {
            /** @var DbAdapterWrapper $db */
            $db = $e->getApplication()->getServiceManager()->get('db2');

            // Load from db ip address login for this user
            $select      = (new Select())
                ->from('members')
                ->columns(['lastLogin'])
                ->where(['member_id' => (int)$identity->member_id]);
            $lastLoginId = $db->fetchOne($select);

            if ($lastLoginId != $identity->lastLogin) {
                // Make sure that user was logged out
                $members->checkMemberAndLogout($identity->member_id, 'logged in from another pc/location');

                // Somebody logged in from other pc
                $auth->clearIdentity();
            }

            // If the user has no identity here - user was logged off
            // because somebody logged with same login
            if (!$auth->hasIdentity()) {
                list($module, $controller, $action) = Settings::getModuleControllerAction($e);
                $routeName = $module == 'superadmin' ? 'superadmin_login' : 'login';

                $arrRouteParams = [
                    'logged_out'      => 'other_pc',
                    'logged_out_from' => $module . '_' . $controller . '_' . $action
                ];

                // Redirect the user to the "Login" page.
                /** @var Response $response */
                $response = $e->getResponse();
                $response->getHeaders()->addHeaderLine('Location', $e->getRouter()->assemble($arrRouteParams, array('name' => $routeName)));
                $response->setStatusCode(302);
                $e->stopPropagation();

                return $response;
            }
        }
    }

    /**
     * Check if the website is turned off in the config - show a message
     *
     * @param MvcEvent $e
     * @throws Exception
     */
    public function checkIsOffline(MvcEvent $e)
    {
        $config = $e->getApplication()->getServiceManager()->get('config');
        if ($config['settings']['offline'] > 0 && !isset($_COOKIE['WantToSeeOfflineSite'])) {
            if (!headers_sent()) {
                header('HTTP/1.1 503 Service Temporarily Unavailable', true, 503);
                header('Status: 503 Service Temporarily Unavailable');
                header('Retry-After: 300'); // Retry in 5 minutes (in seconds)
            }

            exit('We are undergoing a regular system upgrade. The system will be available shortly.');
        }
    }

    /**
     * Debug memory usage for each request
     *
     * @param MvcEvent $event
     * @throws Exception
     */
    public function debugMemory(MvcEvent $event)
    {
        $config = $event->getApplication()->getServiceManager()->get('config');
        if ($config['settings']['debug_memory_usage']) {
            try {
                if (function_exists('memory_get_peak_usage')) {
                    $memory = memory_get_peak_usage(true);
                } else {
                    $memory = memory_get_usage(true);
                }

                // Save profiling information
                $time_end = microtime(true);
                $time     = $time_end - $_SERVER['REQUEST_TIME'];

                $statistics = $event->getApplication()->getServiceManager()->get(Statistics::class);

                list($module, $controller, $action) = Settings::getModuleControllerAction($event);
                $statistics->save($module, $controller, $action, $time, $memory);
            } catch (Exception $e) {
                /** @var Log $log */
                $log = $event->getApplication()->getServiceManager()->get('log');
                $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }
    }


    /**
     * Automatically redirect to the HTTPS version if needed
     *
     * @param MvcEvent $e
     * @throws Exception
     */
    public function checkIsSSL(MvcEvent $e)
    {
        // Pre-flight checks
        $config       = $e->getApplication()->getServiceManager()->get('config');
        $behindProxy  = $config['site_version']['proxy']['enabled'];
        $proxiedProto = !empty($config['site_version']['proxy']['forwarded_proto']) ? $config['site_version']['proxy']['forwarded_proto'] : false;
        if (!$proxiedProto) {
            $proxiedProto = !empty($_SERVER[$config['site_version']['proxy']['forwarded_proto_header']]) ? $_SERVER[$config['site_version']['proxy']['forwarded_proto_header']] : 'http';
        }

        if ($config['site_version']['always_secure'] && (empty($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"] != "on")) && (!$behindProxy || $proxiedProto != 'https')) {
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: https://" . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"]);
            exit();
        }
    }

    /**
     * Save info about the exception to the log
     *
     * @param MvcEvent $e
     * @throws Exception
     */
    public function onError(MvcEvent $e)
    {
        /** @var Exception $exception */
        $exception = $e->getParam('exception');
        if ($exception != null) {
            /** @var Log $log */
            $log = $e->getApplication()->getServiceManager()->get('log');
            $log->debugExceptionToFile($exception);

            // We don't want to show the error details for the regular users
            $config = $e->getApplication()->getServiceManager()->get('config');
            if (!$config['settings']['show_error_details']) {
                $e->setParam('exception', null);
            }
        }

        $layout = $e->getViewModel();
        $layout->setTemplate('layout/error');
    }

    public function getSystemTriggerListeners()
    {
        return [AutomaticReminders::class];
    }

    /**
     * Copies csrfprotector.js from OWASP CSRF Protector library, so it becomes available for a web-server
     */
    private function exposeCsrfProtectorToWebServer()
    {
        $sourcePath = getcwd() . "/vendor/owasp/csrf-protector-php/js/";
        $targetPath = getcwd() . "/public/js/csrf/";
        $fileName   = "csrfprotector.js";

        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        file_put_contents($targetPath . $fileName, file_get_contents($sourcePath . $fileName));
    }

    /**
     * Deploys all assets from modules to public/assets directory
     * @param ModuleManager $moduleManager
     */
    private function deployModuleAssets(ModuleManager $moduleManager)
    {
        $modules          = $moduleManager->getLoadedModules();
        $targetAssetsPath = realpath(getcwd() . '/public/assets/');
        foreach ($modules as $module) {
            if (!$module instanceof AssetsProviderInterface) {
                continue;
            }
            $assets = $module->getAssetPaths();
            foreach ($assets as $key => $assetPath) {
                if (!is_dir($assetPath)) {
                    continue;
                }

                if (!is_numeric($key)) {
                    $targetFolder = $key;
                } else {
                    $pathInfo     = pathinfo($assetPath);
                    $targetFolder = $pathInfo['dirname'];
                }

                $targetFolder = $targetAssetsPath . '/' . $targetFolder;

                $assetCopier = new AssetCopier($assetPath, $targetFolder);
                $assetCopier->copy();
            }
        }
    }

    /**
     * Runs when Composer install or update is executed
     * @param $op
     * @param Application $application
     * @return void
     */
    public function onComposerEvent($op, Application $application)
    {
        $this->exposeCsrfProtectorToWebServer();

        /** @var ModuleManager $moduleManager */
        $moduleManager = $application->getServiceManager()->get('ModuleManager');
        $this->deployModuleAssets($moduleManager);
    }

}
