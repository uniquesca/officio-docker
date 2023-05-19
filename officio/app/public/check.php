<?php

use Composer\Console\Application as ComposerApplication;
use Laminas\Db\Adapter\Adapter;
use Officio\Common\Json;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Uri\UriFactory;
use Officio\Common\Service\Encryption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

function is_cli()
{
    if (defined('STDIN')) {
        return true;
    }

    if (php_sapi_name() === 'cli') {
        return true;
    }

    if (array_key_exists('SHELL', $_ENV)) {
        return true;
    }

    if (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
        return true;
    }

    if (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
        return true;
    }

    return false;
}

if (!is_cli() && !array_key_exists('phpinfo', $_REQUEST) && !array_key_exists('show_info', $_REQUEST)) {
    exit;
}
if (array_key_exists('phpinfo', $_REQUEST)) {
    phpinfo();
    exit;
}


$lb = is_cli() ? ' ' : '<br>';

ini_set('display_errors', '0');
error_reporting(E_ALL ^ E_STRICT ^ E_DEPRECATED ^ E_NOTICE);
clearstatcache();

// SERVER_NAME is required for CLI executions
if (!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = 'localhost';

register_shutdown_function('fatalCatcher');
function fatalCatcher() {
    if(!is_null($error = error_get_last())) {
        if (isset($error['type']) && in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR))) {
            ob_end_clean();

            global $config;
            if (empty($config)) {
                // If config wasn't loaded - try to save error details to the log file
                // (in the same directory where this file is located)
                $config = [
                    'settings' => [
                        'send_fatal_errors_to' => ''
                    ],

                    'site_version' => [
                        'name' => 'Officio',
                        'support_email' => ''
                    ],

                    'log' => [
                        'path' => __DIR__
                    ]
                ];
            }

            $to      = $config['settings']['send_fatal_errors_to'];
            $subject = $config['site_version']['name'] . ' - Fatal Error (check.php)';

            // message
            $message = '<html>
                            <head>
                              <title>'.$subject.'</title>
                            </head>
                            <body>
                              <h2>Fatal Error occurred on: ' . date('Y-m-d H:i:s') . '</h2>
                              <h3>Details:</h3><pre>' . print_r($error, true) . '</pre>
                              <h3>Request details:</h3><pre>' . print_r($_REQUEST, true) . '</pre>
                              <h3>Server details:</h3><pre>' . print_r($_SERVER, true) . '</pre>
                            </body>
                        </html>';

            // To send HTML mail, the Content-type header must be set
            $headers  = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

            // Additional headers
            $headers .= 'From: ' . $config['site_version']['name'] . ' Support <' . $config['site_version']['support_email'] . '>';

            // Mail it
            if (!empty($to)) {
                mail($to, $subject, $message, $headers);
            }

            file_put_contents(
                $config['log']['path'] . '/x-errors_php_fatal-' . date('Y_m_d') . '.txt',
                $message,
                FILE_APPEND | LOCK_EX
            );

            echo 'Internal error.';
        }
    }
}

function return_bytes($val)
{
    $val  = trim($val);
    $last = strtolower($val[strlen($val) - 1]);

    if (!is_numeric($last)) {
        $val = substr($val, 0, -1);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
    }

    return $val;
}

// Try to initialize the config
$booConfigLoaded = false;
$serviceManager = false;
$config = array();
try {
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception(_("Composer not loaded. Please check if 'vendor' directory exists."));
    }

    // Loading composer libraries
    require_once 'vendor/autoload.php';

    $appConfig = require 'config/application.config.php';
    if (file_exists('config/development.config.php')) {
        $appConfig = ArrayUtils::merge($appConfig, require 'config/development.config.php');
    }

    // We don't need much modules for handling the error
    $appConfig['modules'] = [
        'Laminas\Db', // Required for DB checks
        'Officio\Common', // Required for Encryption
    ];
    // And don't try to cache the config - otherwise it'll spoil application's config
    $appConfig['module_listener_options']['config_cache_enabled'] = false;
    $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

    $smConfig = $appConfig['service_manager'] ?? [];
    $smConfig = new ServiceManagerConfig($smConfig);
    $serviceManager = new ServiceManager();
    $smConfig->configureServiceManager($serviceManager);
    $serviceManager->setService('ApplicationConfig', $appConfig);
    $serviceManager->get('ModuleManager')->loadModules();
    /** @var array $config */
    $config = $serviceManager->get('config');

    $booConfigLoaded = true;
} catch (Exception $e) {
    $arrMessages[] = array(
        'section' => 'Config/Composer',
        'title'   => 'Not loaded',
        'type'    => 'error',
        'message' => $e->getMessage()
    );
}

$arrMessages = array();

defined('ACCESS_OWNER') || define('ACCESS_OWNER', 'owner');
defined('ACCESS_GROUP') || define('ACCESS_GROUP', 'group');
defined('ACCESS_OTHERS') || define('ACCESS_OTHERS', 'others');
defined('ACCESS_NONE') || define('ACCESS_NONE', '-');
defined('ACCESS_READ') || define('ACCESS_READ', 'r');
defined('ACCESS_READWRITE') || define('ACCESS_READWRITE', 'w');
defined('ACCESS_READ_EXECUTE') || define('ACCESS_READ_EXECUTE', 'rx');
defined('ACCESS_READWRITE_EXECUTE') || define('ACCESS_READWRITE_EXECUTE', 'wx');

function getFileAccess($perms, $part = ACCESS_OWNER)
{
    $bits = [
        ACCESS_OWNER  => [0x0100, 0x0080, 0x0040],
        ACCESS_GROUP  => [0x0020, 0x0010, 0x0008],
        ACCESS_OTHERS => [0x0004, 0x0002, 0x0001]
    ];

    list($readBit, $writeBit, $executeBit) = $bits[$part];
    $executeAccess = ($perms & $executeBit);
    if ($executeAccess) {
        $access = (($perms & $readBit) ? ACCESS_READ_EXECUTE : ACCESS_NONE);
        if ($access !== ACCESS_NONE) {
            if ($perms & $writeBit) {
                $access = ACCESS_READWRITE_EXECUTE;
            }
        }
    } else {
        $access = (($perms & $readBit) ? ACCESS_READ : ACCESS_NONE);
        if ($access !== ACCESS_NONE) {
            if ($perms & $writeBit) {
                $access = ACCESS_READ_EXECUTE;
            }
        }
    }
    return $access;
}

function isUidApache($uid)
{
    static $apacheUid;
    if (is_null($apacheUid)) {
        if (isPosixAvailable()) {
            $apacheUid = posix_getuid();
        } else {
            $apacheUid = trim(shell_exec('whoami | id -u'));
        }
    }

    return $uid == $apacheUid;
}

