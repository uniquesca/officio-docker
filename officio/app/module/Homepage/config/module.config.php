<?php

namespace Homepage;

use Homepage\Controller\Factory\IndexControllerFactory;
use Homepage\Controller\IndexController;
use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'homepage_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/homepage/index[/:action[/]]',
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
