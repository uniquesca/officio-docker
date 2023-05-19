<?php

//declare(strict_types=1);

// if (!array_key_exists('hidden_admin', $_COOKIE)) {
//     exit('We are undergoing a regular system upgrade. The system will be available shortly.');
// }

use Laminas\Mvc\Application;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Officio\Email\Models\MailAccount;
use Officio\Common\Service\Log;

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_string($path) && __FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}

// Prepare environment variables
// This is used in the debugMemory
$_SERVER['REQUEST_TIME'] = microtime(true);

// SERVER_NAME is required for CLI executions
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'localhost';
}

// Composer autoloading
require 'vendor/autoload.php';

// Autoload PHPDocx
require 'library/PHPDocx/Classes/Phpdocx/AutoLoader.php';

if (!class_exists(Application::class)) {
    throw new RuntimeException(
        "Unable to load application.\n"
        . "- Type `composer install` if you are developing locally.\n"
        . "- Type `vagrant ssh -c 'composer install'` if you are using Vagrant.\n"
        . "- Type `docker-compose run laminas composer install` if you are using Docker.\n"
    );
}

// Retrieve configuration
$appConfig = require 'config/application.config.php';
if (file_exists('config/development.config.php')) {
    $appConfig = ArrayUtils::merge($appConfig, require 'config/development.config.php');
}

// Registering fatal error handler
ob_start();
register_shutdown_function('fatalCatcher');

function showErrorMessage()
{
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        readfile(realpath(__DIR__) . DIRECTORY_SEPARATOR . '500.html');
        exit;
    }
}

function fatalCatcher()
{
    chdir(dirname(__DIR__));

    if (!is_null($error = error_get_last())) {
        if (isset($error['type']) && in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR))) {
            if (!empty($d = ob_get_contents())) {
                ob_end_clean();
            }

            // Prepare config parser
            // Retrieve configuration
            $appConfig = require 'config/application.config.php';
            if (file_exists('config/development.config.php')) {
                $appConfig = ArrayUtils::merge($appConfig, require 'config/development.config.php');
            }
            $appConfig['modules'] = []; // We don't need modules for handling the error
            $smConfig = $appConfig['service_manager'] ?? [];
            $smConfig = new ServiceManagerConfig($smConfig);
            $serviceManager = new ServiceManager();
            $smConfig->configureServiceManager($serviceManager);
            $serviceManager->setService('ApplicationConfig', $appConfig);
            $serviceManager->get('ModuleManager')->loadModules();
            $config = $serviceManager->get('config');

            $to = $config['settings']['send_fatal_errors_to'];

            // subject
            $subject = $config['site_version']['name'] . ' - Fatal Error';

            // message
            $message = '<html>
                            <head>
                              <title>' . $subject . '</title>
                            </head>
                            <body>
                              <h2>Fatal Error occurred on: ' . date('Y-m-d H:i:s') . '</h2>
                              <h3>Details:</h3><pre>' . print_r($error, true) . '</pre>
                              <h3>Request details:</h3><pre>' . print_r($_REQUEST, true) . '</pre>
                              <h3>Server details:</h3><pre>' . print_r($_SERVER, true) . '</pre>
                            </body>
                        </html>';

            // To send HTML mail, the Content-type header must be set
            $headers = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

            // Additional headers
            $headers .= 'From: ' . $config['site_version']['name'] . ' Support <' . $config['site_version']['support_email'] . '>';

            // Mail it
            mail($to, $subject, $message, $headers);

            file_put_contents(
                $config['log']['path'] . '/x-errors_php_fatal-' . date('Y_m_d') . '.txt',
                $message,
                FILE_APPEND | LOCK_EX
            );

            showErrorMessage();
        }
    }

    // Reset "email check in progress" if error was generated during emails loading
    if (preg_match(
        '%^/public/(?P<module>[^/]+)/(?P<controller>[^/]+)/(?P<action>[^/?]+)%',
        $_SERVER['REQUEST_URI'],
        $matches
    )) {
        if ($matches['module'] == 'mail' && $matches['controller'] == 'index' && in_array(
                $matches['action'],
                array(
                    'check-email',
                    'check-emails-in-folder'
                )
            )) {
            $accountId = isset($_GET['account_id']) && is_numeric($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
            if (empty($accountId) && isset($_POST['account_id'])) {
                $accountId = str_replace('"', '', $_POST['account_id']);
                $accountId = is_numeric($accountId) ? (int)$accountId : 0;
            }

            if (!empty($accountId)) {
                $mailAccountManager = new MailAccount($accountId);
                if (!empty($mailAccountManager)) {
                    $mailAccountManager->setIsChecking(0);
                }
            }
        }
    }
}

$application = false;
try {
    // Run the application!
    $application = Application::init($appConfig);
    $application->run();
} catch (Exception $e) {
    if ($application) {
        try {
            $serviceManager = $application->getServiceManager();
            /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugExceptionToFile($e);
        } catch (Exception $err) {
            throw $e;
        }
    }
}
