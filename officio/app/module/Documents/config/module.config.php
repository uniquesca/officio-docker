<?php

namespace Documents;

use Documents\Service\Factory\DocumentsFactory;
use Documents\Controller\IndexController;
use Documents\Controller\ChecklistController;
use Documents\Controller\ManagerController;
use Documents\Controller\Factory\ChecklistControllerFactory;
use Documents\Controller\Factory\ManagerControllerFactory;
use Documents\Controller\Factory\IndexControllerFactory;
use Documents\Service\Documents;
use Documents\Service\Phpdocx;
use Laminas\Router\Http\Segment;
use Officio\Common\Service\Factory\BaseServiceFactory;

return [
    'service_manager' => [
        'factories' => [
            Documents::class => DocumentsFactory::class,
            Phpdocx::class   => BaseServiceFactory::class
        ]
    ],

    'router' => [
        'routes' => [
            'documents_checklist' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/documents/checklist[/:action[/]]',
                    'defaults'    => [
                        'controller' => ChecklistController::class,
                        'action'     => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'documents_manager' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/documents/manager[/:action[/]]',
                    'defaults'    => [
                        'controller' => ManagerController::class,
                        'action'     => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'documents_index' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/documents/index[/:action[/]]',
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
            IndexController::class     => IndexControllerFactory::class,
            ChecklistController::class => ChecklistControllerFactory::class,
            ManagerController::class   => ManagerControllerFactory::class
        ],
    ],

    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'template_path_stack'      => [
            __DIR__ . '/../view',
        ],
        'strategies'               => [
            'ViewJsonStrategy',
        ],
    ],
];
