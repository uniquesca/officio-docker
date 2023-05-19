<?php

namespace Signup;

use Laminas\Router\Http\Segment;
use Signup\Controller\Factory\IndexControllerFactory;
use Signup\Controller\IndexController;

return [
    'router' => [
        'routes' => [
            'signup_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/signup/index[/:action[/]]',
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