function isGidApache($gid)
{
    static $apacheGids;

    if (is_null($apacheGids)) {
        if (isPosixAvailable()) {
            $apacheGids = posix_getgroups();
        } else {
            if (!is_null($groupsStr = shell_exec('groups'))) {
                $apacheGids = array_filter(
                    array_map(
                        function ($n) {
                            $group = trim($n);
                            if (!is_null($gid = shell_exec('getent group ' . $group . ' | awk -F\: \'{print $3}\''))) {
                                return trim($gid);
                            } else {
                                return false;
                            }
                        },
                        explode(' ', trim($groupsStr))
                    )
                );
            }
        }
    }

    return in_array($gid, $apacheGids);
}

function isPosixAvailable()
{
    static $available;

    if (is_null($available)) {
        $available = function_exists('posix_getgrgid') && function_exists('posix_getgroups') &&
            function_exists('posix_getuid') && function_exists('posix_getpwuid');
    }

    return $available;
}

function formatFilePermissions($perms)
{
    switch ($perms & 0xF000) {
        case 0xC000: // socket
            $info = 's';
            break;
        case 0xA000: // symbolic link
            $info = 'l';
            break;
        case 0x8000: // regular
            $info = 'r';
            break;
        case 0x6000: // block special
            $info = 'b';
            break;
        case 0x4000: // directory
            $info = 'd';
            break;
        case 0x2000: // character special
            $info = 'c';
            break;
        case 0x1000: // FIFO pipe
            $info = 'p';
            break;
        default: // unknown
            $info = 'u';
    }

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
        (($perms & 0x0800) ? 's' : 'x') :
        (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
        (($perms & 0x0400) ? 's' : 'x') :
        (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
        (($perms & 0x0200) ? 't' : 'x') :
        (($perms & 0x0200) ? 'T' : '-'));

    return $info;
}

function apacheHasWriteAccess($item)
{
    $permissions = fileperms($item);
    $owner       = fileowner($item);
    $group       = filegroup($item);

    if (isUidApache($owner)) {
        $access = getFileAccess($permissions);
    } elseif (isGidApache($group)) {
        $access = getFileAccess($permissions, ACCESS_GROUP);
    } else {
        // We don't approve approach where apache user is granted write access among with others
        $access = ACCESS_NONE;
    }

    return ($access === ACCESS_READWRITE || $access === ACCESS_READWRITE_EXECUTE);
}

function isExecutableByApache($item)
{
    $permissions = fileperms($item);
    $owner       = fileowner($item);
    $group       = filegroup($item);

    if (isUidApache($owner)) {
        $access = getFileAccess($permissions);
    } elseif (isGidApache($group)) {
        $access = getFileAccess($permissions, ACCESS_GROUP);
    } else {
        // We don't approve approach where apache user is granted execute access among with others
        $access = ACCESS_NONE;
    }

    return ($access === ACCESS_READWRITE_EXECUTE || $access === ACCESS_READ_EXECUTE);
}

function othersHaveWriteAccess($item)
{
    $permissions = fileperms($item);
    $access      = getFileAccess($permissions, ACCESS_OTHERS);
    return ($access === ACCESS_READWRITE || $access === ACCESS_READWRITE_EXECUTE);
}

function othersHaveExecuteAccess($item)
{
    $permissions = fileperms($item);
    $access      = getFileAccess($permissions, ACCESS_OTHERS);
    return ($access === ACCESS_READ_EXECUTE || $access === ACCESS_READWRITE_EXECUTE);
}

/**
 * Checks if the directory is within the "WRITABLE DIR LIST" and whether apache has write access to it.
 * If the directory is not supposed to be writable, function also checks that it actually isn't.
 * @param $dir
 * @param $writableDirList
 * @return array
 */
function checkDirPermissions($dir, &$writableDirList)
{
    $errors = [];

    $othersAccessCorrect = !othersHaveWriteAccess($dir);
    if (!$othersAccessCorrect) {
        $errors[] = 'Non-owner and non-group access should be restricted to this directory and all it\'s subdirectories and files.';
    }

    if (($withinWritableDirKey = array_search($dir, $writableDirList)) !== false) {
        // This directory has to be writable by Apache
        $apacheAccessCorrect = apacheHasWriteAccess($dir);
        if (!$apacheAccessCorrect) {
            $errors[] = 'Write access required for Apache user to this directory and all it\'s subdirectories and files.';
        }
        unset($writableDirList[$withinWritableDirKey]);
    } else {
        // This directory is not within writable directory list
        $apacheAccessCorrect = !apacheHasWriteAccess($dir);
        if (!$apacheAccessCorrect) {
            $errors[] = 'Write access for Apache should be restricted to this directory and all it\'s subdirectories and files.';
        }
    }

    return [($withinWritableDirKey !== false), $errors];
}

function checkFilePermissions($file, &$executableFiles)
{
    $errors = [];
    // TODO Temporary we won't check for this
    // $othersAccessCorrect = !othersHaveExecuteAccess($file);
    // if (!$othersAccessCorrect) {
    //     $errors[] = 'Non-owner and non-group execution access should be restricted to this file.';
    // }

    $warnings = [];
    if (($executableFileKey = array_search($file, $executableFiles)) !== false) {
        $isExecutable = isExecutableByApache($file);
        if (!$isExecutable) {
            $warnings[] = 'This script should be executable by Apache.';
        }
        unset($executableFiles[$executableFileKey]);
    }
    return [($executableFileKey !== false), $warnings, $errors];
}

function checkProjectFilePermissions($dir, &$writableDirList, &$executableFiles, $level = 0, $section = 'Directories and files')
{
    $result      = [];
    $dirContents = scandir($dir);
    foreach ($dirContents as $item) {
        if ($item == '..') {
            continue;
        }
        if ($item == '.' && $level !== 0) {
            continue;
        }

        if ($level !== 0) {
            $item = $dir . DIRECTORY_SEPARATOR . $item;
        }

        $formattedInfo = '';
        if (isPosixAvailable()) {
            $owner = fileowner($item);
            $owner = $userIndex[$owner] ?? ($userIndex[$owner] = posix_getpwuid($owner));

            $group = filegroup($item);
            $group = $groupIndex[$group] ?? ($groupIndex[$group] = posix_getgrgid($group));

            $formattedInfo = 'Owner: ' . $owner['name'] . ':' . $group['name'] . ' | ' .
                'Permissions:  ' . formatFilePermissions(fileperms($item)) . '<br />';
        }

        if (is_dir($item)) {
            list($writableDir, $errors) = checkDirPermissions($item, $writableDirList);

            if ($writableDir) {
                $result[] = [
                    'section' => $section,
                    'title'   => $item,
                    'type'    => !empty($errors) ? 'error' : 'correct',
                    'message' => $formattedInfo . (!empty($errors) ? implode('<br />', $errors) : 'This directory is writable by Web server')
                ];
            } elseif (($item !== '.') && ($level < 3)) {
                if (!empty($errors)) {
                    $result[] = [
                        'section' => $section,
                        'title'   => $item,
                        'type'    => 'error',
                        'message' => $formattedInfo . implode('<br />', $errors)
                    ];
                }
                $result = array_merge($result, checkProjectFilePermissions($item, $writableDirList, $executableFiles, $level + 1, $section));
            }
        } else {
            list($executableFile, $warnings, $errors) = checkFilePermissions($item, $executableFiles);
            if (!empty($errors)) {
                $errors   = array_merge($warnings, $errors);
                $result[] = [
                    'section' => $section,
                    'title'   => $item,
                    'type'    => 'error',
                    'message' => $formattedInfo . implode('<br />', $errors)
                ];
            } else {
                if ($executableFile) {
                    $result[] = [
                        'section' => $section,
                        'title'   => $item,
                        'type'    => !empty($warnings) ? 'warning' : 'correct',
                        'message' => $formattedInfo . (!empty($warnings) ? implode('<br />', $warnings) : 'This script is executable by Web server')
                    ];
                }
            }
        }
    }

    return $result;
}

if (stristr(PHP_OS, 'WIN')) {
    // Check if these folders exist
    $existingDirs  = [
        'backup',
        'data',
        'data/pdf',
        'data/xod',
        'data/reconciliation_reports',
        'var/cache',
        'var/log',
        'var/log/payment',
        'var/log/xfdf',
        'var/pdf_tmp',
        'var/tmp',
        'var/tmp/lock',
        'var/tmp/uploads',
        'public/help_files',
        'public/captcha/images',
        'public/website',
        'public/email/data',
        'public/cache',
    ];
    $existingFiles = [
        'scripts/convert_to_pdf.sh',
        'scripts/cron/cron-empty-tmp.sh',
        'library/PhantomJS/phantomjs'
    ];

    foreach ($existingDirs as $dir) {
        $booExists     = is_dir($dir);
        $arrMessages[] = [
            'section' => 'Directories and files',
            'title'   => $dir,
            'type'    => !$booExists ? 'error' : 'correct',
            'message' => !$booExists ? 'Directory is missing' : 'Directory exists'
        ];
    }

    foreach ($existingFiles as $path) {
        $booExists     = file_exists($path);
        $arrMessages[] = array(
            'section' => 'Directories and files',
            'title'   => $path,
            'type'    => $booExists ? 'correct' : 'error',
            'message' => $booExists ? 'File exists' : 'File is missing'
        );
    }
} else {
    // Check if these folders are writable
    $writableDirs = array(
        'backup',
        'data',
        'var/cache',
        'var/log',
        'var/pdf_tmp',
        'var/tmp',
        'public/help_files',
        'public/captcha/images',
        'public/website',
        'public/email/data',
        'public/cache',
    );
    // Check Executable files for Unix
    $arrExecutables = array(
        'scripts/convert_to_pdf.sh',
        'scripts/cron/cron-empty-tmp.sh',
        'library/PhantomJS/phantomjs'
    );
    $userIndex      = $groupIndex = [];
    $arrMessages    = array_merge($arrMessages, checkProjectFilePermissions(getcwd(), $writableDirs, $arrExecutables));
    foreach ($writableDirs as $dir) {
        $arrMessages[] = array(
            'section' => 'Directories and files',
            'title'   => $dir,
            'type'    => 'error',
            'message' => 'Must be created and granted write access to Apache'
        );
    }

    foreach ($arrExecutables as $path) {
        $arrMessages[] = array(
            'section' => 'Directories and files',
            'title'   => $path,
            'type'    => 'error',
            'message' => 'Missing script!'
        );
    }
}

$arrApacheModules = array('mod_filter', 'mod_deflate', 'mod_expires', 'mod_rewrite', 'mod_headers', 'mod_ssl');
if (function_exists('apache_get_version')) {
    // Will work if not installed as the php fpm and if apache is installed
    $arrMessages[] = array(
        'section' => _('Apache'),
        'title' => _('Version'),
        'type' => 'correct',
        'message' => apache_get_version()
    );

    $arrInstalledApacheModules = function_exists('apache_get_modules') ? apache_get_modules() : array();
    foreach ($arrApacheModules as $apacheModule) {
        $booInstalled = in_array($apacheModule, $arrInstalledApacheModules);

        $arrMessages[] = array(
            'section' => _('Apache'),
            'title' => $apacheModule,
            'type' => $booInstalled ? 'correct' : 'warning',
            'message' => $booInstalled ? _('Installed') : _('Not installed or cannot determine')
        );
    }
} else {
    $serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) && !empty($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
    $booApache      = preg_match('/^Apache(.*)/', $serverSoftware);
    $arrMessages[]  = array(
        'section' => $booApache ? _('Apache') : _('Server software'),
        'title' => _('Version'),
        'type' => $booApache ? 'correct' : 'warning',
        'message' => $serverSoftware ?: _('Apache is not installed or cannot determine')
    );

    if ($booApache) {
        $arrMessages[] = array(
            'section' => _('Apache'),
            'title' => _('Modules'),
            'type' => 'warning',
            'message' => _('We cannot determine if such apache modules are installed:') . $lb . implode($lb, $arrApacheModules)
        );
    }
}


// Check Python v2 Version
$result = exec('python -V' . ' 2>&1');
if (isset($result[7]) && isset($result[9]) && (int)$result[7] == 2 && (int)$result[9] >= 7) {
    $booOldPython = false;
} else {
    $booOldPython = true;
}

$result        = empty($result) ? 'cannot identify or not' : $result;
$arrMessages[] = array(
    'section' => 'Python v2',
    'title'   => 'Version',
    'type'    => $booOldPython ? 'warning' : 'correct',
    'message' => $booOldPython ? sprintf(_("Python 2.7.x is supported (%s installed).%sRequired if RabbitMQ will be used/enabled."), $result, $lb) : $result
);


// Check Python v3 Version
$result = exec('python3 -V' . ' 2>&1');
if (isset($result[7]) && isset($result[9]) && (int)$result[7] == 3 && (int)$result[9] >= 6) {
    $booOldPython = false;
} else {
    $booOldPython = true;
}

$result        = empty($result) ? 'cannot identify or not' : $result;
$arrMessages[] = array(
    'section' => 'Python v3',
    'title'   => 'Version',
    'type'    => $booOldPython ? 'warning' : 'correct',
    'message' => $booOldPython ? sprintf(_("Python 3.x is supported (%s installed).%sRequired if PDFNetPython3 will be used/enabled."), $result, $lb) : $result
);

// Check Java Version
exec('java -version' . ' 2>&1', $arrResult);
if (isset($arrResult[0]) && preg_match('/(java|openjdk) version "(\d)\.(\d)(.*)"/', $arrResult[0], $regs)) {
    $booJavaInstalled = true;
    if( (int)$regs[2] > 1 || ((int)$regs[2] == 1 && (int)$regs[3] >= 6)){
        $javaMessage = $regs[2] . '.' . $regs[3] . $regs[4];
    } else {
        $javaMessage = sprintf(_("Java 1.6.x or high supported (%s installed). Please update.%s/>Used in: Pdf2Html (check README.md for details)"), $regs[2] . '.' . $regs[3] . $regs[4], $lb);
    }
} else {
    $javaMessage      = sprintf('Cannot identify OR not installed.%sUsed in: Pdf2Html (check README.md for details)', $lb);
    $booJavaInstalled = false;
}

$result = empty($result) ? 'not' : $result;
$arrMessages[] = array(
    'section' => 'Java',
    'title'   => 'Version',
    'type'    => $booJavaInstalled ? 'correct' : 'warning',
    'message' => $javaMessage
);

// Check if libreoffice is installed
if (stristr(PHP_OS, 'WIN')) {
    $type    = 'warning';
    $message = 'Not checked (Windows)';
} else {
    unset($arrResult);
    exec('libreoffice --version' . ' 2>&1', $arrResult);

    if (empty($arrResult)) {
        $type    = 'error';
        $message = 'Cannot identify';
    } else {
        $type    = 'correct';
        $message = implode($lb, $arrResult);
    }
}

$arrMessages[] = array(
    'section' => 'Libreoffice',
    'title'   => 'Version',
    'type'    => $type,
    'message' => $message
);

// Check PHP version
$booOldPhp = version_compare(phpversion(), '5.3.0', '<') === true;
$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'Version',
    'type'    => $booOldPhp ? 'error' : 'correct',
    'message' => $booOldPhp ? sprintf(_("Too old PHP version (%s). Please update."), PHP_VERSION) : PHP_VERSION
);

