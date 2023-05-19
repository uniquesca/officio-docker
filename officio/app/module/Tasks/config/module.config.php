<?php

namespace Tasks;

use Laminas\Router\Http\Segment;
use Tasks\Controller\Factory\IndexControllerFactory;
use Tasks\Controller\IndexController;
use Tasks\Service\Factory\TasksFactory;
use Tasks\Service\Tasks;

return [
    'service_manager' => [
        'factories' => [
            Tasks::class => TasksFactory::class
        ]
    ],

    'router' => [
        'routes' => [
            'tasks_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/tasks/index[/:action[/]]',
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
