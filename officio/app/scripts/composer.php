<?php

use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Application;
use Laminas\Stdlib\ArrayUtils;
use Officio\Common\ComposerEventProviderInterface;

echo "Executing Composer events...\n";

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

set_time_limit(0);
ini_set('memory_limit', '-1');

function r_dirname($path, $count = 1)
{
    if ($count > 1) {
        return dirname(r_dirname($path, --$count));
    } else {
        return dirname($path);
    }
}

// SERVER_NAME is required for CLI executions
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'localhost';
}

// Composer autoloading
require 'vendor/autoload.php';

// Check which operation we are running
if (in_array('--install', $argv)) {
    $op = ComposerEventProviderInterface::COMPOSER_EVENT_POST_INSTALL;
} elseif (in_array('--update', $argv)) {
    $op = ComposerEventProviderInterface::COMPOSER_EVENT_POST_UPDATE;
} else {
    exit;
}

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

    // Bootstrap application and don't try to cache the config - otherwise it'll spoil application's config
    $appConfig['module_listener_options']['config_cache_enabled']     = false;
    $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

    $application = Application::init($appConfig)->bootstrap();

    echo "Application bootstrapped...\n";

    $serviceManager = $application->getServiceManager();
    /** @var ModuleManager $moduleManager */
    $moduleManager = $serviceManager->get('ModuleManager');
    $modules = $moduleManager->getLoadedModules();
    foreach ($modules as $module) {
        if (!$module instanceof ComposerEventProviderInterface) {
            continue;
        }

        echo sprintf("Triggering Composer event for module %s...\n", get_class($module));
        $module->onComposerEvent($op, $application);
    }

    // We don't clean cache here, because Composer has to be run by a "normal" user
    // which has write access to "vendor" directory, but doesn't have write access
    // to var/cache directory. However, we don't execut Composer as a web-server, because
    // of the same reason - web-server has to have no write access to "vendor" dir, although
    // has write access to cache dir.
    /*
    echo "Cleaning cache... ";
    $cache =  $serviceManager->get('cache');
    if ($cache instanceof FlushableInterface) {
        try {
            $cache->flush();
            echo "Done.\n";
        }
        catch (Exception $e) {
            echo "Failed: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "Make sure to clean cache manually!\n";
        }
    }
    else {
        echo "Cache is not instance of FlushableInterface, please clean it manually.\n";
    }
    */

    if (file_exists('composer.lock') && file_exists('.officioproject')) {
        $project             = trim(file_get_contents('.officioproject'));
        $projectComposerLock = "composer/$project.composer.lock";
        $result              = file_put_contents($projectComposerLock, file_get_contents('composer.lock'));
        if ($result) {
            echo "Project-specific composer lock $projectComposerLock has been updated.\n";
        }
    }

    echo "Composer update complete\n";
    echo "IMPORTANT: Do not update your root composer.json file, make sure to update composer/composer.json and PROJECT.composer.json files instead.\n";
} catch (Exception $e) {
    exit($e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
}
