<?php

namespace Officio;

use Exception;
use Files\Service\Files;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Encryption;
use Officio\Service\Users;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class MembersImmiAccount extends BaseService
{

    /** @var Files */
    protected $_files;

    /** @var Encryption */
    protected $_encryption;

    /** @var Users */
    protected $_users;

    public function initAdditionalServices(array $services)
    {
        $this->_files      = $services[Files::class];
        $this->_encryption = $services[Encryption::class];
        $this->_users      = $services[Users::class];
    }


    /**
     * Send request to ImmiAccount web site to check credentials correctness
     *
     * @param $login
     * @param $password
     * @return array with error and other details
     */
    public function checkImmiAccountCredentials($login, $password)
    {
        $wc_s = $wc_t = $strError = $cookieFile = '';
        try {
            $cookieFile = $this->_files->createTempFile('immi_cookie');

            list($strError, $r) = $this->sendRequestToImmiAccount('https://online.immi.gov.au/lusc/login', $cookieFile);

            if (empty($strError)) {
                preg_match('!<ui:param name="wc_s" value="([^"]+)" />!', $r, $res);
                $wc_s = urlencode($res[1]);

                preg_match('!<ui:param name="wc_t" value="([^"]+)" />!', $r, $res);
                $wc_t = urlencode($res[1]);

                if (!strpos($r, '<ui:panel id="_2b0a0a0" buttonId="_2b0a0a0e0" title="Login">')) {
                    $strError = $this->_tr->translate('Incorrect response from ImmiAccount site (Login page)');
                }
            }

            if (empty($strError)) {
                if (!strpos($r, '<ui:label id="_2b0a0a0d0a" for="_2b0a0a0d0b0">Username</ui:label>')) {
                    $strError = $this->_tr->translate('Incorrect response from ImmiAccount site (Login page)');
                }
            }

            if (empty($strError)) {
                $time1 = rand(0, 1);
                $time2 = rand(3500, 4500);

                $post = "wc_s=$wc_s&wc_t=$wc_t&_2b0a0a0d0b0=" . urlencode($login) . "&_2b0a0a0d1b0=" . urlencode($password) . "&_2b0a0a0e0=x&cprofile_timings=interface_controls%7Btime%3A$time1%2Cresult%3A1%7D%3Bhtml_start_load%7Btime%3A$time2%2Cresult%3A1%7D%3B";

                list($strError, $r) = $this->sendRequestToImmiAccount('https://online.immi.gov.au/lusc/login', $cookieFile, 'https://online.immi.gov.au/lusc/login', $post);
            }

            if (empty($strError)) {
                preg_match('!<ui:param name="wc_s" value="([^"]+)" />!', $r, $res);
                $wc_s = urlencode($res[1]);

                preg_match('!<ui:param name="wc_t" value="([^"]+)" />!', $r, $res);
                $wc_t = urlencode($res[1]);

                if (!strpos($r, '<ui:button id="_2b0a0b0e0" validates="_2b0a0b0">Continue</ui:button>')) {
                    $strError = $this->_tr->translate('Incorrect credentials');
                }
            }

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'error'            => $strError,
            'wc_s'             => $wc_s,
            'wc_t'             => $wc_t,
            'cookie_file_path' => $cookieFile
        );
    }
    
    /**
     * Send request to ImmiAccount web site to load info about specific client
     *
     * @param $memberId
     * @param $arrFieldsData
     * @return array
     */
    public function submitInfo($memberId, $arrFieldsData)
    {
        $strError           = $pdfTmpPath = '';
        $arrTableFieldsInfo = array();
        try {

            $checkLoginPassResult = array();

            $r = '';
            $login = '';
            $password = '';

            if (empty($strError)) {
                $userInfo = $this->_users->getUserInfo($memberId);

                $login    = $userInfo['vevo_login'];
                $password = $this->_encryption->decode($userInfo['vevo_password']);
            }

            if (empty($strError) && empty($login)) {
                $strError = $this->_tr->translate('Incorrect login');
            }

            if (empty($strError) && empty($password)) {
                $strError = $this->_tr->translate('Incorrect password');
            }

            if (empty($strError)) {
                $this->outputResult(array('status' => 'Logging in as ' . $login . '...'));
                $checkLoginPassResult = $this->checkImmiAccountCredentials($login, $password);
                $strError             = $checkLoginPassResult['error'];
            }

            // Click on Continue
            if (empty($strError)) {
                $wc_s       = $checkLoginPassResult['wc_s'];
                $wc_t       = $checkLoginPassResult['wc_t'];
                $cookieFile = $checkLoginPassResult['cookie_file_path'];

                $time1 = rand(2000, 4000);
                $time2 = $time1 - rand(1, 2);
                $time3 = rand(4000, 5000);
                $time4 = $time3 + 2;
                $post  = "wc_s=$wc_s&wc_t=$wc_t&_2b0a0b0e0=x&cprofile_timings=interface_controls%7Btime%3A0%2Cresult%3A1%7D%3Bhtml_start_load%7Btime%3A$time1%2Cresult%3A1%7D%3Bunload_load%7Btime%3A$time2%2Cresult%3A1%7D%3Bsubmit_load%7Btime%3A$time3%2Cresult%3A1%7D%3Blast_click_load_Login%7Btime%3A$time4%2Cresult%3A1%7D%3B";

                list($strError, $r) = $this->sendRequestToImmiAccount('https://online.immi.gov.au/lusc/login', $cookieFile, 'https://online.immi.gov.au/lusc/login', $post);
            }

            // Click on "New Application" button
            if (empty($strError)) {
//                $post = "wc_s=$wc_s&wc_t=$wc_t&btn_newapp=x&i_instsrchfld=&mainpanel_parent_1b3a-h=x&cprofile_correlation_id=4e6b2d59-b9ce-4790-be36-34d60f4a0072&_0a0c0a.selected=x&_0a0c-h=x&mainpanel_parent_1b3a-row-r0-_0g0a-h=x&mainpanel_parent_1b3a-row-r1-_0g0a-h=x&cprofile_timings=interface_controls%7Btime%3A2%2Cresult%3A1%7D%3Bhtml_start_load%7Btime%3A1564%2Cresult%3A1%7D%3Bunload_load%7Btime%3A1543%2Cresult%3A1%7D%3Bsubmit_load%7Btime%3A2868%2Cresult%3A1%7D%3Blast_click_load_Continue%7Btime%3A2876%2Cresult%3A1%7D%3B";
//                list($strError, $r) = $this->sendRequestToImmiAccount('https://online.immi.gov.au/ola/app', $cookieFile, 'https://online.immi.gov.au/lusc/login', $post);
//
//                if (preg_match('%<ui:messageBox id=".*" type="error">\s{0,}<ui:message>(.*)</ui:message>\s{0,}</ui:messageBox>%', $r, $regs)) {
//                    $strError = $regs[1];
//                }
            }


            // Select a category
//            if (empty($strError)) {
//                $post = "wc_s=$wc_s&wc_t=$wc_t&mainpanel_parent_1b1a-row-r20-_0b0=x&mainpanel_parent_1b1a-h=x&cprofile_correlation_id=c7e6f177-905d-41d4-9506-6e1efde52342&_0a0c0a.selected=x&_0a0c-h=x&cprofile_timings=interface_controls%7Btime%3A2%2Cresult%3A1%7D%3Bhtml_start_load%7Btime%3A1208%2Cresult%3A1%7D%3Bunload_load%7Btime%3A1187%2Cresult%3A1%7D%3Bsubmit_load%7Btime%3A3218%2Cresult%3A1%7D%3Blast_click_load_New+application%7Btime%3A3228%2Cresult%3A1%7D%3B&mainpanel_parent_1b1a.expanded=0&mainpanel_parent_1b1a.sort=0";
//                list($strError, $r) = $this->sendRequestToImmiAccount('https://online.immi.gov.au/ola/app', $cookieFile, 'https://online.immi.gov.au/ola/app', $post);
//
//                if (preg_match('%<ui:messageBox id=".*" type="error">\s{0,}<ui:message>(.*)</ui:message>\s{0,}</ui:messageBox>%', $r, $regs)) {
//                    $strError = $regs[1];
//                }
//            }


        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $arrTableFieldsInfo, $pdfTmpPath);
    }

    /**
     * Internal method to send requests to Immi web site
     *
     * @param $url
     * @param $cookieFile
     * @param string $ref
     * @param bool $post
     * @return array
     */
    public function sendRequestToImmiAccount($url, $cookieFile, $ref = '', $post = false)
    {
        $strError = '';
        $headers  = array(
            "User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: en-us,en;q=0.5",
            "Accept-Encoding: gzip, deflate",
            "Connection: keep-alive"
        );

        $cr = curl_init($url);

        curl_setopt($cr, CURLOPT_TIMEOUT, 30);
        curl_setopt($cr, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($cr, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($cr, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($cr, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($cr, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        if ($ref != '') {
            curl_setopt($cr, CURLOPT_REFERER, $ref);
        }
        curl_setopt($cr, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($cr, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cr, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($cr, CURLOPT_FOLLOWLOCATION, 1);
        if ($post !== false) {
            curl_setopt($cr, CURLOPT_POSTFIELDS, $post);
        }
        $r = curl_exec($cr);
        if (curl_error($cr)) {
            $z           = curl_error($cr);
            $errorNumber = curl_errno($cr);
            curl_close($cr);
            if ($errorNumber == 28) {
                $strError = $this->_tr->translate('Operation timeout. The specified time-out period was reached according to the conditions.');
            } else {
                $strError = $this->_tr->translate('Internal error');
            }
            $this->_log->debugErrorToFile('', 'Curl error: ' . $z . ' Url: ' . $url, 'immi_account');
        } else {
            curl_close($cr);

        }

        return array($strError, $r);
    }
    
    /**
     * Output result, so it can be parsed and showed in the GUI
     * @param $arrResult
     */
    public function outputResult($arrResult)
    {
        echo Json::encode($arrResult);
    }
}