// Check if short tags are allowed
$booShortTagsAllowed = (int)ini_get('short_open_tag');
$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'Short Tags',
    'type'    => !$booShortTagsAllowed ? 'error' : 'correct',
    'message' => $booShortTagsAllowed ? 'Allowed' : 'Not allowed'
);

// Check if allow_url_fopen is allowed
$booAllowUrlFopenEnabled = (int)ini_get('allow_url_fopen');
$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'URL-aware fopen wrappers (allow_url_fopen)',
    'type'    => !$booAllowUrlFopenEnabled ? 'error' : 'correct',
    'message' => $booAllowUrlFopenEnabled ? 'Allowed' : 'Not allowed (is required when file_get_contents is used)'
);

// Check if register globals is Off
$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'Register Globals',
    'type'    => ini_get('register_globals') ? 'error' : 'correct',
    'message' => ini_get('register_globals') ? 'Enabled' : 'Disabled'
);

// Make sure that we can get a huge variables count (especially when add/edit role)
$maxInputVars   = ini_get('max_input_vars');
$maxSize        = 3000;
$booCorrectSize = $maxInputVars >= $maxSize;
$arrMessages[]  = array(
    'section' => 'PHP',
    'title' => 'Max input vars',
    'type' => !$booCorrectSize ? 'error' : 'correct',
    'message' => $booCorrectSize ? $maxInputVars : 'Must be >= than ' . $maxSize . ', now ' . $maxInputVars
);

