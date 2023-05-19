<?php

namespace Clients\Service;

use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\NLP\CountriesSimilarity;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Tickets;
use Officio\Service\Users;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class MembersVevo extends BaseService
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Country */
    protected $_country;

    /** @var Tickets */
    protected $_tickets;

    /** @var Files */
    protected $_files;

    /** @var Users */
    protected $_users;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_clients    = $services[Clients::class];
        $this->_company    = $services[Company::class];
        $this->_files      = $services[Files::class];
        $this->_tickets    = $services[Tickets::class];
        $this->_country    = $services[Country::class];
        $this->_users      = $services[Users::class];
        $this->_encryption = $services[Encryption::class];
    }

    /**
     * Load members vevo mapping list for edit user functionality
     *
     * @param int $memberId
     * @param bool $booIdsOnly
     * @return array result
     */
    public function getMembersToVevoMappingList($memberId = 0, $booIdsOnly = true)
    {
        $arrResult = array();
        $memberId  = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;

        $select = (new Select())
            ->from(array('m' => 'members_vevo_mapping'))
            ->columns(['to_member_id'])
            ->where(['m.from_member_id' => (int)$memberId]);

        $arrToMemberIds = $this->_db2->fetchCol($select);

        if ($booIdsOnly) {
            $arrResult = $arrToMemberIds;
        } else {
            if (is_array($arrToMemberIds) && count($arrToMemberIds)) {
                foreach ($arrToMemberIds as $toMemberId) {
                    $toMemberInfo = $this->_clients->getMemberInfo($toMemberId);
                    $arrResult[] = array(
                        'option_id' => $toMemberId,
                        'option_name' => $toMemberInfo['full_name']
                    );
                }
            }
        }

        return $arrResult;
    }

    /**
     * Load members vevo mapping list for Vevo feature in the Clients
     *
     * @param int $memberId
     * @return array result
     */
    public function getMembersFromVevoMappingList($memberId = 0)
    {
        $arrResult = array();

        $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
        $userInfo = $this->_users->getUserInfo($memberId);

        if (!empty($userInfo['vevo_login']) && !empty($userInfo['vevo_password'])) {
            $memberInfo = $this->_clients->getMemberInfo($memberId);

            $arrResult[] = array(
                'option_id' => $memberId,
                'option_name' => $memberInfo['full_name']
            );
        }

        $select = (new Select())
            ->from(array('m' => 'members_vevo_mapping'))
            ->columns(['from_member_id'])
            ->where(['m.to_member_id' => (int)$memberId]);

        $arrFromMemberIds = $this->_db2->fetchCol($select);

        if (is_array($arrFromMemberIds) && count($arrFromMemberIds)) {
            foreach ($arrFromMemberIds as $fromMemberId) {
                $fromMemberInfo = $this->_clients->getMemberInfo($fromMemberId);
                $arrResult[] = array(
                    'option_id' => $fromMemberId,
                    'option_name' => $fromMemberInfo['full_name']
                );
            }
        }

        return $arrResult;
    }

    /**
     * Load vevo countries suggestions list for Vevo feature in the Clients
     *
     * @param $countryOfPassportFieldValue
     * @return array result
     */
    public function getVevoCountiesSuggestionsList($countryOfPassportFieldValue)
    {
        $arrResult = array();

        $oCountriesSimilarity = new CountriesSimilarity($this->_db2);
        $arrSuggestions       = $oCountriesSimilarity->suggest($countryOfPassportFieldValue);

        if (!empty($arrSuggestions)) {
            foreach ($arrSuggestions as $suggestion) {
                $arrResult[] = array(
                    'option_id' => $suggestion,
                    'option_name' => $suggestion
                );
            }
        } else {
            $arrAllVevoCountries = $this->_country->getCountriesList('vevo');
            foreach ($arrAllVevoCountries as $arrCountryInfo) {
                $arrResult[] = array(
                    'option_id' => $arrCountryInfo['countries_name'],
                    'option_name' => $arrCountryInfo['countries_name']
                );
            }
        }

        return $arrResult;
    }

    /**
     * Check if member has access to Vevo
     *
     * @param int $memberId
     * @return bool has access
     */
    public function hasMemberAccessToVevo($memberId = 0)
    {
        $booHasAccess = false;
        if ($this->_config['site_version']['version'] == 'australia') {
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $userInfo = $this->_users->getUserInfo($memberId);

            if (!empty($userInfo['vevo_login']) && !empty($userInfo['vevo_password'])) {
                $booHasAccess = true;
            } else {
                $select = (new Select())
                    ->from('members_vevo_mapping')
                    ->columns(['from_member_id'])
                    ->where(['to_member_id' => (int)$memberId]);

                $result = $this->_db2->fetchCol($select);

                if (is_array($result) && !empty($result)) {
                    $booHasAccess = true;
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Update Vevo login and password for specific user
     *
     * @param $login
     * @param $password
     * @param $memberId
     * @return string error, empty on success
     */
    public function changeMemberVevoCredentials($login, $password, $memberId)
    {
        $strError = '';
        try {
            $arrUserInfo = array();
            if (empty($login)) {
                //Delete login and password from DB for this account
                $arrUserInfo['vevo_login'] = '';
                $arrUserInfo['vevo_password'] = '';
            } else {
                $vevoAccountFromDb = $this->_users->getUserInfo($memberId);
                if (!empty($vevoAccountFromDb['vevo_login'])) {
                    //Saved in DB
                    $arrUserInfo['vevo_login'] = $login;
                    if (!empty($password)) {
                        $arrUserInfo['vevo_password'] = $this->_encryption->encode($password);
                    }
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights to change credentials.');
                }
            }

            if (empty($strError)) {
                if ($this->_auth->isCurrentUserSuperadmin()) {
                    $companyId      = $this->_company->getMemberCompanyId($memberId);
                    $arrChangesData = $this->_company->createArrChangesData($arrUserInfo, 'users', $companyId, $memberId);
                    if (empty($login)) {
                        $arrChangesData = array_merge($arrChangesData, $this->_company->createArrChangesData(array(), 'members_vevo_mapping', $companyId, $memberId));
                    }
                    $this->_tickets->addTicketsWithCompanyChanges($arrChangesData, $companyId);
                }

                if (empty($login)) {
                    $this->_db2->delete('members_vevo_mapping', ['from_member_id' => (int)$memberId]);
                }

                $this->_db2->update('users', $arrUserInfo, ['member_id' => $memberId]);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }


    /**
     * Check if VEVO login and password are correct
     *
     * @param $login
     * @param $password
     * @param $memberId
     * @return mixed|string
     */
    public function checkMemberVevoCredentials($login, $password, $memberId)
    {
        $strError = '';
        try {
            $memberInfo = $this->_users->getUserInfo($memberId);

            if (empty($memberId)) {
                $memberInfo['vevo_login'] = $memberInfo['vevo_password'] = '';
            }

            //Not Saved in DB
            if (empty($memberInfo['vevo_login']) && empty($memberInfo['vevo_password'])) {
                if (empty($login) || empty($password)) {
                    $strError = 'Insert ImmiAccount Login & Password.';
                }
            } else {
                //Saved in DB
                if (!empty($login) && empty($password)) {
                    if (!empty($memberInfo['vevo_password'])) {
                        $password = $this->_encryption->decode($memberInfo['vevo_password']);
                    } else {
                        $strError = 'Wrong authentication';
                    }
                }

                if (empty($login) && empty($password)) {
                    $strError = 'Insert ImmiAccount Login & Password.';
                }
            }

            if (empty($strError)) {
                $checkLoginPassResult = $this->checkVevoCredentials($login, $password);
                $strError = $checkLoginPassResult['error'];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Send request to VEVO web site to check credentials correctness
     *
     * @param $login
     * @param $password
     * @return array with error and other details
     */
    public function checkVevoCredentials($login, $password)
    {
        $wc_s = $wc_t = $strError = $cookieFile = '';
        try {
            $cookieFile = $this->_files->createTempFile('vevo_cookie');

            list($strError, $r) = $this->sendRequestToVevo('https://online.immi.gov.au/lusc/login', $cookieFile);

            if (empty($strError)) {
                preg_match('/<input [^<>]*name="wc_s" [^<>]*value="([^"]+)"[^<>]*>/', $r, $res);
                $wc_s = urlencode($res[1]);

                preg_match('/<input [^<>]*name="wc_t" [^<>]*value="([^"]+)"[^<>]*>/', $r, $res);
                $wc_t = urlencode($res[1]);

                if (!preg_match('/<button [^<>]*name="login"[^<>]*>Login<\/button>/', $r, $res)) {
                    $strError = $this->_tr->translate('Incorrect response from VEVO site (Login page)');
                }
            }

            if (empty($strError)) {
                if (!preg_match('/<input [^<>]*name="username" [^<>]*>/', $r, $res)) {
                    $strError = $this->_tr->translate('Incorrect response from VEVO site (Login page)');
                }
            }

            if (empty($strError)) {
                $post = "wc_s=$wc_s&wc_t=$wc_t&username=" . urlencode($login) . "&password=" . urlencode($password) . "&login=x";
                list($strError, $r) = $this->sendRequestToVevo('https://online.immi.gov.au/lusc/login', $cookieFile, 'https://online.immi.gov.au/lusc/login', $post);
            }

            if (empty($strError)) {
                preg_match('/<input [^<>]*name="wc_s" [^<>]*value="([^"]+)"[^<>]*>/', $r, $res);
                $wc_s = urlencode($res[1]);

                preg_match('/<input [^<>]*name="wc_t" [^<>]*value="([^"]+)"[^<>]*>/', $r, $res);
                $wc_t = urlencode($res[1]);

                if (!preg_match('/<button [^<>]*name="continue"[^<>]*>/', $r, $res)) {
                    $strError = $this->_tr->translate('Incorrect credentials');
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'error' => $strError,
            'wc_s' => $wc_s,
            'wc_t' => $wc_t,
            'cookie_file_path' => $cookieFile
        );
    }

    /**
     * Send request to VEVO web site to load info about specific client
     *
     * @param $memberId
     * @param $arrFieldsData
     * @return array
     */
    public function getVevoInfo($memberId, $arrFieldsData)
    {
        $strError = $pdfTmpPath = '';
        $arrTableFieldsInfo = array();
        try {
            $checkLoginPassResult = array();
            $cookieFile = $r = $login = $password = $pdf = '';

            $arrFieldsData['DOB2'] = $arrFieldsData['DOB']['value'];
            $arrFieldsData['DOB'] = $this->_settings->formatDate($arrFieldsData['DOB2']);

            $countrySuggestion = $arrFieldsData['country_of_passport']['value'];
            $countryCode = $this->_country->getCountry3CodeByName($countrySuggestion, 'vevo');

            if (!$countryCode) {
                $strError = $this->_tr->translate('Incorrect Country of Passport');
            } else {
                $arrFieldsData['country_of_passport'] = $countryCode;
            }

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
                $checkLoginPassResult = $this->checkVevoCredentials($login, $password);
                $strError = $checkLoginPassResult['error'];
            }

            if (empty($strError)) {
                $wc_s = $checkLoginPassResult['wc_s'];
                $wc_t = $checkLoginPassResult['wc_t'];
                $cookieFile = $checkLoginPassResult['cookie_file_path'];

                $post = "wc_s=$wc_s&wc_t=$wc_t&continue=x";
                list($strError, $r) = $this->sendRequestToVevo('https://online.immi.gov.au/lusc/login', $cookieFile, 'https://online.immi.gov.au/lusc/login', $post);
            }

            if (empty($strError)) {
                list($strError, $r) = $this->sendRequestToVevo('https://online.immi.gov.au/evo/thirdParty', $cookieFile, 'http://www.border.gov.au/Busi/Visa');

                if (preg_match('%<ui:messageBox id=".*" type="error">\s{0,}<ui:message>(.*)</ui:message>\s{0,}</ui:messageBox>%', $r, $regs)) {
                    $strError = $regs[1];
                }
            }

            if (empty($strError)) {
                preg_match('/<input [^<>]*name="wc_s" [^<>]*value="([^"]+)"[^<>]*>/', $r, $res);
                $wc_s = urlencode($res[1]);

                preg_match('/<input [^<>]*name="wc_t" [^<>]*value="([^"]+)"[^<>]*>/', $r, $res);
                $wc_t = urlencode($res[1]);

                $time1 = 0;
                $time2 = rand(300, 400);
                $time3 = rand(300, 400);

                $post = "wc_t=$wc_t&wc_s=$wc_s&_2a9a2a0a2a0d=7&_2a9a2a0a2a0d-h=x&_2a9a2a0a2a0d-h=x&_2a9a2a0a2c0b0=" . urlencode($arrFieldsData['family_name']['value']);
                $post .= "&_2a9a2a0a2c1b0=" . urlencode($arrFieldsData['given_names']['value']);
                $post .= "&_2a9a2a0a2e0a1a=01";
                $post .= "&_0a2-h=x&_1-h=x&cprofile_timings=interface_controls%7Btime%3A$time1%2Cresult%3A1%7D%3Bhtml_start_load%7Btime%3A$time2%2Cresult%3A1%7D%3Bunload_load%7Btime%3A$time3%2Cresult%3A1%7D%3B&wc_ajax=_2a9a2a0a2e0a1a";
                list($strError, $r) = $this->sendRequestToVevo('https://online.immi.gov.au/evo/thirdParty', $cookieFile, 'https://online.immi.gov.au/evo/thirdParty', $post);

                $post = "wc_t=$wc_t&wc_s=$wc_s&_2a9a2a0a2a0d=7&_2a9a2a0a2a0d-h=x&_2a9a2a0a2a0d-h=x&_2a9a2a0a2c0b0=" . urlencode($arrFieldsData['family_name']['value']);
                $post .= "&_2a9a2a0a2c1b0=" . urlencode($arrFieldsData['given_names']['value']);
                $post .= "&_2a9a2a0a2e0a1a=01";
                $post .= "&_2a9a2a0a2g0a1a=" . urlencode($arrFieldsData['DOB']);
                $post .= "&_2a9a2a0a2g0b1a=" . urlencode($arrFieldsData['passport_number']['value']);
                $post .= "&_2a9a2a0a2g0c1a=" . urlencode($arrFieldsData['country_of_passport']);
                $post .= "&_2a9a2a0a2i1b0=true&_2a9a2a0a3b0a=x&_2a9a2a0a2g0a1a-date=" . urlencode($arrFieldsData['DOB2']);
                $post .= "&_0a2-h=x&_1-h=x&cprofile_timings=interface_controls%7Btime%3A$time1%2Cresult%3A1%7D%3Bhtml_start_load%7Btime%3A$time2%2Cresult%3A1%7D%3Bunload_load%7Btime%3A$time3%2Cresult%3A1%7D%3B";
                $this->outputResult(array('status' => 'Sending info...'));

                list($strError, $r) = $this->sendRequestToVevo('https://online.immi.gov.au/evo/thirdParty', $cookieFile, 'https://online.immi.gov.au/evo/thirdParty', $post);

                if (strpos($r, 'The Department has not been able to identify the person. Please check that the details you entered in are correct. Otherwise complete the Online referral form to obtain the work or visa entitlements.')) {
                    $strError = $this->_tr->translate('User not found!');
                }
            }

            if (empty($strError)) {
                preg_match_all('!<span id="[^"]+" class="wc-label">([^<>]*)</span><div class="wc-input">([^<>]*)</div>!', $r, $arrTable, PREG_SET_ORDER);
                $memberTypes = array_merge(Members::getMemberType('individual'), Members::getMemberType('internal_contact'));

                foreach ($arrTable as $fieldInfo) {
                    $fieldType = 'text';
                    $booCanBeSaved = true;
                    switch ($fieldInfo[1]) {
                        case 'Visa expiry date':
                            $fieldName = 'visa_expiry_date';
                            $fieldType = 'date';
                            $fieldInfo[2] = $this->_settings->formatDate($fieldInfo[2]);
                            break;

                        case 'Visa class / subclass':
                            $fieldInfo[1] = 'Current visa';
                            $fieldName = 'current_visa';
                            break;

                        case 'Current date and time':
                            $fieldInfo[1] = 'Date checked';
                            $fieldName = 'vevo_date_checked';
                            $fieldType = 'date';

                            $regExp = "/[a-zA-Z]+ [a-zA-Z]+ \d+, \d+/";

                            preg_match($regExp, $fieldInfo[2], $matches);

                            if (!empty($matches)) {
                                $fieldInfo[2] = $this->_settings->formatDate($matches[0]);
                            }
                            break;

                        case 'Location':
                            $fieldName = 'vevo_location';
                            break;

                        case 'Work entitlement(s)':
                            $fieldName = 'vevo_work';
                            break;

                        default:
                            $fieldName = str_replace(' ', '_', strtolower($fieldInfo[1] ?? ''));
                            $booCanBeSaved = false;
                            $fieldType = 'display';
                            break;
                    }

                    if ($booCanBeSaved) {
                        $fieldId = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId($fieldName, $memberTypes);
                        if (empty($fieldId)) {
                            $booCanBeSaved = false;
                        }
                    }

                    foreach ($arrTableFieldsInfo as $tableFieldsInfo) {
                        if ($tableFieldsInfo['name'] == $fieldName) {
                            continue 2;
                        }
                    }

                    $arrTableFieldsInfo[] = array(
                        'name' => $fieldName,
                        'label' => $fieldInfo[1],
                        'value' => $fieldInfo[2],
                        'type' => $fieldType,
                        'save' => $booCanBeSaved
                    );
                }
                // Sort by save value
                $save = array();
                foreach ($arrTableFieldsInfo as $key => $row) {
                    $save[$key] = $row['save'];
                }
                array_multisort($save, SORT_DESC, $arrTableFieldsInfo);

                $res = array();
                preg_match('/<button [^<>]* data-wc-url="(\/evo\/thirdParty[^"]+)"[^<>]*>/', $r, $res);

                if (!isset($res[1])) {
                    $strError = $this->_tr->translate('Incorrect response from immi web site.');
                } else {
                    $pdf_url = 'https://online.immi.gov.au' . str_replace('&amp;', '&', $res[1]);
                    $this->outputResult(array('status' => 'Retrieving info...'));

                    list($strError, $pdf) = $this->sendRequestToVevo($pdf_url, $cookieFile);
                }
            }

            if (empty($strError)) {
                if ($pdf) {
                    $filename = $this->_files->convertToFilename('VEVO_' . date('Y-m-d H-i') . '.pdf');
                    $pdfTmpPath = $this->_config['directory']['tmp'] . '/' . $filename;

                    file_put_contents($pdfTmpPath, $pdf);
                    $pdfTmpPath = $this->_encryption->encode($pdfTmpPath);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $arrTableFieldsInfo, $pdfTmpPath);
    }

    /**
     * Internal method to send requests to VEVO web site
     *
     * @param $url
     * @param $cfile
     * @param string $ref
     * @param bool $post
     * @return array
     */
    public function sendRequestToVevo($url, $cfile, $ref = '', $post = false)
    {
        $strError = '';
        $headers = array(
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
        curl_setopt($cr, CURLOPT_COOKIEFILE, $cfile);
        curl_setopt($cr, CURLOPT_COOKIEJAR, $cfile);
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
            $z = curl_error($cr);
            $errorNumber = curl_errno($cr);
            curl_close($cr);
            if ($errorNumber == 28) {
                $strError = $this->_tr->translate('Operation timeout. The specified time-out period was reached according to the conditions.');
            } else {
                $strError = $this->_tr->translate('Internal error');
            }
            $this->_log->debugErrorToFile('', 'Curl error: ' . $z . ' Url: ' . $url, 'vevo');
        } else {
            curl_close($cr);
        }

        return array($strError, $r);
    }

    /**
     * Output result, so it can be parsed and showed in the GUI
     * @param $arrResult
     * @return string
     */
    public function outputResult($arrResult)
    {
        return "<script>parent.ApplicantsProfileVevoSender.outputResult(" . Json::encode($arrResult) . ")</script>";
    }

}
