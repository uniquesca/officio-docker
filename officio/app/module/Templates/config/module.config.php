<?php

namespace Templates;


use Laminas\Router\Http\Segment;
use Templates\Controller\Factory\IndexControllerFactory;
use Templates\Controller\IndexController;
use Templates\Service\Factory\TemplateSettingsFactory;
use Templates\Service\Factory\TemplatesFactory;
use Templates\Service\Templates;
use Templates\Service\TemplatesSettings;

return [
    'service_manager' => [
        'factories' => [
            Templates::class         => TemplatesFactory::class,
            TemplatesSettings::class => TemplateSettingsFactory::class,
        ]
    ],

    'router' => [
        'routes' => [
            'templates_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/templates/index[/:action[/]]',
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
