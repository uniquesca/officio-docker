<?php

/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Laminas\I18n\Translator\Loader\Gettext;

return [
    'db'      => [
        'adapter'               => 'PDO_MYSQL',
        'charset'               => 'utf8',
        'options'               => [
            'autoQuoteIdentifiers' => 1
        ],
        'isDefaultTableAdapter' => true
    ],

    // will be used if debug_memory_usage is enabled
    'db_stat' => [
        'adapter'               => 'PDO_MYSQL',
        'charset'               => 'utf8',
        'options'               => [
            'autoQuoteIdentifiers' => 1
        ],
        'isDefaultTableAdapter' => true
    ],

    'doctrine'       => [
        'driver'     => [
            'attribute_driver' => [
                'class' => AttributeDriver::class,
                'cache' => 'array',
                'paths' => [
                ]
            ],
            'orm_default'      => [
                'drivers' => [

                ],
            ],
        ],
        'connection' => [
            'orm_default' => [
                'driverClass' => Driver::class,
                'params'      => [
                    'host'          => getenv('MYSQL_OFFICIO_HOST'),
                    'port'          => getenv('MYSQL_OFFICIO_PORTS'),
                    'user'          => getenv('MYSQL_ROOT_USER'),
                    'password'      => getenv('MYSQL_ROOT_PASSWORD'),
                    'dbname'        => getenv('MYSQL_OFFICIO_DATABASE'),
                    'driverOptions' => [
                        1002 => 'SET NAMES utf8',
                    ],
                ],
            ]
        ]
    ],

    // Session configuration.
    'session_config' => [
        'cookie_lifetime' => 24 * 60 * 60, // Default timeout is 24 hours
        'gc_maxlifetime'  => 60 * 60 * 24 * 7, // How long to store session data on server (for 1 week).

        // HTTP only attribute must be set on cookies containing session tokens
        // If true - we cannot use flash uploader and 'simple extjs' uploader will be used
        'cookie_httponly' => false,

        // Secure attribute must be set on cookies containing session tokens
        'cookie_secure'   => false,
    ],

    'settings'  => [
        // Email address(or several separated by commas) which will be used for:
        // * Fatal error emails
        // * Catchable error emails
        // * Confirmation emails on companies charging
        'send_fatal_errors_to'    => '',

        // 1 - to show error details in error controller
        'show_error_details'                    => 0,

        //  General php settings related to errors
        'display_startup_errors'                => 0,
        'display_errors'                        => 0,
        'error_reporting'                       => E_ALL & ~E_DEPRECATED & ~E_STRICT,

        // If enabled - memory usage statistic will be saved in the Statistic DB
        'debug_memory_usage'                    => 0,

        // If enabled - users will see a message, website will be not accessible
        // Note: superadmin user still can use the website if there is a cookie with WantToSeeOfflineSite name
        'offline'                               => 0,

        // BCPNP Clients auto-import settings
        'bcpnp_import_identificator_field_name' => '',

        // Turn on/off quick search to use a quick (quicker, but fewer details are checked) or detailed (slower) search
        // Turn off only for BCPNP (or when there are too many clients/cases in the company)
        'quick_search_use_detailed_search'      => 1,

        // Turn on/off quick search to filter active clients only
        'quick_search_active_clients_only'      => 1,
    ],

    // **** **** **** **** ****  Security settings **** **** **** ****
    'security' => [

        // Supported:
        // default       - 'encode/decode' (mcrypt, deprecated) methods
        // password_hash - 'password_hash' php method will be used
        // hash          - 'hash' php method will be used
        // Notes:
        // - Cannot be changed if data was already saved
        // - Password cannot be decoded if 'password_hash' or 'hash' is used
        // - We have a limit of 200 chars (for the password field in the members table), so the algo should generate less than 200 chars hash
        // - Must be supported by php
        'password_hashing_algorithm' => 'password_hash',

        // How long the session will be marked as active if no requests were sent to the server
        'session_timeout'            => 86400, // Default timeout is 24 hours (86400 seconds)

        // A salt is used when 'default' or 'hash' selected (not used for 'password_hash', as salt is dynamic there)
        // cannot be changed if data was already saved
        // note that the max length is 32 chars
        'encryption_key'             => 'officio',

        // If 'hash' is enabled - the algorithm below will be used to hash the password
        'hash.algorithm'             => 'sha512',

        'password_hash'               => [
            // If 'password_hash' is enabled - the algorithm below will be used to hash the password
            'algorithm' => PASSWORD_BCRYPT,
            // Additional options for the 'password_hash'
            'options'   => ['cost' => 11],
        ],

        // Different methods that will be used for default 'encode/decode' functionality
        'encoding_decoding'           => [
            // Supported: openssl
            'adapter'                    => 'openssl',

            // OpenSSL settings
            'openssl_cipher'             => 'aes-256-cbc',
            'openssl_key_hash_algorithm' => 'sha512',
            'openssl_iv_length'          => 32,
        ],

        // should we check password for mix case of characters + common passwords + etc...
        'password_high_secure'        => 0,
        'password_min_length'         => 5,
        'password_max_length'         => 32,
        // Determines if we need to send email on password change event
        'send_password_changed_email' => 0,
        // Determines if we need to disable user account (and send email to support) on X tries of failed login in a row (in a day)
        // 0 - means turned off, don't disable account
        'account_lockout_policy'      => 0,
        // How much time account will be locked (in seconds)
        'account_lockout_time'        => 300,

        // Determines if we need to check logged-in user's password aging
        'password_aging'              => [
            // Also if enabled - we'll check if user have changed password at least once
            'enabled'              => 0,
            // How many last passwords will be saved in DB
            'save_passwords_count' => 3,
            // Settings for admin/user in days
            'admin_lifetime'       => 45,
            'client_lifetime'      => 90,
        ],

        // Enable Cross Site Request Forgery protection if 1
        // Note that config file is here: /library/config/csrf_config.php
        'csrf_protection'             => [
            'enabled' => 1
        ],

        // For password autocomplete. 1 - on; 0 - off/new-password
        'autocompletion'              => [
            'enabled' => 1
        ],

        // If enabled - when company admin or superadmin will log in as the user - this user will be logged out
        // will work if CSRF is disabled
        'logout_user_when_login_as_company_admin' => 1,

        // Chmod for new directories created by Officio (local storage on *nix server only)
        'new_directories_mode'        => 0774,

        // A string (salt) that will be used for the login page hash generation
        'login_hash_key' => 'This is a long text used as a salt!',

        'oauth_login' => [
            // If enabled - a link to the oAuth website will be shown,
            // our login page will not work
            'enabled'              => 0,

            // Labels that we'll show on the user details page
            'single_sign_on_label' => 'IDIR Username',
            'guid_label'           => 'GUID',
            // The label we'll use for the button on the login page
            'login_button_label'   => 'BC GOV SSO',

            'provider' => 'keycloak',
            'keycloak' => [
                // Please note that the callback url is: https://officio_url/auth/oauth-callback and it must be added to the allowed list
                'server-url'    => '',
                'realm'         => '',
                'client-id'     => '',
                'client-secret' => '',
                'proxy'         => '',
                'verify'        => false,
            ],

            'google' => [
                // Please note that the callback url is: https://officio_url/mailer/settings/oauth-callback and it must be added to the allowed list
                'client-id'     => '',
                'client-secret' => '',
            ],

            'microsoft' => [
                // Please note that the callback url is: https://officio_url/mailer/settings/oauth-callback and it must be added to the allowed list
                'client-id'     => '',
                'client-secret' => '',
            ]
        ]
    ], // **** **** **** **** ****  Security settings **** **** **** ****

    'site_version'   => [
        // Different site versions settings
        // This name will be used in support emails (e.g. in subject)
        'name'                                         => 'Officio',

        // Default email address - will be used during system emails sending
        'support_email'                                => '',
        'sales_email'                                  => '',

        // A message that will be visible at the top of the main page (both users/admins and superadmins)
        // If empty - nothing will be visible
        'top_warning_message'                          => '',

        // A message that will be visible under the Accounting tab for the case (Payment schedule table, needed for CA)
        'retainer_schedule_help_message'               => '',

        // Used in 'calculate company storage usage' cron - if local storage must be calculated
        'calculate_local_size'                         => 0,

        // Used in 'calculate company storage usage' cron, if 1 - companies without paymentech profile will be not calculated
        'calculate_if_empty_paymentech_profile'        => 0,

        // Use always ssl version if true
        'always_secure'                                => 1,

        // Toggle Comodo SSL check image on the main login page
        'show_ssl_certificate_check_image'             => 0,

        // Toggle POSITIVESSL SSL check image on the main login page
        'show_positivessl_ssl_certificate_check_image' => 0,

        'proxy' => [
            // Determines if Officio is behind the proxy server
            'enabled'                => 0,

            // Header name used to determine client IP if the proxy is enabled
            'forwarded_for_header'   => 'HTTP_X_FORWARDER_FOR',

            // Protocol used to reach proxy. If this is used, forwarded_proto_header setting is ignored.
            'forwarded_proto'        => '',

            // Header name used to determine client scheme if the proxy is enabled
            'forwarded_proto_header' => 'HTTP_X_FORWARDED_PROTO'
        ],

        'package'                                   => [
            // Package id in which client login is allowed
            'client_login_allowed' => 1,
        ],

        // Case Management Setting
        // Used during new company creation
        // If is set to 0 - only one case can be created for each client
        'case_management_enable'                    => 1,

        // If enabled - will be possible to use 'Check ABN' functionality
        // Must be enabled for AU, disabled for CA/DM
        'check_abn_enabled'                         => 1,

        // A label of the Invoice tax field that is shown on the Company Settings page
        // Is used during case invoice generation
        // "Tax Number (GST)" for AU, "Tax Number (GST/HST)" for all others
        'invoice_tax_number_label'                  => 'Tax Number (GST/HST)',

        // Default invoice disclaimer message (is shown on the Company Settings page)
        // Is used during case invoice generation
        'invoice_disclaimer_default'                => '',

        // Authorised Agents Management Setting
        // If is set to 1 - company admin can manage Authorised Agents (divisions groups)
        'authorised_agents_management_enabled'      => 0,

        // Preview files (docs sub tab) in a new browser tab:
        // If enabled - files will be opened in a new browser tab
        // If disabled - files will be opened in a preview panel
        'preview_files_in_new_browser' => 0,

        // List of allowed file types
        'whitelist_files_for_uploading' => 'txt, doc, dot, wbk, docx, docm, dotx, dotm, docb, xls, xlt, xlm, xlsx, xlsm, xltx, xltm, xlsb, xla, xlam, xll, xlw, ppt, pot, pps, pptx, pptm, potx, potm, ppam, ppsx, ppsm, sldx, sldm, adn, accdb, accdr, accdt, accda, mdw, accde, mam, maq, mar, mat, maf, laccdb, ade, adp, mdb, cdb, mda, mdn, mdt, mdf, mde, ldb, pub, xps, tif, tiff, jpg, jpeg, gif, png, bmp, eps, raw, cr2, nef, orf, sr2, pdf, eml, msg',

        // If authorised_agents_management_enabled enabled - use different variables + algorithms to generate fees for submission.
        // Note that this variable will be shown on the superadmin's 'System variables' page
        // Supported: dominica and antigua
        'submission_fees_type'          => 'dominica',

        // Documents Checklist Setting
        // If is set to 1 - it is possible to see and use 'Documents Checklist' tab
        'documents_checklist_enabled'   => 0,

        // Create note if file was uploaded
        'create_note_on_file_upload'    => 0,

        // Username of the API user - will be used to identify how we'll filter file notes
        // If not set - we'll show all the file notes that are not system (if checkbox is not checked)
        // If set - we'll show the file notes which author is not this user
        'fe_api_username'               => '',

        // Google Maps key (used in the company website, contact us page in most templates)
        'google_maps_key'               => '',

        // Google Recaptcha settings (used on the Sign-Up page)
        // if one of keys is empty - recaptcha will be not used/checked
        'google_recaptcha'              => [
            'check_ssl'  => 1,
            'site_key'   => '',
            'secret_key' => '',
        ],

        // Google Tag Manager settings (used on the login + signup pages)
        'google_tag_manager'            => [
            'container_id' => ''
        ],

        // Supported: australia | canada
        'version'                       => 'australia',
        'currency'                      => 'AUD',
        'title'                         => 'Officio! Your Office Online',
        'company_phone'                 => '',
        'company_name'                  => 'Officio',
        'officio_domain'                => getenv('APP_URL'),
        'officio_domain_secure'         => getenv('APP_URL'),

        // If "use static" is enabled - for NON https requests we'll try to use up to 3 static subdomains to serve images
        // e.g. if test.officio.com.au is set, we'll use such subdomains:
        // test.officio.com.au
        // test1.officio.com.au
        // test2.officio.com.au
        // test3.officio.com.au
        'officio_domain_use_static'     => 0,
        'officio_domain_static'         => getenv('APP_URL'),

        'clients' => [
            // A message that will be shown at the top of Client's Profile + Case's Profile tabs
            'warning_message' => '',

            // relationship_status field's "Single" option's label
            // For DM - "Single", for all others - "Never Married"
            // This is used in different checks, e.g. if relationship_status can be changed if there is a dependent "Spouse" already created
            'never_married_label' => 'Never Married',

            // When a case number should be generated:
            // "submission" -> on case submission to the Gov or manually clicking on the generate link (DM only)
            // "default" -> on case/prospect creation or manually clicking on the generate link (all others)
            'generate_case_number_on' => 'default'
        ],

        // Dependant's section settings
        'dependants'                    => [
            // This is how we'll present dependant's row during templates parsing
            'template_row_format'                   => '%relationship, %fName %lName (%DOB_age_number)',
            'template_count_is_1_message'           => 'is also included in the application.',
            'template_count_is_more_than_1_message' => 'are also included in the application.',

            // The list of dependants' fields that will be visible in the tooltip or in the exported excel (advanced search)
            // Only fields that are visible should be used here
            'export_or_tooltip_fields'              => [
                'relationship',
                'lName',
                'fName',
                'DOB',
            ],

            'fields' => [
                'relationship' => [
                    'show' => 1,

                    'options'        => [
                        'spouse' => [
                            'label' => 'Partner/Spouse'
                        ],

                        'siblings' => [
                            // Show for AU and DM only
                            'show'  => 0,

                            // customize siblings possible count
                            'count' => 0,
                        ],

                        'other' => [
                            // Show for AU and DM only
                            'show'  => 0,

                            // customize other dependants possible count
                            'count' => 0,
                        ],
                    ],

                    // customize children count for dependents section
                    'children_count' => 6,
                ],

                'last_name'   => ['show' => 1],
                'first_name'  => ['show' => 1],
                'middle_name' => ['show' => 0],
                'dob'         => ['show' => 1],
                // show for AU version only
                'migrating'   => ['show' => 1],

                'passport_num' => [
                    // show for CA and DM version only
                    'show'     => 0,
                    'required' => 0,
                ],

                'passport_date'           => ['show' => 1],
                // isn't used
                'nationality'             => ['show' => 0],
                // show for DM version only
                'country_of_citizenship'  => ['show' => 0],
                'uci'                     => ['show' => 1],
                // show for AU and CA, not for DM
                'medical_expiration_date' => ['show' => 1],
                'photo'                   => ['show' => 0],

                'address' => [
                    // show for DM
                    'show'      => 0,
                    'multiline' => 0,
                ],

                'city'        => ['show' => 0],
                'country'     => ['show' => 0],
                'region'      => ['show' => 0],
                'postal_code' => ['show' => 0],

                'profession' => [
                    // For DM - Occupation. For others - Profession
                    'label' => 'Profession',
                    // Show for DM only
                    'show'  => 0
                ],

                'place_of_birth' => [
                    // Show for DM only
                    'show' => 0
                ],

                'country_of_birth' => [
                    'show'     => 0,
                    'required' => 1
                ],

                'marital_status' => [
                    // Show for: Antigua, DM
                    'show'     => 0,
                    // Required everywhere, except of DM
                    'required' => 1,

                    'options' => [
                        // Show everywhere
                        'single' => ['show' => 1],

                        // Show everywhere
                        'married' => ['show' => 1],

                        // Show for DM only
                        'engaged' => ['show' => 0],

                        // Show everywhere
                        'widowed' => ['show' => 1],

                        // Show for DM only
                        'separated' => ['show' => 0],

                        // Show everywhere
                        'divorced' => ['show' => 1],
                    ]
                ],

                'sex' => [
                    // Show for DM only
                    'show' => 0
                ],

                'country_of_residence'               => ['show' => 0],
                'passport_issuing_country'           => ['show' => 0],
                'third_country_visa'                 => ['show' => 0],
                'main_applicant_address_is_the_same' => ['show' => 0],

                'spouse_name' => [
                    // Show for DM only
                    'show' => 0
                ],

                'jrcc_result' => [
                    // Show for DM only
                    'show'                    => 0,

                    // if enabled - the field will be visible only if the current user is not Agent (of 3 hardcoded roles) and is visible (a check above)
                    'show_for_non_agent_only' => 0
                ],

                'include_in_minute_checkbox' => [
                    // Show for DM only
                    'show'                    => 0,

                    // if enabled - the field will be visible only if the current user is not Agent (of 3 hardcoded roles) and is visible (a check above)
                    'show_for_non_agent_only' => 0
                ],
            ]
        ],

        // Validation Settings (only for DM)
        'validation'                                => [
            'check_children_age'     => 0,
            'check_investment_type'  => 0,
            'check_marital_status'   => 0,
            'check_date_of_birthday' => 0,
        ],

        // Labels of all blocks we show on the homepage
        'homepage' => [
            'announcements' => [
                'label'       => 'Announcements',
                'help'        => '',

                // The toggle will be shown for users that have access to the profile and are not clients
                'show_toggle' => 0,
                // A label that will be shown near the toggle
                'toggle_label' => 'Email Daily Notification',
                // A help will be shown when hovering mouse above the toggle, if empty - will be not shown
                'toggle_help' => '',

                // Enable/disable special announcements to be shown after user login
                'special_announcement_enabled' => 0
            ],

            'news' => [
                'label' => 'Recent news',
                'help'  => '',
            ],
        ],

        // PUA must be enabled for CA only, disabled for others
        'pua_enabled'                               => 0,

        // CICC reconciliation reports setting
        // If enabled - clients' names will be shortened in the report and 'zero records' can be shown in the bottom table
        'iccrc_reconciliation_hide_names'           => 0,

        // How to assign offices to the Employer
        // If enabled: search for all assigned cases, for these cases search parents (IA can be found) and for these IAs search for other cases too.
        // If disabled: search for all assigned cases and no for parent IA records for these cases.
        'keep_employer_and_applicant_in_one_office' => 0,


        // Show or hide "My Offices" link in the left Offices section/grid
        // Show for gov websites only
        'show_my_offices_link'                      => 0,

        // Generate Comfort Letter functionality
        'custom_templates_settings'                 => [
            'comfort_letter' => [
                'enabled'       => 0,

                // An array of letter templates' names that will be possible to select in the dialog
                'templates'     => [],

                // String format of the generated letter number
                // At this point only 2 variables are supported: investment_type and comfort_letter_number
                'format'        => '',

                // Format of the comfort_letter_number
                // Note that zeroes will be prepended if generate number length is less than in this format
                'number_format' => '0001'
            ]
        ],

        // A field label for the categories/subclasses field - 'category' OR 'subclass'
        // AU only - 'subclass', for all others - 'category'
        'categories_field_default_label' => 'category',

        // A field label for the Case Type / Immigration Program field - 'case_type' OR 'immigration_program'
        // BCPNP - 'case_type', for all others - 'immigration_program'
        'case_type_field_default_label' => 'immigration_program',

        // If enabled - 'case_status' field is multiselect, if disabled - a simple combobox
        'case_status_field_multiselect' => 1,
    ],

    // PDF settings
    'pdf'            => [
        // If pdftk or pdftron should be used for pdf-related tasks (e.g. merge pdf with xfdf)
        'use_pdftk'   => 0,

        // If pdftron uses python3 or python2
        'use_python3' => 0
    ],

    // Zoho settings
    'zoho'      => [
        // If enabled - request will be sent to Zoho server to open/edit supported files
        'enabled'     => 0,

        // If enabled - all request to Zoho will be logged
        'log_enabled' => 0,

        // Enable/disable SSL certificate checking when communicate with the Zoho server
        // Should be enabled on the prod server
        'check_ssl'   => 1,
    ],

    // Help tab settings
    'help'      => [
        // Show/hide Learn button we show at the top
        'show_learn_button' => 0,
    ],

    // SMS sending
    'sms'       => [
        'enabled'       => 0,
        'company_id'    => 738,
        'retry_count'   => 5,
        'sid'           => '',
        'token'         => '',
        'twilio_number' => '',
    ],

    // GeoIP settings
    'geoip'     => [
        'browscap_path' => 'library/Browscap/lite_php_browscap.ini'
    ],

    // Settings for RabbitMQ
    // Can be enabled only if all prerequisites were done (check the readme file)
    'rabbit'    => [
        'enabled'  => 0,
        'host'     => 'localhost',
        'port'     => 5672,
        'login'    => 'guest',
        'password' => 'guest',
    ],

    // Theming
    // css file will be automatically loaded: /public/styles/themes/[theme].css
    'theme'     => 'default',

    // Directories settings
    'directory' => [
        'tmp'                    => 'var/tmp',
        'pdf_temp'               => 'var/pdf_tmp',
        'cache'                  => 'var/cache',
        'companyfiles'           => 'data',
        'blankfiles'             => '/blank',
        'reconciliation_reports' => '/reconciliation_reports',

        'form_files'                 => 'data/forms',

        // 'pdfpath_physical'           => 'data/pdf',
        // 'converted_xod_forms'        => 'data/xod',
        'companyWebsiteTemplates'    => 'public/templates',
        'help_files'                 => 'public/help_files',
        //'converted_pdf_forms'        => 'public/pdf',
        'backup'                     => 'backup',

        // Captcha
        'captcha_font'               => 'Intramural.ttf',
        'captcha_font_path'          => 'public/captcha/fonts',
        'captcha_images_path'        => 'public/captcha/images',

        // Path to company files +
        'company_invoice_documents'  => '.client_files_other/invoice_documents',
        'company_xfdf'               => '.client_files_other/XFDF',
        'company_xdp '               => '.client_files_other/XDP',
        'company_json'               => '.client_files_other/Json',
        'company_barcoded_pdf'       => '.client_files_other/BarcodedPDF',
        'company_dependants'         => '.dependants',
        'company_default_files'      => '.client_files_other/default_files',
        'company_logo'               => '.client_files_other',
        'agent_logo'                 => '.agents',
        'letterheads'                => '.letterheads',
        'letter_templates'           => '.letter_templates',
        'template_attachments'       => '.template_attachments',
        'client_notes_attachments'   => '.client_files_other/notes_attachments',
        'prospect_notes_attachments' => '.prospect_files_other/notes_attachments',

        // Clients auto-import settings
        'clients_xls'                => '.import_clients',
        'bcpnp_xls'                  => '.import_bcpnp',
    ],

    // Mail settings
    'mail'           => [
        'enabled'                => 1,
        'calendar_enabled'       => 0,
        // Total files size in Mb
        'total_files_size'       => 25,
        // In 'Mail Send' dialog: hide 'Send' button if setting is set to '1' AND 'Send and Save' button (some one of them) is showed
        'hide_send_button'       => 0,
        // When try to send/get emails (via SMTP/POP3/IMAP) - check SSL certificate for correctness of the mail host
        // If 0 - ignore SSL issues (e.g. peer name or self-signed certificates)
        'verify_ssl_certificate' => 0
    ],

    // Calendly settings
    'calendly' => [
        'enabled'       => 0,
        'client_id'     => '',
        'client_secret' => '',
    ],

    // Dropbox settings
    'dropbox'      => [
        'app_id' => '',
    ],

    // Google Drive settings
    'google_drive' => [
        // Leave empty to disable
        'app_id'    => '',
        'client_id' => '',
        'api_key'   => '',
    ],

    // PDF to XOD
    'pdf2xod'        => [
        'use_local'  => 0,
        'remote_url' => 'https://www.immigrationsquare.com/api/pdf2xod'
    ],

    // General Cloud Settings
    'storage' => [
        'is_online'        => 0,
        'aws_accesskey'    => 'key',
        'aws_secretkey'    => 'secret',
        'bucket_name'      => 'bucket',
        'check_ssl'        => 0,
        'use_secure_links' => 0,
        'aws_region'       => 'us-east-1',
        // Supported: 'AES256' or if empty - will be not encrypted
        'encryption'       => 'AES256'
    ],

    // Html editor (Froala) settings
    'html_editor' => [
        'froala_license_key' => '',

        // Folder name, where editor images will be saved
        // For the remote storage path will be: /{location}/{company_id}/
        // For the local storage path will be: .../public/{location}/{company_id}/
        // For the local storage please make sure that folder name is unique
        'location'           => 'content_images',

        // Storage location: 'remote' or 'local'
        // If local - please make sure that apache has rw access rights to the {location}
        'storage'            => 'remote',

        // Will be used only if a Storage location is 'remote'
        'remote'             => [
            'is_online'        => 0,
            'aws_accesskey'    => 'key',
            'aws_secretkey'    => 'secret',
            'bucket_name'      => 'bucket',
            'check_ssl'        => 0,
            'use_secure_links' => 0,
            'aws_region'       => 'us-east-1',
            // Supported: 'AES256' or if empty - will be not encrypted
            'encryption'       => 'AES256'
        ],
    ],

    // Cache settings
    // This section is used directly in cache fabric
    // @see https://docs.laminas.dev/laminas-cache/storage/adapter/
    'cache' => [
        'adapter' => 'Filesystem', // Officio supports 'Filesystem' and 'Memcached'
        'options' => [
            // Options for Filesystem
            //'cache_dir' => 'var/cache/',
            //'dir_permission' => false,
            //'file_permission' => false,

            // Options for Memcached
            // 'servers' => [
            //     ['localhost', 11211]
            // ],
        ],
        'plugins' => [
            [
                'name' => 'serializer'
            ]
        ]
    ],

    // RSS settings - news will be showed on the dashboard
    'rss' => [
        // RSS URLs that will be scanned and showed on the dashboard
        'urls' => [
            // in the `key` => url format
            // `key` can be provided and is used to differentiate the way we load/use info, check the RSS.php code
        ],

        // RSS Cache settings
        'cache' => [
            'adapter' => 'Filesystem',
            'options' => [
                'cache_dir'       => 'var/cache/',
                'dir_permission'  => false,
                'file_permission' => false,
                'ttl'             => 3600 // 1 hour
            ],
            'plugins' => [
                [
                    'name' => 'serializer'
                ]
            ]
        ],
    ],

    // Translator config
    'translator' => [
        'locale' => 'en_US', // This is fallback locale used in case language is not defined in cookies
        'translation_file_patterns' => [
            [
                'type' => Gettext::class,
                'base_dir' => 'config/lang/',
                'pattern' => '%s.mo',
            ],
        ],
        // List of timezones is here: http://php.net/manual/en/timezones.php
        'timezone'      => 'Australia/Sydney',
    ],

    // Log config
    'log' => [
        'path' => 'var/log',
    ],

    // Outbound requests proxy settings
    'outbound_proxy' => [
        'use'   => 0,
        'host'  => '',
        'port'  => '',
        'login' => '',
        'pass'  => ''
    ],

    // Minify settings
    // Css/Js minification setting
    'minify'         => [
        // If enabled - all js/css files will be united in one 'minified file', e.g.: minify__a77d901b178b1666b80afc4ae1c35838.js
        'enabled'                => 1,

        // Directory path (under the 'public' dir) where minified js/css files will be placed (note that apache must have R/W access here)
        // e.g. can be '/cache/' or 'cache' or 'cache/' -> will be pointed to the '/public/cache/' dir
        // sub dirs are supported too
        'cache_dir'              => 'cache',

        // If obfuscation enabled - minified version of the js file will be obfuscated
        'js_obfuscation_enabled' => 0
    ],


    // **** Payment options ****
    // *************************
    'payment'        => [
        // General options
        'enabled'                 => 0,
        'save_log'                => 1,
        // timeout in seconds
        'timeout'                 => 90,
        // on error - request will be sent again
        'max_retry_attempts'      => 2,
        // if x errors at one row occurred - send email to support and exit
        'recurring_errors_in_row' => 3,
        'log_directory'           => 'var/log/payment',

        // Payment method - set in relation to the site settings
        // can be paymentech OR payway
        'method'                  => 'payway',

        // Currency code - used in templates and in PaymenTech processing
        // Full list please check here: https://en.wikipedia.org/wiki/ISO_4217
        // Canadian Dollar - 124
        // Australian Dollar - 036
        // US Dollar - 840
        'currencyCode'            => '036',


        // *** These settings are used only by PaymenTech ***
        'use_test_server'         => 1,
        'testMerchantID'          => 'id',
        'testSubmissionUrl'       => 'https://orbitalvar1.paymentech.net/authorize',

        'submissionUrl'    => 'https://orbital1.paymentech.net/authorize',
        'customerBin'      => 'bin',
        'merchantID'       => 'id',
        'terminalID'       => '001',
        // Related to currency, 2 for Canadian Dollar
        'currencyExponent' => '2',


        // *** These settings are used only by PayWay ***
        'payway'           => [
            'username'        => 'user',
            'password'        => 'pass',
            // Use TEST for testing
            'merchant'        => 'merchant',
            'currency_code'   => 'AUD',
            // these are crt and ca files
            'certificate_crt' => 'path_to_crt',
            'certificate_ca'  => 'path_to_ca',
        ],

        // *** These settings are used only by Stripe ***
        'stripe'           => [
            // If not enabled - we'll try to save CC info to the `cc_tmp` table in the DB
            'enabled'  => 0,
            'secret'   => 'secret_key',
            'public'   => 'public_key',
            'currency' => 'usd',
        ],

        // *** These settings are used only by TranPage ***
        'tranPage'         => [
            'merchant_key'               => 'merchant_key',
            'url_transaction_processing' => 'url_transaction_processing',
            'currency'                   => 'USD'
        ]
    ],

    // Phinx related settings
    'phinx'          => [
        // Path to the migrations' directory, where migrations are generated
        'migrations_path' => 'scripts/migrations/au/',
        // Table name, where phinx related changes will be saved
        'migration_table' => 'phinx_log',
    ],

    // Marketplace related settings
    'marketplace' => [
        'enable_on_company_creation' => 0,
        'toggle_status_url' => '',
        'create_profile_url' => '',
        'edit_profile_url' => '',
        // public/private files + key must be the same on MP side
        'private_pem' => 'path_to_private_pem',
        'public_pem' => 'path_to_public_pem',
        'key' => 'key'
    ],

    // Use Inline Manual (https://help.inlinemanual.com/)
    'inline_manual' => [
        // If empty - will be not used
        'api_key' => ''
    ],

    // LMS (Officio Studio) settings
    'lms' => [
        // If enabled - we'll show a link in the main menu and will show RSS block on the dashboard
        // make sure that a correct 'url' is set too
        'enabled'       => 0,

        // If test mode is enabled - clicking on the links will show a warning message
        // No requests to the LMS side will be sent (e.g. user creation)
        'test_mode'     => 0,

        // Enable/disable logging of the communication with the LMS
        'log_enabled'   => 1,

        // URL to the LMS server (e.g. to log in the user)
        'url'           => '',

        // A key that will be used to encode data that will be sent to the LMS side
        'auth_key'      => '',

        // Enable/disable SSL certificate checking when communication with the LMS server
        // Should be enabled on the prod server
        'check_ssl'     => 1,

        // Url to the RSS, that will be used on the home page
        // Note that ['rss']['cache'] will be used for this URL too
        'rss_url'       => '',

        // Maximum rss items to show on the dashboard
        // If empty - no limit will be applied
        'rss_max_items' => 0,
    ],

    // Configuration from the lm-commons/lmc-cors package. Used to provide cors support for API2.
    'lmc_cors' => [
        /**
         * Set the list of "allowed origins" domain with protocol.
         */
        'allowed_origins' => ['https://online.immi.gov.au'],

        /**
         * Set the list of HTTP verbs.
         */
        'allowed_methods' => ['OPTIONS', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

        /**
         * Set the list of headers. This is returned in the preflight request to indicate
         * which HTTP headers can be used when making the actual request
         */
        'allowed_headers' => ['Authorization', 'Content-Type'],

        /**
         * Set the max age of the preflight request in seconds. A non-zero max age means
         * that the preflight will be cached during this amount of time
         */
        // 'max_age' => 120,

        /**
         * Set the list of exposed headers. This is a whitelist that authorize the browser
         * to access to some headers using the getResponseHeader() JavaScript method. Please
         * note that this feature is buggy and some browsers do not implement it correctly
         */
        // 'exposed_headers' => [],

        /**
         * Standard CORS requests do not send or set any cookies by default. For this to work,
         * the client must set the XMLHttpRequest's "withCredentials" property to "true". For
         * this to work, you must set this option to true so that the server can serve
         * the proper response header.
         */
        // 'allowed_credentials' => false,
    ],
];
