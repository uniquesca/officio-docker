<?php

namespace Clients;


use Clients\Controller\AccountingController;
use Clients\Controller\Factory\AccountingControllerFactory;
use Clients\Controller\Factory\IndexControllerFactory;
use Clients\Controller\Factory\TimeTrackerControllerFactory;
use Clients\Controller\IndexController;
use Clients\Controller\TimeTrackerController;
use Clients\Service\Analytics;
use Clients\Service\BusinessHours;
use Clients\Service\Clients;
use Clients\Service\ClientsReferrals;
use Clients\Service\ClientsFileStatusHistory;
use Clients\Service\ClientsVisaSurvey;
use Clients\Service\Factory\AnalyticsFactory;
use Clients\Service\Factory\BusinessHoursFactory;
use Clients\Service\Factory\ClientsServiceFactory;
use Clients\Service\Factory\MembersPuaFactory;
use Clients\Service\Factory\MembersQueuesFactory;
use Clients\Service\Factory\MembersServiceFactory;
use Clients\Service\Factory\MembersVevoFactory;
use Clients\Service\Factory\TimeTrackerServiceFactory;
use Clients\Service\Members;
use Clients\Service\MembersPua;
use Clients\Service\MembersQueues;
use Clients\Service\MembersVevo;
use Clients\Service\TimeTracker;
use Laminas\Router\Http\Segment;
use Officio\Common\Service\Factory\BaseServiceFactory;

return [
    'service_manager' => [
        'factories' => [
            // Officio additional services
            TimeTracker::class              => TimeTrackerServiceFactory::class,
            Analytics::class                => AnalyticsFactory::class,
            Members::class                  => MembersServiceFactory::class,
            MembersPua::class               => MembersPuaFactory::class,
            MembersQueues::class            => MembersQueuesFactory::class,
            MembersVevo::class              => MembersVevoFactory::class,
            Clients::class                  => ClientsServiceFactory::class,
            ClientsVisaSurvey::class        => BaseServiceFactory::class,
            ClientsReferrals::class         => BaseServiceFactory::class,
            ClientsFileStatusHistory::class => BaseServiceFactory::class,
            BusinessHours::class            => BusinessHoursFactory::class,

            Clients\Search::class                     => Clients\Factory\SearchFactory::class,
            Clients\TrustAccount::class               => Clients\Factory\TrustAccountFactory::class,
            Clients\Accounting::class                 => Clients\Factory\AccountingFactory::class,
            Clients\ClientsDependentsChecklist::class => Clients\Factory\ClientsDependentsFactory::class,
            Clients\CaseNumber::class                 => Clients\Factory\CaseNumberFactory::class,
            Clients\FieldTypes::class                 => Clients\Factory\FieldTypesFactory::class,
            Clients\CaseTemplates::class              => Clients\Factory\CaseTemplatesFactory::class,
            Clients\CaseVACs::class                   => Clients\Factory\CaseVACsFactory::class,
            Clients\CaseCategories::class             => Clients\Factory\CaseCategoriesFactory::class,
            Clients\CaseStatuses::class               => Clients\Factory\CaseStatusesFactory::class,
            Clients\ApplicantTypes::class             => BaseServiceFactory::class,
            Clients\ApplicantFields::class            => Clients\Factory\ApplicantFieldsFactory::class,
            Clients\Fields::class                     => Clients\Factory\FieldsFactory::class,

        ],
    ],

    'controllers' => [
        'factories' => [
            AccountingController::class => AccountingControllerFactory::class,
            IndexController::class => IndexControllerFactory::class,
            TimeTrackerController::class => TimeTrackerControllerFactory::class
        ],
    ],

    'router' => [
        'routes' => [
            'clients_accounting' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/clients/accounting[/:action[/]]',
                    'defaults' => [
                        'controller' => AccountingController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'clients_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/clients/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'clients_time_tracker' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/clients/time-tracker[/:action[/]]',
                    'defaults' => [
                        'controller' => TimeTrackerController::class,
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
