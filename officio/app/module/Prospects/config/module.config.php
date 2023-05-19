<?php

namespace Prospects;


use Laminas\Router\Http\Segment;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Prospects\Controller\Factory\IndexControllerFactory;
use Prospects\Controller\IndexController;
use Prospects\Service\CompanyProspectOffices;
use Prospects\Service\CompanyProspects;
use Prospects\Service\CompanyProspectsPoints;
use Prospects\Service\CompanyQnr;
use Prospects\Service\Factory\CompanyProspectPointsFactory;
use Prospects\Service\Factory\CompanyProspectsFactory;
use Prospects\Service\Factory\CompanyQnrFactory;
use Prospects\Service\Factory\ProspectsFactory;
use Prospects\Service\Prospects;

return [
    'service_manager' => [
        'factories' => [
            Prospects::class        => ProspectsFactory::class,
            CompanyProspects::class => CompanyProspectsFactory::class,

            CompanyQnr::class             => CompanyQnrFactory::class,
            CompanyProspectOffices::class => BaseServiceFactory::class,
            CompanyProspectsPoints::class => CompanyProspectPointsFactory::class,
        ]
    ],
    'router' => [
        'routes' => [
            'prospects_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/prospects/index[/:action[/:filename]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'filename' => '[a-zA-Z][a-zA-Z0-9\._-]*',
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
