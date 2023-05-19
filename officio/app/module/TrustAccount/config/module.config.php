<?php

namespace TrustAccount;

use Laminas\Router\Http\Segment;
use TrustAccount\Controller\AssignController;
use TrustAccount\Controller\EditController;
use TrustAccount\Controller\Factory\AssignControllerFactory;
use TrustAccount\Controller\Factory\EditControllerFactory;
use TrustAccount\Controller\Factory\HistoryControllerFactory;
use TrustAccount\Controller\Factory\ImportControllerFactory;
use TrustAccount\Controller\Factory\IndexControllerFactory;
use TrustAccount\Controller\HistoryController;
use TrustAccount\Controller\ImportController;
use TrustAccount\Controller\IndexController;

return [
    'router' => [
        'routes' => [
            'trust_account_assign' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/trust-account/assign[/:action[/]]',
                    'defaults' => [
                        'controller' => AssignController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'trust_account_edit' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/trust-account/edit[/:action[/]]',
                    'defaults' => [
                        'controller' => EditController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'trust_account_history' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/trust-account/history[/:action[/]]',
                    'defaults' => [
                        'controller' => HistoryController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'trust_account_import' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/trust-account/import[/:action[/]]',
                    'defaults' => [
                        'controller' => ImportController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'trust_account_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/trust-account/index[/:action[/]]',
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
            AssignController::class => AssignControllerFactory::class,
            EditController::class => EditControllerFactory::class,
            HistoryController::class => HistoryControllerFactory::class,
            ImportController::class => ImportControllerFactory::class,
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
