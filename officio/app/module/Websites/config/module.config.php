<?php

namespace Websites;


use Laminas\Router\Http\Segment;
use Websites\Controller\Factory\IndexControllerFactory;
use Websites\Controller\IndexController;
use Websites\Service\CompanyWebsites;
use Websites\Service\Factory\CompanyWebsitesFactory;

return [
    'service_manager' => [
        'factories' => [
            CompanyWebsites::class => CompanyWebsitesFactory::class
        ]
    ],
    'router' => [
        'routes' => [
            'websites_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/websites[/:action[/:entrance[/:page]]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                        'page' => 'homepage',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'entrance' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'page' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'websites_companies' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/webs/:entrance[/:page[/:action[/]]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                        'page' => 'homepage',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'page' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'entrance' => '[a-zA-Z][a-zA-Z0-9_-]*',
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
        'template_map' => [
            'layout/websites' => __DIR__ . '/../view/layout/main.phtml',
            'website/boleo' => 'public/templates/boleo/index.php',
            'website/DK2' => 'public/templates/DK2/index.php',
            'website/gipo' => 'public/templates/gipo/index.php',
            'website/gourmet' => 'public/templates/gourmet/index.php',
            'website/prospect' => 'public/templates/prospect/index.php',
            'website/editTemplate' => 'public/templates/editTemplate/index.php',
            'website/viewTemplate' => 'public/templates/viewTemplate/index.php',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