$maxExecutionTime = ini_get('max_execution_time');
$minExecutionTime = 300;
$booCorrectSize   = empty($maxExecutionTime) || $maxExecutionTime >= $minExecutionTime;
$arrMessages[]    = array(
    'section' => 'PHP',
    'title' => 'Max execution time',
    'type' => !$booCorrectSize ? 'error' : 'correct',
    'message' => $booCorrectSize ? $maxExecutionTime : 'Must be >= than ' . $minExecutionTime . ', now ' . $maxExecutionTime
);

$maxInputTime   = ini_get('max_input_time');
$minInputTime   = 90;
$booCorrectSize = empty($maxInputTime) || $maxInputTime >= $minInputTime;
$arrMessages[]  = array(
    'section' => 'PHP',
    'title' => 'Max input time',
    'type' => !$booCorrectSize ? 'error' : 'correct',
    'message' => $booCorrectSize ? $maxInputTime : 'Must be >= than ' . $minInputTime . ', now ' . $maxInputTime
);

$maxAllowedSize = ini_get('post_max_size');
$maxSize        = 15; // In Mb
$booCorrectSize = return_bytes($maxAllowedSize) / 1024 / 1024 >= $maxSize;
$arrMessages[]  = array(
    'section' => 'PHP',
    'title' => 'Post max size',
    'type' => !$booCorrectSize ? 'error' : 'correct',
    'message' => $booCorrectSize ? $maxAllowedSize : 'Must be >= than ' . $maxSize . 'M, now ' . $maxAllowedSize
);

$maxAllowedSize = ini_get('upload_max_filesize');
$booCorrectSize = return_bytes($maxAllowedSize) / 1024 / 1024 >= $maxSize;
$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'Upload max size',
    'type'    => !$booCorrectSize ? 'error' : 'correct',
    'message' => $booCorrectSize ? $maxAllowedSize : 'Must be >= than ' . $maxSize . 'M, now ' . $maxAllowedSize
);

$memoryLimit    = ini_get('memory_limit');
$minMemoryLimit = 256; // In Mb
$booCorrectSize = $memoryLimit < 0 || (return_bytes($memoryLimit) / 1024 / 1024 >= $minMemoryLimit);
$memoryLimit    = $memoryLimit < 0 ? 'Unlimited' : $memoryLimit;
$arrMessages[]  = array(
    'section' => 'PHP',
    'title'   => 'Memory limit',
    'type'    => !$booCorrectSize ? 'error' : 'correct',
    'message' => $booCorrectSize ? $memoryLimit : 'Must be >= ' . $minMemoryLimit . 'M, now ' . $memoryLimit
);



// get_magic_quotes_gpc must be turned off
$booQuotesOn = false;
if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase')) != "off"))) {
    $booQuotesOn = true;
}
$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'Magic quotes',
    'type'    => $booQuotesOn ? 'error' : 'correct',
    'message' => $booQuotesOn ? _("Please turn off magic_quotes_gpc or magic_quotes_sybase in php settings [e.g. magic_quotes_gpc = Off]") : 'Disabled'
);


// ZIP support
$booZipSupportOn = function_exists('gzcompress');
$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'ZIP support',
    'type'    => $booZipSupportOn ? 'correct' : 'error',
    'message' => $booZipSupportOn ? 'Enabled' : 'Disabled'
);

// Session cookie samesite - https://www.php.net/manual/en/session.configuration.php#ini.session.cookie-samesite
$sessionCookieSameSite        = ini_get('session.cookie_samesite');
$sessionCookieSameSiteCorrect = !empty($sessionCookieSameSite);

$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'Session (cookie_samesite)',
    'type'    => $sessionCookieSameSiteCorrect ? 'correct' : 'error',
    'message' => $sessionCookieSameSiteCorrect ? $sessionCookieSameSite : 'Should be changed to "Lax" or "Strict"'
);

$arrLoadedExtensions = array_map('strtolower', get_loaded_extensions());

