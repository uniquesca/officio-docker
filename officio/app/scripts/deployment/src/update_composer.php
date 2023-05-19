<?php

/**
 * This scripts selects all the projects (or one selected) and updates composer locks for all of them
 */

$script = sprintf('%s %s', $argv[0], $argv[1]);
array_shift($argv);
array_shift($argv);

$projects                = ['au', 'ca', 'bcpnp', 'ntnp', 'dm'];
$results                 = [];
$projectArgFound         = false;
$composerPackageArgFound = false;
$packageNames            = [];
foreach ($argv as $arg) {
    if (!$projectArgFound && in_array($arg, $projects)) {
        $projects        = [$arg];
        $projectArgFound = true;
    } elseif (preg_match('/^[\w-]+\/[\w+-]+$/', $arg)) {
        $packageNames[]          = $arg;
        $composerPackageArgFound = true;
    } else {
        echo "Usage: $script [au|ca|bcpnp|ntnp|dm] [%composer-package-name 1%] ... [%composer-package-name N%]\n";
        exit(1);
    }
}

$rootDir      = dirname(Phar::running(false));
$composerPhar = $rootDir . '/composer.phar';
chdir($rootDir);

$command = trim(sprintf(
    "php composer.phar update --no-dev %s",
    implode(' ', $packageNames)
));

// Get all the functions
require_once "shared.php";

// Saving current state
$currentComposerJson = file_exists("$rootDir/composer.json") ? file_get_contents("$rootDir/composer.json") : null;
$currentComposerLock = file_exists("$rootDir/composer.lock") ? file_get_contents("$rootDir/composer.lock") : null;
$currentProject      = file_exists("$rootDir/.officioproject") ? file_get_contents("$rootDir/.officioproject") : null;
$currentModules      = file_exists("$rootDir/config/modules.json") ? file_get_contents("$rootDir/config/modules.json") : null;
if (is_dir("$rootDir/_vendor")) {
    remove_dir_recursively("$rootDir/_vendor");
}
if (is_dir("$rootDir/vendor")) {
    rename("$rootDir/vendor", "$rootDir/_vendor");
}

foreach ($projects as $project) {
    $output     = [];
    $resultCode = null;

    if (is_dir("$rootDir/vendor")) {
        remove_dir_recursively("$rootDir/vendor");
    }

    echo "Preparing modules list for project $project...\n";
    prep_modules_config("$rootDir/config", $project, false);
    echo "Preparing composer files for project $project...\n";
    setup_project_flag($rootDir, $project);
    $composerPrepped = prep_composer($rootDir, $project, false);
    if ($composerPrepped) {
        echo "Executing: $command\n";
        exec($command, $output, $resultCode);
        foreach ($output as $line) {
            echo "$line\n";
        }
        if ($resultCode === 0) {
            echo "Done.\n";
            $results[$project] = "OK";
        } else {
            echo "Failure.\n";
            $results[$project] = "Failure";
        }
    } else {
        echo "Failed to prepare composer files.\n";
    }

    echo "=============================================================\n";
}

// Rolling back initial state
echo "Rolling back composer to initial state...\n";
if (!is_null($currentModules)) {
    file_put_contents("$rootDir/config/modules.json", $currentModules);
}
if (!is_null($currentComposerJson)) {
    file_put_contents("$rootDir/composer.json", $currentComposerJson);
}
if (!is_null($currentComposerLock)) {
    file_put_contents("$rootDir/composer.lock", $currentComposerLock);
}
if (!is_null($currentProject)) {
    file_put_contents("$rootDir/.officioproject", $currentProject);
}
if (is_dir("$rootDir/vendor")) {
    remove_dir_recursively("$rootDir/vendor");
}
if (is_dir("$rootDir/_vendor")) {
    rename("$rootDir/_vendor", "$rootDir/vendor");
}
echo "Done.\n";
echo "=============================================================\n";
echo "Summary:\n";
foreach ($results as $project => $result) {
    echo "$project => $result\n";
}