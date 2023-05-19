<?php
/**
 * Configuration file for CSRF Protector
 * Necessary configurations are (library would throw exception otherwise)
 * ---- logDirectory
 * ---- failedAuthAction
 * ---- jsUrl
 * ---- tokenLength
 */

function generateCsrfConfig(array $config)
{
    return array(
        "CSRFP_TOKEN"  => 'CSRFP-Token',
        "logDirectory" => $config['log']['path'],

        "failedAuthAction" => array(
            "GET"  => 2,
            "POST" => 2
        ),

        "errorRedirectionPage" => empty($config['urlSettings']['baseUrl']) ? '' : $config['urlSettings']['baseUrl'] . '/auth/logout',
        "customErrorMessage"   => "",
        "jsUrl"                => empty($config['urlSettings']['baseUrl']) ? '' : $config['urlSettings']['baseUrl'] . '/js/csrf/csrfprotector.js',
        "tokenLength"          => 10,

        "cookieConfig" => array(
            "path"     => '/',
            "domain"   => '',
            "secure"   => $config['session_config']['cookie_secure'],
            "httponly" => $config['session_config']['cookie_httponly'],
            "samesite" => $config['session_config']['cookie_samesite'],
            "expire"   => 0
        ),

        "disabledJavascriptMessage" => "This site attempts to protect users against <a href=\"https://www.owasp.org/index.php/Cross-Site_Request_Forgery_%28CSRF%29\">
            Cross-Site Request Forgeries </a> attacks. In order to do so, you must have JavaScript enabled in your web browser otherwise this site will fail to work correctly for you.
             See details of your web browser for how to enable JavaScript.",

        "verifyGetFor" => array()
    );

    return $arrSettings;
}


