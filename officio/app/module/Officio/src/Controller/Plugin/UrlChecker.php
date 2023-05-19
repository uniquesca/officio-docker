<?php

namespace Officio\Controller\Plugin;

use Exception;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class UrlChecker extends AbstractPlugin
{

    public function __invoke($url) {
        $strError = '';
        $hash = '';
        if (($ch = curl_init($url)) === false) {
            throw new Exception("curl_init error for url $url.");
        }

        // Use different user agents
        $arrUserAgents = array(
            'Mozilla/5.0 (Windows NT 10.0; rv:78.0) Gecko/20100101 Firefox/78.0', // Firefox 78
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/3.0', // Firefox 3
            'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)', // IE7
            'Mozilla/4.8 [en] (Windows NT 6.0; U)', // Netscape
            'Opera/9.25 (Windows NT 6.0; U; en)' // Opera
        );
        curl_setopt($ch, CURLOPT_USERAGENT, $arrUserAgents[rand(0, 4)]);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FAILONERROR,true);

        $result = curl_exec ( $ch );
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if($result === false) {
            $strError = 'Curl error: ' . curl_error($ch);
        } elseif ($httpcode < 200 || $httpcode >= 400) {
            $strError = 'Not exists';
        } else {
            $hash = hash_hmac('sha256', $result, 'secret');
        }
        curl_close ( $ch );

        return array(
            'error' => $strError,
            'hash' => $hash
        );
    }
}