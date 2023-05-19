<?php

namespace Help;


use Help\Controller\Factory\IndexControllerFactory;
use Help\Controller\Factory\PublicControllerFactory;
use Help\Controller\IndexController;
use Help\Controller\PublicController;
use Help\Service\Factory\HelpFactory;
use Help\Service\Help;
use Laminas\Router\Http\Segment;

return [
    'service_manager' => [
        'factories' => [
            Help::class => HelpFactory::class
        ],
    ],

    'router' => [
        'routes' => [
            'help_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/help/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'help_public' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/help/public[/:action[/]]',
                    'defaults' => [
                        'controller' => PublicController::class,
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
            IndexController::class => IndexControllerFactory::class,
            PublicController::class => PublicControllerFactory::class
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
