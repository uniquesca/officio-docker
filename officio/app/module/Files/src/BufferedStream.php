<?php

namespace Files;

use Laminas\Http\Headers;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Http\Response\Stream;

class BufferedStream extends Stream
{

    public function __construct($contentType, $contentLength = null, $disposition = 'attachment', $cache = false)
    {
        $headers = new Headers();
        $headers->addHeaders(
            [
                'Content-Description' => 'File Transfer',
                'Content-Type' => $contentType,
                'Content-Disposition' => "$disposition"
            ]
        );

        if ($contentLength !== null) {
            $headers->addHeaders(
                [
                    'Content-Length' => $contentLength
                ]
            );
        }

        if ($cache) {
            $expires = 60 * 60 * 24 * 30; // 1 month
            $headers->addHeaders(
                [
                    'Pragma' => 'public',
                    'Cache-Control' => "max-age=0",
                    'Expires' => gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT'
                ]
            );
        } else {
            $headers->addHeaders(
                [
                    'Cache-Control' => 'no-store',
                ]
            );
        }
        $this->setHeaders($headers);
        $response = new Response();
        $response->setHeaders($headers);
        $response->sendHeaders();

        ob_start();
    }

}
