<?php

namespace CmiSignup;

use CmiSignup\Controller\Factory\IndexControllerFactory;
use CmiSignup\Controller\IndexController;
use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'cmi_signup_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/cmi-signup/index[/:action[/]]',
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
