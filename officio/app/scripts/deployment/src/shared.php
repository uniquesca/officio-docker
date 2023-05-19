<?php

/**
 * Set of shared functions for deployment scripts
 */

function get_projects()
{
    return ['au', 'ca', 'bcpnp', 'ntnp', 'dm'];
}

/**
 * Merges json files together
 * @return false|string
 */
function merge_json()
{
    $args     = func_get_args();
    $contents = array_map(function ($file) {
        return json_decode(file_get_contents($file), true);
    }, $args);
    return json_encode(array_merge_recursive(...$contents), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Prepares project-specific config
 * @param $configDir
 * @param $project
 * @return void
 */
function prep_config($configDir, $project)
{
    $projectSampleConfig = "$configDir/autoload/stubs/$project.local.php.dist";
    if (file_exists($projectSampleConfig)) {
        copy($projectSampleConfig, "$configDir/autoload/local.php.dist");
        if (!file_exists("$configDir/autoload/local.php")) {
            copy($projectSampleConfig, "$configDir/autoload/local.php");
            echo "Config stub prepared, make sure to review and properly set it up: $configDir/autoload/local.php\n";
        } else {
            echo "Config $configDir/autoload/local.php already exists, skipping. If you want config set, delete it manually and re-run this script.\n";
        }
    } else {
        echo "Config stub $projectSampleConfig not found for the project!\n";
    }
}

/**
 * Prepares project specific list of modules
 * @param $configDir
 * @param $project
 * @param bool $printOutput Whether function should print status output
 * @return bool
 */
function prep_modules_config($configDir, $project, $printOutput = true)
{
    $baseModulesList    = "$configDir/modules/base.modules.json";
    $projectModulesList = "$configDir/modules/$project.modules.json";
    $output             = "$configDir/modules.json";
    if (file_exists($projectModulesList)) {
        $outputContents = merge_json($baseModulesList, $projectModulesList);
        file_put_contents($output, $outputContents);
        if ($printOutput) {
            echo "Module config stub prepared, you can review it at: $output\n";
        }
        return true;
    } else {
        if ($printOutput) {
            echo "Module config stub $projectModulesList not found for the project!\n";
        }
        return false;
    }
}

/**
 * Prepares project-specific composer files
 * @param $rootDir
 * @param $project
 * @param string $printOutput Whether function should print status output
 * @return bool Result of the operation
 */
function prep_composer($rootDir, $project, $printOutput = true)
{
    $result                = true;
    $composerBaseConfig    = "$rootDir/composer/base.composer.json";
    $composerProjectConfig = "$rootDir/composer/$project.composer.json";
    $composerProjectLock   = "$rootDir/composer/$project.composer.lock";
    $output                = "$rootDir/composer.json";
    if (file_exists($composerProjectConfig)) {
        $outputContents = merge_json($composerBaseConfig, $composerProjectConfig);
        file_put_contents($output, $outputContents);
        if ($printOutput) {
            echo "composer.json file prepared, you can review it at: $output\n";
        }
    } else {
        if ($printOutput) {
            echo "composer.json stub $composerProjectConfig not found for the project!\n";
        }
        $result = false;
    }

    if (file_exists($composerProjectLock)) {
        copy($composerProjectLock, "$rootDir/composer.lock");
    } elseif (file_exists("$rootDir/composer.lock")) {
        unlink("$rootDir/composer.lock");
    }

    return $result;
}

/**
 * Sets up project flag
 * @param $rootDir
 * @param $project
 * @return void
 */
function setup_project_flag($rootDir, $project)
{
    file_put_contents("$rootDir/.officioproject", $project);
}

/**
 * Replaces main .gitignore file with the project-specific one
 * @param $rootDir
 * @param $project
 * @return void
 */
function setup_gitignore($rootDir, $project)
{
    $projectGitignore = "$rootDir/.gitignore.$project";
    if (file_exists($projectGitignore)) {
        unlink("$rootDir/.gitignore");
        copy($projectGitignore, "$rootDir/.gitignore");
        unlink($projectGitignore);

        foreach (get_projects() as $existingProject) {
            if ($existingProject !== $project) {
                unlink("$rootDir/.gitignore.$existingProject");
            }
        }
    }
}

/**
 * Drops directory with all it's contents
 * @param $dir
 * @return void
 */
function remove_dir_recursively($dir)
{
    if (stristr(PHP_OS, 'WIN')) {
        exec(sprintf("rd /s /q %s", escapeshellarg($dir)));
    } else {
        exec(sprintf("rm -rf %s", escapeshellarg($dir)));
    }
}

/**
 * Copies directory with all it's contents
 * @param $sourceDirectory
 * @param $destinationDirectory
 * @return void
 */
function copy_dir_recursively($sourceDirectory, $destinationDirectory)
{
    if (is_dir($sourceDirectory) === false) {
        return;
    }

    $directory = opendir($sourceDirectory);

    if (is_dir($destinationDirectory) === false) {
        mkdir($destinationDirectory);
    }

    while (($file = readdir($directory)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        if (is_dir("$sourceDirectory/$file") === true) {
            copy_dir_recursively("$sourceDirectory/$file", "$destinationDirectory/$file");
        } else {
            copy("$sourceDirectory/$file", "$destinationDirectory/$file");
        }
    }

    closedir($directory);
}