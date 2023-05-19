<?php

namespace Calendar;

use Calendar\Controller\Factory\IndexControllerFactory;
use Calendar\Controller\IndexController;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'calendar_index'  => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/calendar/index',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'index',
                    ]
                ],
            ],
            'calendar_public' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/calendar/public/:token',
                    'defaults'    => [
                        'controller' => IndexController::class,
                        'action'     => 'public',
                    ],
                    'constraints' => [
                        'token' => '[a-zA-Z0-9]*',
                    ],
                ],
            ],

            'calendar_application'        => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/calendar/get-application',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'getApplication',
                    ],
                ],
            ],
            'calendar_public_application' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/calendar/get-public-application/:token',
                    'defaults'    => [
                        'controller' => IndexController::class,
                        'action'     => 'getPublicApplication',
                    ],
                    'constraints' => [
                        'token' => '[a-zA-Z0-9]*',
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
    ],
];
