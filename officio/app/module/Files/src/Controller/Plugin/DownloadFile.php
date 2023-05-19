<?php

namespace Files\Controller\Plugin;

use Laminas\Http\Header\SetCookie;
use Laminas\Http\Headers;
use Laminas\Http\Response\Stream;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;

/**
 * Class DownloadFile
 * @property BaseController $controller
 * @package Files\Controller\Plugin
 */
class DownloadFile extends AbstractPlugin
{

    public function __invoke($filePath, $fileName = '', $fileMime = '', $booEnableCache = false, $booAsAttachment = true, $booReturnFileDownloadCookie = false)
    {
        if (is_file($filePath)) {
            // Flushing and ending all buffers, so no extra output it messed into the files, including CSRF
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $disposition = $booAsAttachment ? 'attachment' : 'inline';

            if (empty($fileName)) {
                $fileName = pathinfo($filePath, PATHINFO_BASENAME);
            }
            $fileName = FileTools::cleanupFileName($fileName);

            // Get file MIME
            if (!$fileMime) {
                $fileMime = FileTools::getMimeByFileName($fileName);
            }

            // Return correct encoding if needed
            // Load part of the file
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                // Should not be here
                return $this->controller->fileNotFound();
            }

            $strContentCheck = fread($handle, 1024);
            fclose($handle);
            if (strpos($strContentCheck, 'charset=windows-1252') !== false) {
                // Note: ';' is at the beginning
                $fileMime .= ';charset=windows-1252';
            }
            unset($strContentCheck);

            $pointer = fopen($filePath, 'r');

            $stream = new Stream();
            $stream->setStream($pointer);
            $stream->setStreamName($fileName);

            $headers = new Headers();
            $headers->addHeaders(
                [
                    'Content-Description' => 'File Transfer',
                    'Content-Type'        => $fileMime,
                    'Content-Disposition' => "$disposition; filename=\"$fileName\"",
                    'Content-Length'      => filesize($filePath)
                ]
            );

            // Add cookie
            if ($booReturnFileDownloadCookie) {
                // This cookie is needed when "jQuery File Download Plugin" is used
                $cookie = new SetCookie();
                $cookie
                    ->setName('fileDownload')
                    ->setValue('true')
                    ->setSameSite('Lax')
                    ->setPath('/');
                $headers->addHeader($cookie);
            }

            if ($booEnableCache) {
                $expires = 60 * 60 * 24 * 30; // 1 month
                $headers->addHeaders(
                    [
                        'Pragma'        => 'public',
                        'Cache-Control' => "max-age=0",
                        'Expires'       => gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT'
                    ]
                );
            } else {
                $headers->addHeaders(
                    [
                        'Cache-Control' => 'no-store',
                    ]
                );
            }

            $stream->setHeaders($headers);

            return $stream;
        } else {
            return $this->controller->fileNotFound();
        }
    }

}