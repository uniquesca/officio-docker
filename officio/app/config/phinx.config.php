<?php

use Laminas\Mvc\Application;
use Laminas\Stdlib\ArrayUtils;

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
if (!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = 'localhost';

// Composer autoloading
require 'vendor/autoload.php';

// Retrieve configuration
$appConfig = require 'config/application.config.php';
if (file_exists( 'config/development.config.php')) {
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
    $appConfig['module_listener_options']['config_cache_enabled'] = false;
    $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

    $application = Application::init($appConfig);
    $config = $application->getServiceManager()->get('config');
}
catch (Exception $e) {
    exit($e->getMessage() . PHP_EOL);
}

// Get database connection from "migrations" section first
$database = $config['phinx']['db'] ?? null;
if (is_null($database)) {
    // and use main connection if no specific one is defined
    $database = $config['db'] ?? null;
}

if ($database === null) {
    exit('Please set db in the config file' . PHP_EOL);
}

if (!isset($database['host'])) {
    exit('Please set database host in the config file' . PHP_EOL);
}

if (!isset($database['username'])) {
    exit('Please set database username in the config file' . PHP_EOL);
}

if (!isset($database['password'])) {
    exit('Please set database password in the config file' . PHP_EOL);
}

if (!isset($database['dbname'])) {
    exit('Please set database name in the config file' . PHP_EOL);
}

if (!isset($database['adapter'])) {
    exit('Please set adapter in the config file' . PHP_EOL);
}

if (!isset($config['phinx']['migrations_path'])) {
    exit('Please set the correct path to migrations directory in the config file' . PHP_EOL);
}

$migrationsPath = array_filter($config['phinx']['migrations_path'], 'realpath');
if (empty($migrationsPath)) {
    exit('Please set the correct path to migrations directory in the config file' . PHP_EOL);
}

if (empty($config['phinx']['migration_table'])) {
    exit('Please set the correct migrations table in the config file' . PHP_EOL);
}

switch ($database['adapter']) {
    case 'PDO_MYSQL':
        $adapter = 'mysql';
        break;

    case 'Pdo_Sqlite':
        $adapter = 'sqlite';
        break;

    case 'Pdo_Pgsql':
        $adapter = 'pgsql';
        break;

    case 'Pdo_Mssql':
        $adapter = 'sqlsrv';
        break;

    default:
        $adapter = '';
        break;
}

$arrDBParams = [
    'adapter' => $adapter,
    'host'    => $database['host'],
    'port'    => $database['port'],
    'name'    => $database['dbname'],
    'user'    => $database['username'],
    'pass'    => $database['password'],
    'charset' => $database['charset'] ?? 'utf8'
];

// Collation cannot be empty, but if was set - use it
if (!empty($database['collation'])) {
    $arrDBParams['collation'] = $database['collation'];
}

$arrConfig = array(
    'paths'        => array(
        'migrations' => $migrationsPath,
    ),
    'environments' => array(
        'default_migration_table' => $config['phinx']['migration_table'],
        'default_database'        => 'default',

        'default' => $arrDBParams
    )
);

return $arrConfig;
