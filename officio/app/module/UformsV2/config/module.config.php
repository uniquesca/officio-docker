<?php

namespace UformsV2;

use UformsV2\Controller\Factory\IndexControllerFactory;
use UformsV2\Controller\IndexController;
use Laminas\Router\Http\Literal;

return [
    'router' => [
        'routes' => [
            'uformsv2_new-prototype' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/new-prototype',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'newPrototype',
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
