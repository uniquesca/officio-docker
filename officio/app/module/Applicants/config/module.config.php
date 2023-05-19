<?php

namespace Applicants;

use Applicants\Controller\AnalyticsController;
use Applicants\Controller\Factory\AnalyticsControllerFactory;
use Applicants\Controller\Factory\IndexControllerFactory;
use Applicants\Controller\Factory\ProfileControllerFactory;
use Applicants\Controller\Factory\QueueControllerFactory;
use Applicants\Controller\Factory\SearchControllerFactory;
use Applicants\Controller\IndexController;
use Applicants\Controller\ProfileController;
use Applicants\Controller\QueueController;
use Applicants\Controller\SearchController;
use Laminas\Router\Http\Segment;

return [
    'service_manager' => [
        'factories' => [
        ]
    ],

    'controllers' => [
        'factories' => [
            AnalyticsController::class => AnalyticsControllerFactory::class,
            IndexController::class => IndexControllerFactory::class,
            ProfileController::class => ProfileControllerFactory::class,
            QueueController::class => QueueControllerFactory::class,
            SearchController::class => SearchControllerFactory::class
        ],
    ],

    'router' => [
        'routes' => [
            'applicants_analytics' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/applicants/analytics[/:action[/]]',
                    'defaults' => [
                        'controller' => AnalyticsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'applicants_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/applicants/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'applicants_profile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/applicants/profile[/:action[/]]',
                    'defaults' => [
                        'controller' => ProfileController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'applicants_queue' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/applicants/queue[/:action[/]]',
                    'defaults' => [
                        'controller' => QueueController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'applicants_search' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/applicants/search[/:action[/]]',
                    'defaults' => [
                        'controller' => SearchController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],
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
