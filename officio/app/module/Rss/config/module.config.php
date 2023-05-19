<?php

namespace Rss;

use Laminas\Router\Http\Segment;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Rss\Controller\Factory\IndexControllerFactory;
use Rss\Controller\IndexController;
use Rss\Service\Rss;

return [
    'service_manager' => [
        'factories' => [
            Rss::class => BaseServiceFactory::class
        ]
    ],
    'router' => [
        'routes' => [
            'rss_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/rss/index[/:action[/]]',
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
    ],
];
