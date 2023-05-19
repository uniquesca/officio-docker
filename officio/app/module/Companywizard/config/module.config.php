<?php

namespace Companywizard;

use Companywizard\Controller\Factory\IndexControllerFactory;
use Companywizard\Controller\IndexController;
use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'companywizard_index' => [
                'type' => Segment::class,
                'options' => [
                    'route'       => '/companywizard[/index[/:action[/]]]',
                    'defaults'    => [
                        'controller' => IndexController::class,
                        'action'     => 'index',
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
    ],
];
