<?php

namespace Websites\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Laminas\Mail\Address;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Comms\Service\Mailer;
use Officio\Controller\AuthController;
use Officio\Service\Company;
use Websites\BuilderLayout;
use Websites\Service\CompanyWebsites;
use Laminas\Validator\EmailAddress;

/**
 * Websites IndexController - main controller for Web Sites
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */

class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var CompanyWebsites */
    private $_companyWebsites;

    /** @var BuilderLayout */
    private $_builderLayoutModel;

    /** @var StripTags */
    private $_filter;

    /** @var Mailer */
    private $_mailer;

    private $_pages;

    public function initAdditionalServices(array $services)
    {
        $this->_company         = $services[Company::class];
        $this->_mailer          = $services[Mailer::class];
        $this->_companyWebsites = $services[CompanyWebsites::class];
    }

    public function init()
    {
        $this->_builderLayoutModel = new BuilderLayout($this->_config, $this->_db2, $this->_log);
        $this->_filter             = new StripTags();
        $this->_pages              = [
            'homepage',
            'about',
            'canada',
            'immigration',
            'assessment',
            'contact'
        ];
    }

    public function indexAction()
    {
        $view     = new ViewModel();
        $strError = '';
        try {
            $entranceName = trim($this->_filter->filter($this->params()->fromRoute('entrance', '')));
            if (empty($entranceName)) {
                $strError = $this->_tr->translate('Company not found');

                $view->setTerminal(true);
                $view->setTemplate('layout/plain');
                $view->setVariables(
                    [
                        'content' => $strError
                    ]
                );
            }

            // get website data
            if (empty($strError)) {
                $companyWebsite = $this->_companyWebsites->getCompanyWebsiteByEntrance($entranceName);
                if (empty($companyWebsite) || $companyWebsite['visible'] != 'Y') {
                    $strError = $this->_tr->translate('Company website not found or not available');

                    $view->setTerminal(true);
                    $view->setTemplate('layout/plain');
                    $view->setVariables(
                        [
                            'content' => $strError
                        ]
                    );
                }
            }

            if (empty($strError)) {
                // get template
                $template = $this->_companyWebsites->getCompanyWebsitesTemplates()->getTemplate($companyWebsite['template_id']);
                if (empty($template)) {
                    $strError = $this->_tr->translate('Company website not found');

                    $view->setTerminal(true);
                    $view->setTemplate('layout/plain');
                    $view->setVariables(
                        [
                            'content' => $strError
                        ]
                    );
                }
            }

            if (empty($strError)) {
                // check for incoming errors
                $view->setVariable('error', $this->_filter->filter($this->findParam('error')));

                // page title
                $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->set($companyWebsite['title']);

                // generate pages list
                $arrPages = $this->_companyWebsites->getPagesList();
                $menu = array();
                foreach ($arrPages as $page) {
                    // remove inactive pages
                    if ($companyWebsite[$page . '_on'] != 'Y') {
                        continue;
                    }

                    // generate links
                    $menu[$page] = $this->layout()->getVariable('baseUrl') . '/webs/' . $entranceName . ($page == 'homepage' ? '' : '/' . $page);
                }

                // get homepage
                $currentPage = $this->_filter->filter($this->params('page'));
                if (!array_key_exists($currentPage, $menu)) {
                    $currentPage = 'homepage';
                }

                // format some fields
                $booUseSlider = isset($companyWebsite['options']['slider']) && $companyWebsite['options']['slider'] == 'on' && isset($companyWebsite['options']['selected-slide']) && count($companyWebsite['options']['selected-slide']) > 0;
                $companyWebsite['footer_text'] = str_replace('<YEAR>', date('Y'), $companyWebsite['footer_text']);

                // get page title
                if (!isset($companyWebsite[$currentPage . '_name']) || empty($companyWebsite[$currentPage . '_name'])) {
                    switch ($currentPage) {
                        case 'homepage':
                        default:
                            $pageTitle = $this->_tr->translate('welcome');
                            break;
                        case 'about':
                            $pageTitle = $this->_tr->translate('about us');
                            break;
                        case 'canada':
                            $pageTitle = $this->_tr->translate('about canada');
                            break;
                        case 'immigration':
                            $pageTitle = $this->_tr->translate('immigration');
                            break;
                        case 'assessment':
                            $pageTitle = $this->_tr->translate('free assessment');
                            break;
                        case 'contact':
                            $pageTitle = $this->_tr->translate('primary contacts');
                            break;
                    }
                } else {
                    $pageTitle = $companyWebsite[$currentPage . '_name'];
                }

                $view->setVariable('companyWebsite', $companyWebsite);

                // render
                $arrViewParams = [
                    'entranceName'   => $entranceName,
                    'currentPage'    => $currentPage,
                    'companyWebsite' => $companyWebsite,
                    'baseUrl'        => $this->layout()->getVariable('baseUrl'),
                    'templateUrl'    => $this->layout()->getVariable('baseUrl') . '/templates/' . $template['template_name'],
                    'uploadsUrl'     => $this->layout()->getVariable('baseUrl') . '/website/',
                    'menu'           => $menu,
                    'userId'         => $this->_auth->getCurrentUserId(),
                    'userName'       => $this->_members->getCurrentMemberName(),
                    'booUseSlider'   => $booUseSlider,
                    'pageTitle'      => $pageTitle,
                    'googleMapsKey'  => $this->_config['site_version']['google_maps_key']
                ];

                $templateName = 'website/' . $template['template_name'];
                $view->setTemplate($templateName);
                $view->setVariables($arrViewParams);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            $view->setTerminal(true);
            $view->setTemplate('layout/plain');
            $view->setVariables(
                [
                    'content' => null
                ],
                true
            );
        }

        return $view;
    }

    public function newAction()
    {
        $view = new ViewModel();

        try {
            $strError = '';

            $entranceName = trim($this->_filter->filter($this->params('entrance', '')));
            if (empty($entranceName)) {
                $strError = $this->_tr->translate('Company not found');

                $view->setTerminal(true);
                $view->setTemplate('layout/plain');
                $view->setVariables(
                    [
                        'content' => $strError
                    ]
                );
            }

            if (empty($strError)) {
                $view->setVariable('error', $this->_filter->filter($this->findParam('error')));

                $currentPage = $this->params('page');
                if (!in_array($currentPage, $this->_pages)) {
                    $currentPage = 'homepage';
                }
                // get website data
                $builderData = $this->_builderLayoutModel->getBuilderContentByEntranceName($entranceName, $currentPage);

                // get names and availability of pages
                $activePages = $this->_builderLayoutModel->getActivePages($builderData);

                //if website visibility = false
                if (empty($builderData) || empty($builderData['visible'])) {
                    $strError = $this->_tr->translate('Website not found');

                    $view->setTerminal(true);
                    $view->setTemplate('layout/plain');
                    $view->setVariables(
                        [
                            'content' => $strError
                        ]
                    );
                }
            }

            if (empty($strError)) {
                //if page available = false
                if (empty($activePages) || empty($activePages[$currentPage]) || !$activePages[$currentPage]['available']) {
                    $strError = $this->_tr->translate('Page not found');

                    $view->setTerminal(true);
                    $view->setTemplate('layout/plain');
                    $view->setVariables(
                        [
                            'content' => $strError
                        ]
                    );
                }
            }

            if (empty($strError)) {
                if (empty($activePages[$currentPage]['name'])) {
                    switch ($currentPage) {
                        case 'homepage':
                        default:
                            $pageTitle = $this->_tr->translate('welcome');
                            break;
                        case 'about':
                            $pageTitle = $this->_tr->translate('about us');
                            break;
                        case 'canada':
                            $pageTitle = $this->_tr->translate('about canada');
                            break;
                        case 'immigration':
                            $pageTitle = $this->_tr->translate('immigration');
                            break;
                        case 'assessment':
                            $pageTitle = $this->_tr->translate('free assessment');
                            break;
                        case 'contact':
                            $pageTitle = $this->_tr->translate('primary contacts');
                            break;
                    }
                } else {
                    $pageTitle = $activePages[$currentPage]['name'];
                }

                // page title
                $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->set($pageTitle);

                //default page data
                $pageData = [
                    'header' => '',
                    'footer' => '',
                    'css' => '',
                    'content' => '',
                    'contentCss' => '',
                    'address' => '',
                    'phone' => '',
                    'company_name' => '',
                    'title' => '',
                    'assessment_url' => '',
                ];
                $companyWebsite = array(
                    'script_facebook_pixel' => $builderData['fb_script'] ?? '',
                    'script_google_analytics' => $builderData['google_script'] ?? '',
                );
                //if isset builder data
                if (!empty($builderData)) {
                    $pageData = [
                        'header' => !empty($builderData['header']) ? $builderData['header'] : '',
                        'footer' => !empty($builderData['footer']) ? $builderData['footer'] : '',
                        'css' => !empty($builderData['css']) ? $builderData['css'] : '',
                        'content' => '',
                        'contentCss' => '',
                        'address' => !empty($builderData['address']) ? $builderData['address'] : 'Company address',
                        'phone' => !empty($builderData['phone']) ? $builderData['phone'] : 'Company phone',
                        'company_name' => !empty($builderData['company_name']) ? strip_tags($builderData['company_name'] ?? '') : 'Company name',
                        'title' => !empty($builderData['title']) ? $builderData['title'] : 'Navbar',
                        'assessment_url' => !empty($builderData['assessment_url']) ? $builderData['assessment_url'] : ''
                    ];
                    //if isset page
                    if ($builderData[$currentPage] !== null) {
                        $pageData['content'] = !empty($builderData['content']) ? $builderData['content'] : '';
                        $pageData['contentCss'] = !empty($builderData['contentCss']) ? $builderData['contentCss'] : '';
                    }
                }

                $arrViewParams = [
                    'entranceName' => $entranceName,
                    'pageData' => $pageData,
                    'currentPage' => $currentPage,
                    'activePages' => $activePages,
                    'templateUrl' => $this->layout()->getVariable('topBaseUrl') . '/templates/viewTemplate',
                    'topBaseUrl' => $this->layout()->getVariable('topBaseUrl')
                ];

                $this->layout()->setTemplate('layout/websites');
                $view->setVariable('companyWebsite', $companyWebsite);
                $view->setTemplate('website/viewTemplate');
                $view->setVariables($arrViewParams);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');
            $view->setVariables(
                [
                    'content' => null
                ],
                true
            );
        }

        return $view;
    }

    public function sendMessageAction()
    {
        $view = new JsonModel();

        $error = $msg = '';

        try {

            $entranceName = trim($this->_filter->filter($this->params('entrance', '')));
            if(empty($entranceName)) {
                $error = $this->_tr->translate('Company not found');
            }

            // get website data
            if(empty($error)) {
                $old = $this->_companyWebsites->builderIsNew($entranceName);

               if (empty($old)) {
                $companyWebsite = $this->_companyWebsites->getCompanyWebsiteByEntrance($entranceName);
                if(empty($companyWebsite) || $companyWebsite['visible'] != 'Y') {
                    $error = $this->_tr->translate('Company website not found or not available');
                }
               } else {
                  $companyWebsite = $this->_builderLayoutModel->getBuilderRow($entranceName);
                  if (empty($companyWebsite) || empty($companyWebsite['visible'])) {
                     $error = $this->_tr->translate('Company website not found or not available');
                  }
               }
            }

            // get contact email
            $companyEmail = '';
            if (empty($error) && !empty($companyWebsite) && isset($companyWebsite['company_id']) && !empty($companyWebsite['company_id'])) {
                $companyEmail = $this->_company->getCompanyEmailById($companyWebsite['company_id']);
                if(empty($companyEmail)) {
                    $error = $this->_tr->translate('Sorry. You cannot send email to company right now.');
                }
            }

            // validate fields
            if (empty($error)) {

                $name = trim($this->_filter->filter($this->params()->fromPost('name', '')));
                $email = trim($this->_filter->filter($this->params()->fromPost('email', '')));
                $message = trim($this->_filter->filter($this->params()->fromPost('message', '')));
                $phone = trim($this->_filter->filter($this->params()->fromPost('phone', '')));

                // validate name
                $emailValidator = new EmailAddress();
                if(empty($name)) {
                    $error = $this->_tr->translate('Your name cannot be empty');
                } else if (empty($email)) {
                    $error = $this->_tr->translate('Your email address cannot be empty');
                } else if (!$emailValidator->isValid($email)) {
                    $error = $this->_tr->translate('Incorrect email address');
                } else if (empty($message)) {
                    $error = $this->_tr->translate('Your message cannot be empty');
                } else {
                    // append phone number
                    if (!empty($phone)) {
                        $message .= "\n\r------\n\r" . $this->_tr->translate('Phone') . ': ' . $phone;
                    }

                    $transport = $this->_mailer->getOfficioSmtpTransport();

                    // send mail
                    $this->_mailer->processAndSendMail(
                        $companyEmail,
                        $this->_tr->translate("New message send from contact form"),
                        $message,
                        new Address($name, $email),
                        null,
                        null,
                        [],
                        true,
                        $transport
                    );
                    $msg = $this->_tr->translate('Message has been sent');
                }
            }
        } catch (Exception $e) {
            $error = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariables(array('success' => empty($error), 'error' => $error, 'msg' => $msg));
    }

    public function loginAction()
    {
        $entranceName = trim($this->_filter->filter($this->params('entrance', '')));
        if (empty($entranceName)) {
            $error = $this->_tr->translate('Company not found');
        } else {
            $error = $this->forward()->dispatch(
                AuthController::class,
                array(
                    'action' => 'login',
                    'redirect' => false
                )
            );
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $view = new JsonModel();
            return $view->setVariables(array('success' => empty($error), 'error' => $error, 'redirect' => $this->layout()->getVariable('baseUrl') . '/'));
        } elseif (empty($error)) {
            return $this->redirect()->toRoute('home');
        } else {
            return $this->redirect()->toRoute('login');
        }
    }

    public function logoutAction()
    {
        $this->forward()->dispatch(
            AuthController::class,
            array(
                'action' => 'logout',
                'redirect' => false
            )
        );

        $entranceName = trim($this->_filter->filter($this->params('entrance', '')));
        if (empty($entranceName)) {
            return $this->redirect()->toUrl('/auth/logout');
        } else {
            return $this->redirect()->toUrl('/webs/' . $entranceName);
        }
    }
}