try {
    if (in_array('curl', $arrLoadedExtensions)) {
        $ch = curl_init('https://www.howsmyssl.com/a/check');
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);

        if ($n = curl_errno($ch)) {
            throw new Exception("curl error ($n) : ".curl_error($ch));
        }
        curl_close($ch);
        $json = json_decode($data);

        $tlsVersion = $json->tls_version;
        $booTLSSupport = true;
    } else {
        $tlsVersion = 'CURL support is disabled.';
        $booTLSSupport = false;
    }
} catch (Exception $e) {
    $booTLSSupport = false;

    try {
        $ch = curl_init('https://www.howsmyssl.com/a/check');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);

        if ($n = curl_errno($ch)) {
            throw new Exception("curl error ($n) : ".curl_error($ch));
        }
        curl_close($ch);
        $json = json_decode($data);

        $tlsVersion = $json->tls_version;
    } catch (Exception $e) {
        $tlsVersion = 'Unknown.';
    }

    $tlsVersion = sprintf('TLS 1.2 (or high) is required for Stripe.%sYour version: %s%sDetails: %s', $lb, $tlsVersion, $lb, $e->getMessage());
}

$arrMessages[] = array(
    'section' => 'PHP',
    'title'   => 'TLS version',
    'type'    => $booTLSSupport ? 'correct' : 'warning',
    'message' => $tlsVersion
);

try {
    if (!$booConfigLoaded) {
        throw new Exception(_("Config was not loaded. Please fix and after that composer will be checked."));
    }

    /**
     * Check required extensions
     *
     * @NOTE:
     * Also don't forget to LOAD mbstring before you load mailparse
     * example in the php.ini place in this order:
     *
     * extension=php_mbstring.dll
     * extension=php_mailparse.dll
     *
     * Or you will get an error.
     **/
    // TODO Print additional composer output
    // TODO Check suggested extensions
    putenv("COMPOSER_HOME=" . getcwd());
    putenv("COMPOSER_CACHE_DIR=/tmp/composer-cache-tmp");
    putenv("COMPOSER_HTACCESS_PROTECT=0");
    $composerExtensionsOutput = [];
    $composerApplication = new ComposerApplication();
    $output = new BufferedOutput();
    $input = new ArrayInput(array(
        'command' => 'check-platform-reqs'
    ));
    $composerApplication->setAutoExit(false);
    $composerApplication->run($input, $output);
    $output = $output->fetch();
    $outputSplit = preg_split("/\n/", $output);
    $extLineRegex = "/^(?'extension'[a-zA-Z\-_]+)[ ]+(?'version'(?>[\d]+\.?){0,4}|(?>n\/a))[ ]+(?'comment'.*?)[ ]+(?'status'success|missing|failed)/";
    foreach ($outputSplit as $line) {
        $result = preg_match($extLineRegex, $line, $matches);
        if (!$result) {
            $composerExtensionsOutput[] = $line;
        }
        else {
            $module = $matches['extension'] ?? false;
            $status = $matches['status'] ?? 'unknown';
            $version = $matches['version'] ?? 'unknown';
            $comment = $matches['comment'] ?? '';

            if ($module) {
                $arrMessages[] = array(
                    'section' => 'PHP Required Modules',
                    'title'   => $module,
                    'type'    => ($status == 'success') ? 'correct' : 'error',
                    'message' => ($status == 'success')
                        ? ($version ? "v." . $version : "Loaded")
                        : 'Not loaded',
                );
            }
        }
    }

    // Displaying composer-installed dependencies
    $composerDependenciesWarning = [];
    $composerApplication = new ComposerApplication();
    $output = new BufferedOutput();
    $input = new ArrayInput(array(
       'command' => 'show',
       '--direct' => true,
       '--format' => 'json',
       '--installed' => false
    ));
    $composerApplication->setAutoExit(false);
    $composerApplication->run($input, $output);
    $output = $output->fetch();
    $matches = array();
    $result = preg_match_all("/(?'warnings'.*?)(?'json'{.*})/s", $output, $matches);
    if (!$result) {
        $composerDependenciesWarning[] = $output;
    }
    else {
        $warnings = $matches['warnings'] ?? [];
        if (!empty($warnings)) {
            foreach ($warnings as $warning) {
                $splitWarnings               = array_filter(
                    preg_split("/\n/", $warning),
                    function ($n) {
                        return trim($n) !== '';
                    }
                );
                $composerDependenciesWarning += $splitWarnings;
            }
        }

        $json = $matches['json'] ?? false;
        if ($json) {
            $json = reset($json);
            try {
                $decoded = Json::decode($json, Json::TYPE_ARRAY);
            }
            catch (Exception $e) {
                $composerDependenciesWarning[] = $json;
                $decoded                       = false;
            }

            $installed = $decoded['installed'] ?? false;
            foreach ($installed as $dependency) {
                $name = $dependency['name'] ?? false;
                $version = $dependency['version'] ?? false;
                $description = $dependency['description'] ?? false;

                if ($name && $version && $description) {
                    $arrMessages[] = array(
                        'section' => 'Dependencies<br /><small>Note: This list currently doesn\'t show if there is anything missing, also it doesn\'t cover all 3rd-party libraries, only Composer-installed ones.</small>',
                        'title' => $name,
                        'type' => 'correct',
                        'message' => $version . "<br /><span style='white-space: normal;word-wrap: break-word;'>" . $description . "</span>",
                        'composer_dependencies' => true
                    );
                }
            }
        }
        else {
            $composerDependenciesWarning[] = 'Couldn\'t get any output from Composer.';
        }
    }


    $arrOptionalExtensions = array(
        array('id' => 'memcached', 'comment' => 'Required for caching via Memcached (can be configured).'),
    );
    foreach ($arrOptionalExtensions as $arrOptionalExtensionInfo) {
        $booLoaded = in_array($arrOptionalExtensionInfo['id'], $arrLoadedExtensions);
        $status = false;
        if ($booLoaded) {
            $status = phpversion($arrOptionalExtensionInfo['id']);
        }
        if (!$status) {
            $status = $booLoaded ? 'Loaded' : 'Not loaded';
        }
        $arrMessages[] = array(
            'section' => 'PHP Optional Modules',
            'title' => $arrOptionalExtensionInfo['id'],
            'type' => !$booLoaded ? 'warning' : 'correct',
            'message' => $status .
                (empty($arrOptionalExtensionInfo['comment']) ? '' : $lb . $arrOptionalExtensionInfo['comment'])
        );
    }
} catch (Exception $e) {
    $arrMessages[] = array(
        'section' => 'Composer',
        'title'   => 'Not loaded',
        'type'    => 'error',
        'message' => $e->getMessage()
    );
}

// TODO Check 3rd-party libraries installation

// Check PHPDocx
$message = '';
try {
    // We temporarily remove HTTP_USER_AGENT, so PHPDocx checker doesn't do HTML output, but raw one
    $userAgent = false;
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    ob_start();
    include_once('library/PHPDocx/check.php');
    $phpDocxOutput = ob_get_clean();
    $matches       = array();
    $result        = preg_match_all('/^(?\'status\'OK|Error)\s+(?\'message\'.+)/m', $phpDocxOutput, $matches);
    if (!$result) {
        throw new Exception('Unable to parse response from PHPDocx checker.' . $lb . 'Raw response:' . $lb . implode($lb, explode(PHP_EOL, $phpDocxOutput)));
    } else {
        $statuses = $matches['status'];
        $badStatuses = array_filter(
            $statuses,
            function ($n) {
                return $n !== 'OK';
            }
        );
        if (!empty($badStatuses)) {
            throw new Exception(implode($lb, explode(PHP_EOL, $phpDocxOutput)));
        }
    }

    if ($userAgent) {
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;
    }
} catch (Exception $e) {
    $message = $e->getMessage();
}

