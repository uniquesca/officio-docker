<?php

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

$lockSettings = require 'config/lock.config.php';
$accountId = (int)$_POST['account_id'];
$fp        = fopen($lockSettings['tmp_lock'] . DIRECTORY_SEPARATOR . $accountId . '.lc8', 'w');
fclose($fp);