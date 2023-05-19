<?php

namespace Mailer;

use Exception;
use Laminas\Mail\Headers;
use Officio\Email\Storage\Message;
use Officio\Email\Storage\Message\ImapMessage;
use RecursiveIteratorIterator;

// TODO Move to Email module
class MessageParser
{

    /**
     * TODO: place this utility function in a logical more accessible place
     * Decodes an RFC 2231 encoded string.
     *
     * @access public
     * @param string $string The entire string to decode,optionally including the parameter name.
     * @param string $charset The character set to return result in.
     * @return array|bool               Array of decoded info
     */
    private function _decodeRFC2231Header($string, $charset = null)
    {
        // search for characteristic * in string and return if not found
        if (($pos = strpos($string, '*')) === false) {
            return false;
        }

        if (!isset($charset)) {
            $charset = 'UTF-8';
        }

        $attribute = substr($string, 0, $pos);
        $encoded_charset = $encoded_language = null;
        $output = '';

        /* Get the characer set and language used in the encoding, if any. */
        if (preg_match("/^[^=]+\*\=([^']*)'([^']*)'/", $string, $m)) {
            $encoded_charset = $m[1];
            $encoded_language = $m[2];
            $string = str_replace($encoded_charset . "'" . $encoded_language . "'", '', $string);
        }

        $lines = preg_split('/' . preg_quote($attribute) . '(?:\*\d)*/', $string);
        foreach ($lines as $line) {
            $pos = strpos($line, '*=');
            if ($pos === 0) {
                $line = substr($line, 2);
                $line = str_replace('_', '%20', $line);
                $line = str_replace('=', '%', $line);
                $output .= urldecode($line);
            } else {
                $line = substr($line, 1);
                $output .= $line;
            }
        }

        // convert to required charset
        if (isset($encoded_charset)) {
            $output = iconv($encoded_charset, $charset, $output);
        }

        return array(
            'attribute' => $attribute,
            'language' => $encoded_language,
            'value' => $output
        );
    }

    private function _returnDecodedPart($part)
    {
        // takes a part and decodes it into utf-8 or 8bit (binary)
        // RULES: if Content-Type contains name= then return as 8bit else utf-8

        $headers = $part->getHeaders()->toArray();

        $encoding = isset($headers['Content-Transfer-Encoding']) ? strtolower($headers['Content-Transfer-Encoding'] ?? '') : '8bit';
        $content  = $part->getContent() ?? '';

        if ($encoding == 'quoted-printable') {
            $content = quoted_printable_decode($content);
        } elseif ($encoding == 'base64') {
            $content = base64_decode($content);
        }

        // only convert character sets to UTF-8 if Content-Type contains charset
        // charset encoded data is human readable so can be a bit more lenient on attempting to decode possible corrupted/badly encoded data
        if (isset($headers['Content-Type'])) {
            if (is_array($headers['Content-Type'])) {
                $headers['Content-Type'] = $headers['Content-Type'][count($headers['Content-Type']) - 1];
            }

            $contentTypeMatches = array();
            preg_match('@([^;]+)([^(charset=)]+charset=[\s"\']?([\w\-.]+)[\s"\']?)*@i', $headers['Content-Type'], $contentTypeMatches);

            if (count($contentTypeMatches) > 2 && strlen($contentTypeMatches[3] ?? '') > 0) {
                $charset = strtoupper($contentTypeMatches[3] ?? '');

                // finish decoding from encoding to charset
                if ($encoding == '7bit') {
                    $content = iconv('ascii', $charset . '//IGNORE', $content);
                }

                // convert from charset to UTF-8
                if ($charset == 'ISO-8859-1' || $charset == 'ISO8859-1') {
                    $content = utf8_encode($content);
                } elseif (strtoupper($charset) == 'UNICODE-1-1-UTF-7') {
                    $content = iconv('UTF-7', "utf-8//IGNORE", $content);
                } elseif (strtoupper($charset) == 'GB2312') {
                    $content = iconv('GB18030', "utf-8//IGNORE", $content);
                } elseif (strtoupper($charset) == 'KS_C_5601-1987') {
                    $content = iconv('euckr', "utf-8//IGNORE", $content);
                } else {
                    $content = iconv($charset, "utf-8//IGNORE", $content);
                }
            } elseif (substr($headers['Content-Type'], 0, 9) == 'text/html' || substr($headers['Content-Type'], 0, 10) == 'text/plain') {
                $charset = mb_detect_encoding($content, '7bit, ASCII, WINDOWS-1252, UTF7, UTF8, ISO-8859-1', true);
                $content = mb_convert_encoding($content, 'UTF-8', $charset);
            }
        }
        return $content;
    }