$arrMessages[] = array(
    'section' => 'PHPDocx Check',
    'title'   => 'Loaded',
    'type'    => empty($message) ? 'correct' : 'error',
    'message' => empty($message) ? 'OK' : $message
);


$booIncorrectMySQLVersion  = true;
$booConnectedToMainDB      = false;
$booConnectedToSecondaryDB = false;
$booIncorrectDBCollation   = true;

// Check connection to DB
$sqlVersion           = '-';
$currentCollation     = '-';
$arrCorrectCollations = ['utf8_general_ci', 'utf8mb3_general_ci', 'utf8mb4_general_ci'];
if ($booConfigLoaded) {
    $dbAdapter  = null;
    $dbAdapter2 = null;
    try {
        $dbAdapter = $serviceManager->get('db2');
        $dbAdapter->getDriver()->getConnection()->connect();
        $booConnectedToMainDB = true;
    } catch (Exception $e) {
    }

    try {
        $dbAdapter2 = $serviceManager->get('debugDb');
        $dbAdapter2->getDriver()->getConnection()->connect();
        $booConnectedToSecondaryDB = true;
    } catch (Exception $e) {
    }

    if ($booConnectedToMainDB) {
        // Check mysql version
        $sqlVersion               = $dbAdapter
            ->query("SELECT VERSION() AS v;", Adapter::QUERY_MODE_EXECUTE)
            ->current()->v;
        $booIncorrectMySQLVersion = version_compare($sqlVersion, '5.0') === -1;

        $currentCollation        = $dbAdapter
            ->query("SELECT @@collation_database AS collation;", Adapter::QUERY_MODE_EXECUTE)
            ->current()->collation;
        $booIncorrectDBCollation = !in_array($currentCollation, $arrCorrectCollations);
    }
}

$arrMessages[] = array(
    'section' => 'Config File',
    'title'   => 'Loaded',
    'type'    => !$booConfigLoaded ? 'error' : 'correct',
    'message' => $booConfigLoaded ? 'Loaded' : 'Not loaded'
);

if ($booConfigLoaded) {
    $arrPaths = [
        [
            'path' => 'config/application.config.php',
            'required' => true
        ],
        [
            'path' => 'config/development.config.php',
            'required' => false
        ],
        [
            'path' => 'config/modules.config.php',
            'required' => true
        ],
        [
            'path' => 'config/phinx.config.php',
            'required' => true
        ],
        [
            'path' => 'config/csrf.config.php',
            'required' => true
        ],
        [
            'path' => 'config/minify.config.php',
            'required' => true
        ],
        [
            'path' => 'config/autoload/global.php',
            'required' => true
        ],
        [
            'path' => 'config/autoload/local.php',
            'required' => true
        ],
        [
            'path' => 'config/autoload/development.local.php',
            'required' => false
        ],
    ];

    foreach ($arrPaths as $arrPathInfo) {
        $booFileExists = file_exists($arrPathInfo['path']);

        if ($arrPathInfo['required']) {
            $type = $booFileExists ? 'correct' : 'error';
        } else {
            if (!$booFileExists) {
                continue;
            }

            $type = 'correct';
        }

        $arrMessages[] = array(
            'section' => 'Config File',
            'title' => $arrPathInfo['path'],
            'type' => $type,
            'message' => $booFileExists ? 'File exists' : 'File does not exist'
        );
    }
}

// Check if security "password hashing" methods are correct/supported
if ($booConfigLoaded) {
    $password = 'This is a test password to hash';

    // Check all possible hashing options
    // Show errors and suggestions
    switch ($config['security']['password_hashing_algorithm']) {
        case 'password_hash':
            if (function_exists('password_hash')) {
                    $res = password_hash(
                        $password,
                        $config['security']['password_hash']['algorithm'],
                        $config['security']['password_hash']['options'] ?? null
                    );

                if (empty($res)) {
                    $type    = 'error';
                    $message = 'Incorrect "password_hash" settings';
                } elseif ($config['security']['password_hash']['algorithm'] == PASSWORD_BCRYPT) {
                    // Check if "cost" can be improved
                    $timeTarget = 0.1; // 100 milliseconds
                    $cost       = 8;

                    do {
                        $cost++;
                        $start = microtime(true);
                        password_hash($password, PASSWORD_BCRYPT, array('cost' => $cost));
                        $end = microtime(true);
                    } while (($end - $start) < $timeTarget);

                    $arrAlgoDetails = password_get_info($res);

                    $type    = 'correct';
                    $message = 'Enabled (algo: ' . $arrAlgoDetails['algoName'] . ', cost:' . $arrAlgoDetails['options']['cost'] . ')';

                    if ($arrAlgoDetails['options']['cost'] < $cost) {
                        $message .= '<div class="warning" style="padding-top: 10px;">The cost can be safely changed from ' . $arrAlgoDetails['options']['cost'] . ' to ' . $cost . '.</div>';
                    }

                } else {
                    $arrAlgoDetails = password_get_info($res);

                    $type    = 'correct';
                    $message = 'Enabled (' . $arrAlgoDetails['algoName'] . ', options:' . print_r($arrAlgoDetails['options'], true) . ')';
                }
            } else {
                $type    = 'error';
                $message = 'password_hash not supported by PHP - starts from PHP 5.5';
            }
            break;

        case 'hash':
            $arrSupportedAlgos = hash_algos();
            if (in_array($config['security']['hash']['algorithm'], $arrSupportedAlgos)) {
                $type    = 'correct';
                $message = 'Hash (' . $config['security']['hash']['algorithm'] . ')' . '<div class="warning" style="padding-top: 10px;">Note: please make sure that a selected algorithm is secure.</div>';
            } else {
                $type    = 'error';
                $message = 'Unsupported ' . $config['security']['hash']['algorithm'] . '<div class="normal" style="max-width: 300px; padding-top: 10px;">' . 'Supported: ' . implode(', ', $arrSupportedAlgos) . '</div>';
            }
            break;

        case 'default':
        default:
            $type    = 'warning';
            $message = 'Default (deprecated)';
            break;
    }

    $arrMessages[] = array(
        'section' => 'Config File',
        'title'   => 'Security: passwords hashing',
        'type'    => $type,
        'message' => $message
    );
}

