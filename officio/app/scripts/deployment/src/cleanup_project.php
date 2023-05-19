<?php

/**
 * This script deletes all the development-related files and prepares Officio for a project-specific distribution.
 */

$script = sprintf('%s %s', $argv[0], $argv[1]);
if (sizeof($argv) < 3) {
    echo "No project specified.\n";
    echo "Usage: $script au|ca|bcpnp|ntnp|dm\n";
    exit(1);
}

$project  = $argv[2];
$projects = ['au', 'ca', 'bcpnp', 'ntnp', 'dm'];
if (!in_array($project, $projects)) {
    echo "Unknown project specified.\n";
    echo "Usage: $script au|ca|bcpnp|ntnp|dm\n";
    exit(1);
}

// Get all the functions
require_once "shared.php";

$rootDir = dirname(Phar::running(false));
remove_dir_recursively("$rootDir/.github");
remove_dir_recursively("$rootDir/composer");
remove_dir_recursively("$rootDir/config/autoload/stubs");
remove_dir_recursively("$rootDir/config/modules");
remove_dir_recursively("$rootDir/docs");

$dirsToCleanup   = array_map('realpath', [
    "$rootDir/scripts/db/",
    "$rootDir/scripts/migrations/",
    "$rootDir/scripts/migrations/archive/",
    "$rootDir/scripts/migrations/manual/",
]);
$miscDbScriptDir = "$rootDir/scripts/misc/";
foreach ($dirsToCleanup as $dirToCleanup) {
    $dirContent = scandir($dirToCleanup);
    foreach ($dirContent as $item) {
        if (in_array($item, ['.', '..'])) {
            continue;
        }

        $itemPath = realpath($dirToCleanup . '/' . $item);
        if (in_array($itemPath, $dirsToCleanup) || $itemPath == $miscDbScriptDir) {
            continue;
        }

        $isDir = is_dir($itemPath);
        if ($isDir) {
            if ($item !== $project) {
                remove_dir_recursively($itemPath);
            }
        }
    }
}

copy_dir_recursively("$rootDir/scripts/db/$project/", "$rootDir/scripts/db/");
copy_dir_recursively("$rootDir/scripts/migrations/$project/", "$rootDir/scripts/migrations/");
copy_dir_recursively("$rootDir/scripts/migrations/archive/$project/", "$rootDir/scripts/migrations/archive/");
copy_dir_recursively("$rootDir/scripts/migrations/manual/$project/", "$rootDir/scripts/migrations/manual/");
remove_dir_recursively("$rootDir/scripts/db/$project/");
remove_dir_recursively("$rootDir/scripts/migrations/$project/");
remove_dir_recursively("$rootDir/scripts/migrations/archive/$project/");
remove_dir_recursively("$rootDir/scripts/migrations/manual/$project/");

// Setup gitignore file
setup_gitignore($rootDir, $project);

// Delete deployment scripts
remove_dir_recursively("$rootDir/scripts/deployment/src/");
unlink("$rootDir/scripts/deployment/build_phar.php");
unlink("$rootDir/officio.phar");