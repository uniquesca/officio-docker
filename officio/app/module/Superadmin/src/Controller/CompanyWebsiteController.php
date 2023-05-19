<?php

namespace Superadmin\Controller;

use Exception;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Laminas\View\Helper\Partial;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Prospects\Service\CompanyProspects;
use Websites\BuilderLayout;
use Websites\BuilderPage;
use Websites\Service\CompanyWebsites;
use Laminas\Validator\EmailAddress;

/**
 * Company Website Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class CompanyWebsiteController extends BaseController
{
    public const SLIDE_MIN_WIDTH = 900; // px
    public const SLIDE_MIN_HEIGHT = 400; // px
    public const BACKGROUND_MAX_WIDTH = 400; // px
    public const BACKGROUND_MAX_HEIGHT = 300; // px
    public const MAX_UPLOAD_FILE_SIZE = 1048576; // 1Mb

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var CompanyWebsites */
    private $_companyWebsites;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var BuilderLayout */
    private $_builderLayout;

    /** @var BuilderPage */
    private $_builderPage;

    private $_pages;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_files            = $services[Files::class];
        $this->_companyWebsites  = $services[CompanyWebsites::class];
        $this->_companyProspects = $services[CompanyProspects::class];
    }

    public function init()
    {
        $this->_builderLayout = new BuilderLayout($this->_config, $this->_db2, $this->_log);
        $this->_builderPage   = new BuilderPage($this->_config, $this->_db2, $this->_log);

        $this->_pages = [
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
        $view = new ViewModel();

        $booIsAustralia = $this->_config['site_version']['version'] == 'australia';
        $companyId      = $this->_auth->getCurrentUserCompanyId();

        $arrError = array();

        /* ########################
           ADD/UPDATE WEBSITE
           ####################### */

        $data = array();
        if ($this->getRequest()->isPost()) {

            // get previous data
            $prevWebsite = $this->_companyWebsites->getCompanyWebsite($companyId);

            $filter = new StripTags();

            /* Get options */
            $data = array(
                'template_id'             => (int)$this->findParam('templateId'),
                'visible'                 => $this->findParam('visible', false) ? 'Y' : 'N',
                'company_name'            => trim($this->findParam('company_name', '')),
                //'entrance_name' => trim($filter->filter($this->findParam('entrance_name', ''))),
                'title'                   => trim($filter->filter($this->findParam('title', ''))),
                'company_phone'           => trim($filter->filter($this->findParam('company_phone', ''))),
                'company_skype'           => trim($filter->filter($this->findParam('company_skype', ''))),
                'company_fax'             => trim($filter->filter($this->findParam('company_fax', ''))),
                'company_linkedin'        => trim($filter->filter($this->findParam('company_linkedin', ''))),
                'company_facebook'        => trim($filter->filter($this->findParam('company_facebook', ''))),
                'company_twitter'         => trim($filter->filter($this->findParam('company_twitter', ''))),
                'company_email'           => trim($this->findParam('company_email', '')),
                'footer_text'             => $this->findParam('footer_text'),
                'homepage_on'             => 'Y',
                'homepage_name'           => trim($filter->filter($this->findParam('homepage_name', ''))),
                'homepage_text'           => $this->findParam('homepage_text'),
                'about_on'                => $this->findParam('about_on') == 'on' ? 'Y' : 'N',
                'about_name'              => trim($filter->filter($this->findParam('about_name', ''))),
                'about_text'              => $this->findParam('about_text'),
                'canada_on'               => $this->findParam('canada_on') == 'on' ? 'Y' : 'N',
                'canada_name'             => trim($filter->filter($this->findParam('canada_name', ''))),
                'canada_text'             => $this->findParam('canada_text'),
                'immigration_on'          => $this->findParam('immigration_on') == 'on' ? 'Y' : 'N',
                'immigration_name'        => trim($filter->filter($this->findParam('immigration_name', ''))),
                'immigration_text'        => $this->findParam('immigration_text'),
                'assessment_on'           => $this->findParam('assessment_on') == 'on' ? 'Y' : 'N',
                'assessment_name'         => trim($filter->filter($this->findParam('assessment_name', ''))),
                'assessment_url'          => trim($filter->filter($this->findParam('assessment_url', ''))),
                'assessment_background'   => trim($filter->filter($this->findParam('assessment_background', ''))),
                'assessment_foreground'   => trim($filter->filter($this->findParam('assessment_foreground', ''))),
                'contact_on'              => $this->findParam('contact_on') == 'on' ? 'Y' : 'N',
                'contact_name'            => trim($filter->filter($this->findParam('contact_name', ''))),
                'contact_text'            => trim($this->findParam('contact_text', '')),
                'contact_map'             => $this->findParam('contact_map') == 'on' ? 'Y' : 'N',
                'contact_map_coords'      => $this->findParam('contact_map_coords'),
                'login_block_on'          => $this->findParam('login_block_on') == 'on' ? 'Y' : 'N',
                'external_links_on'       => $this->findParam('external_links_on') == 'on' ? 'Y' : 'N',
                'external_links_title'    => trim($filter->filter($this->findParam('external_links_title', ''))),
                'external_links_name'     => $this->findParam('external_links_name'),
                'external_links_url'      => $this->findParam('external_links_url'),
                'options'                 => array(),
                'script_google_analytics' => $this->findParam('script_google_analytics'),
                'script_facebook_pixel'   => $this->findParam('script_facebook_pixel'),
            );

            // get all options
            $allOptions = $this->findParams();

            // get external links
            $data['external_links'] = array();
            if(is_array($data['external_links_name']) && is_array($data['external_links_url']) && count($data['external_links_name']) == count($data['external_links_url'])) {
                $data['external_links'] = array_combine($data['external_links_name'], $data['external_links_url']);
            }
            unset($data['external_links_name'], $data['external_links_url']);

            // get template options
            foreach($allOptions as $key => $value) {
                if(!array_key_exists($key, $data) && !in_array($key, array('external_links_name', 'external_links_url'))) {
                    $data['options'][$key] = $value;
                }
            }

            // get uploads
            foreach($_FILES as $name => $file) {
                if($name == 'company_logo' || $name == 'assessment_banner') {
                    if (!empty($file['name'])) {
                        $data[$name] = $file;
                    } else {
                        if (!empty($prevWebsite[$name])) {
                            $data[$name] = $prevWebsite[$name];
                        }
                    }
                } else if((isset($data['options'][$name]) && is_array($data['options'][$name])) || in_array($name, array('background', 'slide'))) {
                    if(!empty($file['name'])) {
                        $data['options'][$name][] = $file;
                    }
                }/* else {
                    $data['options'][$name] = empty($file['name']) ? @$prevWebsite['options'][$name] : $file;
                    if(empty($data['options'][$name])) {
                        unset($data['options'][$name]);
                    }
                }*/
            }

            /* Validations */

            // validate template
            if(empty($data['template_id']) || $data['template_id'] <= 0) {
                $arrError[] = 'Incorrect template selected';
            } else {
                $template = $this->_companyWebsites->getCompanyWebsitesTemplates()->getTemplate($data['template_id']);
            }

            // validate company email
            if(!empty($data['company_email'])) {
                $emailValidator = new EmailAddress();
                if(!$emailValidator->isValid($data['company_email'])) {
                    $arrError[] = 'Incorrect company email address';
                }
            }

            /*
            // validate entrance name
            $entranceName = $this->_companyWebsites->generateEntranceName($companyId, $data['entrance_name']);
            if(empty($data['entrance_name'])) {
                $arrError[] = 'Entrance name can not be empty';
            } else if($data['entrance_name'] != $entranceName) {
                $arrError[] = 'Incorrect entrance name. (entrance name can contains only latin words, number and symbols: "-" and "_")';
            }
            */

            // validate external links
            foreach($data['external_links'] as $name => $link) {
                if(empty($name)) {
                    $arrError[] = 'External link name can not be empty';
                } else if(empty($link)) {
                    $arrError[] = sprintf('External link with name "%s" can not be empty', $name);
                }
            }

            // validate contact information
            if($data['contact_on'] == 'Y' && empty($data['company_email'])) {
                $arrError[] = 'You need to specify contact email if you want to use contact form. Otherwise please turn off page "Contact us"';
            }

            // validate assessment url
            if($data['assessment_on'] == 'Y') {
                if(empty($data['assessment_url'])) {
                    $arrError[] = 'Assessment url cannot be empty.';
                } else if(!filter_var($data['assessment_url'], FILTER_VALIDATE_URL)) {
                    $arrError[] = 'You have provided incorrect assessment url';
                }
            }

            // validate linkedIn
            if(!empty($data['company_linkedin']) && !filter_var($data['company_linkedin'], FILTER_VALIDATE_URL)) {
                $arrError[] = 'You have provided an incorrect LinkedIn url';
            }

            // validate Facebook
            if(!empty($data['company_facebook']) && !filter_var($data['company_facebook'], FILTER_VALIDATE_URL)) {
                $arrError[] = 'You have provided an incorrect Facebook url';
            }

            // validate Twitter
            if(!empty($data['company_twitter']) && !filter_var($data['company_twitter'], FILTER_VALIDATE_URL)) {
                $arrError[] = 'You have provided an incorrect Twitter url';
            }

            // validate background
            if($data['assessment_background'] == 'transparent' || !Settings::isHexColor($data['assessment_background'])) {
                $data['assessment_background'] = '';
            }

            // validate foreground
            if (empty($data['assessment_foreground']) || !Settings::isHexColor($data['assessment_foreground'])) {
                $data['assessment_foreground'] = '#000000';
            }

            // bg != fg
            if (!empty($data['assessment_foreground']) && !empty($data['assessment_background']) && $data['assessment_foreground'] == $data['assessment_background']) {
                $arrError[] = 'Assessment link can not have the same colors for background and foreground';
            }

            // validate map and map coordinates
            if (isset($data['contact_map']) && $data['contact_map'] == 'Y') {
                if (empty($data['contact_map_coords']) || empty($data['contact_map_coords']['x']) || empty($data['contact_map_coords']['y'])) {
                    $arrError[] = 'If you want to show a Google Map on your contact page you must specify the coordinates of your company';
                } else {
                    if (!is_numeric($data['contact_map_coords']['x']) || !is_numeric($data['contact_map_coords']['y'])) {
                        $arrError[] = 'You have specified an incorrect coordinates of your company';
                    } else {
                        $data['contact_map_coords']['x'] = (float)$data['contact_map_coords']['x'];
                        $data['contact_map_coords']['y'] = (float)$data['contact_map_coords']['y'];
                    }
                }
            } else {
                $data['contact_map_coords'] = ['x' => '', 'y' => ''];
            }

            // validate template slider
            if (!empty($template) && isset($_FILES['slide']) && !empty($_FILES['slide']['tmp_name'])) {
                if (!$this->_files->isImage($_FILES['slide']['type'])) {
                    $arrError[] = 'Uploaded slide is not an image';
                } else if (filesize($_FILES['slide']['tmp_name']) > self::MAX_UPLOAD_FILE_SIZE) {
                    $arrError[] = sprintf('Uploaded slides is too large (max %s)', Settings::formatSize(self::MAX_UPLOAD_FILE_SIZE / 1024));
                } else {
                    $size = getimagesize($_FILES['slide']['tmp_name']);
                    if ($size && ($size[0] < self::SLIDE_MIN_WIDTH || $size[1] < self::SLIDE_MIN_HEIGHT)) {
                        $arrError[] = sprintf('One of the uploaded slides has too small resolution. (min: %dx%d, recommended size: 950x440 px)', self::SLIDE_MIN_WIDTH, self::SLIDE_MIN_HEIGHT);
                    }
                }
            }

            // validate template background
            if (!empty($template) && isset($_FILES['background']) && !empty($_FILES['background']['tmp_name'])) {
                if (!$this->_files->isImage($_FILES['background']['type'])) {
                    $arrError[] = 'Uploaded background is not an image';
                } else if (filesize($_FILES['background']['tmp_name']) > self::MAX_UPLOAD_FILE_SIZE) {
                    $arrError[] = sprintf('The size of the background is too large (max %s)', Settings::formatSize(self::MAX_UPLOAD_FILE_SIZE / 1024));
                } else {
                    $size = getimagesize($_FILES['background']['tmp_name']);
                    if ($size && ($size[0] > self::BACKGROUND_MAX_WIDTH || $size[1] > self::BACKGROUND_MAX_HEIGHT)) {
                        $arrError[] = sprintf('Resolution of the background is too large. (max: %dx%dpx)', self::BACKGROUND_MAX_WIDTH, self::BACKGROUND_MAX_HEIGHT);
                    }
                }
            }

            // save website
            $message = '';
            if(empty($arrError)) {
                $result = $this->_companyWebsites->saveWebsite($companyId, $data);
                if(!$result['success']) {
                    $arrError[] = $result['message'];
                } else {
                    $message = 'Web site was saved';
                }
            }

            $view->setVariable('error', $arrError);
            $view->setVariable('successMessage',$message);
        }

        /* #################################
         * GET AND DISPLAY WEBSITE DATA
         * ################################# */

        // get main options
        $website = $this->_companyWebsites->getCompanyWebsite($companyId);
        if(!empty($arrError) && is_array($website)) {
            // Use already saved paths to images
            $data['company_logo']      = $website['company_logo'];
            $data['assessment_banner'] = $website['assessment_banner'];

            $website = array_merge($website, $data);
        }

        // if no main stored - paste default data (company details)
        // if (empty($website) || (isset($website['old']) && !empty($website['old']))) {
        //
        //    $builderWebsite = $this->_builderLayoutModel->getBuilderByCompanyId($companyId);
        //    $builderTemplates = $this->_builderLayoutModel->getTemplates();
        //
        //    $view->setVariable('builderWebsite', $builderWebsite);
        //    $view->setVariable('builderTemplates', $builderTemplates);
        //    $view->setVariable('booIsAustralia', $booIsAustralia);
        //    $title = $this->_auth->isCurrentUserSuperadmin() ? 'Default Website Settings' : 'Web-builder';
        //    $this->layout()->setVariable('title', $title);
        //    $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);
        //
        //    $view->setTemplate('superadmin/company-website/new-builder.phtml');
        // } else {
           // if no main stored - paste default data (company details)
            if(empty($website)) {

                $companyInfo = $this->_company->getCompanyInfo($companyId);

                // default data
                $website = array(
                    'id' => 0,
                    'company_id' => $companyId,
                    'template_id' => 0,
                    'visible' => 'N',
                    'company_name' => $companyInfo['companyName'],
                    'entrance_name' => '',
                    'title' => $companyInfo['companyName'],
                    'company_phone' => $companyInfo['phone1'],
                    'company_skype' => '',
                    'company_fax' => $companyInfo['fax'],
                    'company_linkedin' => '',
                    'company_facebook' => '',
                    'company_twitter' => '',
                    'company_email' => $companyInfo['companyEmail'],
                    'footer_text' => sprintf('All Rights reserved @ %s', date('Y')),
                    'homepage_text' => '',
                    'homepage_name' => 'Home',
                    'about_text' => '',
                    'about_name' => 'About us',
                    'canada_text' => '',
                    'canada_name' => $booIsAustralia ? 'About Australia' : 'About Canada',
                    'immigration_text' => '',
                    'immigration_name' => 'Immigration',
                    'assessment_on' => 'N',
                    'assessment_name' => 'Free assessment',
                    'assessment_url' => '',
                    'assessment_background' => 'transparent',
                    'assessment_foreground' => '#000000',
                    'homepage_on' => 'Y',
                    'contact_on' => 'Y',
                    'contact_name' => 'Contact us',
                    'contact_text' => $companyInfo['address'],
                    'contact_map' => 'N',
                    'contact_map_coords' => array('x' => '', 'y' => ''),
                    'login_block_on' => 'N',
                    'about_on' => 'N',
                    'canada_on' => 'N',
                    'immigration_on' => 'N',
                    'external_links_on' => 'N',
                    'options' => array(),
                    'dataAvailableInDB' => false,
                    'script_google_analytics' => '',
                    'script_facebook_pixel' => '',
                    'external_links_title' => ''
                );
            }

            // check entrance name
            if(empty($website['entrance_name'])) {
                $website['entrance_name'] = $this->_companyWebsites->generateEntranceName($companyId);
            }

            // get QNR and default QNR
            $companyQnrs = $this->_companyProspects->getCompanyQnr()->getCompanyQuestionnaires($companyId, true);
            $website['default_assessment_url'] = empty($companyQnrs) ? '' :$this->layout()->getVariable('topBaseUrl') . '/qnr?id=' . urlencode($companyQnrs[0]) . '&hash=' . urlencode($this->_companyProspects->getCompanyQnr()->generateHashForQnrId($companyQnrs[0]));
            $website['assessment_url'] = empty($website['assessment_url']) ? $website['default_assessment_url'] : $website['assessment_url'];

            // get templates list
            $templates = $this->_companyWebsites->getCompanyWebsitesTemplates()->getTemplates();

            $view->setVariable('templates',$templates);
            $view->setVariable('arrWebsite',$website);
            $view->setVariable('booWebsiteAvailable',!empty($website) && !empty($templates));
            $view->setVariable('booIsAustralia', $booIsAustralia);

            $title = $this->_auth->isCurrentUserSuperadmin() ? 'Default Website Settings' : 'Web-builder';
            $this->layout()->setVariable('title', $title);
            $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

            $view->setVariable('googleMapsKey', $this->_config['site_version']['google_maps_key']);

            $view->setTemplate('superadmin/company-website/index.phtml');
       // }

        return $view;
    }

    public function templateOptionsAction()
    {
        $view = new JsonModel();

        $templateId   = (int) $this->params()->fromQuery('id');
        $strError     = '';
        $options      = [];
        $settingsPage = '';

        if ($templateId <= 0) {
            $strError = 'Incorrect template ID';
        }

        // get template defaults info
        $template = [];
        if (empty($strError)) {
            $template = $this->_companyWebsites->getCompanyWebsitesTemplates()->getTemplate($templateId);
            if (empty($template)) {
                $strError = 'Can not get information about template';
            }
        }


        if (empty($strError)) {
            // get current company template data
            $companyId      = $this->_auth->getCurrentUserCompanyId();
            $companyWebsite = $this->_companyWebsites->getCompanyWebsite($companyId);

            // merge template options
            if (!empty($companyWebsite) && is_array($companyWebsite['options'])) {
                $options = array_merge($template['options'], $companyWebsite['options']);
            } else {
                $options = $template['options'];
            }

            // get settings page
            if (file_exists($template['pathToSettingsFile'])) {
                $arrParsedInfo = [
                    'companyId' => $companyId,
                    'baseUrl' => $this->layout()->getVariable('baseUrl'),
                    'topBaseUrl' => $this->layout()->getVariable('topBaseUrl'),
                    'templateUrl' => $this->layout()->getVariable('topBaseUrl') . '/templates/' . $template['template_name'],
                    'options' => $options,
                    'defaultBackgrounds' => $template['defaultBackgrounds'],
                    'defaultSlides' => $template['defaultSlides'],
                ];

                /** @var HelperPluginManager $pluginManager */
                $pluginManager = $this->_serviceManager->get('ViewHelperManager');
                /** @var Partial $partial */
                $partial = $pluginManager->get('partial');

                $viewModel = new ViewModel($arrParsedInfo);
                $template = 'website/' . $template['template_name'] . '/settings';
                $viewModel->setTemplate($template);
                $settingsPage = $partial($viewModel);
            }
        }

        $result = array(
            'success' => empty($strError),
            'message' => $strError,
            'options' => $options,
            'settingsPage' => $settingsPage
        );

        return $view->setVariables($result);
    }

    public function removeImageAction()
    {
        $filter = new StripTags();

        $companyId = $this->_auth->getCurrentUserCompanyId();
        $option    = $filter->filter(trim($this->findParam('option', '')));

        if($companyId > 0 && !empty($option)) {
            if($option == 'company_logo' || $option == 'assessment_banner') {
                $this->_companyWebsites->removeImage($companyId, $option);
            } else {
                $this->_companyWebsites->removeOptionImage($companyId, $option);
            }
        }

        if(!$this->getRequest()->isXmlHttpRequest()) {
            return $this->redirect()->toUrl('/superadmin/company-website');
        }
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function createWebsiteAction()
    {
        $view = new JsonModel();

        $strError = '';

        $companyId   = $this->_auth->getCurrentUserCompanyId();
        $companyData = $this->_company->getCompanyInfo($companyId);

        $oldBuilder = $this->_companyWebsites->getCompanyWebsite($companyId);
        if (isset($oldBuilder['entrance_name']) && !empty($oldBuilder['entrance_name'])) {
            $entranceName = $oldBuilder['entrance_name'];
        } else {
            $entranceName = $this->_builderLayout->generateEntranceName($companyId, $companyData['companyName']);
        }

        if (!$this->_builderLayout->saveDefLayout($companyId, $entranceName, $oldBuilder)) {
            $strError = 'Error on creating builder default data';
        }

        $arrResult = [
            'success' => empty($strError),
            'msg' => $strError
        ];

        return $view->setVariables($arrResult);
    }

    public function switchTemplateAction()
    {
        $view = new JsonModel();

        $strError   = '';
        $companyId  = $this->_auth->getCurrentUserCompanyId();
        $templateId = $this->findParam('templateId');

        if (!$this->_builderLayout->switchTemplate($companyId, $templateId)) {
            $strError = 'Error on changing template';
        }

        $arrResult = [
            'success' => empty($strError),
            'msg' => $strError,
            'templateId' => $templateId
        ];

        return $view->setVariables($arrResult);
    }

    public function builderAction()
    {
        $view = new ViewModel();

        try {
            $filter = new StripTags();
            $entranceName = trim($filter->filter($this->findParam('entrance', '')));
            if (empty($entranceName)) {
                $view->setTemplate('layout/plain');
                return $view->setVariables(
                    [
                        'content' => $this->_tr->translate('Company not found')
                    ]
                );
            }
            $old = $this->_companyWebsites->builderIsNew($entranceName);

            if (!empty($old)) {
                $view->setTemplate('layout/plain');
                return $view->setVariables(
                    [
                        'content' => $this->_tr->translate('Website not found')
                    ]
                );
           }

            // check for incoming errors
            $view->setVariable('error', $filter->filter($this->findParam('error')));

            // get homepage
            $currentPage = $this->findParam('page');

            if (!in_array($currentPage, $this->_pages)) {
                $currentPage = 'homepage';
            }

            $builderData = $this->_builderLayout->getBuilderContentByEntranceName($entranceName, $currentPage);
            $activePages = $this->_builderLayout->getActivePages($builderData);

            if (empty($builderData) || empty($builderData['visible'])) {
                $view->setTemplate('layout/plain');
                return $view->setVariables(
                    [
                        'content' => $this->_tr->translate('Website not found')
                    ]
                );
            }
            if (empty($activePages) || empty($activePages[$currentPage]) || !$activePages[$currentPage]['available']) {
                $view->setTemplate('layout/plain');
                return $view->setVariables(
                    [
                        'content' => $this->_tr->translate('Page not found')
                    ]
                );
            }

            //set pageTitle variable
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
                'assessment_url'=>''
            ];

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
                    'company_name' => !empty($builderData['company_name']) ? strip_tags($builderData['company_name']) : 'Company name',
                    'title' => !empty($builderData['title']) ? $builderData['title'] : 'Navbar',
                    'assessment_url' => !empty($builderData['assessment_url']) ? $builderData['assessment_url'] : ''
                ];
                //if isset page
                if ($builderData[$currentPage] !== null) {
                    $pageData['content'] = !empty($builderData['content']) ? $builderData['content'] : '';
                    $pageData['contentCss'] = !empty($builderData['contentCss']) ? $builderData['contentCss'] : '';
                }
            }

            $view->setVariables(
                [
                    'entranceName' => $entranceName,
                    'pageData' => $pageData,
                    'currentPage' => $currentPage,
                    'activePages' => $activePages,
                    'templateUrl' => $this->layout()->getVariable('topBaseUrl') . '/templates/editTemplate',
                    'topBaseUrl' => $this->layout()->getVariable('topBaseUrl')
                ]
            );
            $view->setTemplate('website/editTemplate');
        } catch (Exception $e) {
            $view->setTemplate('layout/plain');
            $view->setVariable('content', $this->_tr->translate('Internal error'));
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    public function editPageAction()
    {
        $view = new JsonModel();

        $error = $msg = '';
        if ($this->getRequest()->isPost()) {
            try {
                $companyId = $this->_auth->getCurrentUserCompanyId();
                $request   = $this->findParams();

                if (empty($error)) {
                    $builder = $this->_builderLayout->getBuilderRow($companyId);

                    if ($builder) {
                        $updateBuilderData = [
                            'company_name' => $request['company_name'],
                            'title' => $request['title'],
                            'address' => $request['address'],
                            'phone' => $request['phone'],
                            'assessment_url' => $request['assessment_url'],
                            'fb_script' => $request['fb_script'],
                            'google_script' => $request['google_script']
                        ];

                        if ($request['visible'] == 'on') {
                            $updateBuilderData['visible'] = 1;
                        } else {
                            $updateBuilderData['visible'] = 0;
                        }
                        $this->_builderLayout->updateBuilder($builder['builder_id'], $updateBuilderData);

                        foreach ($this->_pages as $value) {
                            if ($value == 'homepage') {
                                $pageCheckbox = 1;
                            } elseif (isset($request[$value . '_available']) && $request[$value . '_available'] == 'on') {
                                $pageCheckbox = 1;
                            } else {
                                $pageCheckbox = 0;
                            }

                            $res = $this->_builderPage->updatePage($builder[$value], ['name' => $request[$value . '_name'], 'available' => $pageCheckbox]);
                            if (!$res) {
                                throw new Exception("$value: Error in update method!");
                            }
                        }
                    } else {
                        throw new Exception("The builder with company id $companyId does not exist.");
                    }

                    if (!$this->_builderLayout->updateNav($builder)) {
                        throw new Exception('Navigation did not update');
                    }

                    $msg = 'Web site was saved';
                }
            } catch (Exception $e) {
                $error = $this->_tr->translate('Internal error.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        $arrResult = array(
            'success' => empty($error),
            'error' => $error,
            'msg' => $msg
        );

        return $view->setVariables($arrResult);
    }

    public function saveDataAction()
    {
        $view = new JsonModel();
        $filter = new StripTags();

        $error = $msg = '';
        if ($this->getRequest()->isXmlHttpRequest()) {
            try {

                $request = $this->findParams();
                $entranceName = trim($filter->filter($this->findParam('entrance', '')));
                if (empty($entranceName)) {
                    $error = $this->_tr->translate('Company not found');
                    $view->setVariables(array('success' => empty($error), 'error' => $error, 'msg' => $msg));
                    return $view;
                }
                // get companyId
                $companyId = $this->_builderLayout->getCompanyIdByEntrance($entranceName);
                if ($companyId === false) {
                    $error = $this->_tr->translate('Company website not found or not available');
                    $view->setVariables(array('success' => empty($error), 'error' => $error, 'msg' => $msg));
                    return $view;
                }

                //configuration builder data before update
                $builder            = [];
                $builder['main']    = [
                    'header' => $request['headerHtml'],
                    'footer' => $request['footerHtml'],
                    'css' => $request['mainCss']
                ];
                $builder['content'] = [
                    'html' => $request['contentHtml'],
                    'css'  => $request['contentCss'],
                ];
                $res                = $this->_builderLayout->updateLayout($companyId, $builder, $request['page_name']);

                if (!$res) {
                    throw new Exception('Error in save method!');
                }
            } catch (Exception $e) {
                $error = $this->_tr->translate('Internal error.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }

            $view->setVariables(array('success' => empty($error), 'error' => $error, 'msg' => $msg));
        }

        return $view;
    }

    public function switchBuilderAction()
    {
        $view = new JsonModel();

        $error = $msg = '';
        if ($this->getRequest()->isXmlHttpRequest()) {
            try {
                $companyId          = $this->_auth->getCurrentUserCompanyId();
                $switchToOldBuilder = filter_var($this->findParam('switchToOldBuilder'), FILTER_VALIDATE_BOOLEAN);
                if ($this->_companyWebsites->changeStatusBuilder($companyId, $switchToOldBuilder)) {
                    $oldBuilder  = $this->_companyWebsites->getCompanyWebsite($companyId);
                    $newBuilder  = $this->_builderLayout->getBuilderByCompanyId($companyId);
                    $activePages = $this->_builderLayout->getActivePages($newBuilder);
                    if (!empty($oldBuilder) && !empty($newBuilder) && count($oldBuilder) > 0 && count($newBuilder) > 0) {
                        if (!empty($switchToOldBuilder)) {
                            $this->_builderLayout->updateFromOldBuilderAvailability($oldBuilder, $activePages);
                            $this->_builderLayout->updateNewBuilderData($newBuilder['builder_id'], $oldBuilder);
                        } else {
                            $this->_companyWebsites->updateOldBuilderMainData($oldBuilder['id'], $activePages, $newBuilder);
                        }
                    }
                } else {
                    throw new Exception('Error in switch method!');
                }
            } catch (Exception $e) {
                $error = $this->_tr->translate('Internal error.');
            }
        }

        if (!empty($error)) {
            $this->getResponse()->setStatusCode(500);
        }

        $arrResult = array(
            'success' => empty($error),
            'error' => $error,
            'msg' => $msg
        );

        return $view->setVariables($arrResult);
    }
}