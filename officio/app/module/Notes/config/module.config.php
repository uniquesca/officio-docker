<?php

namespace Notes;

use Laminas\Router\Http\Segment;
use Notes\Controller\Factory\IndexControllerFactory;
use Notes\Controller\IndexController;
use Notes\Service\Factory\NotesFactory;
use Notes\Service\Notes;

return [
    'service_manager' => [
        'factories' => [
            Notes::class => NotesFactory::class
        ]
    ],

    'router' => [
        'routes' => [
            'notes_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/notes/index[/:action[/]]',
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
            IndexController::class => IndexControllerFactory::class,
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
