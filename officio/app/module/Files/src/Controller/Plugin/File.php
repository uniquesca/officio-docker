<?php

namespace Files\Controller\Plugin;

use Laminas\Http\Header\SetCookie;
use Laminas\Http\Headers;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;

/**
 * Class File
 * @property BaseController $controller
 * @package Files\Controller\Plugin
 */
class File extends AbstractPlugin
{

    public function __invoke($strFileContent, $fileName, $fileMime, $booEnableCache = false, $booAsAttachment = true, $booReturnFileDownloadCookie = false)
    {
        // Turn off error reporting so it doesn't spoil the output
        error_reporting(0);

        /** @var Response $response */
        $response = $this->controller->getResponse();

        if (!empty($strFileContent)) {
            // Flushing and ending all buffers, so no extra output it messed into the files, including CSRF
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $disposition = $booAsAttachment ? 'attachment' : 'inline';

            // Return correct encoding if needed
            if (strpos($strFileContent, 'charset=windows-1252') !== false) {
                // Note: ';' is at the beginning
                $fileMime .= ';charset=windows-1252';
            }

            // Return content
            $headers = new Headers();

            // Set required headers
            $contentDisposition = $disposition;
            if ($fileName) {
                $fileName           = FileTools::cleanupFileName($fileName);
                $contentDisposition .= "; filename=\"$fileName\"";
            }
            $headers->addHeaders(
                [
                    'Content-Description' => 'File Transfer',
                    'Content-Type' => $fileMime,
                    'Content-Disposition' => $contentDisposition,
                    'Content-Transfer-Encoding' => 'binary',
                    'Accept-Ranges' => 'bytes',
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
                // seconds, minutes, hours, days
                $expires = 60 * 60 * 24 * 30; // - 1 month
                $headers->addHeaders(
                    [
                        'Pragma' => 'public',
                        'Cache-Control' => "maxage=$expires",
                        'Expires' => gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT'
                    ]
                );
            } else {
                $headers->addHeaders(
                    [
                        'Cache-Control' => 'must-revalidate',
                        'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT'
                    ]
                );
            }

            $headers->addHeaders(
                [
                    'Content-Length' => strlen($strFileContent)
                ]
            );
            $response->setContent($strFileContent);
            $response->setHeaders($headers);

            return $response;
        } else {
            return $this->controller->fileNotFound();
        }
    }

}