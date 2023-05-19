<?php

namespace Forms;

use Forms\Controller\AngularFormsController;
use Forms\Controller\Factory\AngularFormsControllerFactory;
use Forms\Controller\Factory\FormsFoldersControllerFactory;
use Forms\Controller\Factory\IndexControllerFactory;
use Forms\Controller\Factory\SyncControllerFactory;
use Forms\Controller\FormsFoldersController;
use Forms\Controller\IndexController;
use Forms\Controller\Plugin\XfdfPreprocessor;
use Forms\Controller\Plugin\XfdfProcessor;
use Forms\Controller\SyncController;
use Forms\Service\Factory\DominicaFactory;
use Forms\Service\Factory\FormsFactory;
use Forms\Service\Factory\PdfFactory;
use Forms\Service\Factory\XfdfDbSyncFactory;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Forms\Service\Dominica;
use Forms\Service\XfdfDbSync;
use Laminas\Router\Http\Segment;
use Officio\Common\Service\Factory\BaseServiceFactory;

return [
    'service_manager' => [
        'factories' => [
            Forms::class         => FormsFactory::class,
            Pdf::class           => PdfFactory::class,
            Dominica::class      => DominicaFactory::class,
            XfdfDbSync::class    => XfdfDbSyncFactory::class,

            Forms\FormAssigned::class  => BaseServiceFactory::class,
            Forms\FormVersion::class   => Forms\Factory\FormVersionFactory::class,
            Forms\FormUpload::class    => BaseServiceFactory::class,
            Forms\FormTemplates::class => BaseServiceFactory::class,
            Forms\FormLanding::class   => BaseServiceFactory::class,
            Forms\FormFolder::class    => BaseServiceFactory::class,
            Forms\FormMap::class       => BaseServiceFactory::class,
            Forms\FormProcessed::class => BaseServiceFactory::class,
            Forms\FormRevision::class  => Forms\Factory\FormRevisionFactory::class,
            Forms\FormSynField::class  => BaseServiceFactory::class,
        ]
    ],
    'controller_plugins' => [
        'invokables' => [
            'xfdfPreprocessor' => XfdfPreprocessor::class,
            'xfdfProcessor' => XfdfProcessor::class,
        ]
    ],
    'controllers' => [
        'factories' => [
            AngularFormsController::class => AngularFormsControllerFactory::class,
            FormsFoldersController::class => FormsFoldersControllerFactory::class,
            IndexController::class => IndexControllerFactory::class,
            SyncController::class => SyncControllerFactory::class
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

    'router' => [
        'routes' => [
            'forms_angular' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/forms/angular-forms[/:action[/]]',
                    'defaults' => [
                        'controller' => AngularFormsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'forms_folders' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/forms/forms-folders[/:action[/]]',
                    'defaults' => [
                        'controller' => FormsFoldersController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'forms_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/forms/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'forms_sync' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/forms/sync[/:action[/]]',
                    'defaults' => [
                        'controller' => SyncController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],
        ],
    ],
];
