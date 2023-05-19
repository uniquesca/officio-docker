<?php

namespace Api;


use Api\Controller\Factory\GvControllerFactory;
use Api\Controller\Factory\IndexControllerFactory;
use Api\Controller\Factory\MarketplaceControllerFactory;
use Api\Controller\Factory\RemoteControllerFactory;
use Api\Controller\GvController;
use Api\Controller\IndexController;
use Api\Controller\MarketplaceController;
use Api\Controller\RemoteController;
use Laminas\Router\Http\Segment;

return [
    'service_manager' => [
        'factories' => [
        ]
    ],

    'controllers' => [
        'factories' => [
            GvController::class => GvControllerFactory::class,
            IndexController::class => IndexControllerFactory::class,
            MarketplaceController::class => MarketplaceControllerFactory::class,
            RemoteController::class => RemoteControllerFactory::class
        ],
    ],

    'router' => [
        'routes' => [
            'api_gv' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/api/gv[/:action[/]]',
                    'defaults' => [
                        'controller' => GvController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'api_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/api/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'api_marketplace' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/api/marketplace[/:action[/]]',
                    'defaults' => [
                        'controller' => MarketplaceController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'api_remote' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/api/remote[/:action[/]]',
                    'defaults' => [
                        'controller' => RemoteController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],
        ],
    ],

    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,

        'template_map' => [
            'layout/api' => __DIR__ . '/../view/layout/main.phtml',
        ],

        'template_path_stack' => [
            __DIR__ . '/../view',
        ],

        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