// Check if security encode/decode methods are correct/supported
if ($booConfigLoaded && $serviceManager) {
    $strError = '';
    $strWarning = '';

    try {
        /** @var Encryption $encryption */
        $encryption = $serviceManager->get(Encryption::class);

        $securityConfig = $config['security']['encoding_decoding'];

        // Make sure that we support this adapter
        $arrSupportedAdapters = array('openssl');
        if (!in_array($securityConfig['adapter'], $arrSupportedAdapters)) {
            $strError = 'Only such adapters are supported: ' . implode(', ', $arrSupportedAdapters);
        }

        // For the openssl adapter - check if the provided cipher/alias is supported and is not weak
        if (empty($strError) && $securityConfig['adapter'] == 'openssl') {
            if (!extension_loaded('openssl')) {
                $strError = 'Openssl extension must be enabled';
            }

            if (empty($strError)) {
                $arrSupportedAlgos = hash_algos();
                if (!in_array($securityConfig['openssl_key_hash_algorithm'], $arrSupportedAlgos)) {
                    $strError = sprintf(
                        'Provided key hashing algorithm %s is not supported. Only such algos are supported: %s',
                        $securityConfig['openssl_cipher'],
                        implode(', ', $arrSupportedAlgos)
                    );
                }
            }

            if (empty($strError) && (empty($securityConfig['openssl_iv_length']) || !is_numeric($securityConfig['openssl_iv_length']))) {
                $strError = sprintf(
                    'Provided iv length is incorrect: %s',
                    $securityConfig['openssl_iv_length']
                );
            }

            $arrSupportedCiphers           = openssl_get_cipher_methods();
            $arrSupportedCiphersAndAliases = openssl_get_cipher_methods(true);
            $arrSupportedCipherAliases     = array_diff($arrSupportedCiphersAndAliases, $arrSupportedCiphers);
            if (empty($strError)) {
                if (!in_array($securityConfig['openssl_cipher'], $arrSupportedCiphersAndAliases)) {
                    $strError = sprintf(
                        'Provided cipher %s is not supported. Only such ciphers are supported: %s',
                        $securityConfig['openssl_cipher'],
                        implode(', ', $arrSupportedCiphersAndAliases)
                    );
                }
            }

            if (empty($strError)) {
                //ECB mode should be avoided
                $arrSupportedCiphers = array_filter( $arrSupportedCiphers, function($n) { return stripos($n,"ecb")===FALSE; } );

                //At least as early as Aug 2016, Openssl declared the following weak: RC2, RC4, DES, 3DES, MD5 based
                $arrSupportedCiphers = array_filter( $arrSupportedCiphers, function($c) { return stripos($c,"des")===FALSE; } );
                $arrSupportedCiphers = array_filter( $arrSupportedCiphers, function($c) { return stripos($c,"rc2")===FALSE; } );
                $arrSupportedCiphers = array_filter( $arrSupportedCiphers, function($c) { return stripos($c,"rc4")===FALSE; } );
                $arrSupportedCiphers = array_filter( $arrSupportedCiphers, function($c) { return stripos($c,"md5")===FALSE; } );
                $arrSupportedCipherAliases = array_filter($arrSupportedCipherAliases,function($c) { return stripos($c,"des")===FALSE; } );
                $arrSupportedCipherAliases = array_filter($arrSupportedCipherAliases,function($c) { return stripos($c,"rc2")===FALSE; } );

                if (!in_array($securityConfig['openssl_cipher'], $arrSupportedCipherAliases) && !in_array($securityConfig['openssl_cipher'], $arrSupportedCiphers)) {
                    $strWarning = sprintf(
                        'Provided cipher %s is weak. The list of strong ciphers: %s and aliases: %s',
                        $securityConfig['openssl_cipher'],
                        implode(', ', $arrSupportedCiphers),
                        implode(', ', $arrSupportedCipherAliases)
                    );
                }
            }
        }

        // Try to encode and decode. The result should be the same.
        if (empty($strError)) {
            $testString = 'A test string to encode and decode';

            if ($testString != $encryption->decode($encryption->encode($testString))) {
                $strError = 'Error during encoding/decoding';
                $strError .= $lb . 'Config: ';
                if (is_cli()) {
                    $strError .= str_replace(array("\n", "\0"), "", print_r($securityConfig, true));
                } else {
                    $strError .= '<pre>' . print_r($securityConfig, true) . '</pre>';
                }
            } elseif (empty($strWarning)) {
                $type = 'correct';
                $message = 'Config: ';
                if (is_cli()) {
                    $message .= str_replace(array("\n", "\0"), "", print_r($securityConfig, true));
                } else {
                    $message .= '<pre>' . print_r($securityConfig, true) . '</pre>';
                }
            }
        }
    } catch (Exception $e) {
        $strError = $e->getMessage();
    }

    if (!empty($strError)) {
        $type    = 'error';
        $message = $strError;
    } elseif (!empty($strWarning)) {
        $type    = 'warning';
        $message = $strWarning;
    }

    $arrMessages[] = array(
        'section' => 'Config File',
        'title'   => 'Security: encoding/decoding',
        'type'    => $type,
        'message' => $message
    );
}


if ($booConfigLoaded && $serviceManager) {
    $arrUrls = array(
        $config['marketplace']['toggle_status_url'],
        $config['marketplace']['create_profile_url'],
        $config['marketplace']['edit_profile_url']
    );

    $arrIncorrectUrls = array();
    foreach ($arrUrls as $urlToCheck) {
        if (!UriFactory::factory($urlToCheck)->isValid()) {
            $arrIncorrectUrls[] = $urlToCheck;
        }
    }

    if (count($arrIncorrectUrls) === count($arrUrls)) {
        $type    = 'warning';
        $message = 'Communication with MP is disabled';
    } else {
        if (empty($arrIncorrectUrls)) {
            $test    = 'This is a test message to encode and decode!';

            try {
                $hash = $encryption->customEncrypt(
                    $test,
                    $config['marketplace']['key'],
                    $config['marketplace']['private_pem'],
                    $config['marketplace']['public_pem']
                );

                $decoded = $encryption->customDecrypt(
                    $hash,
                    $config['marketplace']['key'],
                    $config['marketplace']['private_pem']
                );
            } catch (Exception $e) {
                $decoded = '';
            }

            if ($test === $decoded) {
                $type    = 'correct';
                $message = 'Communication with MP is enabled, openssl config is correct.';
            } else {
                $type    = 'error';
                $message = 'Communication with MP is enabled, openssl config is NOT correct.';
            }
        } else {
            $type    = 'error';
            $message = 'Communication with MP is enabled, ALL urls should be correct. Please check.';
        }
    }

    $arrMessages[] = array(
        'section' => 'Config File',
        'title'   => 'Marketplace',
        'type'    => $type,
        'message' => $message
    );
}

