<?php

namespace Files;


use Files\Controller\Plugin\DownloadFile;
use Files\Controller\Plugin\File;
use Files\Controller\Plugin\FileNotFound;
use Files\Service\Cloud;
use Files\Service\Factory\CloudFactory;
use Files\Service\Factory\FilesFactory;
use Files\Service\Factory\FoldersFactory;
use Files\Service\Files;
use Files\Service\FolderAccess;
use Files\Service\Folders;
use Officio\Common\Service\Factory\BaseServiceFactory;

return [
    'service_manager' => [
        'factories' => [
            Files::class        => FilesFactory::class,
            Cloud::class        => CloudFactory::class,
            Folders::class      => FoldersFactory::class,
            FolderAccess::class => BaseServiceFactory::class,
        ]
    ],
    'router' => [
        'routes' => [

        ],
    ],
    'controllers' => [
        'invokables' => [

        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'file' => File::class,
            'downloadFile' => DownloadFile::class,
            'fileNotFound' => FileNotFound::class,
        ]
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
