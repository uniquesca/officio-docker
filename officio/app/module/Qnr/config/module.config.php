<?php

namespace Qnr;

use Laminas\Router\Http\Segment;
use Qnr\Controller\Factory\IndexControllerFactory;
use Qnr\Controller\IndexController;

return [
    'router' => [
        'routes' => [
            'qnr_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/qnr[/index[/:action[/]]]',
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
        'template_map' => [
            'layout/qnr' => __DIR__ . '/../view/layout/main.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