if ($booConfigLoaded && $serviceManager) {
    $section = 'HTML Editor (Froala)';
    $booKeyCorrect = !empty($config['html_editor']['froala_license_key']);
    $arrMessages[] = array(
        'section' => $section,
        'title'   => 'Key',
        'type'    => $booKeyCorrect ? 'correct' : 'warning',
        'message' => $booKeyCorrect ? 'Set' : 'Not Set'
    );

    $arrMessages[] = array(
        'section' => $section,
        'title'   => 'Storage',
        'type'    => 'correct',
        'message' => $config['html_editor']['storage'] == 'remote' ? 'Remote (S3)' : 'Local'
    );

    if ($config['html_editor']['storage'] != 'remote') {
        $arrWritableDirs = [
            'public/' . $config['html_editor']['location'],
        ];

        foreach ($arrWritableDirs as $dir) {
            $strDirCheckError = '';
            if (!is_dir($dir)) {
                $strDirCheckError = 'Directory is missing';
            }

            if (empty($strDirCheckError) && !stristr(PHP_OS, 'WIN') && !apacheHasWriteAccess($dir)) {
                $strDirCheckError = 'Write access required for Apache user to this directory and all it\'s subdirectories and files.';
            }

            $arrMessages[] = [
                'section' => $section,
                'title'   => $dir,
                'type'    => empty($strDirCheckError) ? 'correct' : 'error',
                'message' => empty($strDirCheckError) ? 'Directory exists and is writtable' : $strDirCheckError
            ];
        }
    }
}


$arrMessages[] = array(
    'section' => 'MySQL',
    'title'   => 'Main database',
    'type'    => !$booConnectedToMainDB ? 'error' : 'correct',
    'message' => $booConnectedToMainDB ? 'Connected to ' . $config['db']['dbname'] : 'Not connected'
);

$arrMessages[] = array(
    'section' => 'MySQL',
    'title'   => 'Statistics database',
    'type'    => !$booConnectedToSecondaryDB ? 'error' : 'correct',
    'message' => $booConnectedToSecondaryDB ? 'Connected to ' . $config['db_stat']['dbname'] : 'Not connected'
);

$arrMessages[] = array(
    'section' => 'MySQL',
    'title'   => 'Version',
    'type'    => $booIncorrectMySQLVersion ? 'error' : 'correct',
    'message' => $booConnectedToMainDB ? ($booIncorrectMySQLVersion ? sprintf (_("Too old MySQL version (%s). Please update."), $sqlVersion) : $sqlVersion) : '-'
);

$arrMessages[] = array(
    'section' => 'MySQL',
    'title'   => 'Main database collation',
    'type'    => $booIncorrectDBCollation ? 'error' : 'correct',
    'message' => $booConnectedToMainDB ? ($booIncorrectDBCollation ? sprintf (_("Current DB collation: %s%sMust be: %s"), $currentCollation, $lb, implode(' or ', $arrCorrectCollations)) : $currentCollation) : '-'
);


if (stristr(PHP_OS, 'WIN')) {
    $booChecked = false;
    $res = false;
    $cronJobs   = 'NOT CHECKED (Windows)';
} else {
    $booChecked = true;
    exec('crontab -l 2>&1', $cronJobs, $res);
}

$arrMessages[] = array(
    'section' => 'Cron',
    'title'   => 'Cron Jobs',
    'type'    => $booChecked ? 'correct' : 'warning',
    'message' => is_array($cronJobs) ? implode($lb, $cronJobs) : $cronJobs
);

if (is_cli()) {
    $title = '* Officio: Requirements checking script *';
    echo str_repeat('*', strlen($title)) . PHP_EOL;
    echo $title . PHP_EOL;
    echo str_repeat('*', strlen($title)) . PHP_EOL;

    $arrGroupedMessages = array();
    foreach ($arrMessages as $arrMessageInfo) {
        $arrGroupedMessages[$arrMessageInfo['section']][] = $arrMessageInfo;
    }

    foreach ($arrGroupedMessages as $currentSection => $arrMessages) {
        $maxTitleWidth   = 0;
        $maxMessageWidth = 0;
        $maxTypeWidth    = 0;
        foreach ($arrMessages as $arrMessageInfo) {
            $maxTitleWidth   = max($maxTitleWidth, strlen($arrMessageInfo['title']));
            $maxMessageWidth = max($maxMessageWidth, strlen($arrMessageInfo['message']));
            $maxTypeWidth    = max($maxTypeWidth, strlen($arrMessageInfo['type']));
        }
        $mask = "| %-{$maxTitleWidth}s | %-{$maxMessageWidth}s | %{$maxTypeWidth}s |\n";


        $totalWidth = $maxTitleWidth + $maxMessageWidth + $maxTypeWidth + 8;
        echo PHP_EOL . '+' . str_repeat('-', $totalWidth) . '+' . PHP_EOL;
        echo '| ' . $currentSection . str_repeat(' ', $totalWidth - strlen($currentSection) - 1) . '|'. PHP_EOL;
        echo '+' . str_repeat('-', $totalWidth) . '+' . PHP_EOL;


        foreach ($arrMessages as $arrMessageInfo) {
            printf($mask, $arrMessageInfo['title'], $arrMessageInfo['message'], $arrMessageInfo['type']);
        }
        echo '+' . str_repeat('-', $totalWidth) . '+' . PHP_EOL;
    }

    exit();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title>Officio!: testing requirements</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta http-equiv="Content-Language" content="en-US" />
    <style type="text/css">
        body {
            background: url("images/default/main-bg.png") repeat-x left top;
        }

        table {
            border-collapse: collapse;
            margin: 0 auto;
        }

        td {
            border: 1px solid #cccccc;
            padding: 5px 10px;
        }

        .section {
            font-weight: bold;
            border: none;
            padding: 20px 0 5px;
        }

        .normal {
            color: black;
        }

        .correct {
            color: green;
        }

        .warning {
            color: orange;
        }

        .error {
            color: red;
        }

        .header_logo {
            background: url(images/default/logo.png) no-repeat left;
            height: 60px;
            border: none;
            text-align: right;
            padding-right: 10px;
            font-weight: bold;
            font-size: 1.1em !important;

            text-shadow: 2px 4px 3px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
<table cellpadding="0" cellspacing="0" style="max-width: min(1280px, 80%);">
    <tr>
        <td colspan="2" class="header_logo">Requirements checking script</td>
    </tr>

    <?php $currentSection = ''; ?>
    <?php $goingThroughComposerDependencies = false; ?>
    <?php foreach ($arrMessages as $arrMessageInfo): ?>
        <?php if ($currentSection != $arrMessageInfo['section']) : ?>
            <?php $currentSection = $arrMessageInfo['section']; ?>
            <tr>
                <td colspan="2" class="section"><?php echo $arrMessageInfo['section'] ?></td>
            </tr>
        <?php endif ?>
        <tr>
            <td style="vertical-align: top"><?php echo $arrMessageInfo['title'] ?></td>
            <td class="<?php echo $arrMessageInfo['type'] ?>"><?php echo $arrMessageInfo['message'] ?></td>
        </tr>
        <?php if (!empty($arrMessageInfo['composer_dependencies'])): ?>
            <?php $goingThroughComposerDependencies = true; ?>
        <?php elseif ($goingThroughComposerDependencies): ?>
            <?php $goingThroughComposerDependencies = false; ?>
            <?php foreach ($composerDependenciesWarning as $warning): ?>
                <tr>
                    <td colspan="2" class="section error"><?= $warning ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endforeach ?>
</table>
</body>
</html>
