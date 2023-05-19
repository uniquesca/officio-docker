<?php

namespace News;


use Laminas\Router\Http\Segment;
use News\Controller\Factory\IndexControllerFactory;
use News\Controller\IndexController;
use News\Service\Factory\NewsFactory;
use News\Service\News;

return [
    'service_manager' => [
        'factories' => [
            News::class => NewsFactory::class
        ]
    ],

    'router' => [
        'routes' => [
            'news_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/news/index[/:action[/]]',
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
