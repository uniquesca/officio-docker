<?php

namespace Officio;

use Clients\Service\Factory\MembersServiceFactory;
use Clients\Service\Members;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\Session\SaveHandler\SaveHandlerInterface;
use Laminas\Session\Storage\SessionArrayStorage;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Controller\AdminController;
use Officio\Controller\AuthController;
use Officio\Controller\DepositTypesController;
use Officio\Controller\DestinationAccountController;
use Officio\Controller\ErrorController;
use Officio\Controller\Factory\AuthControllerFactory;
use Officio\Controller\Factory\DepositTypesControllerFactory;
use Officio\Controller\Factory\DestinationAccountControllerFactory;
use Officio\Controller\Factory\IndexControllerFactory;
use Officio\Controller\Factory\TranPageControllerFactory;
use Officio\Controller\Factory\TrialControllerFactory;
use Officio\Controller\Factory\WithdrawalTypesControllerFactory;
use Officio\Controller\IndexController;
use Officio\Controller\Plugin\ParamsFromPostOrGet;
use Officio\Controller\TranPageController;
use Officio\Controller\TrialController;
use Officio\Controller\WithdrawalTypesController;
use Officio\Service\AngularApplicationHost;
use Officio\Service\AuthHelper;
use Officio\Service\AutomatedBillingErrorCodes;
use Officio\Service\AutomatedBillingLog;
use Officio\Service\AutomaticReminders;
use Officio\Service\Bcpnp;
use Officio\Service\Company;
use Officio\Service\CompanyCreator;
use Officio\Service\ConditionalFields;
use Officio\Common\Service\Country;
use Officio\Service\Factory\AuthHelperFactory;
use Officio\Service\Factory\GstHstFactory;
use Officio\Service\Factory\NavigationFactory;
use Officio\Service\Factory\RolesFactory;
use Officio\Service\Factory\SessionSaveHandlerFactory;
use Officio\Service\Factory\SmsFactory;
use Officio\Service\Factory\StatisticsFactory;
use Officio\Service\Factory\SummaryNotificationsFactory;
use Officio\Service\Factory\SystemTriggersFactory;
use Officio\Service\GstHst;
use Officio\Service\Navigation;
use Officio\Service\OAuth2Client;
use Officio\Service\Payment\Stripe;
use Officio\Service\Payment\TranPage;
use Officio\Service\Factory\PaymentServiceFactory;
use Officio\Service\PricingCategories;
use Officio\Service\Factory\AutomaticRemindersFactory;
use Officio\Service\Factory\CompanyFactory;
use Officio\Service\Factory\CompanyCreatorFactory;
use Officio\Service\Factory\LetterheadsFactory;
use Officio\Service\Factory\TicketsFactory;
use Officio\Service\Factory\UsersServiceFactory;
use Officio\Service\Letterheads;
use Officio\Service\Roles;
use Officio\Service\Sms;
use Officio\Service\Statistics;
use Officio\Service\SummaryNotifications;
use Officio\Service\SystemTriggers;
use Officio\Service\Tickets;
use Officio\Service\Users;
use Officio\Service\ZohoKeys;
use Officio\View\Helper\Factory\MinifierFactory;
use Officio\View\Helper\FormDropdown;
use Officio\View\Helper\ImgUrl;
use Officio\View\Helper\MessageBox;
use Officio\View\Helper\Minifier;
use Officio\View\Helper\MinJs;
use Officio\View\Helper\MinStyleSheets;

