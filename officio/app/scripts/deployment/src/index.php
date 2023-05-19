<?php

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    echo 'Warning: Officio should be invoked via the CLI version of PHP, not the ' . PHP_SAPI . ' SAPI' . PHP_EOL;
}

setlocale(LC_ALL, 'C');
error_reporting(-1);

if (sizeof($argv) < 2) {
    echo "Command not specified.\n";
    echo "Usage: officio.phar [command]\n";
    echo "Available commands are:\n";
    echo "  * select_project\n";
    echo "  * update_composer\n";
    exit(1);
}

$command = $argv[1];
if (!in_array($command, ['select_project', 'update_composer', 'cleanup_project'])) {
    echo "Command unknown.\n";
    echo "Usage: officio.phar [command]\n";
    echo "Available commands are:\n";
    echo "  * select_project\n";
    echo "  * update_composer\n";
    echo "  * cleanup_project\n";
    exit(1);
}

switch ($command) {
    case 'select_project':
        require_once 'select_project.php';
        break;

    case 'update_composer':
        require_once 'update_composer.php';
        break;

    case 'cleanup_project':
        require_once 'cleanup_project.php';
        break;
}

