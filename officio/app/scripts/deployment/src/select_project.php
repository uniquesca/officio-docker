<?php

/**
 * This script prepares configuration for the project selected. It includes:
 * - config/autoload/local.php config, which is copied from the corresponding stub;
 * - config/modules.json, which is a list of enabled modules. It is a merge of modules.json and PROJECT.modules.json lists located in config/modules folder;
 * - composer.json, which is a merge of composer.json and PROJECT.composer.json files located on composer folder;
 * - composer.lock which is copied from composer/PROJECT.composer.lock file.
 * Usage: php select_project.sh au|ca|bcpnp|ntnp|dm [--config] [--modules] [--composer]
 * By default all these operations are performed, unless any of the flags are specified, in this case script will perform only
 * selected operation(s).
 */

$script = sprintf('%s %s', $argv[0], $argv[1]);
if (sizeof($argv) < 3) {
    echo "No project specified.\n";
    echo "Usage: $script au|ca|bcpnp|ntnp|dm [--config] [--modules] [--composer]. If no optional parameters specified, all operations will be done by default.\n";
    exit(1);
}

$project = $argv[2];
if (!in_array($project, ['au', 'ca', 'bcpnp', 'ntnp', 'dm'])) {
    echo "Unknown project specified.\n";
    echo "Usage: $script au|ca|bcpnp|ntnp|dm [--config] [--modules] [--composer]. If no optional parameters specified, all operations will be done by default.\n";
    exit(1);
}

$rootDir   = dirname(Phar::running(false));
$configDir = "$rootDir/config";

$args     = array_slice($argv, 3);
$doConfig = $doModuleConfig = $doComposer = empty($args);
while ($arg = array_shift($args)) {
    switch ($arg) {
        case '--config':
            $doConfig = true;
            break;
        case '--modules':
            $doModuleConfig = true;
            break;
        case '--composer':
            $doComposer = true;
            break;
        default:
            echo "Usage: $script au|ca|bcpnp|ntnp|dm [--config] [--modules] [--composer]. If no optional parameters specified, all operations will be done by default.\n";
            exit(1);
    }
}

// Get all the functions
require_once "shared.php";

if ($doConfig) {
    prep_config($configDir, $project);
}
if ($doModuleConfig) {
    prep_modules_config($configDir, $project);
}
if ($doComposer) {
    prep_composer($rootDir, $project);
}

setup_project_flag($rootDir, $project);
