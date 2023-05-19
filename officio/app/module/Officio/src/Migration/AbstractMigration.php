<?php

namespace Officio\Migration;

use Exception;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Mvc\Application;
use Laminas\Stdlib\ArrayUtils;
use Officio\Common\Service\Acl;
use Officio\Service\Company;

class AbstractMigration extends \Phinx\Migration\AbstractMigration
{

    /** @var Application */
    protected static $application;

    /** @var bool */
    protected $clearCache = false;

    /** @var bool */
    protected $clearAclCache = false;

    /**
     * Sets application
     * @param Application $application
     */
    public static function setApplication(Application $application)
    {
        static::$application = $application;
    }

    /**
     * Retrieves application object
     * @return Application
     */
    public static function getApplication()
    {
        return static::$application;
    }

    /**
     * Retrieves object
     *
     * @param $serviceName
     * @return array|mixed|object
     */
    public static function getService($serviceName)
    {
        return self::getApplication()->getServiceManager()->get($serviceName);
    }

    /**
     * Check for statically cached application object, and if there is none - initialize it
     */
    public function init()
    {
        if (null === self::getApplication()) {
            $application = $this->bootstrap();
            self::setApplication($application);
        }
    }


    /**
     * Starts Laminas Application
     * @return Application
     */
    public function bootstrap()
    {
        // Retrieve configuration
        $appConfig = require 'config/application.config.php';
        if (file_exists('config/development.config.php')) {
            $appConfig = ArrayUtils::merge($appConfig, require 'config/development.config.php');
        }

        try {
            // Remove any unnecessary modules
            foreach ($appConfig['modules'] as $key => $module) {
                if (strpos($module, 'Laminas\ApiTools') !== false) {
                    unset($appConfig['modules'][$key]);
                }
            }

            // And don't try to cache the config - otherwise it'll spoil application's config
            $appConfig['module_listener_options']['config_cache_enabled']     = false;
            $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

            $application = Application::init($appConfig);
            $application->bootstrap();
        } catch (Exception $e) {
            var_dump($e);
            echo PHP_EOL;
            exit;
        }

        return $application;
    }

    public function postFlightCheck($direction = null)
    {
        if ($this->clearCache || $this->clearAclCache) {
            /** @var StorageInterface $cache */
            $cache = self::getService('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
        }
        // Usage of Officio services here causes session_start which causes exception, because phinx has already done output.
        // TODO Officio should be improved to allow running it from CLI and skip starting SessionManager if that's the case
        //elseif ($this->clearAclCache) {
        //    /** @var Company $company */
        //    $company    = self::getService(Company::class);
        //    $companyIds = $company->getAllCompanies(true);
        //    /** @var Acl $acl */
        //    $acl = self::getService('acl');
        //    $acl->clearCache($companyIds);
        //}

        parent::postFlightCheck($direction);
    }

}