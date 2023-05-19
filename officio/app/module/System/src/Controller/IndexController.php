<?php

namespace System\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use FilesystemIterator;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Helper\Partial;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Comms\Service\Mailer;
use Officio\Import\SpreadsheetExcelReader;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Payment\PaymentServiceInterface;
use Officio\Service\Roles;
use Officio\Common\Service\Settings;
use Officio\Service\Users;
use Prospects\Service\CompanyProspects;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tasks\Service\Tasks;

/**
 * System Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Users */
    protected $_users;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var Files */
    protected $_files;

    /** @var Roles */
    protected $_roles;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Tasks */
    protected $_tasks;

    /** @var PaymentServiceInterface */
    protected $_payment;

    /** @var Encryption */
    protected $_encryption;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_mailer           = $services[Mailer::class];
        $this->_clients          = $services[Clients::class];
        $this->_users            = $services[Users::class];
        $this->_files            = $services[Files::class];
        $this->_country          = $services[Country::class];
        $this->_roles            = $services[Roles::class];
        $this->_tasks            = $services[Tasks::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_payment          = $services['payment'];
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('/system/index/check-version');
    }

    public function appendNewRuleAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');


        $select = (new Select())
            ->from('company')
            ->columns(['company_id'])
            ->where([(new Where())->greaterThan('company_id', 1)]);

        $companies = $this->_db2->fetchCol($select);
        foreach ($companies as $companyId) {
            $companyRoles = $this->_company->getCompanyRoles($companyId);
            foreach ($companyRoles as $role) {
                if ($role['role_type'] == 'admin') {
                    $this->_db2->insert(
                        'acl_role_access',
                        [
                            'role_id' => $role['role_parent_id'],
                            'rule_id' => 1400
                        ]
                    );
                }
            }
        }

        return $view->setVariable('content', 'Done.');
    }

    public function createQnrAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);

        $arrSuccess = $arrFailed = array();
        $strError   = '';
        try {
            $page = $this->findParam('page', 0);
            $page = is_numeric($page) ? $page : 0;
            $page = max($page, 0);


            $limit  = 5;
            $offset = $limit * $page;

            $select = (new Select())
                ->from(['p' => 'company_packages'])
                ->columns([])
                ->join(array('c' => 'company'), 'c.company_id = p.company_id', array('company_id', 'admin_id'), Select::JOIN_LEFT_OUTER)
                ->where([(new Where())->notIn('p.company_id', [0, 1, 41]), 'p.package_id' => 4])
                ->limit($limit)
                ->offset($offset);

            $arrCompanies = $this->_db2->fetchAll($select);

            // Load info about default QNR
            $defaultQnrId  = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
            $arrDefaultQnr = $this->_companyProspects->getCompanyQnr()->getQuestionnaireInfo($defaultQnrId);

            foreach ($arrCompanies as $arrCompanyInfo) {
                $companyId = $arrCompanyInfo['company_id'];
                $adminId   = $arrCompanyInfo['admin_id'];

                $qnrId = $this->_companyProspects->getCompanyQnr()->createQnr(
                    $companyId,
                    $adminId,
                    $arrDefaultQnr['q_name'],
                    $arrDefaultQnr['q_noc'],
                    $defaultQnrId
                );

                if (!empty($qnrId)) {
                    $arrSuccess[] = $companyId;
                } else {
                    $arrFailed[] = $companyId;
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariable('content', empty($strError) ? 'Done.<br/><br/>Success:<br/>' . implode(', ', $arrSuccess) . '<br/> Failed:<br/>' . implode(', ', $arrFailed) : $strError);
    }


    public function updateDefaultQnrAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);

        $select = (new Select())
            ->from('company_questionnaires')
            ->columns(['q_id'])
            ->where([(new Where())->notEqualTo('q_id', 1)]);

        $arrQNRIds = $this->_db2->fetchCol($select);

        $select = (new Select())
            ->from('company_questionnaires_sections_templates')
            ->where(['q_id' => 1, 'q_section_id' => [11, 12]]);

        $arrSectionsTemplates = $this->_db2->fetchAll($select);

        $select = (new Select())
            ->from('company_questionnaires_fields_templates')
            ->where(['q_id' => 1, 'q_field_id' => range(106, 113)]);

        $arrFieldTemplates = $this->_db2->fetchAll($select);

        $select = (new Select())
            ->from('company_questionnaires_fields_options_templates')
            ->where(['q_id' => 1, 'q_field_option_id' => range(335, 362)]);

        $arrOptions = $this->_db2->fetchAll($select);


        foreach ($arrQNRIds as $qnrId) {
            foreach ($arrSectionsTemplates as $arrSectionTemplateInfo) {
                $arrSectionTemplateInfo['q_id'] = $qnrId;
                $this->_db2->insert('company_questionnaires_sections_templates', $arrSectionTemplateInfo);
            }

            foreach ($arrFieldTemplates as $arrFieldTemplateInfo) {
                $arrFieldTemplateInfo['q_id'] = $qnrId;
                $this->_db2->insert('company_questionnaires_fields_templates', $arrFieldTemplateInfo);
            }

            foreach ($arrOptions as $arrOptionsInfo) {
                $arrOptionsInfo['q_id'] = $qnrId;
                $this->_db2->insert('company_questionnaires_fields_options_templates', $arrOptionsInfo);
            }
        }

        return $view->setVariable('content', 'Done.');
    }


    public function clearCacheAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $filter = new StripTags();

        $action = $filter->filter($this->findParam('act', ''));
        $id     = $filter->filter($this->findParam('id', ''));

        switch ($action) {
            case 'all':
                $this->_cache->flush();
                $view->setVariable('content', "Cleared whole cache.");
                break;

            case 'id':
                if (!empty($id)) {
                    $this->_cache->removeItem($id);
                    $view->setVariable('content', "Cleared cache by id: <i>$id</i>.");
                } else {
                    $view->setVariable('content', "Id cannot be empty.");
                }

                break;

            default:
                $view->setVariable('content', 'Nothing to do...');
                break;
        }

        return $view;
    }


    public function checkVersionAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $errMsg = '';
            $filter = new StripTags();

            $arrVersionInfo = array(
                'version_os'              => Json::decode($this->findParam('version_os'), Json::TYPE_ARRAY),
                'version_browser'         => Json::decode($this->findParam('version_browser'), Json::TYPE_ARRAY),
                'version_pdf'             => Json::decode($this->findParam('version_pdf'), Json::TYPE_ARRAY),
                'version_additional_info' => Json::decode($this->findParam('version_additional_info'), Json::TYPE_ARRAY),
                'version_comments'        => Json::decode($this->findParam('version_comments'), Json::TYPE_ARRAY),
            );

            if (empty($arrVersionInfo['version_comments'])) {
                $arrVersionInfo['version_comments'] = '&nbsp;';
            }

            if (empty($errMsg)) {
                $subject = 'Version Info';

                /** @var HelperPluginManager $viewHelperManager */
                $viewHelperManager = $this->_serviceManager->get('ViewHelperManager');
                /** @var Partial $partial */
                $partial = $viewHelperManager->get('partial');

                $view->setTemplate('system/index/email-version-info.phtml');
                $message = $partial($view);
                foreach ($arrVersionInfo as $key => $val) {
                    $message = $filter->filter(str_replace('__' . $key . '__', nl2br($val), $message));
                }
                $this->_mailer->sendEmailToSupport($subject, $message);
            }

            $view      = new JsonModel();
            $arrResult = array(
                'success' => empty($errMsg),
                'message' => $errMsg
            );
            $view->setVariables($arrResult);
        } else {
            $view->setVariable('settings', $this->_settings);
        }

        return $view;
    }

    // TODO PHP7 This should do streaming output
    public function paymentCertificationAction()
    {
        error_reporting(E_ALL);

        try {
            $this->_payment->init();

            $customerRefNum    = 'test_company_111';
            $creditCardNum     = '4055011111111111';
            $creditCardExpDate = '0511';

            $customerRefNum2    = 'test_company_222';
            $creditCardNum2     = '5454545454545454';
            $creditCardExpDate2 = '0211';

            $customerRefNum3    = 'test_company_555';
            $creditCardNum3     = '371449635398431';
            $creditCardExpDate3 = '0211';

            $customerRefNum4    = 'test_company_666';
            $creditCardNum4     = '5405222222222226';
            $creditCardExpDate4 = '0412';

            echo '<h1>1. Section B - Auth Capture Transactions- Message Type "AC"</h1>';

            // ******************************************************
            // 1.1. Auth Capture Transactions - Message Type 'AC' (VISA, amount 30.00)
            // ******************************************************
            $arrOrderParams = array(
                'creditCardNum'     => '4012888888881',
                'creditCardExpDate' => '1211',
                'orderId'           => 'order 000',
                'amount'            => 100.00,
                'AVSzip'            => '11111',
                'CardSecVal'        => '111'
            );
            $arrResult      = $this->_payment->chargeAmount($arrOrderParams);

            echo '<h2>1.1. Auth Capture Transactions - Message Type "AC" (VISA, amount 100.00):</h2>';
            echo '<pre>' . print_r($arrResult, true) . '</pre>';

            /*
            // ******************************************************
            // 1.2. Auth Capture Transactions - Message Type 'AC' (VISA, amount 38.01)
            // ******************************************************
            $arrOrderParams = array(
                'creditCardNum'     => '4012888888881',
                'creditCardExpDate' => '1211',
                'orderId'           => 'order 222',
                'amount'            => 38.01,
                'AVSzip'            => '33333',
                'CardSecVal'        => '222'
            );
            $arrResult = $oPaymentService->chargeAmount($arrOrderParams);
                        
            echo '<h2>1.2. Auth Capture Transactions - Message Type "AC" (VISA, amount 38.01):</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';

            // ******************************************************
            // 1.3. Auth Capture Transactions - Message Type 'AC' (MC, amount 41.00)
            // ******************************************************
            $arrOrderParams = array(
                'creditCardNum'     => '5454545454545454',
                'creditCardExpDate' => '1011',
                'orderId'           => 'order 333',
                'amount'            => 41.00,
                'AVSzip'            => '44444',
                'CardSecVal'        => '333'
            );
            $arrResult = $oPaymentService->chargeAmount($arrOrderParams);
                        
            echo '<h2>1.3. Auth Capture Transactions - Message Type "AC" (MC, amount 41.00):</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';

            // ******************************************************
            // 1.4. Auth Capture Transactions - Message Type 'AC' (MC, amount 11.02)
            // ******************************************************
            $arrOrderParams = array(
                'creditCardNum'     => '5454545454545454',
                'creditCardExpDate' => '1011',
                'orderId'           => 'order 444',
                'amount'            => 11.02,
                'AVSzip'            => '88888',
                'CardSecVal'        => '666'
            );
            $arrResult = $oPaymentService->chargeAmount($arrOrderParams);
                        
            echo '<h2>1.4. Auth Capture Transactions - Message Type "AC" (MC, amount 11.02):</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';            
            */

            /*
            echo '<h1>2. Section J - Customer Profiles</h1>';
            echo '<h1>Add - Perform an add profile transaction using information below.</h1>';
            
            // ******************************************************
            // 1. Create profile 1 (Visa)
            // ******************************************************
            $customerName      = 'Test company for certification';
            
            $arrProfileInfo = array(
                'customerName'      => $customerName,
                'customerRefNum'    => $customerRefNum,
                'creditCardNum'     => $creditCardNum,
                'creditCardExpDate' => $creditCardExpDate,
                'customerAddress1'  => 'customer address 1',
                'customerAddress2'  => 'customer address 2',
                'customerCity'      => 'city',
                'customerState'     => 'SA',
                'customerZIP'       => '11111',
                'customerEmail'     => 'email@email.com'
            );
            
            $arrResult = $oPaymentService->createProfile($arrProfileInfo);
            
            echo '<h2>1. Profile 1 creation result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';

            // ******************************************************
            // 2. Create profile 2 (MC)
            // ******************************************************
            $customerName2      = 'Test company 2';
            
            $arrProfileInfo2 = array(
                'customerName'      => $customerName2,
                'customerRefNum'    => $customerRefNum2,
                'creditCardNum'     => $creditCardNum2,
                'creditCardExpDate' => $creditCardExpDate2,
                'customerZIP'       => '22222',
            );
            
            $arrResult2 = $oPaymentService->createProfile($arrProfileInfo2);

            echo '<h2>2. Profile 2 creation result:</h2>'; 
            echo '<pre>' . print_r($arrResult2, true) . '</pre>';
            */
            /*
            
            echo '<h1>xxx. Section I - Retry Logic</h1>';
            echo '<h1>First Set.</h1>';
            
            // ******************************************************
            // xxx. [RETRY] First Set - Transactions sent for the first time. (Visa)
            // ******************************************************
            $traceNumber = $oPaymentService->generatePaymentTraceNumber();
            $arrParams = array(
                'orderId'        => 'test order yyy',
                'customerRefNum' => 'TEST_COMPANY_11',
                'amount'         => 128.00,
                'traceNumber'    => $traceNumber
            );
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);

            echo '<h2>a. Visa process result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            
            echo "<div>Trace Number: $traceNumber</div>";
            
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
            echo '<h2>b. Retry result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            
            $arrParams['traceNumber'] = '123456';
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
            echo '<h2>c. Third retry result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            */

            /*
            echo '<h1>Second Set.</h1>';
            $traceNumber = $oPaymentService->generatePaymentTraceNumber();
            $arrParams = array(
                'orderId'        => 'test order zzz',
                'customerRefNum' => 'TEST_COMPANY_22',
                'amount'         => 271.00,
                'traceNumber'    => $traceNumber
            );
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
                        
            echo '<h2>a. MC process result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            
            echo "<div>Trace Number: $traceNumber</div>";
            
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
            echo '<h2>b. Retry result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            */

            /*
            echo '<h1>Third Set.</h1>';
            $traceNumber = $oPaymentService->generatePaymentTraceNumber();
            $arrParams = array(
                'orderId'        => 'test order xyz',
                'customerRefNum' => 'TEST_COMPANY_11',
                'amount'         => 554.00,
                'traceNumber'    => $traceNumber
            );
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
                        
            
            echo '<h2>a. Visa 2 process result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            
            echo "<div>Trace Number: $traceNumber</div>";
            
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
            echo '<h2>b. Retry result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            */

            /*
            // ******************************************************
            // 3. Update profile 1 (Change card to American Express)
            // ******************************************************
            $creditCardNum = '370000000000002';
            $creditCardExpDate = '1210';
            $arrProfileInfo3 = array(
                'customerName'      => $customerName,
                'customerRefNum'    => $customerRefNum,
                'creditCardNum'     => $creditCardNum,
                'creditCardExpDate' => $creditCardExpDate
            );
            $arrResult = $oPaymentService->updateProfile($arrProfileInfo3);
            
            echo '<h2>3. Profile 1 update result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            
            
            // ******************************************************
            // 4. Update profile 2 (Add address and phone to profile)
            // ******************************************************
            $arrProfileInfo2 = array(
                'customerName'      => $customerName2,
                'customerRefNum'    => $customerRefNum2,
                'customerAddress1'  => '123 Test Drive',
                'customerAddress2'  => 'customer 2 address 2',
                'customerPhone'     => '11122233334444',
            
            );
            $arrResult = $oPaymentService->updateProfile($arrProfileInfo2);
            
            echo '<h2>4. Profile 2 update result:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            
            
            
            // ******************************************************
            // 5. Retrieve - Retrieve the first customer profile created. 
            // ******************************************************
            $arrResult = $oPaymentService->readProfile($customerRefNum);
            
            echo '<h2>5. Retrieve profile:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            
            
            
            // ******************************************************
            // 6. Auth Capture - Amex
            // ******************************************************
            $arrParams = array(
                'orderId'        => 'test order 1111',
                'customerRefNum' => $customerRefNum,
                'amount'         => 45.00
            );
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
            
            echo '<h2>6. A/C Amex, amount 45.00:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
             
            
            // ******************************************************
            // 7. Auth Capture - MC
            // ******************************************************
            $arrParams = array(
                'orderId'        => 'test order 222',
                'customerRefNum' => $customerRefNum2,
                'AVSzip'         => '33333',
                'amount'         => 50.00
            );
            $arrResult = $oPaymentService->chargeAmountBasedOnProfile($arrParams);
            
            echo '<h2>7. A/C MC, amount 50.00:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            */


            // ******************************************************
            // 8. Add Profile during auth/capture (Amex)
            // ******************************************************
            /*
            $customerName3      = 'Test company for certification 3';
            $arrOrderParams = array(
                'customerRefNum'                  => $customerRefNum3,
                'customerProfileFromOrderInd'     => 'S',
                'customerProfileOrderOverrideInd' => 'NO',

                'creditCardNum'     => $creditCardNum3,
                'creditCardExpDate' => $creditCardExpDate3,
            
                'AVSzip'            => '22222',
                'AVSaddress1'       => 'customer 3 address 1',
                'AVSaddress2'       => 'customer 3 address 2',
                'AVSphoneNum'       => '11122233334444',
            
                'orderId'           => 'order 300',
                'amount'            => 30.00
            );
            $arrResult = $oPaymentService->chargeAmount($arrOrderParams);
            
            
            echo '<h2>8. Add Profile during auth/capture (Amex):</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';            
            */

            /*
            // ******************************************************
            // 9. Add Profile during auth/capture (MC)
            // ******************************************************
            $customerName4      = 'Test company for certification 4';
            $arrOrderParams = array(
                'customerRefNum'                  => $customerRefNum4,
                'customerProfileFromOrderInd'     => 'S',
                'customerProfileOrderOverrideInd' => 'NO',

                'creditCardNum'     => $creditCardNum4,
                'creditCardExpDate' => $creditCardExpDate4,
            
                'AVSzip'            => '33333',
                'AVSaddress1'       => 'customer 4 address 1',
            
                'orderId'           => 'order 400',
                'amount'            => 50.00
            );
            
            $arrResult = $oPaymentService->chargeAmount($arrOrderParams);
                        
            echo '<h2>9. Add Profile during auth/capture (MC):</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';            
            
            
            /*
            // ******************************************************
            // 10. Delete profile 3 (Amex)
            // ******************************************************
            $arrResult = $oPaymentService->deleteProfile($customerRefNum3);
            
            echo '<h2>10. Delete profile 3 (Amex):</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';

            
            // ******************************************************
            // 11. Delete profile 4 (MC)
            // ******************************************************
            $arrResult = $oPaymentService->deleteProfile($customerRefNum4);
            
            echo '<h2>11. Delete profile 4 (MC):</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            

            
            
            // ******************************************************
            // 12. [ERROR] Use customer profile - 45461SAAX
            // ******************************************************
            $arrProfileInfo = array(
                'customerRefNum'    => '45461SAAX',
                'customerName'      => 'test company (not created)',
                'creditCardNum'     => $creditCardNum,
                'creditCardExpDate' => $creditCardExpDate,
            );
            $arrResult = $oPaymentService->createProfile($arrProfileInfo);
                        
            echo '<h2>12. Use customer profile - 45461SAAX:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            */

            // ******************************************************
            // 13. [ERROR] Add profile but do not include customer name.
            // ******************************************************
            /*
            $arrProfileInfo = array(
                'customerRefNum'    => $customerRefNum4 . '123123',
                'creditCardNum'     => $creditCardNum4,
                'creditCardExpDate' => $creditCardExpDate4,
            );
            $arrResult = $oPaymentService->createProfile($arrProfileInfo);
                        
            echo '<h2>13. Add profile but do not include customer name:</h2>'; 
            echo '<pre>' . print_r($arrResult, true) . '</pre>';
            */
        } catch (Exception $e) {
            echo "Exception " . $e->getMessage();
        }

        echo '<br/>Done.';
    }

    public function enableTimeTrackerAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $select = (new Select())
            ->from('company')
            ->columns(['company_id'])
            ->where([(new Where())->greaterThan('company_id', 0)]);

        $companies = $this->_db2->fetchCol($select);

        $this->_db2->update('company_details', ['time_tracker_enabled' => 'Y'], []);


        foreach ($companies as $companyId) {
            $this->_company->updateTimeTracker($companyId, true);
        }

        return $view->setVariable('content', 'Done.');
    }

    public function enableApplicantFieldsAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $strResult = '';
        try {
            $companyId = 1;

            // Load all company roles
            $companyRoles = $this->_company->getCompanyRoles($companyId);

            // Get all admin|processing roles for this company
            $role_names       = 'admin|processing';
            $arrSelectedRoles = array();
            if (!empty($companyRoles) && is_array($companyRoles)) {
                foreach ($companyRoles as $role) {
                    if (preg_match('/^(.*)' . $role_names . '(.*)$/i', $role['role_name'], $regs)) {
                        $arrSelectedRoles[$role['role_id']] = $role['role_id'];
                    }
                }
            }

            $arrResult  = $this->_clients->getApplicantFields()->createDefaultCompanyFieldsAndGroups(0, $companyId, $arrSelectedRoles);
            $booSuccess = $arrResult['success'];
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $strResult .= '<br/><br/>';
        $strResult .= 'Done. ';
        $strResult .= $booSuccess ? 'Without errors.' : 'With errors';

        return $view->setVariable('content', $strResult);
    }

    // TODO PHP7 This should do streaming output
    public function fillClientCaseNumbersAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            echo '<h1>' . date('c') . '</h1>';

            $select = (new Select())
                ->from(['mr' => 'members_relations'])
                ->join(array('m' => 'members'), 'mr.child_member_id = m.member_id', [], Select::JOIN_LEFT_OUTER)
                ->join(array('m2' => 'members'), 'mr.parent_member_id = m2.member_id', 'userType', Select::JOIN_LEFT_OUTER)
                ->where(['m.userType' => 3])
                ->order(['mr.parent_member_id ASC', 'mr.child_member_id ASC']);

            $arrAllClients = $this->_db2->fetchAll($select);

            $arrGroupedIndividualClients = array();
            $arrIndividualClients        = array();
            $individualTypeId            = $this->_clients->getMemberTypeIdByName('individual');
            foreach ($arrAllClients as $arrAllClientInfo) {
                if ($arrAllClientInfo['userType'] == $individualTypeId) {
                    $arrGroupedIndividualClients[$arrAllClientInfo['parent_member_id']][] = $arrAllClientInfo['child_member_id'];
                    $arrIndividualClients[]                                               = $arrAllClientInfo['child_member_id'];
                }
            }

            foreach ($arrGroupedIndividualClients as $arrGroupedIndividualRecords) {
                foreach ($arrGroupedIndividualRecords as $num => $memberId) {
                    $this->_db2->update('clients', ['case_number_of_parent_client' => $num + 1], ['member_id' => (int)$memberId]);
                }
            }


            $arrEmployerClients = array();
            $employerTypeId     = $this->_clients->getMemberTypeIdByName('employer');
            foreach ($arrAllClients as $arrAllClientInfo) {
                if ($arrAllClientInfo['userType'] == $employerTypeId && !in_array($arrAllClientInfo['child_member_id'], $arrIndividualClients)) {
                    $arrEmployerClients[$arrAllClientInfo['parent_member_id']][] = $arrAllClientInfo['child_member_id'];
                }
            }

            foreach ($arrEmployerClients as $arrGroupedEmployerRecords) {
                foreach ($arrGroupedEmployerRecords as $num => $memberId) {
                    $this->_db2->update('clients', ['case_number_of_parent_client' => $num + 1], ['member_id' => (int)$memberId]);
                }
            }

            $view->setVariable('content', 'Done.');
        } catch (Exception $e) {
            $view->setVariable('content', 'Fatal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    // TODO PHP7 This should do streaming output
    public function moveCloudFoldersAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            echo '<h1>' . date('c') . '</h1>';

            // AMVL Migrations (AMVL)
            $companyId = 2;

            $select = (new Select())
                ->from(['m' => 'members'])
                ->columns([])
                ->join(array('c' => 'clients'), 'm.member_id = c.member_id', array('fileNumber', 'member_id'), Select::JOIN_LEFT_OUTER)
                ->where([
                    'm.company_id' => $companyId,
                    'm.userType'   => 3,
                    (new Where())->notEqualTo('c.fileNumber', '')
                ]);

            $arrClients = $this->_db2->fetchAssoc($select);

            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
            } else {
                $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
            }

            $pathToShared = $this->_files->getCompanySharedDocsPath($companyId, false, $booLocal);

            $pathToShared   = $this->_files->getCloud()->preparePath($pathToShared) . '/';
            $arrDirectories = $this->_files->getCloud()->getDirectorySubDirectoriesList($pathToShared);

            $count = count($arrDirectories);
            if ($count == 1) {
                echo '<h2>There is ' . $count . ' folder to move</h2>';
            } else {
                echo '<h2>There are ' . $count . ' folders to move</h2>';
            }
            echo '<div>' . str_repeat('*', 100) . '</div>';

            $arrDirectoriesToMove = array();
            foreach ($arrDirectories as $currentDirectory) {
                $currentDirectory = rtrim($currentDirectory, '/');
                $caseNumber       = substr($currentDirectory, strlen($pathToShared ?? ''));
                if (!array_key_exists($caseNumber, $arrClients)) {
                    $color = 'red';
                    $msg   = 'There is no case with <i>' . $caseNumber . '</i> number.';
                    echo "<div style='color: $color'>$msg</div>";
                } else {
                    $arrDirectoriesToMove[$currentDirectory] = $this->_files->getMemberFolder($companyId, $arrClients[$caseNumber]['member_id'], true, $booLocal) . '/' . 'Documents';
                }
            }

            echo '<div>' . str_repeat('*', 100) . '</div>';


            if (count($arrDirectoriesToMove)) {
                foreach ($arrDirectoriesToMove as $moveFrom => $moveTo) {
                    $booSuccess = $this->_files->getCloud()->renameObject($moveFrom . '/', $moveTo . '/');

                    if (!$booSuccess) {
                        $color = 'red';
                        $msg   = "Directory <i>$moveFrom</i> was not moved to <i>$moveTo</i>";
                    } else {
                        $color = 'green';
                        $msg   = "Directory <i>$moveFrom</i> was moved to <i>$moveTo</i>";
                    }

                    echo "<div style='color: $color'>$msg</div>";
                }
            }

            $view->setVariable('content', 'Done.');
        } catch (Exception $e) {
            $view->setVariable('content', 'Fatal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }


    protected function getFieldsFromXLS($importFilePath, $companyId)
    {
        $cache_id = 'import_tasks_' . $companyId;
        if (!($arrSheets = $this->_cache->getItem($cache_id))) {
            if (empty($importFilePath) || !file_exists($importFilePath)) {
                return array();
            }

            $data = new SpreadsheetExcelReader();
            $data->setOutputEncoding('UTF-8');
            $data->read($importFilePath);

            $arrSheets = $data->sheets;

            $this->_cache->setItem($cache_id, $arrSheets);
        }

        // First sheet is used for client's info
        $sheet = $arrSheets[0];

        // Load headers
        $arrRowHeaders = array();
        $colIndex      = 1;
        while ($colIndex <= $sheet['numCols']) {
            $arrRow = $sheet['cells'][1];

            if (isset($arrRow[$colIndex])) {
                $arrRowHeaders[$colIndex] = trim($arrRow[$colIndex] ?? '');
            }

            $colIndex++;
        }

        // Load each client's info
        $arrClientsInfo = array();
        $clientsCount   = $sheet['numRows'];
        $rowStart       = 0;
        for ($rowIndex = $rowStart; $rowIndex < $clientsCount; $rowIndex++) {
            if (empty($rowIndex)) {
            } else {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow   = $sheet['cells'][$rowIndex + 1];
                    $columnId = @$arrRowHeaders[$colIndex];

                    if ($columnId) {
                        $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    }

                    $colIndex++;
                }
            }

            if (!empty($rowEnd) && $rowIndex >= $rowEnd) {
                break;
            }
        }

        return $arrClientsInfo;
    }

    private function _clearCache($companyId)
    {
        $arrClearCache = array(
            'import_tasks_' . $companyId,
        );
        foreach ($arrClearCache as $cacheId) {
            $this->_cache->removeItem($cacheId);
        }
    }

    // TODO PHP7 This should do streaming output
    public function importTasksAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');
        ob_end_flush();

        try {
            echo '<h1>' . date('c') . '</h1>';
            @ob_flush();

            // AMVL Migrations (AMVL)
            $companyId = 2;

            $this->_clearCache($companyId);

            $importFilePath = $this->_config['log']['path'] . '/amvl_tasks.xls';
            $arrTasks       = $this->getFieldsFromXLS($importFilePath, $companyId);

            // Check info
            $arrCompanyCases = $this->_clients->getActiveClientsList(array(), false, $companyId, true);

            $arrUsers        = $this->_users->getAssignedToUsers(false, $companyId, 0, true);
            $arrUsersGrouped = array();
            foreach ($arrUsers as $arrUserInfo) {
                $arrUsersGrouped[$arrUserInfo['option_name']] = $arrUserInfo['option_id'];
            }

            $row              = 1;
            $arrTasksToCreate = array();
            $arrRowErrors     = array();
            $arrRowWarnings   = array();
            $excelDateFormat  = 'MM/dd/yy';
            $format           = 'm/d/y';
            foreach ($arrTasks as $arrTaskInfo) {
                foreach ($arrTaskInfo as $fieldName => $fieldVal) {
                    $fieldVal = trim($fieldVal ?? '');
                    switch ($fieldName) {
                        case 'Case File #':
                            $memberId = 0;
                            foreach ($arrCompanyCases as $arrCompanyCaseInfo) {
                                if ($arrCompanyCaseInfo['fileNumber'] == $fieldVal) {
                                    $memberId = $arrCompanyCaseInfo['member_id'];
                                    break;
                                }
                            }

                            if (!$memberId) {
                                $arrRowWarnings[$row][] = sprintf(
                                    '<div>Case not found by <em>%s</em> (task row #%d).</div>',
                                    $fieldVal,
                                    $row + 1
                                );
                            } else {
                                $arrTasksToCreate[$row]['member_id'] = $memberId;
                            }
                            break;

                        case 'File Note':
                            if ($fieldVal == '') {
                                $arrRowErrors[$row][] = sprintf(
                                    '<div>Empty message <em>%s</em> (task row #%d).</div>',
                                    $fieldVal,
                                    $row + 1
                                );
                            } else {
                                $arrTasksToCreate[$row]['message'] = $fieldVal;
                            }
                            break;

                        case 'Task Activate On':
                            if (!Settings::isValidDateFormat($fieldVal, $format)) {
                                $arrRowErrors[$row][] = sprintf(
                                    '<div>Incorrect create on date <em>%s</em> (task row #%d).</div>',
                                    $fieldVal,
                                    $row + 1
                                );
                            } else {
                                $arrTasksToCreate[$row]['due_on'] = $this->_settings->reformatDate($fieldVal, $format, Settings::DATE_UNIX);
                            }
                            break;

                        case 'Task Deadline':
                            if ($fieldVal != '') {
                                if (!Settings::isValidDateFormat($fieldVal, $format)) {
                                    $arrRowErrors[$row][] = sprintf(
                                        '<div>Incorrect deadline date <em>%s</em> (task row #%d).</div>',
                                        $fieldVal,
                                        $row + 1
                                    );
                                } else {
                                    $arrTasksToCreate[$row]['deadline'] = $this->_settings->reformatDate($fieldVal, $format, Settings::DATE_UNIX);
                                }
                            }
                            break;

                        case 'Assign Task to':
                            if ($fieldVal != '') {
                                if (!array_key_exists($fieldVal, $arrUsersGrouped)) {
                                    $arrRowErrors[$row][] = sprintf(
                                        '<div>Incorrect user (assign to) <em>%s</em> (task row #%d).</div>',
                                        $fieldVal,
                                        $row + 1
                                    );
                                } else {
                                    $arrTasksToCreate[$row]['assign_to'] = $arrUsersGrouped[$fieldVal];
                                }
                            }
                            break;

                        case 'CC Task to':
                            if ($fieldVal != '') {
                                if (!array_key_exists($fieldVal, $arrUsersGrouped)) {
                                    $arrRowErrors[$row][] = sprintf(
                                        '<div>Incorrect user (cc to) <em>%s</em> (task row #%d).</div>',
                                        $fieldVal,
                                        $row + 1
                                    );
                                } else {
                                    $arrTasksToCreate[$row]['assign_cc'] = $arrUsersGrouped[$fieldVal];
                                }
                            }
                            break;

                        default:
                            break;
                    }
                }

                if (!isset($arrTasksToCreate[$row]['assign_to']) && isset($arrTasksToCreate[$row]['assign_cc'])) {
                    $arrTasksToCreate[$row]['assign_to'] = $arrTasksToCreate[$row]['assign_cc'];
                    unset($arrTasksToCreate[$row]['assign_cc']);
                }

                if (!isset($arrTasksToCreate[$row]['assign_to'])) {
                    $arrRowErrors[$row][] = sprintf(
                        '<div>Must be set "assign to" (task row #%d).</div>',
                        $row + 1
                    );
                }

                $row++;
            }

            $authorName = 'Officio Transfer';
            $authorId   = 0;
            if (!array_key_exists($authorName, $arrUsersGrouped)) {
                $arrRowErrors[0][] = sprintf(
                    '<div>Author <em>%s</em> was not found.</div>',
                    $authorName
                );
            } else {
                $authorId = $arrUsersGrouped[$authorName];
            }


            if (count($arrRowWarnings)) {
                echo '<div style="color: orange; padding: 10px 0;">';
                echo 'Warnings:<br/>';
                foreach ($arrRowWarnings as $row => $arrThisRowWarning) {
                    echo implode('<br>', $arrThisRowWarning);
                }
                echo '</div>';
            }

            if (count($arrRowErrors)) {
                echo '<div style="color: red; padding: 10px 0;">';
                echo 'Errors:<br/>';
                foreach ($arrRowErrors as $row => $arrThisRowErrors) {
                    echo implode('<br>', $arrThisRowErrors);
                }
                echo '</div>';
            }
            @ob_flush();

            if (!count($arrRowErrors)) {
                $countSuccess = 0;
                $countSkipped = 0;
                $countFailed  = 0;

                echo '<div style="padding-top: 10px;">Creation results:</div>';

                $subject = 'Officio transfer';
                foreach ($arrTasksToCreate as $row => $arrTaskInfo) {
                    // Skip task creation if case wasn't found
                    if (!isset($arrTaskInfo['member_id'])) {
                        $countSkipped++;
                        continue;
                    }

                    $arrNewTaskInfo = array(
                        'member_id'      => $arrTaskInfo['member_id'],
                        'subject'        => $subject,
                        'message'        => $arrTaskInfo['message'],
                        'author_id'      => $authorId,
                        'deadline'       => $arrTaskInfo['deadline'] ?? '',
                        'type'           => 'S',
                        'due_on'         => $arrTaskInfo['due_on'],
                        'number'         => 0,
                        'days'           => '',
                        'ba'             => '',
                        'prof'           => '',
                        'notify_client'  => 'N',
                        'is_due'         => 'N',
                        'auto_task_type' => 0,

                        'to' => array($arrTaskInfo['assign_to']),
                        'cc' => isset($arrTaskInfo['assign_cc']) ? array($arrTaskInfo['assign_cc']) : array(),
                    );

                    $createdTaskId = $this->_tasks->addTask($arrNewTaskInfo, false, false, false);
                    if (empty($createdTaskId)) {
                        echo "<div style='color: red;'>Task creation failed for row# $row</div>";
                        $countFailed++;
                    } else {
                        echo "<div style='color: green;'>Created task for row# $row</div>";
                        $countSuccess++;
                    }
                    @ob_flush();
                }

                $this->_tasks->triggerTaskIsDue();

                echo sprintf(
                    '<div style="padding: 15px 0">Done (' .
                    '<span style="color: green">%d created</span>, ' .
                    '<span style="color: orange">%d skipped</span>, ' .
                    '<span style="color: red">%d failed</span>' .
                    ').</div>',
                    $countSuccess,
                    $countSkipped,
                    $countFailed
                );
            }
        } catch (Exception $e) {
            $view->setVariable('content', 'Fatal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    // TODO PHP7 This should do streaming output
    public function compareFieldsAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            echo '<h1>' . date('c') . '</h1>';

            $page = $this->findParam('page', -1);
            if (!is_numeric($page) || $page < 0) {
                return $view->setVariable('content', 'No!');
            }

            $processAtOnce = 10000;
            $rowStart      = $page * $processAtOnce;

            $select = (new Select())
                ->from(['c' => 'company'])
                ->columns(['company_id'])
                ->where([(new Where())->notEqualTo('company_id', 0)])
                ->order('company_id ASC');

            $companyIds     = $this->_db2->fetchCol($select);
            $companiesCount = $this->_db2->fetchResultsCount($select);

            if (!is_array($companyIds) || !count($companyIds)) {
                exit('NO COMPANIES FOUND');
            }

            $select = (new Select())
                ->from('client_form_fields')
                ->where(['company_id' => 0]);

            $arrDefaultFields = $this->_db2->fetchAll($select);

            $select = (new Select())
                ->from('client_form_fields')
                ->where(['company_id' => $companyIds])
                ->order('company_id ASC');

            $arrCompaniesFields = $this->_db2->fetchAll($select);

            $arrCompaniesFieldsFormatted = array();
            foreach ($arrCompaniesFields as $arrCompanyFieldInfo) {
                $arrCompaniesFieldsFormatted[$arrCompanyFieldInfo['company_id']][] = $arrCompanyFieldInfo;
            }

            $select = (new Select())
                ->from(['g' => 'client_form_groups'])
                ->where(['company_id' => 0]);

            $arrDefaultGroups = $this->_db2->fetchAll($select);

            $select = (new Select())
                ->from('client_form_groups')
                ->where(['company_id' => $companyIds])
                ->order('company_id ASC');

            $arrCompaniesGroups = $this->_db2->fetchAll($select);

            $arrCompaniesGroupsFormatted = array();
            foreach ($arrCompaniesGroups as $arrCompanyGroupInfo) {
                $arrCompaniesGroupsFormatted[$arrCompanyGroupInfo['company_id']][] = $arrCompanyGroupInfo;
            }

            $arrResultToShow = array();
            foreach ($companyIds as $companyId) {
                if (!isset($arrCompaniesGroupsFormatted[$companyId])) {
                    $arrResultToShow[$companyId][] = "<div style='color: red'>GROUPS NOT SET</div>";
                } else {
                    foreach ($arrDefaultGroups as $arrDefaultGroupInfo) {
                        $booExists = false;
                        foreach ($arrCompaniesGroupsFormatted[$companyId] as $arrThisGroupInfo) {
                            if ($arrDefaultGroupInfo['title'] == $arrThisGroupInfo['title']) {
                                $booExists = true;
                            }
                        }

                        if (!$booExists) {
                            $arrResultToShow[$companyId][] = "<div style='color: red'>Default group does not exists: $arrDefaultGroupInfo[title]</div>";
                        }
                    }


                    foreach ($arrCompaniesGroupsFormatted[$companyId] as $arrThisGroupInfo) {
                        $booExists = false;
                        foreach ($arrDefaultGroups as $arrDefaultGroupInfo) {
                            if ($arrDefaultGroupInfo['title'] == $arrThisGroupInfo['title']) {
                                $booExists = true;
                            }
                        }

                        if (!$booExists) {
                            $arrResultToShow[$companyId][] = "<div style='color: green'>New group: $arrThisGroupInfo[title]</div>";
                        }
                    }
                }

                if (!isset($arrCompaniesFieldsFormatted[$companyId])) {
                    $arrResultToShow[$companyId][] = "<div style='color: red'>FIELDS NOT SET</div>";
                } else {
                    foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
                        $booExists = false;
                        foreach ($arrCompaniesFieldsFormatted[$companyId] as $arrThisFieldInfo) {
                            if ($arrDefaultFieldInfo['company_field_id'] == $arrThisFieldInfo['company_field_id']) {
                                $booExists = true;
                            }
                        }

                        if (!$booExists) {
                            $arrResultToShow[$companyId][] = "<div style='color: red'>Default field does not exists: $arrDefaultFieldInfo[company_field_id]</div>";
                        }
                    }


                    foreach ($arrCompaniesFieldsFormatted[$companyId] as $arrThisFieldInfo) {
                        $booExists = false;
                        foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
                            if ($arrDefaultFieldInfo['company_field_id'] == $arrThisFieldInfo['company_field_id']) {
                                $booExists = true;
                            }
                        }

                        if (!$booExists) {
                            $arrResultToShow[$companyId][] = "<div style='color: orange'>New field: $arrThisFieldInfo[company_field_id]</div>";
                        }
                    }
                }
            }

            foreach ($arrResultToShow as $companyId => $arrToShow) {
                echo "<h1>Company id: #$companyId</h1>";
                echo implode('', $arrToShow);
            }

            echo sprintf(
                "<h1>Changed %d companies from %d</h1>",
                count($arrResultToShow),
                count($companyIds)
            );


            if (($rowStart + $processAtOnce < $companiesCount)) {
                $strResult = sprintf('<a href="%s/system/index/compare-fields?page=%d">Next Page &gt;&gt;</a>', $this->layout()->getVariable('baseUrl'), $page + 1);
            } else {
                $strResult = 'Done.';
            }

            $pagesCount = round($companiesCount / $processAtOnce);
            echo sprintf(
                '<div style="padding-top: 5px; margin-top: 10px; border-top: 1px solid #000;">Page %d of %d. <span style="color: green;">%s</span></div>',
                $page + 1,
                $pagesCount <= 0 ? 1 : $pagesCount + 1,
                $strResult
            );
        } catch (Exception $e) {
            $view->setVariable('content', 'Fatal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    // Can be deleted after run (updateAuFilesAction)
    public function _getCompanyGroupedFolders($currentDir, $localDir, $remoteDir)
    {
        $arrResult = array();

        if (is_dir($currentDir)) {
            $localDirFixed = $localDir;
            if (DIRECTORY_SEPARATOR == '\\') {
                $currentDir    = str_replace('\\', '/', $currentDir ?? '');
                $localDirFixed = str_replace('\\', '/', $localDirFixed ?? '');
            }

            if ($currentDir == $localDirFixed . '/' . '.client_files_other') {
                return array();
            }

            $dirIterator = new RecursiveDirectoryIterator($currentDir, FilesystemIterator::SKIP_DOTS);
            $iterator    = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST, FilesystemIterator::SKIP_DOTS);
            $iterator->setMaxDepth(0);

            try {
                foreach ($iterator as $path => $file) {
                    if ($file->isDir()) {
                        $remotePath = $remoteDir . substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $localDir)));
                        if (!preg_match('/^.*\.client_files_other.*$/', $remotePath)) {
                            $arrResult['folders'][] = $remotePath;
                        }

                        $arrInnerResult = $this->_getCompanyGroupedFolders($path, $localDir, $remoteDir);
                        if (count($arrInnerResult)) {
                            $arrResult = array_merge_recursive($arrResult, $arrInnerResult);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'files');
            }
        }

        return $arrResult;
    }

    // TODO PHP7 This should do streaming output
    // Can be deleted after run
    public function updateAuFilesAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            echo '<h1>' . date('c') . '</h1>';

            $companyId = (int)$this->findParam('company_id', -1);
            if ($companyId < 1) {
                return $view->setVariable('content', 'Incorrect company id');
            }

            echo "<h1>Company id: #$companyId</h1>";

            $companyLocalPath  = $this->_files->getCompanyPath($companyId);
            $companyRemotePath = $this->_files->getCompanyPath($companyId, false);

            $this->_files->getCloud()->createFolder($companyRemotePath);
            $arrResult = $this->_getCompanyGroupedFolders($companyLocalPath, $companyLocalPath, $companyRemotePath);

            if (isset($arrResult['does_not_exists'])) {
                echo "<div style='color: red'>DOES NOT EXISTS:<br/>" . implode('<br/>', $arrResult['does_not_exists']) . "</div>";
            }

            if (isset($arrResult['folders'])) {
                $booFoldersCreated = $this->_files->getCloud()->createFolders($arrResult['folders']);

                if ($booFoldersCreated) {
                    echo "<div style='color: green'>Created: " . count($arrResult['folders']) . " folders</div>";
                } else {
                    echo "<div style='color: red'>NOT Created: " . count($arrResult['folders']) . " folders</div>";
                }
            }

            if (!count($arrResult)) {
                echo "<div style='color: red'>THERE ARE NO FILES??</div>";
            }

            echo '<h1>' . date('c') . '</h1>';
        } catch (Exception $e) {
            $view->setVariable('content', 'Fatal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    // Can be deleted when GeorgeS will run this
    public function setAllCompaniesFieldsAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $output = '';

        try {
            $internalContactId = $this->_clients->getMemberTypeIdByName('internal_contact');

            $select = (new Select())
                ->from('company')
                ->columns(['company_id'])
                ->order('company_id ASC');

            $arrCompanyIds = $this->_db2->fetchCol($select);

            //INSERT BLOCKS
            $blocksCount = 0;
            foreach ($arrCompanyIds as $companyId) {
                $this->_db2->insert(
                    'applicant_form_blocks',
                    [
                        'member_type_id'    => $internalContactId,
                        'company_id'        => $companyId,
                        'applicant_type_id' => null,
                        'order'             => 0,
                    ]
                );
                $blocksCount++;
            }

            $output .= sprintf('<div>%d blocks were created.</div>', $blocksCount);

            $select = (new Select())
                ->from('applicant_form_blocks')
                ->where(['applicant_form_blocks.member_type_id' => $internalContactId]);
            $insertedBlocks = $this->_db2->fetchAll($select);

            $groupsCount = 0;
            $fieldsCount = 0;
            foreach ($insertedBlocks as $insertedBlock) {
                // INSERT GROUPS
                $groupId = $this->_db2->insert(
                    'applicant_form_groups',
                    [
                        'applicant_block_id' => $insertedBlock['applicant_block_id'],
                        'company_id'         => $insertedBlock['company_id'],
                        'title'              => 'Main Group',
                        'cols_count'         => 3,
                        'order'              => 0,
                    ]
                );
                $groupsCount++;

                // Place fields to this group
                $select = (new Select())
                    ->from('applicant_form_fields')
                    ->where([
                        'member_type_id' => (int)$internalContactId,
                        'company_id'     => (int)$insertedBlock['company_id']
                    ]);

                $currentGroupFields = $this->_db2->fetchAll($select);

                $order = 0;
                foreach ($currentGroupFields as $currentGroupField) {
                    $this->_db2->insert(
                        'applicant_form_order',
                        [
                            'applicant_group_id' => $groupId,
                            'applicant_field_id' => $currentGroupField['applicant_field_id'],
                            'use_full_row'       => 'N',
                            'field_order'        => $order++
                        ]
                    );
                    $fieldsCount++;
                }
            }

            $output .= sprintf('<div>%d groups were created.</div>', $groupsCount);
            $output .= sprintf('<div>%d fields were placed.</div>', $fieldsCount);

            $view->setVariable('content', $output);
        } catch (Exception $e) {
            $view->setVariable('content', 'Fatal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    /**
     * Relates to 20170413153811_prospects_specific_points.php migration
     */
    public function updateProspectsPointsAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $strError   = '';
        $limit      = 10000;
        $totalCount = 0;
        $arrAll     = array();

        $start     = microtime(true);
        $strResult = 'Started on: ' . date('c') . '<br>';

        try {
            $select = (new Select())
                ->from('company_prospects')
                ->where(
                    [
                        (new Where())->isNotNull('assessment'),
                        (new Where())->isNull('points_skilled_worker'),
                        (new Where())->isNull('points_express_entry')
                    ]
                )
                ->limit($limit);

            $arrAll     = $this->_db2->fetchAll($select);
            $totalCount = $this->_db2->fetchResultsCount($select);

            foreach ($arrAll as $arrProspectInfo) {
                $assessment = unserialize($arrProspectInfo['assessment']);
                $arrUpdate  = array();

                if (isset($assessment['skilled_worker']['global']['total'])) {
                    $arrUpdate['points_skilled_worker'] = $assessment['skilled_worker']['global']['total'];
                }

                if (isset($assessment['express_entry']['global']['total'])) {
                    $arrUpdate['points_express_entry'] = $assessment['express_entry']['global']['total'];
                }

                if (!empty($arrUpdate)) {
                    $this->_db2->update('company_prospects', $arrUpdate, ['prospect_id' => (int)$arrProspectInfo['prospect_id']]);
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $strResult .= 'Finished on: ' . date('c') . '<br>';
        $strResult .= 'Worked: ' . round(microtime(true) - $start, 2) . ' sec<br><br>';
        if (empty($strError)) {
            $strResult .= 'Updated: ' . count($arrAll) . '<br>';

            $left      = $totalCount - $limit;
            $left      = $left > 0 ? $left : 0;
            $strResult .= 'Left: ' . $left . '<br><br>';
        } else {
            $strResult .= $strError;
        }

        return $view->setVariables(
            [
                'content' => $strResult
            ]
        );
    }

    // Can be deleted when GeorgeS will run this
    public function createAdditionalDocumentsFoldersAction()
    {
        $view = new JsonModel();

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $strError = '';

        try {
            $arrCompanies = $this->_company->getAllCompanies();

            foreach ($arrCompanies as $company) {
                $companyId = $company[0];
                $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);

                $select = (new Select())
                    ->from(['m' => 'members'])
                    ->columns(['member_id'])
                    ->join(array('c' => 'clients'), 'c.member_id = m.member_id', [], Select::JOIN_LEFT_OUTER)
                    ->where([
                        'm.userType'   => $this->_members::getMemberType('case'),
                        'm.company_id' => (int)$companyId
                    ]);

                $arrMemberIds = $this->_db2->fetchCol($select);

                foreach ($arrMemberIds as $memberId) {
                    $path          = $this->_files->getMemberFolder($companyId, $memberId, true, $booLocal);
                    $pathToFolder  = $path . '/' . 'Additional Documents';
                    $arrPathCreate = array($path, $pathToFolder);

                    foreach ($arrPathCreate as $strPath) {
                        if ($booLocal) {
                            $this->_files->createFTPDirectory($strPath);
                        } else {
                            $this->_files->createCloudDirectory($strPath);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Additional Documents folders were created successfully.') : $this->_tr->translate('Additional Documents folders were not created.')
        );

        return $view->setVariables($arrResult);
    }

    // Can be deleted when GeorgeS will run this
    public function createDdReportsFoldersAction()
    {
        $view = new ViewModel();

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $strError                    = '';
        $arrClientsWithMissingFolder = array();

        try {
            $arrCompanies = $this->_company->getAllCompanies();

            foreach ($arrCompanies as $company) {
                $companyId = $company[0];

                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

                if (!$booLocal) {
                    $arrClients     = $this->_clients->getAllClientsList($companyId);
                    $arrParentsList = $this->_clients->getCasesListWithParents($arrClients);

                    foreach ($arrClients as $key => $arrClientInfo) {
                        $name = '';
                        foreach ($arrParentsList as $arrParentInfo) {
                            if ($arrClientInfo['member_id'] == $arrParentInfo['clientId']) {
                                $name = $arrParentInfo['clientFullName'];
                                break;
                            }
                        }
                        $arrClientInfo['client_full_name'] = $name;
                        $arrClients[$key]                  = $arrClientInfo;
                    }

                    foreach ($arrClients as $arrClientInfo) {
                        $memberId = $arrClientInfo['member_id'];
                        $path     = $this->_files->getMemberFolder($companyId, $memberId, true, $booLocal);
                        $arrItems = $this->_files->getCloud()->getList($path);

                        if (count($arrItems)) {
                            foreach ($arrItems as $object) {
                                $name = $object['Key'];
                                if ($this->_files->getCloud()->isFolder($name)) {
                                    $folderName = $this->_files->getCloud()->getFolderNameByPath($name);
                                    if ($folderName == 'DD Reports') {
                                        continue 2;
                                    }
                                }
                            }
                        }

                        $arrClientsWithMissingFolder[] = array(
                            'member_id'             => $memberId,
                            'name_with_file_number' => $arrClientInfo['client_full_name']
                        );

                        $pathToFolder  = $path . '/' . 'DD Reports';
                        $arrPathCreate = array($path, $pathToFolder);

                        foreach ($arrPathCreate as $strPath) {
                            //$this->_files->createCloudDirectory($strPath);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
        }

        $arrResult = array(
            'success'                     => empty($strError),
            'clients_with_missing_folder' => $arrClientsWithMissingFolder
        );

        return $view->setVariables($arrResult);
    }
}
