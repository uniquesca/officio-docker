<?php

namespace Links;


use Laminas\Router\Http\Segment;
use Links\Controller\Factory\IndexControllerFactory;
use Links\Controller\IndexController;
use Links\Service\Factory\LinksFactory;
use Links\Service\Links;

return [
    'service_manager' => [
        'factories' => [
            Links::class => LinksFactory::class
        ]
    ],

    'router' => [
        'routes' => [
            'links_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/links/index[/:action[/]]',
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