    private function _makeFSSafe($filename = null)
    {
        // the minimum rules for FS: filenames is must be non null and must not contain slash and have max length
        if ($filename == null) {
            $filename = 'unknown.txt';
        }
        // fix filename for FS filename limitations.
        if (strlen($filename ?? '') > 255) {
            $filename = substr($filename ?? '', -254);
        }
        return str_replace(array('/', '\\'), '', $filename);
    }

    public function returnPreparedMessageArray($message, $argsSupplied = array())
    {
        $argsDefault = array('parentEmbeddedAttachmentPrefix' => '', 'siteBaseUrl' => '/');
        $argsRequired = array('tmpFolderBaseName', 'folder', 'uniqueId');

        foreach ($argsRequired as $argRequired) {
            if (
                !array_key_exists($argRequired, $argsSupplied)
                ||
                ($argRequired == 'tmpFolderBaseName' && strlen($argsSupplied['tmpFolderBaseName'] ?? '') == 0)
                ||
                ($argRequired == 'folder' && strlen($argsSupplied['folder'] ?? '') == 0)
                ||
                ($argRequired == 'uniqueId' && strlen($argsSupplied['uniqueId'] ?? '') == 0)
            ) {
                throw new Exception('invalid call to returnPreparedMessageArray: required argument missing: ' . $argRequired);
            }
        }

        $args = array_merge($argsDefault, $argsSupplied);

        // folder must be in UTF7 format, same as it comes from the IMAP server
        $folderUTF7 = $args['folder'];
        $folderUTF7UrlencodedDouble = urlencode(urlencode($folderUTF7));

        $unpackAllAttachments = false;

        $tmpFolderBaseName = $args['tmpFolderBaseName'];

        $uniqueid                 = $args['uniqueId'];
        $embeddedAttachmentPrefix = 1;

        // compose array to return containing:
        $preparedMessageArray                    = array();
        $preparedMessageArray['headersOriginal'] = $message->getHeaders()->toArray();
        $preparedMessageArray['attachmentsList'] = [];

        $additionalHeaderFields = array();
        if (!isset($preparedMessageArray['headers']['uniqueid']) || $preparedMessageArray['headers']['uniqueid'] == '') {
            $additionalHeaderFields = array('uniqueid' => $uniqueid);
        }

        #UNIQUES CHANGE:
        $preparedMessageArray['headers'] = array(); //$message->getProcessedHeaders( $additionalHeaderFields );

        $preparedMessageArray['bodyPreparedHtml'] = '';
        // not yet reliable and also requires additional call - TODO: consider utilizing by adding BODYSTRUCTIRE to message content call
        // $preparedMessageArray['attachmentsList'] = $message->getAttachmentsList(); // list of all uncategorised attachments
        $preparedMessageArray['forcedInlines']     = array(); // can be type with content (messages) or url
        $preparedMessageArray['forcedAttachments'] = array();
        $preparedMessageArray['cids']              = array();

        $textContent   = '';
        $htmlContent   = '';
        $foundHtmlPart = false;
        $foundTextPart = false;
        $cidHashList   = array(); // list of cids in body to convert to temporary file name urls once all parts processed

        $forcedInlineMimeTypes = array('image/gif', 'image/png', 'image/jpeg', 'message/rfc822', 'text/html');
        $forceToAttachment     = array('application/pdf');
        $existingPartFilenames = array();
        $filenamePrependId     = 1;
        $iconClass             = array(
            'default' => 'default',
            'doc'     => 'doc',
            'docx'    => 'doc',
            'gif'     => 'img',
            'gz' => 'zip',
            'html' => 'html',
            'ics' => 'ics',
            'jpg' => 'img',
            'js' => 'code',
            'message' => 'email',
            'pdf' => 'pdf',
            'php' => 'code',
            'pl' => 'code',
            'plain' => 'txt',
            'png' => 'img',
            'ppt' => 'ppt',
            'psd' => 'img',
            'rar' => 'zip',
            'rtf' => 'txt',
            'sh' => 'code',
            'tar' => 'zip',
            'tgz' => 'zip',
            'txt' => 'txt',
            'xls' => 'default',
            'xlsx' => 'default',
            'zip' => 'zip'
        );

        $fileTypeNames = array(
            'ics' => 'Calendar Appointment',
            'pdf' => 'PDF Document',
            'php' => 'PHP file',
            'js' => 'Javascript file',
            'pl' => 'Perl script',
            'sh' => 'Shell script',
            'jpg' => 'JPEG image',
            'psd' => 'Photoshop file',
            'gif' => 'GIF image',
            'png' => 'PNG image',
            'ppt' => 'Powerpoint slide',
            'txt' => 'Text file',
            'rtf' => 'Rich text file',
            'html' => 'HTML document',
            'doc' => 'Microsoft Word document',
            'docx' => 'Microsoft Word document',
            'xls' => 'Microsoft Word spreadsheet',
            'xlsx' => 'Microsoft Word spreadsheet',
            'zip' => 'Zip archive',
            'tgz' => 'TGZ archive',
            'gz' => 'Compressed archive',
            'rar' => 'RAR archive',
            'tar' => 'Tar archive',
            'message' => 'Embeded email',
            'default' => 'Downloadable File',
            'eml' => 'Email message'
        );

        if (!$message->isMultipart()) {
            $partHeaders = $message->getHeaders()->toArray();
            if (isset($partHeaders['Content-Type']) && preg_match('/text\/html/', $partHeaders['Content-Type'])) {
                $htmlContent = $this->_returnDecodedPart($message);
                $foundHtmlPart = true;
            } elseif (isset($partHeaders['Content-Type'])) {
                $partFilename = null;
                // we found an email with illegal multiple content types use last
                if (is_array($partHeaders['Content-Type'])) {
                    $partHeaders['Content-Type'] = $partHeaders['Content-Type'][count($partHeaders['Content-Type']) - 1];
                }

                $contentType = $partHeaders['Content-Type'];
                preg_match('|([^/]+)/(.+)|', $contentType, $contentTypeParts);
                preg_match('@([^;]+)([^(charset=)]+charset=[\s"\']?([\w\-.]+)[\s"\']?)?([^(name=)]+name=[\s]?["\']?([^"\';]+)["\']?)?@i', $partHeaders['content-type'], $contentTypeMatches);

                // now if we find a filename then force to attachment so that we can generate preview, download link etc. else return content as basic message body.
                if (isset($partHeaders['Content-Disposition']) && preg_match('/name=\s*([^;]+)/i', $partHeaders['Content-Disposition'], $contentDispositionMatches)) {
                    $partFilename = trim($contentDispositionMatches[1], '"\' ');
                } else {
                    // convert to UTF-8, all internals are utf-8
                    $textContent = $this->_returnDecodedPart($message);
                    // this should be in ui layer
                    $foundTextPart = true;
                }
            }
        }

        foreach (new RecursiveIteratorIterator($message, RecursiveIteratorIterator::SELF_FIRST) as $part) {
            /** @var Headers $partHeaders */
            $partHeaders = $part->getHeaders()->toArray();

            // try match filename here as may be needed at various parts
            $partFilename = 'unknownfilename';
            $contentDispositionMatches = array();
            $matches = array();
            $mimeType = 'unknown/unknown';
            if (isset($partHeaders['Content-Type'])) {
                // we found an email with illegal multiple content types use last
                if (is_array($partHeaders['Content-Type'])) {
                    $partHeaders['Content-Type'] = $partHeaders['Content-Type'][count($partHeaders['Content-Type']) - 1];
                }
                preg_match(
                    '@([^;]+)([^(charset=)]+charset=[\s"\']?([\w\-.]+)[\s"\']?)?([^(name=)]+name=[\s]?["\']?([^"\';]+)["\']?)?@i',
                    $partHeaders['Content-Type'],
                    $matches
                );
            }

            // we found an email with multiple content dispositions (use last type and hope for the best)
            if (isset($partHeaders['Content-Disposition']) && is_array($partHeaders['Content-Disposition'])) {
                // TODO: find better way to handle multiple content-disposition part header lines.
                $longestStringFoundId = 0;
                foreach ($partHeaders['Content-Disposition'] as $k => $v) {
                    if (strlen($v) > strlen($partHeaders['Content-Disposition'][$longestStringFoundId] ?? '')) {
                        $longestStringFoundId = $k;
                    }
                }
                $partHeaders['Content-Disposition'] = $partHeaders['Content-Disposition'][$longestStringFoundId];
            }

            if (isset($matches[5])) {
                $partFilename = $matches[5];
            } elseif (isset($partHeaders['Content-Disposition']) && preg_match('/name=\s*([^;]+)/i', $partHeaders['Content-Disposition'], $m)) {
                $partFilename = trim($m[1], '"');
            } // strip wrapping quotation marks
            elseif (
                isset($partHeaders['Content-Disposition'])
                &&
                preg_match('@([\w/\-]+)([^(filename=)]+filename=[\s"\']?([^"\';]+)["\']?)?([^(name=)]+name=[\s]?["\']?([^"\';]+)["\']?)?@i', $partHeaders['Content-Disposition'], $contentDispositionMatches)
                &&
                isset($contentDispositionMatches[3])
            ) {
                $partFilename = $contentDispositionMatches[3];
            } elseif (
                isset($partHeaders['Content-Disposition'])
                &&
                preg_match_all('/filename(\*\d+)="(.+?)"/i', $partHeaders['Content-Disposition'], $m, PREG_PATTERN_ORDER)
            ) {
                $partFilename = join('', $m[2]);
            } // some clients split up file names in the header

            elseif (
                isset($partHeaders['Content-Type'])
                &&
                preg_match_all('/name(\*\d+)="(.+?)"/i', $partHeaders['Content-Type'], $m, PREG_PATTERN_ORDER)
            ) {
                $partFilename = join('', $m[2]);
            } // some clients split up file names in the header

            elseif (
                isset($partHeaders['Content-Type']) &&
                preg_match_all('/name=\s*([^;]+)/i', $partHeaders['Content-Type'], $m, PREG_PATTERN_ORDER)
            ) {
                $partFilename = str_replace('"', '', $m[1][0]);
            } elseif (isset($partHeaders['Content-Description'])) {
                $partFilename = str_replace('"', '', $partHeaders['Content-Description']);
            } elseif (isset($partHeaders['Content-Location'])) {
                $partFilename = str_replace('"', '', $partHeaders['Content-Location']);
            } elseif (
                isset($partHeaders['Content-Disposition'])
                &&
                strpos($partHeaders['Content-Disposition'], '*') !== false
            ) {
                /*
                See: http://www.faqs.org/rfcs/rfc2231.html
                RFC 2231 - MIME Parameter Value and Encoded Word Extensions: Character Sets, Languages, and Continuations
                This desribes the iMail style of encoding i18n attachment filenames
                Not implimented in A5
                // test possibly existing support in:
                // array imap_mime_header_decode ( string $text )
                // string iconv_mime_decode ( string $encoded_header [, int $mode [, string $charset]] )
                */
                $result = $this->_decodeRFC2231Header($partHeaders['Content-Disposition']);

                if (strlen($result['value'] ?? '') > 0) {
                    $partFilename = $result['value'];
                }
            }

            if (isset($partHeaders['Content-Type'])) {
                $mimeType = strtolower($matches[1] ?? '');
                $mimeTypeParts = explode('/', $mimeType, 2);

                // if is multipart part continue to next part
                if ($mimeTypeParts[0] == 'multipart') {
                    continue;
                }

                if ($mimeType == 'text/plain' || $mimeType == 'message/delivery-status') {
                    if (
                        (!$foundTextPart || $mimeType == 'message/delivery-status')
                        &&
                        (!isset($partHeaders['Content-Disposition']) || (strpos($partHeaders['Content-Disposition'], 'name') === false))
                        &&
                        ((strpos($partHeaders['Content-Type'], 'name') === false))

                    ) {
                        $textContent .= $this->_returnDecodedPart($part);
                        $foundTextPart = true;
                    } else {
                        $content = $this->_returnDecodedPart($part);
                        // ignore if larger than x
                        // TODO: force to attachment if larger than x
                        if (strlen($content) > (1024 * 5)) {
                            $content = '';
                        }
                        if ($partFilename == 'unknownfilename') {
                            $partFilename = 'embeddedtext' . $args['parentEmbeddedAttachmentPrefix'] . ($embeddedAttachmentPrefix++) . '.txt';
                        }
                        $partFilenameOriginal = $partFilename;
                        $partFilenameFS       = $this->_makeFSSafe($folderUTF7 . $args['uniqueId'] . $partFilename);

                        if (!strlen($content)) {
                            continue;
                        }

                        $preparedMessageArray['forcedInlines'][] = array('iconClass' => $iconClass['plain'], 'mimeType' => $mimeType, 'filenameFS' => $partFilenameFS, 'filenameOriginal' => $partFilenameOriginal, 'content' => $content,);
                        $preparedMessageArray['attachmentsList'][] = array('iconClass' => $iconClass['plain'], 'mimeType' => $mimeType, 'filenameFS' => $partFilenameFS, 'filenameOriginal' => $partFilenameOriginal, 'content' => $content);
                    }
                    // don't continue unpacking the first text part as the main email text part (non downloadable without downloading the entire .eml)
                    continue;
                }

                if ($mimeType == 'text/html' || $mimeType == 'text/x-amp-html') {
                    if (
                        (!isset($partHeaders['Content-Disposition']) || (strpos($partHeaders['Content-Disposition'], 'name') === false))
                        &&
                        ((strpos($partHeaders['Content-Type'], 'name') === false))
                    ) {
                        $htmlContentNew = $this->_returnDecodedPart($part);

                        $htmlContent   .= $htmlContentNew;
                        $foundHtmlPart = true;
                    } else {
                        $content = $this->_returnDecodedPart($part);

                        // ignore content preview if larger than x
                        // TODO: force to attachment if larger than x
                        // if( strlen($content) > (1024*10) )
                        //    $content = '';

                        if ($partFilename == 'unknownfilename') {
                            $partFilename = 'embeddedhtml' . $args['parentEmbeddedAttachmentPrefix'] . ($embeddedAttachmentPrefix++) . '.html';
                        }
                        $partFilenameOriginal = $partFilename;
                        $partFilenameFS       = $this->_makeFSSafe($folderUTF7 . $args['uniqueId'] . $partFilename);

                        if (!strlen($content)) {
                            continue;
                        }
                        $preparedMessageArray['forcedInlines'][] = array('iconClass' => $iconClass['html'], 'mimeType' => $mimeType, 'filenameFS' => $partFilenameFS, 'filenameOriginal' => $partFilenameOriginal, 'content' => $content);
                        $preparedMessageArray['attachmentsList'][] = array('iconClass' => $iconClass['html'], 'mimeType' => $mimeType, 'filenameFS' => $partFilenameFS, 'filenameOriginal' => $partFilenameOriginal, 'content' => $content);
                    }
                    // don't unpacking the first html part as the main email text part (normal to be non downloadable without downloading the entire .eml)
                    continue;
                }

                if ($mimeType == 'message/rfc822') {
                    $inlineMessage = new Message(array('raw' => $this->_returnDecodedPart($part)));
                    //$inlineMessage->processHeaders(  array('uniqueid' => $preparedMessageArray['headers']['uniqueid'])  );
                    $contentArray = self::returnPreparedMessageArray(
                        $inlineMessage,
                        array(
                            'parentEmbeddedAttachmentPrefix' => $args['parentEmbeddedAttachmentPrefix'] . $embeddedAttachmentPrefix,
                            'tmpFolderBaseName' => $args['tmpFolderBaseName'],
                            'folder' => $folderUTF7,
                            'uniqueId' => $args['uniqueId']
                        )
                    );

                    if ($partFilename == 'unknownfilename') {
                        $partFilename = 'embeddedEmail' . $args['parentEmbeddedAttachmentPrefix'] . ($embeddedAttachmentPrefix++) . '.eml';
                    }

                    $partFilenameOriginal = $partFilename;
                    $partFilenameFS = $this->_makeFSSafe($folderUTF7 . $args['uniqueId'] . $partFilename);

                    preg_match('/\.(\w+)$/', $partFilenameOriginal, $m);
                    $fileTypeName = $fileTypeNames[strtolower($m[1] ?? '')] ?? $fileTypeNames['default'];

                    $preparedMessageArray['forcedInlines'][] = array(
                        'iconClass'        => $iconClass['message'],
                        'mimeType'         => $mimeType,
                        'filenameFS'       => $partFilenameFS,
                        'filenameOriginal' => $partFilenameOriginal,
                        'sizeRaw'          => $part->getSize(),
                        'fileTypeName'     => $fileTypeName,
                        'content'          => $contentArray
                    );

                    if (!empty($contentArray['attachmentsList'])) {
                        $preparedMessageArray['attachmentsList'] = empty($preparedMessageArray['attachmentsList']) ? [] : $preparedMessageArray['attachmentsList'];
                        $preparedMessageArray['attachmentsList'] = array_merge($preparedMessageArray['attachmentsList'], $contentArray['attachmentsList']);
                    }

                    continue;
                }
            }

            // ZF encoded attachment filename broken so decode here (some CLI issues here)
            if (strpos($partFilename ?? '', '=?') !== false && strpos($partFilename, '?=') !== false) {
                $partFilename = iconv_mime_decode($partFilename, 0, "UTF-8");
            }

            if (isset($partHeaders['Content-Id'])) {
                // most inlines CIDs are images
                $fileExtension = '';
                if ($mimeType == 'image/jpeg') {
                    $fileExtension = '.jpg';
                } elseif ($mimeType == 'image/gif') {
                    $fileExtension = '.gif';
                } elseif ($mimeType == 'image/png') {
                    $fileExtension = '.png';
                }

                if (strpos($partFilename ?? '', 'unknownfilename') !== false && isset($partHeaders['Content-Location'])) {
                    $partFilename = $partHeaders['Content-Location'];
                } elseif (strlen($fileExtension) > 0 && substr($partFilename, -4) != $fileExtension) {
                    // catch .jpeg && ($fileExtension !=)
                    if (substr($partFilename, -5) != '.jpeg') {
                        $partFilename .= $fileExtension;
                    }
                }


                if (in_array($partFilename, $existingPartFilenames)) {
                    // attachment name already used (lazy email clients) so increment the filename before writing it to disk
                    // insert an ordinal number number into unknown filenames so attachment fetcher can fetch correct attachment
                    $filenamePrependId++;
                    $partFilename = $filenamePrependId . $partFilename;
                }
                $existingPartFilenames[] = $partFilename;

                $partFilenameFS = $this->_makeFSSafe($folderUTF7 . $args['uniqueId'] . $partFilename);

                if (strpos($partFilename ?? '', 'unknownfilename') !== false) {
                    $partFilename = $folderUTF7 . $args['uniqueId'] . $partFilename;
                }

                // finally make partFilename FS safe and unique to folder and uniqueId
                $partFilenameOriginal = $partFilename;

                preg_match('/\.(\w+)$/', $partFilenameOriginal, $m);

                if (isset($m[1]) && isset($iconClass[strtolower($m[1] ?? '')])) {
                    $iconState = $iconClass[strtolower($m[1] ?? '')];
                } else {
                    $iconState = $iconClass['default'];
                }

                if (isset($m[1]) && isset($fileTypeNames[strtolower($m[1])])) {
                    $fileTypeName = $fileTypeNames[strtolower($m[1] ?? '')];
                } else {
                    $fileTypeName = $fileTypeNames['default'];
                }

                $cid = $partHeaders['Content-Id'];
                if (substr($cid, 0, 1) == "<") {
                    $cid = substr($cid, 1);
                }
                if (substr($cid, -1) == ">") {
                    $cid = substr($cid, 0, -1);
                }

                // TODO: prevent duplicates
                $preparedMessageArray['cids'][] = array('iconClass' => $iconState, 'mimeType' => $mimeType, 'filenameFS' => $partFilenameFS, 'filenameOriginal' => $partFilenameOriginal, 'fileTypeName' => $fileTypeName, 'cid' => $cid);
                $preparedMessageArray['attachmentsList'][] = array(
                    'iconClass' => $iconState,
                    'mimeType' => $mimeType,
                    'filenameFS' => $partFilenameFS,
                    'filenameOriginal' => $partFilenameOriginal,
                    'fileTypeName' => $fileTypeName,
                    'content' => $this->_returnDecodedPart($part)
                );

                // CONSIDER: consider forcing to forcedInlines for cid inlines as well
                continue;
            }

            // finally make partFilename URL and FS safe and unique to folder and uniqueId
            $partFilenameOriginal = $partFilename;
            $partFilenameFS = $this->_makeFSSafe($folderUTF7 . $args['uniqueId'] . $partFilename);

            preg_match('/\.(\w+)$/', $partFilenameOriginal, $m);

            if (isset($m[1]) && isset($iconClass[strtolower($m[1] ?? '')])) {
                $iconState = $iconClass[strtolower($m[1] ?? '')];
            } else {
                $iconState = $iconClass['default'];
            }

            if (isset($m[1]) && isset($fileTypeNames[strtolower($m[1])])) {
                $fileTypeName = $fileTypeNames[strtolower($m[1] ?? '')];
            } else {
                $fileTypeName = $fileTypeNames['default'];
            }

            if (!strlen($this->_returnDecodedPart($part))) {
                continue;
            }

            // now try to match for other part types
            if (in_array($mimeType, $forcedInlineMimeTypes)) {
                $preparedMessageArray['forcedInlines'][] = array('iconClass' => $iconState, 'mimeType' => $mimeType, 'filenameFS' => $partFilenameFS, 'filenameOriginal' => $partFilenameOriginal, 'fileTypeName' => $fileTypeName);
            } else {
                $preparedMessageArray['forcedAttachments'][] = array('iconClass' => $iconState, 'mimeType' => $mimeType, 'filenameFS' => $partFilenameFS, 'filenameOriginal' => $partFilenameOriginal, 'fileTypeName' => $fileTypeName);
            }
            $preparedMessageArray['attachmentsList'][] = array(
                'iconClass'        => $iconState,
                'mimeType'         => $mimeType,
                'filenameFS'       => $partFilenameFS,
                'filenameOriginal' => $partFilenameOriginal,
                'fileTypeName'     => $fileTypeName,
                'content'          => $this->_returnDecodedPart($part)
            );
        } // EO foreach recursion

        // if html part found use html part else prep and include text part
        if ($foundHtmlPart) {
            foreach ($preparedMessageArray['cids'] as $hash) {
                // force as forcedInline if cid not found in html
                // all URLvars that could contain chars that confuse url parsing or ZF url handline (/ ? & etc.) must be double urlencoded because ZF auto decodes before looking for SEF vars and gets confused if only single urlencoded
                $mimeTypeUrlencodedDouble         = urlencode(urlencode($hash['mimeType']));
                $filenameOriginalUrlencodedDouble = urlencode(urlencode($hash['filenameOriginal']));

                // consider moving this the html cid replacement to the rendering portion
                $imgSrc = $args['siteBaseUrl'] . 'index.php/mailer/viewmessage/getattachment?folder=' . $folderUTF7UrlencodedDouble . '&uniqueId=' . $uniqueid . '&mimeType=' . $mimeTypeUrlencodedDouble . '&filenameOriginal=' . $filenameOriginalUrlencodedDouble;
                if (strpos($htmlContent, $hash['cid']) !== false) {
                    $htmlContent = str_ireplace('cid:' . $hash['cid'], $imgSrc, $htmlContent);
                } else {
                    $preparedMessageArray['forcedInlines'][] = $hash;
                }
            }

            $preparedMessageArray['bodyPreparedHtml'] = $htmlContent;
            //$preparedMessageArray['bodyPreparedHtml'] = substr($htmlContent, 0, 50);

        } elseif ($foundTextPart) {
            $preparedMessageArray['bodyPreparedHtml'] = nl2br($textContent);
            //$preparedMessageArray['bodyPreparedHtml'] = substr(nl2br($textContent), 0, 50);
            foreach ($preparedMessageArray['cids'] as $hash) {
                // force as forcedInline if cids exist (illegal, but gracefully handle - some MMS clients do may this)
                $preparedMessageArray['forcedInlines'][] = $hash;
            }
        } else {
            foreach ($preparedMessageArray['cids'] as $hash) {
                // force as forcedInline if unmatched cids exist (illegal, but gracefully handle - some MMS clients may do this)
                $preparedMessageArray['forcedInlines'][] = $hash;
            }
        }

        return $preparedMessageArray;
    }

}