<?php

namespace System;

use Laminas\Router\Http\Segment;
use System\Controller\Factory\ImportControllerFactory;
use System\Controller\Factory\IndexControllerFactory;
use System\Controller\ImportController;
use System\Controller\IndexController;

return [
    'router' => [
        'routes' => [
            'system_import' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/system/import[/:action[/]]',
                    'defaults' => [
                        'controller' => ImportController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'system_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/system/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            ImportController::class => ImportControllerFactory::class,
            IndexController::class => IndexControllerFactory::class
        ],
    ],

    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
