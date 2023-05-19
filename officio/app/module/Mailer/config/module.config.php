<?php

namespace Mailer;

use Laminas\Router\Http\Segment;
use Mailer\Controller\Factory\IndexControllerFactory;
use Mailer\Controller\Factory\SettingsControllerFactory;
use Mailer\Controller\IndexController;
use Mailer\Controller\SettingsController;
use Mailer\Service\Factory\MailerFactory;
use Mailer\Service\Mailer;
use Mailer\Service\MailerLog;
use Officio\Common\Service\Factory\BaseServiceFactory;

return [
    'service_manager' => [
        'factories' => [
            Mailer::class    => MailerFactory::class,
            MailerLog::class => BaseServiceFactory::class,
        ]
    ],

    'router' => [
        'routes' => [
            'mail_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/mailer/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'mail_settings' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/mailer/settings[/:action[/]]',
                    'defaults' => [
                        'controller' => SettingsController::class,
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
            SettingsController::class => SettingsControllerFactory::class
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