return [
    'session_storage' => [
        'type' => SessionArrayStorage::class
    ],

    'service_manager' => [
        'factories' => [
            // Laminas services
            SaveHandlerInterface::class       => SessionSaveHandlerFactory::class,

            // Officio services
            AuthHelper::class                 => AuthHelperFactory::class,
            OAuth2Client::class               => BaseServiceFactory::class,
            AutomatedBillingLog::class        => BaseServiceFactory::class,
            AutomatedBillingErrorCodes::class => BaseServiceFactory::class,
            Letterheads::class                => LetterheadsFactory::class,
            Tickets::class                    => TicketsFactory::class,
            Users::class                      => UsersServiceFactory::class,
            Company::class                    => CompanyFactory::class,
            CompanyCreator::class             => CompanyCreatorFactory::class,
            ConditionalFields::class          => BaseServiceFactory::class,
            ZohoKeys::class                   => BaseServiceFactory::class,
            Members::class                    => MembersServiceFactory::class,
            AutomaticReminders::class         => AutomaticRemindersFactory::class,
            Bcpnp::class                      => BaseServiceFactory::class,
            GstHst::class                     => GstHstFactory::class,
            Navigation::class                 => NavigationFactory::class,
            PricingCategories::class          => BaseServiceFactory::class,
            Statistics::class                 => StatisticsFactory::class,
            SummaryNotifications::class       => SummaryNotificationsFactory::class,
            Country::class                    => BaseServiceFactory::class,
            Roles::class                      => RolesFactory::class,
            SystemTriggers::class             => SystemTriggersFactory::class,
            Sms::class                        => SmsFactory::class,
            AngularApplicationHost::class     => BaseServiceFactory::class,

            // TODO This should be an alias
            'payment'                         => PaymentServiceFactory::class,
            Stripe::class                     => BaseServiceFactory::class,
            TranPage::class                   => BaseServiceFactory::class,

            AutomaticReminders\Triggers::class   => AutomaticReminders\Factory\TriggersFactory::class,
            AutomaticReminders\Conditions::class => AutomaticReminders\Factory\ConditionsFactory::class,
            AutomaticReminders\Actions::class    => AutomaticReminders\Factory\ActionsFactory::class,

            Company\Packages::class             => Company\Factory\PackagesFactory::class,
            Company\CompanyTADivisions::class   => BaseServiceFactory::class,
            Company\CompanyExport::class        => BaseServiceFactory::class,
            Company\CompanyTrial::class         => BaseServiceFactory::class,
            Company\CompanyCMI::class           => BaseServiceFactory::class,
            Company\CompanyInvoice::class       => Company\Factory\CompanyInvoiceFactory::class,
            Company\CompanyDivisions::class     => Company\Factory\CompanyDivisionsFactory::class,
            Company\CompanySubscriptions::class => Company\Factory\CompanySubscriptionsFactory::class,
            Company\CompanyMarketplace::class   => Company\Factory\CompanyMarketplaceFactory::class,

            Minifier::class => InvokableFactory::class,
        ],
    ],

    'controllers' => [
        'factories' => [
            AuthController::class => AuthControllerFactory::class,
            AdminController::class => BaseControllerFactory::class,
            DepositTypesController::class => DepositTypesControllerFactory::class,
            DestinationAccountController::class => DestinationAccountControllerFactory::class,
            ErrorController::class => BaseControllerFactory::class,
            IndexController::class => IndexControllerFactory::class,
            TranPageController::class => TranPageControllerFactory::class,
            TrialController::class => TrialControllerFactory::class,
            WithdrawalTypesController::class => WithdrawalTypesControllerFactory::class,
        ],
    ],

    'controller_plugins' => [
        'invokables' => [
            'paramsFromPostOrGet' => ParamsFromPostOrGet::class,
        ]
    ],

    'router' => [
        'routes' => [
            'api2' => [
                // API v2 routes which didn't make it into the API2 module
                'child_routes' => [
                    'api2-login'  => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/login',
                            'defaults' => [
                                'controller' => AuthController::class,
                                'action'     => 'api-login'
                            ]
                        ]
                    ],
                    'api2-logout' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/logout',
                            'defaults' => [
                                'controller' => AuthController::class,
                                'action'     => 'api-logout'
                            ]
                        ]
                    ],
                ],
            ],

            'default_error' => [
                'type'    => 'literal',
                'options' => [
                    'route'    => '/error/error',
                    'defaults' => [
                        'controller' => ErrorController::class,
                        'action'     => 'error',
                    ],
                ],
            ],

            'access_denied_error' => [
                'type'    => 'literal',
                'options' => [
                    'route'    => '/error/access-denied',
                    'defaults' => [
                        'controller' => ErrorController::class,
                        'action'     => 'access-denied',
                    ],
                ],
            ],

            'login' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/login[/:logged_out/:logged_out_from]',
                    'defaults'    => [
                        'controller' => AuthController::class,
                        'action'     => 'login',
                    ],
                    'constraints' => [
                        'logged_out' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'logged_out_from' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'logout' => [
                'type' => 'literal',
                'options' => [
                    'route'    => '/logout',
                    'defaults' => [
                        'controller' => AuthController::class,
                        'action'     => 'logout',
                    ],
                ],
            ],

            'auth' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/auth[/:action[/]]',
                    'defaults'    => [
                        'controller' => AuthController::class,
                        'action'     => 'index',
                    ],
                    'constraints' => [
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'home' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ]
                ]
            ],

            'default_admin' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/default/admin[/:action[/]]',
                    'defaults' => [
                        'controller' => AdminController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'default_deposit_types' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/default/deposit-types[/:action[/]]',
                    'defaults' => [
                        'controller' => DepositTypesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'default_destination_account' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/default/destination-account[/:action[/]]',
                    'defaults' => [
                        'controller' => DestinationAccountController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'default_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/default/index[/:action[/]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'default_tran_page' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/default/tran-page[/:action[/]]',
                    'defaults' => [
                        'controller' => TranPageController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'default_trial' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/default/trial[/:action[/]]',
                    'defaults' => [
                        'controller' => TrialController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'default_withdrawal_types' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/default/withdrawal[/:action[/]]',
                    'defaults' => [
                        'controller' => WithdrawalTypesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],
            'min' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/min[/]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'min',
                    ]
                ],
            ],
        ],
    ],

    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/not-found',
        'exception_template'       => 'error/index',
        'template_map'             => [
            'layout/admin'     => __DIR__ . '/../view/layout/admin/admin.phtml',
            'layout/public'    => __DIR__ . '/../view/layout/public/main.phtml',
            'layout/plain'     => __DIR__ . '/../view/layout/main/plain.phtml',
            'layout/layout'    => __DIR__ . '/../view/layout/main/main.phtml',
            'layout/error'     => __DIR__ . '/../view/layout/main/error.phtml',
            'layout/bootstrap' => __DIR__ . '/../view/layout/bootstrap/main.phtml',
            'auth/login'       => __DIR__ . '/../view/layout/auth/login.phtml',
            'auth/recovery'    => __DIR__ . '/../view/layout/auth/recovery.phtml',
            'error/not-found'  => __DIR__ . '/../view/error/404.phtml',
            'error/forbidden'  => __DIR__ . '/../view/error/forbidden.phtml',
            'error/index'      => __DIR__ . '/../view/error/error.phtml',
        ],
        'template_path_stack'      => [
            __DIR__ . '/../view',
        ],
        'strategies'               => [
            'ViewJsonStrategy',
        ]
    ],

    // The following registers our custom view
    // helper classes in view plugin manager.
    'view_helpers' => [
        'factories' => [
            FormDropdown::class   => InvokableFactory::class,
            ImgUrl::class         => InvokableFactory::class,
            MessageBox::class     => InvokableFactory::class,
            Minifier::class       => MinifierFactory::class,
            MinStyleSheets::class => MinifierFactory::class,
            MinJs::class          => MinifierFactory::class,
        ],
        'aliases'   => [
            'formDropdown'   => FormDropdown::class,
            'messageBox'     => MessageBox::class,
            'imgUrl'         => ImgUrl::class,
            'minifier'       => Minifier::class,
            'minStyleSheets' => MinStyleSheets::class,
            'minJs'          => MinJs::class,
        ]
    ],
];
