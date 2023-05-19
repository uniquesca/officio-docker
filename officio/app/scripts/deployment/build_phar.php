<?php

$filename = __DIR__ . "/../../officio.phar";

// clean up
if (file_exists($filename)) {
    unlink($filename);
}

if (file_exists($filename . '.gz')) {
    unlink($filename . '.gz');
}

$phar = new Phar($filename);
$phar->startBuffering();
$phar->setSignatureAlgorithm(Phar::SHA512);
$defaultStub = Phar::createDefaultStub('index.php');
$phar->buildFromDirectory(__DIR__ . '/src/');
$stub = "#!/usr/bin/env php \n" . $defaultStub;
$phar->setStub($stub);
$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);
chmod($filename, 0660);
echo "officio.phar file successfully created.\n";