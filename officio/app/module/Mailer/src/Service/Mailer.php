<?php

namespace Mailer\Service;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Laminas\Mail\Header\ContentDisposition;
use Laminas\Mail\Header\ContentTransferEncoding;
use Laminas\Mail\Header\ContentType;
use Laminas\Mail\Header\Date;
use Laminas\Mail\Header\HeaderInterface;
use Laminas\Mail\Header\MessageId;
use Laminas\Mail\Headers;
use Laminas\Mail\Protocol\Exception\ExceptionInterface;
use Laminas\Mail\Storage;
use Laminas\Mail\Storage\AbstractStorage;
use Laminas\Mail\Storage\Imap;
use Laminas\Mime\Mime;
use Laminas\Mime\Part;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\EmailAddress;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Mailer\LoaderDispatcher;
use Mailer\MessageParser;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Common\SubServiceOwner;
use Officio\Email\FileManagerInterface;
use Officio\Email\Models\Attachment;
use Officio\Email\Models\Folder;
use Officio\Email\Models\MailAccount;
use Officio\Email\Models\Message;
use Officio\Email\Protocol\CancelException;
use Officio\Email\Storage\Message\ImapMessage;
use Officio\Email\Transport\Fake;
use Officio\Email\Utils;
use Officio\Service\Company;
use Officio\Service\OAuth2Client;
use RecursiveIteratorIterator;
use Officio\Email\Storage\Imap as OfficioImapStorage;
use Officio\Email\Storage\Pop3 as OfficioPop3Storage;
use Officio\Comms\Service\Mailer as CommsManager;
use Uniques\Php\StdLib\DateTimeTools;
use Uniques\Php\StdLib\FileTools;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Mailer extends SubServiceOwner
{

    protected $accountCredentials = array();

    /** @var OfficioImapStorage|OfficioPop3Storage */
    public $_storage;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var Pdf */
    protected $_pdf;

    /** @var MailerLog */
    protected $_mailerLog;

    /** @var Forms */
    protected $_forms;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    /** @var Encryption */
    protected $_encryption;

    /** @var CommsManager */
    protected $_commsManager;

    /** @var OAuth2Client */
    protected $_oauth2Client;

    public function initAdditionalServices(array $services)
    {
        $this->_oauth2Client      = $services[OAuth2Client::class];
        $this->_company           = $services[Company::class];
        $this->_files             = $services[Files::class];
        $this->_pdf               = $services[Pdf::class];
        $this->_forms             = $services[Forms::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
        $this->_encryption        = $services[Encryption::class];
        $this->_commsManager      = $services[CommsManager::class];
    }

    /**
     * @return MailerLog
     */
    public function getMailerLog()
    {
        if (is_null($this->_mailerLog)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_mailerLog = $this->_serviceContainer->build(MailerLog::class, ['parent' => $this]);
            } else {
                $this->_mailerLog = $this->_serviceContainer->get(MailerLog::class);
                $this->_mailerLog->setParent($this);
            }
        }

        return $this->_mailerLog;
    }

    /**
     * Prefix that is used for mail uid when saving email from file to inbox
     *
     * @return string
     */
    public function getUniquesEmailPrefix()
    {
        return Message::EMAIL_PREFIX;
    }


    /**
     * Check if the current user has access to specific emails (by their ids)
     * @param array|int $arrMailIds
     * @return bool true if the user has access
     */
    public function hasAccessToMail($memberId, $arrMailIds)
    {
        $booCanAccess = false;

        $arrIds   = array();
        $accounts = MailAccount::getAccounts($memberId);
        foreach ($accounts as $arrAccountInfo) {
            $arrIds[] = $arrAccountInfo['id'];
        }

        if (count($accounts)) {
            $arrMailIds = (array)$arrMailIds;
            foreach ($arrMailIds as $mailId) {
                if (is_numeric($mailId) && !empty($mailId)) {
                    $messageModel = Message::createFromMailId($mailId);
                    if ($messageModel && $arrMailInfo = $messageModel->getEmailInfo()) {
                        if (in_array($arrMailInfo['id_account'], $arrIds)) {
                            $booCanAccess = true;
                            break;
                        }
                    }
                }
            }
        }

        return $booCanAccess;
    }

    /**
     * Connect to real POP3/IMAP account.
     *
     * @param array $account
     * @param null|LoaderDispatcher $loaderDispatcher
     * @return void
     */
    private function connectToAccount($account, ?LoaderDispatcher $loaderDispatcher = null)
    {
        $this->accountCredentials = $account;

        $loaderDispatcher?->change("Connecting to {$account['email']}...");

        $accessToken = null;
        if (($account['inc_login_type'] ?? '') === 'oauth2') {
            list($strError, $accessToken) = $this->_oauth2Client->getAccessToken($account['member_id'], $account['inc_host'], $account['email'], $account['inc_type']);

            if (!empty($strError)) {
                throw new Exception($strError);
            }
        }

        try {
            $this->_storage = MailAccount::createStorage($account, $accessToken);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function decodeBase64Header($str)
    {
        // Make sure that it has ?= at the end
        $str2 = substr($str ?? '', -2) == '?=' ? $str : $str . '?=';
        preg_match_all('/=\?([^?]+)\?([B|Q])\?([^?]+)\?=/i', $str2, $match);

        if (count($match[1]) == 0) {
            // Strip all non-ASCII characters
            // TODO Add support for non-ASCII characters
            return self::strip4ByteSequences($str);
        }

        $str = $str2;
        $res = array();
        foreach ($match[2] as $key => $encodingType) {
            switch (strtoupper($encodingType)) {
                case 'Q':
                    $res[$key] = quoted_printable_decode($match[3][$key] ?? '');
                    break;
                case 'B':
                    $res[$key] = base64_decode($match[3][$key]);
                    break;
            }
            if (!empty($match[1][$key]) && (strtolower($match[1][$key]) != 'utf-8')) {
                $convertedString = self::convertStringEncoding($res[$key], $match[1][$key], 'UTF-8');
                $res[$key]       = $convertedString ?? '';
            }
        }

        // Can be several encoded parts in single string
        foreach ($res as $key => $val) {
            $str = str_replace('=?' . $match[1][$key] . '?' . $match[2][$key] . '?' . $match[3][$key] . '?=', $val, $str);
        }

        // Strip all non-ASCII characters
        // TODO Add support for non-ASCII characters
        return self::strip4ByteSequences($str);
    }

    /**
     * Strips all non-printable characters from the string.
     * This is a temporary solution against extended UTF8 symbols.
     * @param $str
     * @return string|string[]|null
     */
    public static function strip4ByteSequences($str)
    {
        //$str = preg_replace('/[^[:print:]]/u', '', $str);
        return preg_replace(
            '%(?:
                  \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
                | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
                | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )%x',
            '',
            $str
        );
        // return $str;
    }

    /**
     * @return mixed
     */
    public function getSqlQueryMaxLength()
    {
        $info = $this->_db2->query("show variables like 'max_allowed_packet'", Adapter::QUERY_MODE_EXECUTE)->current();
        return $info['Value'];
    }

    /**
     * Check if content size can be saved in DB
     *
     * @param int $size
     * @return bool true if email can be saved
     */
    public function isContentSizeAllowed($size)
    {
        return $size < $this->getSqlQueryMaxLength() - 3 * 1024;
    }

    /**
     * Extracts timestamp from Received email header
     * @param string $receivedHeader
     * @return false|int
     */
    private function extractReceivedDatetimeFromReceivedHeader($receivedHeader)
    {
        $tokens = explode(';', $receivedHeader ?? '');
        if (!empty($tokens)) {
            $lastToken = trim(array_pop($tokens));
            $time      = strtotime($lastToken);
            if ($time) {
                return $time;
            }
        }
        return false;
    }

    /**
     * Parse email message
     * @param \Officio\Email\Storage\Message $msg
     * @return array
     */
    private function parseEmailMessage($msg)
    {
        $bodyHtml       = '';
        $bodyPlain      = '';
        $arrAttachments = array();

        if ($msg->isMultipart()) {
            // Multipart Mime Message
            $partsIterator = new RecursiveIteratorIterator($msg);

            $arrAttachmentsFileNames = array();
            /** @var Storage\Part $part */
            foreach ($partsIterator as $part) {
                try {
                    $filename                = null;
                    $mimeType                = null;
                    $charset                 = null;
                    $contentTransferEncoding = null;
                    $contentType             = null;

                    if ($part->getHeaders()->has('Content-Disposition')) {
                        $contentDisposition = $part->getHeader('Content-Disposition');
                        if ($contentDisposition instanceof ContentDisposition) {
                            $filename = $contentDisposition->getParameter('filename');
                        }
                    }

                    if ($part->getHeaders()->has('Content-Type')) {
                        $contentType = $part->getHeader('Content-Type');
                        if ($contentType instanceof ContentType) {
                            if (!$filename) {
                                $filename = $contentType->getParameter('name');
                            }
                            $mimeType    = $contentType->getType();
                            $charset     = $contentType->getParameter('charset');
                            $contentType = $contentType->getFieldValue();
                        }
                    }

                    if ($part->getHeaders()->has('Content-Transfer-Encoding')) {
                        $contentTransferEncoding = $part->getHeader('Content-Transfer-Encoding');
                        if ($contentTransferEncoding instanceof ContentTransferEncoding) {
                            $contentTransferEncoding = $contentTransferEncoding->getFieldValue();
                        }
                    }

                    // In some cases file name is not provided
                    if ($mimeType === 'message/rfc822' && empty($filename)) {
                        $filename = 'email.eml';
                    }

                    // In some cases content type is not provided
                    // so, we think this is plain text
                    if (empty($mimeType) && empty($bodyPlain)) {
                        $mimeType = 'text/plain';
                    }

                    switch ($mimeType) {
                        case 'text/plain' :
                            $content = $part->getContent() ?? '';
                            if (!empty($filename)) {
                                // this is an attachment
                                $attachmentFileName = self::decodeBase64Header($filename);
                                if (!isset($arrAttachmentsFileNames[$attachmentFileName])) {
                                    $arrAttachmentInfo = array(
                                        'mime'     => $mimeType,
                                        'filename' => $attachmentFileName,
                                        'data' => $this->decodeBody($content, $contentTransferEncoding, $charset, true)
                                    );

                                    if (isset($part->{'content-id'})) {
                                        $arrAttachmentInfo['content-id'] = $part->{'content-id'};
                                    }

                                    $arrAttachments[] = $arrAttachmentInfo;

                                    $arrAttachmentsFileNames[$attachmentFileName] = 1;
                                }
                            } else {
                                // this is a simple content
                                if (!$this->isContentSizeAllowed(strlen($content))) {
                                    return array('error' => 'body is too long');
                                }
                                $bodyPlain .= nl2br(htmlspecialchars($this->decodeBody($content, $contentTransferEncoding, $charset)));
                            }
                            break;

                        case 'text/html' :
                            // this is a simple content
                            $content = $part->getContent();
                            if (!$this->isContentSizeAllowed(strlen($content))) {
                                return array('error' => 'body is too long');
                            }
                            $bodyHtml .= $this->decodeBody($content, $contentTransferEncoding, $charset);
                            break;

                        default:
                            if (!empty($filename)) {
                                // this is an attachment
                                $attachmentFileName = self::decodeBase64Header($filename);
                                if (!isset($arrAttachmentsFileNames[$attachmentFileName])) {
                                    $content = $part->getContent();

                                    $arrAttachmentInfo = array(
                                        'mime'     => $mimeType,
                                        'filename' => $attachmentFileName,
                                        'data' => $this->decodeBody($content, $contentTransferEncoding, $charset, true)
                                    );

                                    if (isset($part->{'content-id'})) {
                                        $arrAttachmentInfo['content-id'] = $part->{'content-id'};
                                    }

                                    $arrAttachments[] = $arrAttachmentInfo;

                                    $arrAttachmentsFileNames[$attachmentFileName] = 1;
                                }
                            } else {
                                // It's simple content
                                $content = $msg->getContent();
                                if (!$this->isContentSizeAllowed(strlen($content))) {
                                    return array('error' => 'body is too long');
                                }
                                $body = $this->decodeBody($content, $contentTransferEncoding, $charset) ?? '';

                                // TODO These checks don't even make much sense
                                $bodyHtml  .= strpos($contentType, 'text/plain') === 0 ? nl2br(htmlspecialchars($body)) : '';
                                $bodyPlain .= strpos($contentType, 'text/plain') !== 0 ? $body : '';
                            }
                    }
                } catch (Exception $e) {
                    if ($e->getMessage() != 'cannot read - connection closed?') {
                        $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                    }
                }
            }

            // check for additional attachments (forced)
            try {
                $messageParser = new MessageParser();

                $m = $messageParser->returnPreparedMessageArray(
                    $msg,
                    array(
                        'tmpFolderBaseName' => $this->_config['directory']['tmp'],
                        'id'                => 888000555,
                        'folder'            => 'folderName',
                        'uniqueId'          => '1024UID'
                    )
                );

                if (isset($m['attachmentsList'])) {
                    foreach ($m['attachmentsList'] as $attachment) {
                        $attachmentFileName = self::decodeBase64Header($attachment['filenameOriginal']);
                        if (!isset($arrAttachmentsFileNames[$attachmentFileName])) {
                            $arrAttachments[] = array(
                                'mime'     => $attachment['mimeType'],
                                'filename' => $attachmentFileName,
                                'data'     => $attachment['content']
                            );

                            $arrAttachmentsFileNames[$attachmentFileName] = 1;
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore
            }
        } else {
            // Plain text message
            $filename                = null;
            $mimeType                = null;
            $charset                 = null;
            $contentTransferEncoding = null;
            $contentType             = null;

            if ($msg->getHeaders()->has('Content-Disposition')) {
                $contentDisposition = $msg->getHeader('Content-Disposition');
                if ($contentDisposition instanceof ContentDisposition) {
                    $filename = $contentDisposition->getParameter('filename');
                }
            }

            if ($msg->getHeaders()->has('Content-Type')) {
                $contentType = $msg->getHeader('Content-Type');
                if ($contentType instanceof ContentType) {
                    if (!$filename) {
                        $filename = $contentType->getParameter('name');
                    }
                    $mimeType    = $contentType->getType();
                    $charset     = $contentType->getParameter('charset');
                    $contentType = $contentType->getFieldValue();
                }
            }

            if ($msg->getHeaders()->has('Content-Transfer-Encoding')) {
                $contentTransferEncoding = $msg->getHeader('Content-Transfer-Encoding');
                if ($contentTransferEncoding instanceof ContentTransferEncoding) {
                    $contentTransferEncoding = $contentTransferEncoding->getFieldValue();
                }
            }

            // In some cases file name is not provided
            if ($mimeType === 'message/rfc822' && empty($filename)) {
                $filename = 'email.eml';
            }

            // In some cases content type is not provided
            // so we think this is plain text
            if (empty($mimeType) && empty($bodyPlain)) {
                $mimeType = 'text/plain';
            }

            if (!empty($filename)) {
                // this is an attachment
                $arrAttachmentInfo = array(
                    'mime'     => $mimeType,
                    'filename' => self::decodeBase64Header($filename),
                    'data' => $this->decodeBody($msg->getContent(), $contentTransferEncoding, $charset, true)
                );

                if (isset($msg->{'content-id'})) {
                    $arrAttachmentInfo['content-id'] = $msg->{'content-id'};
                }

                $arrAttachments[] = $arrAttachmentInfo;
            } else {
                // this is a simple content
                $content = $msg->getContent();

                if (!$this->isContentSizeAllowed(strlen($content))) {
                    return array('error' => 'body is too long');
                }

                if ($msg instanceof ImapMessage) {
                    $attachmentParts = $msg->getAttachmentsList();
                    if (!empty($attachmentParts)) {
                        foreach ($attachmentParts as $attachPart) {
                            $arrAttachments[] = array(
                                'mime'      => $attachPart['type'],
                                'filename'  => self::decodeBase64Header($attachPart['params']['name']),
                                'data'      => '',
                                'part_info' => $attachPart,
                            );
                        }
                    }
                }

                $body = $this->decodeBody($content, $contentTransferEncoding, $charset) ?? '';

                // TODO These checks don't even make much sense
                $bodyHtml  .= strpos($contentType, 'text/plain') === 0 ? nl2br(htmlspecialchars($body)) : '';
                $bodyPlain .= strpos($contentType, 'text/plain') !== 0 ? $body : '';
            }
        }

        return array(
            'html'        => !empty($bodyHtml) ? self::strip4ByteSequences($bodyHtml) : $bodyPlain,
            'attachments' => $arrAttachments
        );
    }

    /**
     * Decodes mail body
     * @param $body
     * @param string $contentTransferEncoding
     * @param string $charset
     * @param $isAttach
     * @return array|false|mixed|string|string[]|null
     * @throws Exception
     */
    private function decodeBody($body, $contentTransferEncoding, $charset, $isAttach = false)
    {
        // Don't try to decode if the passed encoding isn't a string
        if (!is_string($contentTransferEncoding)) {
            return $body;
        }

        switch (strtolower($contentTransferEncoding)) {
            case 'base64' :
                $body = base64_decode($body);
                break;
            case '8bit' :
                // $body = imap_8bit($body);
            case '7bit' :
            case 'quoted-printable' :
                $body = quoted_printable_decode($body ?? '');
                break;
        }

        if ($isAttach) {
            // Nothing to convert; we don't need to convert encoding in attachments; (may be...)
            return $body;
        }

        try {
            if (is_null($charset) || (strtoupper($charset) === 'UTF-8')) {
                $body = iconv('UTF-8', 'UTF-8//IGNORE', $body);
            } else {
                $body = self::convertStringEncoding($body, $charset, 'UTF-8');
            }
        } catch (Exception $e) {
            $body = '';
        }

        return $body;
    }

    /**
     * Convert string from one encoding to another using iconv and then mbstring
     * @param string $string
     * @param string $fromEncoding
     * @param string $toEncoding
     * @return null|string Converted string or null in case of failure
     */
    public static function convertStringEncoding(string $string, string $fromEncoding, string $toEncoding): ?string
    {
        $encodingAliases = array(
            //            'ascii'=>'us-ascii',
            //            'us-ascii'=>'us-ascii',
            //            'ansi_x3.4-1968'=>'us-ascii',
            //            '646'=>'us-ascii',
            //            'iso-8859-1'=>'ISO-8859-1',
            //            'iso-8859-2'=>'ISO-8859-2',
            //            'iso-8859-3'=>'ISO-8859-3',
            //            'iso-8859-4'=>'ISO-8859-4',
            //            'iso-8859-5'=>'ISO-8859-5',
            //            'iso-8859-6'=>'ISO-8859-6',
            //            'iso-8859-6-i'=>'ISO-8859-6-I',
            //            'iso-8859-6-e'=>'ISO-8859-6-E',
            //            'iso-8859-7'=>'ISO-8859-7',
            //            'iso-8859-8'=>'ISO-8859-8',
            //            'iso-8859-8-i'=>'ISO-8859-8-I',
            //            'iso-8859-8-e'=>'ISO-8859-8-E',
            //            'iso-8859-9'=>'ISO-8859-9',
            //            'iso-8859-10'=>'ISO-8859-10',
            //            'iso-8859-11'=>'ISO-8859-11',
            //            'iso-8859-13'=>'ISO-8859-13',
            //            'iso-8859-14'=>'ISO-8859-14',
            //            'iso-8859-15'=>'ISO-8859-15',
            //            'iso-8859-16'=>'ISO-8859-16',
            //            'iso-ir-111'=>'ISO-IR-111',
            //            'iso-2022-cn'=>'ISO-2022-CN',
            //            'iso-2022-cn-ext'=>'ISO-2022-CN',
            //            'iso-2022-kr'=>'ISO-2022-KR',
            //            'iso-2022-jp'=>'ISO-2022-JP',
            //            'utf-16be'=>'UTF-16BE',
            //            'utf-16le'=>'UTF-16LE',
            //            'utf-16'=>'UTF-16',
            //            'windows-1250'=>'windows-1250',
            //            'windows-1251'=>'windows-1251',
            //            'windows-1252'=>'windows-1252',
            //            'windows-1253'=>'windows-1253',
            //            'windows-1254'=>'windows-1254',
            //            'windows-1255'=>'windows-1255',
            //            'windows-1256'=>'windows-1256',
            //            'windows-1257'=>'windows-1257',
            //            'windows-1258'=>'windows-1258',
            //            'ibm866'=>'IBM866',
            //            'ibm850'=>'IBM850',
            //            'ibm852'=>'IBM852',
            //            'ibm855'=>'IBM855',
            //            'ibm857'=>'IBM857',
            //            'ibm862'=>'IBM862',
            //            'ibm864'=>'IBM864',
            //            'utf-8'=>'UTF-8',
            //            'utf-7'=>'UTF-7',
            //            'shift_jis'=>'Shift_JIS',
            //            'big5'=>'Big5',
            //            'euc-jp'=>'EUC-JP',
            //            'euc-kr'=>'EUC-KR',
            //            'gb2312'=>'GB2312',
            //            'gb18030'=>'gb18030',
            //            'viscii'=>'VISCII',
            //            'koi8-r'=>'KOI8-R',
            //            'koi8_r'=>'KOI8-R',
            //            'cskoi8r'=>'KOI8-R',
            //            'koi'=>'KOI8-R',
            //            'koi8'=>'KOI8-R',
            //            'koi8-u'=>'KOI8-U',
            //            'tis-620'=>'TIS-620',
            //            't.61-8bit'=>'T.61-8bit',
            //            'hz-gb-2312'=>'HZ-GB-2312',
            //            'big5-hkscs'=>'Big5-HKSCS',
            //            'gbk'=>'gbk',
            //            'cns11643'=>'x-euc-tw',
            //            'x-imap4-modified-utf7'=>'x-imap4-modified-utf7',
            //            'x-euc-tw'=>'x-euc-tw',
            //            'x-mac-ce'=>'x-mac-ce',
            //            'x-mac-turkish'=>'x-mac-turkish',
            //            'x-mac-greek'=>'x-mac-greek',
            //            'x-mac-icelandic'=>'x-mac-icelandic',
            //            'x-mac-croatian'=>'x-mac-croatian',
            //            'x-mac-romanian'=>'x-mac-romanian',
            //            'x-mac-cyrillic'=>'x-mac-cyrillic',
            //            'x-mac-ukrainian'=>'x-mac-cyrillic',
            //            'x-mac-hebrew'=>'x-mac-hebrew',
            //            'x-mac-arabic'=>'x-mac-arabic',
            //            'x-mac-farsi'=>'x-mac-farsi',
            //            'x-mac-devanagari'=>'x-mac-devanagari',
            //            'x-mac-gujarati'=>'x-mac-gujarati',
            //            'x-mac-gurmukhi'=>'x-mac-gurmukhi',
            //            'armscii-8'=>'armscii-8',
            //            'x-viet-tcvn5712'=>'x-viet-tcvn5712',
            //            'x-viet-vps'=>'x-viet-vps',
            //            'iso-10646-ucs-2'=>'UTF-16BE',
            //            'x-iso-10646-ucs-2-be'=>'UTF-16BE',
            //            'x-iso-10646-ucs-2-le'=>'UTF-16LE',
            //            'x-user-defined'=>'x-user-defined',
            //            'x-johab'=>'x-johab',
            //            'latin1'=>'ISO-8859-1',
            //            'iso_8859-1'=>'ISO-8859-1',
            //            'iso8859-1'=>'ISO-8859-1',
            //            'iso8859-2'=>'ISO-8859-2',
            //            'iso8859-3'=>'ISO-8859-3',
            //            'iso8859-4'=>'ISO-8859-4',
            //            'iso8859-5'=>'ISO-8859-5',
            //            'iso8859-6'=>'ISO-8859-6',
            //            'iso8859-7'=>'ISO-8859-7',
            //            'iso8859-8'=>'ISO-8859-8',
            //            'iso8859-9'=>'ISO-8859-9',
            //            'iso8859-10'=>'ISO-8859-10',
            //            'iso8859-11'=>'ISO-8859-11',
            //            'iso8859-13'=>'ISO-8859-13',
            //            'iso8859-14'=>'ISO-8859-14',
            //            'iso8859-15'=>'ISO-8859-15',
            //            'iso_8859-1:1987'=>'ISO-8859-1',
            //            'iso-ir-100'=>'ISO-8859-1',
            //            'l1'=>'ISO-8859-1',
            //            'ibm819'=>'ISO-8859-1',
            //            'cp819'=>'ISO-8859-1',
            //            'csisolatin1'=>'ISO-8859-1',
            //            'latin2'=>'ISO-8859-2',
            //            'iso_8859-2'=>'ISO-8859-2',
            //            'iso_8859-2:1987'=>'ISO-8859-2',
            //            'iso-ir-101'=>'ISO-8859-2',
            //            'l2'=>'ISO-8859-2',
            //            'csisolatin2'=>'ISO-8859-2',
            //            'latin3'=>'ISO-8859-3',
            //            'iso_8859-3'=>'ISO-8859-3',
            //            'iso_8859-3:1988'=>'ISO-8859-3',
            //            'iso-ir-109'=>'ISO-8859-3',
            //            'l3'=>'ISO-8859-3',
            //            'csisolatin3'=>'ISO-8859-3',
            //            'latin4'=>'ISO-8859-4',
            //            'iso_8859-4'=>'ISO-8859-4',
            //            'iso_8859-4:1988'=>'ISO-8859-4',
            //            'iso-ir-110'=>'ISO-8859-4',
            //            'l4'=>'ISO-8859-4',
            //            'csisolatin4'=>'ISO-8859-4',
            //            'cyrillic'=>'ISO-8859-5',
            //            'iso_8859-5'=>'ISO-8859-5',
            //            'iso_8859-5:1988'=>'ISO-8859-5',
            //            'iso-ir-144'=>'ISO-8859-5',
            //            'csisolatincyrillic'=>'ISO-8859-5',
            //            'arabic'=>'ISO-8859-6',
            //            'iso_8859-6'=>'ISO-8859-6',
            //            'iso_8859-6:1987'=>'ISO-8859-6',
            //            'iso-ir-127'=>'ISO-8859-6',
            //            'ecma-114'=>'ISO-8859-6',
            //            'asmo-708'=>'ISO-8859-6',
            //            'csisolatinarabic'=>'ISO-8859-6',
            //            'csiso88596i'=>'ISO-8859-6-I',
            //            'csiso88596e'=>'ISO-8859-6-E',
            //            'greek'=>'ISO-8859-7',
            //            'greek8'=>'ISO-8859-7',
            //            'sun_eu_greek'=>'ISO-8859-7',
            //            'iso_8859-7'=>'ISO-8859-7',
            //            'iso_8859-7:1987'=>'ISO-8859-7',
            //            'iso-ir-126'=>'ISO-8859-7',
            //            'elot_928'=>'ISO-8859-7',
            //            'ecma-118'=>'ISO-8859-7',
            //            'csisolatingreek'=>'ISO-8859-7',
            //            'hebrew'=>'ISO-8859-8',
            //            'iso_8859-8'=>'ISO-8859-8',
            //            'visual'=>'ISO-8859-8',
            //            'iso_8859-8:1988'=>'ISO-8859-8',
            //            'iso-ir-138'=>'ISO-8859-8',
            //            'csisolatinhebrew'=>'ISO-8859-8',
            //            'csiso88598i'=>'ISO-8859-8-I',
            //            'iso-8859-8i'=>'ISO-8859-8-I',
            //            'logical'=>'ISO-8859-8-I',
            //            'csiso88598e'=>'ISO-8859-8-E',
            //            'latin5'=>'ISO-8859-9',
            //            'iso_8859-9'=>'ISO-8859-9',
            //            'iso_8859-9:1989'=>'ISO-8859-9',
            //            'iso-ir-148'=>'ISO-8859-9',
            //            'l5'=>'ISO-8859-9',
            //            'csisolatin5'=>'ISO-8859-9',
            //            'unicode-1-1-utf-8'=>'UTF-8',
            //            'utf8'=>'UTF-8',
            //            'x-sjis'=>'Shift_JIS',
            //            'shift-jis'=>'Shift_JIS',
            //            'ms_kanji'=>'Shift_JIS',
            //            'csshiftjis'=>'Shift_JIS',
            //            'windows-31j'=>'Shift_JIS',
            //            'cp932'=>'Shift_JIS',
            //            'sjis'=>'Shift_JIS',
            //            'cseucpkdfmtjapanese'=>'EUC-JP',
            //            'x-euc-jp'=>'EUC-JP',
            //            'csiso2022jp'=>'ISO-2022-JP',
            //            'iso-2022-jp-2'=>'ISO-2022-JP',
            //            'csiso2022jp2'=>'ISO-2022-JP',
            //            'csbig5'=>'Big5',
            //            'cn-big5'=>'Big5',
            //            'x-x-big5'=>'Big5',
            //            'zh_tw-big5'=>'Big5',
            //            'cseuckr'=>'EUC-KR',
            'ks_c_5601-1987' => 'EUC-KR',
            //            'iso-ir-149'=>'EUC-KR',
            //            'ks_c_5601-1989'=>'EUC-KR',
            //            'ksc_5601'=>'EUC-KR',
            //            'ksc5601'=>'EUC-KR',
            //            'korean'=>'EUC-KR',
            //            'csksc56011987'=>'EUC-KR',
            //            '5601'=>'EUC-KR',
            //            'windows-949'=>'EUC-KR',
            //            'gb_2312-80'=>'GB2312',
            //            'iso-ir-58'=>'GB2312',
            //            'chinese'=>'GB2312',
            //            'csiso58gb231280'=>'GB2312',
            //            'csgb2312'=>'GB2312',
            //            'zh_cn.euc'=>'GB2312',
            //            'gb_2312'=>'GB2312',
            //            'x-cp1250'=>'windows-1250',
            //            'x-cp1251'=>'windows-1251',
            //            'x-cp1252'=>'windows-1252',
            //            'x-cp1253'=>'windows-1253',
            //            'x-cp1254'=>'windows-1254',
            //            'x-cp1255'=>'windows-1255',
            //            'x-cp1256'=>'windows-1256',
            //            'x-cp1257'=>'windows-1257',
            //            'x-cp1258'=>'windows-1258',
            //            'windows-874'=>'windows-874',
            //            'ibm874'=>'windows-874',
            //            'dos-874'=>'windows-874',
            //            'macintosh'=>'macintosh',
            //            'x-mac-roman'=>'macintosh',
            //            'mac'=>'macintosh',
            //            'csmacintosh'=>'macintosh',
            //            'cp866'=>'IBM866',
            //            'cp-866'=>'IBM866',
            //            '866'=>'IBM866',
            //            'csibm866'=>'IBM866',
            //            'cp850'=>'IBM850',
            //            '850'=>'IBM850',
            //            'csibm850'=>'IBM850',
            //            'cp852'=>'IBM852',
            //            '852'=>'IBM852',
            //            'csibm852'=>'IBM852',
            //            'cp855'=>'IBM855',
            //            '855'=>'IBM855',
            //            'csibm855'=>'IBM855',
            //            'cp857'=>'IBM857',
            //            '857'=>'IBM857',
            //            'csibm857'=>'IBM857',
            //            'cp862'=>'IBM862',
            //            '862'=>'IBM862',
            //            'csibm862'=>'IBM862',
            //            'cp864'=>'IBM864',
            //            '864'=>'IBM864',
            //            'csibm864'=>'IBM864',
            //            'ibm-864'=>'IBM864',
            //            't.61'=>'T.61-8bit',
            //            'iso-ir-103'=>'T.61-8bit',
            //            'csiso103t618bit'=>'T.61-8bit',
            //            'x-unicode-2-0-utf-7'=>'UTF-7',
            //            'unicode-2-0-utf-7'=>'UTF-7',
            //            'unicode-1-1-utf-7'=>'UTF-7',
            //            'csunicode11utf7'=>'UTF-7',
            //            'csunicode'=>'UTF-16BE',
            //            'csunicode11'=>'UTF-16BE',
            //            'iso-10646-ucs-basic'=>'UTF-16BE',
            //            'csunicodeascii'=>'UTF-16BE',
            //            'iso-10646-unicode-latin1'=>'UTF-16BE',
            //            'csunicodelatin1'=>'UTF-16BE',
            //            'iso-10646'=>'UTF-16BE',
            //            'iso-10646-j-1'=>'UTF-16BE',
            //            'latin6'=>'ISO-8859-10',
            //            'iso-ir-157'=>'ISO-8859-10',
            //            'l6'=>'ISO-8859-10',
            //            'csisolatin6'=>'ISO-8859-10',
            //            'iso_8859-15'=>'ISO-8859-15',
            //            'csisolatin9'=>'ISO-8859-15',
            //            'l9'=>'ISO-8859-15',
            //            'ecma-cyrillic'=>'ISO-IR-111',
            //            'csiso111ecmacyrillic'=>'ISO-IR-111',
            //            'csiso2022kr'=>'ISO-2022-KR',
            //            'csviscii'=>'VISCII',
            //            'zh_tw-euc'=>'x-euc-tw',
            //            'iso88591'=>'ISO-8859-1',
            //            'iso88592'=>'ISO-8859-2',
            //            'iso88593'=>'ISO-8859-3',
            //            'iso88594'=>'ISO-8859-4',
            //            'iso88595'=>'ISO-8859-5',
            //            'iso88596'=>'ISO-8859-6',
            //            'iso88597'=>'ISO-8859-7',
            //            'iso88598'=>'ISO-8859-8',
            //            'iso88599'=>'ISO-8859-9',
            //            'iso885910'=>'ISO-8859-10',
            //            'iso885911'=>'ISO-8859-11',
            //            'iso885912'=>'ISO-8859-12',
            //            'iso885913'=>'ISO-8859-13',
            //            'iso885914'=>'ISO-8859-14',
            //            'iso885915'=>'ISO-8859-15',
            //            'tis620'=>'TIS-620',
            //            'cp1250'=>'windows-1250',
            //            'cp1251'=>'windows-1251',
            //            'cp1252'=>'windows-1252',
            //            'cp1253'=>'windows-1253',
            //            'cp1254'=>'windows-1254',
            //            'cp1255'=>'windows-1255',
            //            'cp1256'=>'windows-1256',
            //            'cp1257'=>'windows-1257',
            //            'cp1258'=>'windows-1258',
            //            'x-gbk'=>'gbk',
            //            'windows-936'=>'gbk',
            //            'ansi-1251'=>'windows-1251',
        );
        $fromEncoding    = strtolower($fromEncoding);
        if (isset($encodingAliases[$fromEncoding])) {
            $fromEncoding = $encodingAliases[$fromEncoding];
        }

        if (!$string || $fromEncoding == strtolower($toEncoding)) {
            return $string;
        }

        try {
            // Try iconv first, mbstring second
            $convertedString = function_exists('iconv')
                ? iconv($fromEncoding, $toEncoding . '//IGNORE', $string)
                : mb_convert_encoding($string, $toEncoding, $fromEncoding);
        } catch (Exception $e) {
            $convertedString = null;
        }

        return $convertedString ?: null;
    }

    private function emailForm2Array(array $msg)
    {
        return array(
            'uid'             => $msg['uid'],
            'id_account'      => $msg['id_account'],
            'from'            => $msg['from'],
            'to'              => $msg['email'],
            'cc'              => $msg['cc'],
            'bcc'             => $msg['bcc'],
            'subject'         => $msg['subject'],
            'sent_date'       => time(),
            'has_attachments' => $msg['has_attachments'],
            'body_html'       => self::strip4ByteSequences($msg['message']),
            'size'            => strlen($msg['message'] ?? ''),
            'seen'            => 1,
            'priority'        => 3, // TODO: fix it
            'x_spam'          => 0,
            'replied'         => $msg['replied'],
            'forwarded'       => $msg['forwarded']
        );
    }

    /**
     * Save attachment(s) in DB + as file
     * Also replace cid for inline attachments
     *
     * @param array $attachments
     * @param $msgId
     * @param string $htmlBody
     * @return string $htmlBody with replaced inline attachments
     */
    public function saveAttachmentPartsToDb(array $attachments, $msgId, $htmlBody = '')
    {
        foreach ($attachments as $attachment) {
            $fileName = empty($attachment['filename']) ? 'UNKNOWN' : $attachment['filename'];
            $attachId = Attachment::addAttachment($msgId, $fileName, $attachment['part_info']);

            // if it's inline attachment:
            if ($attachment['part_info']['attachment_type'] == 'inline') {
                $cid = $attachment['part_info']['id'];

                // download and store it physically
                $attachment = $this->fetchAttachment($msgId, array_merge($attachment['part_info'], array('id' => $attachId)));
                if ($attachment) {
                    $this->saveAttachmentsToDb(array($attachment), $msgId, '');
                }

                // replace all `cid` in email body to valid urls on attachments
                /** @var Layout $layout */
                $layout   = $this->_viewHelperManager->get('layout');
                $htmlBody = str_replace('cid:' . substr($cid, 1, -1), $layout()->getVariable('baseUrl') . '/mailer/index/download-attach?attach_id=' . $attachId, $htmlBody);
            }
        }

        return $htmlBody;
    }

    /**
     * Save attachment(s) to DB + file (local or remote)
     * @param array $attachments
     * @param $msgId
     * @param $htmlBody
     * @param bool $memberIdDefault
     * @param bool $companyIdDefault
     * @return string|string[]|null
     */
    public function saveAttachmentsToDb(array $attachments, $msgId, $htmlBody, $memberIdDefault = false, $companyIdDefault = false)
    {
        try {
            $memberId         = $memberIdDefault ?: $this->_auth->getCurrentUserId();
            $companyId        = $companyIdDefault ?: $this->_auth->getCurrentUserCompanyId();
            $booLocal         = $this->_company->isCompanyStorageLocationLocal($companyId);
            $emlFolder        = $this->_files->getMemberEmailAttachmentsPath($companyId, $memberId, $booLocal);
            $companyEmailPath = $this->_files->getCompanyEmailAttachmentsPath($companyId, $booLocal) . '/';

            foreach ($attachments as $attachment) {
                if (is_array($attachment) && !empty($attachment)) {
                    if (!array_key_exists('already_created_in_db_id', $attachment)) { // no need to add already added attach; we'll update record later in 'updateAttachmentsPath' method
                        $fileName = empty($attachment['filename']) ? 'UNKNOWN' : $attachment['filename'];
                        $attachId = Attachment::addAttachment($msgId, $fileName);
                    } else {
                        $attachId = $attachment['already_created_in_db_id'];
                    }

                    $filePath = $this->storagePath($attachment['filename'], $emlFolder) . $attachId;

                    if ($booLocal) {
                        $booCreated = $this->_files->createFile($filePath, $attachment['data']);
                    } else {
                        $booCreated = $this->_files->getCloud()->createObject($filePath, $attachment['data']);
                    }

                    if (!$booCreated) {
                        throw new Exception('Can\'t store attachment to: ' . $filePath);
                    }

                    $attachmentsModel = new Attachment($attachId);
                    $attachmentsModel->updateAttachment(array('path' => substr($filePath, strlen($companyEmailPath)), 'is_downloaded' => 1, 'part_info' => ''));

                    $idKey = isset($attachment['content-id']) ? 'content-id' : (isset($attachment['id']) ? 'id' : '');
                    if (!empty($idKey) && !empty($htmlBody)) {
                        /** @var Layout $layout */
                        $layout   = $this->_viewHelperManager->get('layout');
                        $htmlBody = str_replace('cid:' . substr($attachment[$idKey], 1, -1), $layout()->getVariable('baseUrl') . '/mailer/index/download-attach?attach_id=' . $attachId, $htmlBody);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }

        return self::strip4ByteSequences($htmlBody);
    }

    /**
     * Generate full path to the file (attachment) by its name
     *
     * @param $fileName
     * @param $emlFolder
     * @return string
     */
    private function storagePath($fileName, $emlFolder)
    {
        return $emlFolder . $this->getStorageFolderPath($fileName);
    }

    /**
     * Generate path to the file (attachment) by its name
     *
     * @param $fileName
     * @return string
     */
    private function getStorageFolderPath($fileName)
    {
        $md5 = md5($fileName ?? '');

        $firstLiteral = array($md5[0], $md5[7] /* $md5{3}, $md5{11} */);

        return '/' . implode('/', $firstLiteral) . '/';
    }

    /**
     * Load flags list (as they are saved in DB)
     * @NOTE the same list is hardcoded in js too
     *
     * @return array
     */
    public function getMailFlags()
    {
        return array(
            0 => 'empty',
            1 => 'red',
            2 => 'blue',
            3 => 'yellow',
            4 => 'green',
            5 => 'orange',
            6 => 'purple',
            7 => 'complete',
        );
    }

    public function getMailFlagId($strFlag)
    {
        $arrFlags  = $this->getMailFlags();
        $intFlagId = 0;
        foreach ($arrFlags as $key => $val) {
            if ($val == $strFlag) {
                $intFlagId = $key;
                break;
            }
        }

        return $intFlagId;
    }

    public function updateMailFlag($mailId, $intFlagId)
    {
        $messageModel = Message::createFromMailId($mailId);
        return $messageModel->updateEmail(array('flag' => $intFlagId));
    }

    private function disconnect()
    {
        $this->_storage->close();
        $this->_storage = null;
    }

    public function isIMAP()
    {
        return $this->_storage instanceof Imap;
    }

    /**
     * @param $accountId
     * @param LoaderDispatcher|null $loaderDispatcher
     * @return array
     * @throws Exception
     */
    public function connect($accountId, ?LoaderDispatcher $loaderDispatcher = null)
    {
        $mailAccount             = new MailAccount($accountId);
        $account                 = $mailAccount->getAccountDetails();
        $account['inc_password'] = empty($account['inc_password']) ? '' : $this->_encryption->decode($account['inc_password']);
        $account['out_password'] = empty($account['out_password']) ? '' : $this->_encryption->decode($account['out_password']);

        $booVerifySslCert      = (bool)$this->_config['mail']['verify_ssl_certificate'];
        $account['verify_ssl'] = $booVerifySslCert;

        // optimization:
        if ($this->_storage instanceof AbstractStorage) {
            return $account;
        }

        try {
            $this->connectToAccount($account, $loaderDispatcher);
        } catch (Exception $e) {
            throw new Exception("Can't connect to email account.");
        }

        return $account;
    }

    /**
     * @param $folderId
     * @param $parentFolderId
     * @param $order
     * @return bool
     */
    public function moveFolder($folderId, $parentFolderId, $order)
    {
        $folder     = new Folder($folderId);
        $folderInfo = $folder->getFolderInfo();

        // Only selectable folders can be moved for IMAP
        if ($this->_storage instanceof Imap && empty($folderInfo['selectable'])) {
            return false;
        }

        $accountId = $folderInfo['id_account'];

        $parentFolder = null;
        $parentInfo   = array();
        if (!empty($parentFolderId)) {
            // Empty folder id = root folder
            $parentFolder = new Folder($parentFolderId);
            $parentInfo   = $parentFolder->getFolderInfo();
        }

        $old_folder_order = $folderInfo['order'];
        $old_level        = $folderInfo['level'];
        $old_parent_id    = $folderInfo['id_parent'];

        $newLevel = isset($parentInfo['id']) && !empty($parentInfo['id']) ? $parentInfo['level'] + 1 : 0;
        $newOrder = $order;

        $folderFullPath = (isset($parentInfo['full_path']) && !empty($parentInfo['full_path']) ? $parentInfo['full_path'] . '/' : '') . $folderInfo['label'];

        if ($this->_storage instanceof Imap) {
            $mailAccountManager = new MailAccount($accountId);
            $delimiter          = $mailAccountManager->getDelimiter($this->_storage);
            $folderFullPath     = str_replace('/', $delimiter, $folderFullPath);
        }

        $folder->updateFolder($parentFolderId, $newLevel, $newOrder, $folderFullPath);

        // get all subfolders
        $subFolders = $folder->getSubfoldersIds(true);

        $subFoldersListToDelete = array();
        foreach ($subFolders as $subFolderId) {
            $subFolder                                           = new Folder($subFolderId);
            $subFolderInfo                                       = $subFolder->getFolderInfo();
            $subFoldersListToDelete[(int)$folderInfo['level']][] = $subFolderInfo['full_path'];
        }
        ksort($subFoldersListToDelete, SORT_NUMERIC);
        $subFoldersListToDelete = array_reverse($subFoldersListToDelete);

        if (!empty($subFolders)) {
            $folder->updateSubFoldersFullPath($folderFullPath);
        }

        // change all orders for folders, which are on this level and under this parent
        if ($newLevel == $old_level) { // one level
            if ($order < $old_folder_order) {
                $inc = "+1";

                $order_condition = (new Where())
                    ->nest()
                    ->greaterThanOrEqualTo('order', $newOrder)
                    ->and
                    ->lessThanOrEqualTo('order', $old_folder_order)
                    ->unnest();
            } else {
                $inc = "-1";

                $order_condition = (new Where())
                    ->nest()
                    ->lessThanOrEqualTo('order', $newOrder)
                    ->and
                    ->greaterThanOrEqualTo('order', $old_folder_order)
                    ->unnest();
            }

            $this->_db2->update(
                'eml_folders',
                ["order" => new Expression("(`order` $inc)")],
                [
                    $order_condition,
                    'id_parent'  => $parentFolderId,
                    'level'      => $newLevel,
                    'id_account' => $accountId,
                    (new Where())->notEqualTo('id', $folderId)
                ]
            );
        } else { // diff levels
            // INC order on new level, where order of old items >= new_order AND `id`!=$folderId
            $this->_db2->update(
                'eml_folders',
                ["order" => new Expression("(`order`+1)")],
                [
                    'id_parent'  => $parentFolderId,
                    'order'      => $newOrder,
                    'level'      => $newLevel,
                    'id_account' => $accountId,
                    (new Where())->notEqualTo('id', $folderId)
                ]
            );

            // DEC order on prev level, where order of old items >= old_order
            $this->_db2->update(
                'eml_folders',
                ["order" => new Expression("(`order`-1)")],
                [
                    'id_parent'  => $old_parent_id,
                    'order'      => $old_folder_order,
                    'level'      => $old_level,
                    'id_account' => $accountId
                ]
            );

            // INC/DEC level for all subfolders
            $level_diff = abs($newLevel - $old_level);

            $inc = ($newLevel > $old_level) ? "+$level_diff" : "-$level_diff";

            if (!empty($subFolders)) {
                $this->_db2->update(
                    'eml_folders',
                    ["level" => new Expression("(`level` $inc)")],
                    [
                        'id'         => $subFolders,
                        'id_account' => $accountId
                    ]
                );
            }
        }

        if ($this->_storage instanceof Imap && $folderInfo['full_path'] != $folderFullPath) {
            $movableFolderInImap = false;
            try {
                if ($folderInfo['id_folder'] === '0') {
                    $this->_storage->selectFolder(Folder::encodeFolderName($folderInfo['full_path']));
                    $movableFolderInImap = true;
                }
            } catch (Exception $e) {
                // Don't log here, in some cases an exception is generated (folder isn't selectable)
            }

            $rootFolderInImap = false;

            $rootInfo = [];
            if (!is_null($parentFolder)) {
                $rootParentFolder = new Folder($parentFolder->getRootParentFolderId());
                $rootInfo         = $rootParentFolder->getFolderInfo();
            }

            if (empty($rootInfo)) {
                // if root does not exist - top level make imap
                $rootFolderInImap = true;
            } else {
                try {
                    if ($rootInfo['id_folder'] === '0') {
                        $this->_storage->selectFolder(Folder::encodeFolderName($rootInfo['full_path']));
                        $rootFolderInImap = true;
                    }
                } catch (Exception $e) {
                    // Don't log here
                }
            }

            // from IMAP to IMAP
            if ($movableFolderInImap && $rootFolderInImap) {
                try {
                    $this->_storage->renameFolder(Folder::encodeFolderName($folderInfo['full_path']), Folder::encodeFolderName($folderFullPath));
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                    return false;
                }
            }

            // from IMAP to LOCAL
            if ($movableFolderInImap && !$rootFolderInImap) {
                foreach ($subFoldersListToDelete as $level) {
                    foreach ($level as $subFolderToDelete) {
                        try {
                            $this->_storage->removeFolder(Folder::encodeFolderName($subFolderToDelete));
                        } catch (Exception $e) {
                            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                            return false;
                        }
                    }
                }

                try {
                    $this->_storage->removeFolder(Folder::encodeFolderName($folderInfo['full_path']));
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                    return false;
                }
            }

            // from LOCAL to IMAP
            if (!$movableFolderInImap && $folderInfo['id_folder'] === '0' && $rootFolderInImap) {
                $this->_createFolderForImapFromLocalFolder($accountId, $folderId, $folderFullPath);
            }
        }
        return true;
    }

    /**
     * @param Storage\Folder $remoteFolder
     * @param $folderData
     * @param $allNewEmailsCounter
     * @param $lockFilePath
     * @param LoaderDispatcher|null $loaderDispatcher
     * @param $allNewEmails
     * @param array $arrAccountInfo
     * @param $arrAccountDefaultFolders
     * @param $booOnlyHeaders
     * @param bool $booRefreshFoldersList
     * @return bool
     * @throws CancelException
     * @throws ExceptionInterface
     */
    public function syncFolder(
        Storage\Folder $remoteFolder,
        $folderData,
        &$allNewEmailsCounter,
        $lockFilePath,
        ?LoaderDispatcher $loaderDispatcher,
        $allNewEmails,
        $arrAccountInfo,
        $arrAccountDefaultFolders,
        $booOnlyHeaders,
        $booRefreshFoldersList = false
    ) {
        $folderId   = $folderData['id'];
        $folder     = new Folder($folderId);
        $folderInfo = $folder->getFolderInfo();

        // Skip this folder if:
        // 1. It is not selectable
        // 2. It is not visible
        // 3. There is nothing to download
        // 4. Incorrect account settings
        if (!$remoteFolder->isSelectable() || (is_array($folderInfo) && isset($folderInfo['visible']) && empty($folderInfo['visible'])) || !isset($arrAccountInfo['id'])) {
            return true;
        }

        $accountId   = $arrAccountInfo['id'];
        $mailAccount = new MailAccount($accountId);

        // Select folder, if failed - exit
        try {
            $this->_storage->selectFolder($remoteFolder);
        } catch (Exception $e) {
            if ($e->getMessage() == 'cannot read - connection closed?') {
                $loaderDispatcher?->change($accountId, 'Connecting was refused.');

                $this->disconnect();
                $this->connectToAccount($this->accountCredentials, $loaderDispatcher);
            } else {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }

            return true;
        }

        if (is_array($folderData['uids']) && count($folderData['uids'])) {
            // In relation to the mail account settings:
            // If "only headers" option is turned on - download XX first emails fully (header + body), all others - headers only
            // If "only headers" option is turned off - download all emails fully (header + body + attachments)
            if ($booOnlyHeaders) {
                $arrDownloadEmailsHeaders = $folderData['uids'];
                $arrDownloadEmailsFully   = array();
                $booWithoutAttachments    = true;
            } else {
                $arrDownloadEmailsFully   = $folderData['uids'];
                $arrDownloadEmailsHeaders = array();
                $booWithoutAttachments    = false;
            }


            // Download emails (headers only)
            if (count($arrDownloadEmailsHeaders)) {
                $preparedUidsArray = array();
                foreach ($arrDownloadEmailsHeaders as $emailUidToDownload) {
                    $preparedUidsArray[] = array('UID' => $emailUidToDownload);
                }

                if (!empty($preparedUidsArray)) {
                    while (!empty($preparedUidsArray)) {
                        try {
                            $preparedUidsArrayPushed = array_splice($preparedUidsArray, 0, 50);

                            $messages = $this->_storage->getBasicHeaders(
                                $preparedUidsArrayPushed,
                                null,
                                false,
                                $loaderDispatcher,
                                $lockFilePath,
                                $this,
                                $accountId,
                                $arrAccountDefaultFolders,
                                $allNewEmailsCounter,
                                $allNewEmails
                            );

                            $this->saveMessagesHeadersFromServerToFolder($messages, $accountId, null, $folderId);
                        } catch (CancelException $e) {
                            $loaderDispatcher?->change('Cancelled', 100, $mailAccount->getTotalUnreadCount($arrAccountDefaultFolders), false, $booRefreshFoldersList || $allNewEmailsCounter > 0);
                            throw $e;
                        } catch (Exception $e) {
                            if ($e->getMessage() == 'cannot read - connection closed?') {
                                $loaderDispatcher?->change('Connecting was refused.');

                                $this->disconnect();
                                $this->connectToAccount($this->accountCredentials, $loaderDispatcher);
                            } else {
                                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                            }
                        }
                    }
                }
            }

            // Download emails (headers + body + attachments [optional])
            if (count($arrDownloadEmailsFully)) {
                foreach ($arrDownloadEmailsFully as $emailUidToDownload) {
                    if (file_exists($lockFilePath)) {
                        $loaderDispatcher?->change('Cancelled', 100, $mailAccount->getTotalUnreadCount($arrAccountDefaultFolders), false, $booRefreshFoldersList || $allNewEmailsCounter > 0);

                        unlink($lockFilePath);
                        throw new Exception('The check was cancelled by user');
                    }

                    if ($allNewEmailsCounter % 15 == 0) {
                        // If you're using a remote storage and have some long tasks you might need to keep the connection alive via noop:
                        try {
                            $this->_storage->noop(); // keep alive
                        } catch (Exception $e) {
                        }
                    }

                    try {
                        // retrieve email by number
                        $realEmailNumber = $this->_storage->getNumberByUniqueId($emailUidToDownload);
                        $mailSize        = $this->_storage->getSize($realEmailNumber);

                        if ($this->isContentSizeAllowed($mailSize)) {
                            $email = $this->_storage->getMessage($realEmailNumber);

                            if ($booWithoutAttachments) {
                                $email->getContent(); // hack to pick only body content with valid content-type and encoding
                            }

                            $booSeen = $email->hasFlag(Storage::FLAG_SEEN);
                            $this->saveMessageFromServerToFolder($email, $emailUidToDownload, $accountId, null, $folderId, $booWithoutAttachments);

                            // Mark email as unread, as it was before
                            // This is required because email is marked as read, even it wasn't read yet
                            if (!$booSeen) {
                                $this->_storage->setFlags($realEmailNumber, array(Storage::FLAG_SEEN), null, '-');
                            }
                        } else {
                            $messages = $this->_storage->getBasicHeaders(
                                array(array('UID' => $emailUidToDownload)),
                                null,
                                false,
                                $loaderDispatcher,
                                $lockFilePath,
                                $this,
                                $accountId,
                                $arrAccountDefaultFolders,
                                $allNewEmailsCounter,
                                $allNewEmails
                            );

                            foreach ($messages as $msg) {
                                $this->saveWarningEmail(
                                    $accountId,
                                    $folderId,
                                    $emailUidToDownload,
                                    $this->parseBasicHeaders($accountId, $msg)
                                );
                            }
                        }
                    } catch (CancelException $e) {
                        $loaderDispatcher?->change('Cancelled', 100, $mailAccount->getTotalUnreadCount($arrAccountDefaultFolders), false, $booRefreshFoldersList || $allNewEmailsCounter > 0);
                        Message::addUIDToDeletedTable($emailUidToDownload, $accountId);

                        throw $e;
                    } catch (Exception $e) {
                        // We cannot parse email or even cannot get headers, simply mark this email as deleted
                        Message::addUIDToDeletedTable($emailUidToDownload, $accountId);
                    }

                    $allNewEmailsCounter++;
                    $loaderDispatcher?->changeStatus($allNewEmailsCounter, $allNewEmails);
                }
            }
        }

        // Sync seen/unseen states
        try {
            $searchResults = $this->_storage->search(
                array(
                    array(
                        'field' => 'raw',
                        'value' => 'UNSEEN'
                    )
                )
            );

            $arrUnreadUids = array();
            foreach ($searchResults as $uid) {
                $arrUnreadUids[] = $uid['UID'];
            }

            // Don't check/mark new emails - we cannot load them via this "unseen" query :(
            $arrNewEmails = isset($folderData['uids']) && is_array($folderData['uids']) ? $folderData['uids'] : array();

            Message::toggleFolderMailRead($accountId, $folderId, $arrUnreadUids, $arrNewEmails);
        } catch (Exception $e) {
        }

        return true;
    }

    public function appendBodyAndAttachmentsToEmailInDb($email, $id)
    {
        $htmlBody = $email['body_html'] ?? '';
        if (isset($email['attachments']) && count($email['attachments']) > 0) {
            if (!empty($email['attachments'][0]['part_info'])) { // save only attachment part info without attachment content (for further downloading)
                $htmlBody = $this->saveAttachmentPartsToDb($email['attachments'], $id, $htmlBody);
            } else { // save real files, not only parts
                $htmlBody = $this->saveAttachmentsToDb($email['attachments'], $id, $htmlBody);
            }
        }

        $data = array(
            'body_html'       => self::strip4ByteSequences($htmlBody),
            'is_downloaded'   => 1,
            'has_attachments' => isset($email['attachments']) && count($email['attachments']) > 0
        );

        $messageModel = Message::createFromMailId($id);
        $messageModel->updateEmail($data);
        return $htmlBody;
    }

    /**
     * @param $emailDbId
     * @param $attachmentPart
     * @return null|array
     * @throws Exception
     */
    public function fetchAttachment($emailDbId, $attachmentPart)
    {
        $attachment   = null;
        $messageModel = Message::createFromMailId($emailDbId);
        $emailInfo    = $messageModel->getEmailInfo();

        if (!empty($emailInfo)) {
            try {
                $this->connect($emailInfo['id_account']);

                $folder           = new Folder(Folder::getFolderIdByMailId($emailDbId));
                $folderInfo       = $folder->getFolderInfo();
                $folderGlobalName = $folderInfo['full_path'];

                $imapFolderList = $this->getFoldersListForIMAP();
                $imapFolder     = $this->findStorageFolderByGlobalName($imapFolderList, $folderGlobalName);

                if (!is_null($imapFolder) && $imapFolder->isSelectable()) {
                    $this->_storage->selectFolder($imapFolder);

                    $emailNumber = $this->_storage->getNumberByUniqueId($emailInfo['uid']);

                    $attachmentBody = $this->_storage->fetchPartByIndex($emailNumber, $attachmentPart['index']);

                    $attachment = array(
                        'already_created_in_db_id' => $attachmentPart['id'],
                        'mime'                     => $attachmentPart['type'],
                        'filename'                 => $attachmentPart['params']['name'],
                        'data'                     => $this->decodeBody($attachmentBody, $attachmentPart['transfer_encoding'], null, true)
                    );
                }
            } catch (Exception $e) {
                if (!in_array($e->getMessage(), array("Can't connect to email account.", 'cannot read - connection closed?'))) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                }
            }
        }

        return $attachment;
    }


    /**
     * Convert ZF message to array
     *
     * @param Storage\Message $msg
     * @return array
     */
    private function zendMailMessage2Array($msg)
    {
        $sentDate = time();
        if (isset($msg->date)) {
            $sentDate = strtotime($msg->date);
            if (!$sentDate) {
                $sentDate = DateTimeTools::convertDateTimeFromStringToTime($msg->date);
            }
        } else {
            try {
                // TODO Switch to $msg->getHeaderPart() - need testing
                $receivedHeader = $msg->getHeader('Received');
                if (is_array($receivedHeader)) {
                    $receivedHeader = array_shift($receivedHeader);
                } elseif ($receivedHeader instanceof \ArrayIterator) {
                    $receivedHeader = $receivedHeader->current();
                }

                if ($receivedHeader instanceof HeaderInterface) {
                    $receivedHeader = $receivedHeader->getFieldValue();
                }
            } catch (Exception $e) {
                $receivedHeader = false;
            }

            if ($receivedHeader && ($parsedTime = $this->extractReceivedDatetimeFromReceivedHeader($receivedHeader))) {
                $sentDate = $parsedTime;
            }
        }

        return array(
            'from'      => isset($msg->from) ? self::decodeBase64Header($msg->from) : '',
            'to'        => isset($msg->to) ? self::decodeBase64Header($msg->to) : '',
            'cc'        => isset($msg->cc) ? self::decodeBase64Header($msg->cc) : '',
            'bcc'       => isset($msg->bcc) ? self::decodeBase64Header($msg->bcc) : '',
            'subject'   => isset($msg->subject) ? self::decodeBase64Header($msg->subject) : '',
            'sent_date' => $sentDate,
            'size'      => $msg->getSize(),
            'seen'      => (int)$msg->hasFlag(Storage::FLAG_SEEN),
            'priority'  => isset($msg->priority) ? (int)$msg->priority : (isset($msg->{'x-priority'}) ? (int)$msg->{'x-priority'} : 3),
            'x_spam'    => (int)isset($msg->{'x-spam'}),
            'replied'   => 0,
            'forwarded' => 0
        );
    }

    public function getEmailFromImap($uid, $dbId, $accountId)
    {
        $this->connect($accountId);

        $folderId         = Folder::getFolderIdByMailId($dbId);
        $folder           = new Folder($folderId);
        $folderInfo       = $folder->getFolderInfo();
        $folderGlobalName = $folderInfo['full_path'] ?? '';

        $imapFolder = null;
        if (!empty($folderGlobalName)) {
            $imapFolderList = $this->getFoldersListForIMAP();
            $imapFolder     = $this->findStorageFolderByGlobalName($imapFolderList, $folderGlobalName);
        }

        $returnEmail = array();
        if (!is_null($imapFolder) && $imapFolder->isSelectable()) {
            $this->_storage->selectFolder($imapFolder);

            try {
                $emailNumber = $this->_storage->getNumberByUniqueId($uid);

                // hack to pick only body content with valid content-type and encoding
                $msg = $this->_storage->getMessage($emailNumber);
                $msg->getContent();

                // TODO Review what are these two for as from the variables name it's unclear
                $email    = $this->parseEmailMessage($msg);
                $mailData = $this->zendMailMessage2Array($msg);

                if (!isset($email['error'])) {
                    $additionalMailData = array(
                        'uid'             => $uid,
                        'has_attachments' => intval(count($email['attachments']) > 0),
                        'body_html'       => $email['html'],
                        'attachments'     => $email['attachments']
                    );

                    $returnEmail = array_merge($mailData, $additionalMailData);
                } elseif ($email['error'] == 'body is too long') {
                    $this->saveWarningEmail(
                        $accountId,
                        $folderId,
                        $uid,
                        $this->parseBasicHeaders($accountId, $mailData)
                    );

                    Message::addUIDToDeletedTable($uid, $accountId);
                }
            } catch (Exception $e) {
                $returnEmail['body_html'] = '<h2 style="color:red">Cannot fetch email</h2>';
            }
        }

        return $returnEmail;
    }

    public function getFoldersListForIMAP()
    {
        return new RecursiveIteratorIterator($this->_storage->getFolders(), RecursiveIteratorIterator::SELF_FIRST);
    }

    /**
     * @param RecursiveIteratorIterator $folders list of folders
     * @param string $name global folder's name
     * @return ?Storage\Folder
     */
    public function findStorageFolderByGlobalName(RecursiveIteratorIterator $folders, $name)
    {
        foreach ($folders as $folder) {
            if (mb_strtolower($name) === 'inbox') {
                // We don't care about the registry for Inbox folder
                /** @var Storage\Folder $folder */
                if (Folder::decodeFolderName(mb_strtolower($folder->getGlobalName())) === mb_strtolower($name)) {
                    return $folder;
                }
            } else {
                /** @var Storage\Folder $folder */
                if (Folder::decodeFolderName($folder->getGlobalName()) == $name) {
                    return $folder;
                }
            }
        }
        return null;
    }

    /**
     * If folder is selectable - select it
     * If folder was not created in DB - create it
     * If folder is selectable and is visible - load list of emails (new, etc.)
     *
     * @param Storage\Folder $folder
     * @param $foldersDepth
     * @param $accountId
     * @param $sinceDate
     * @param bool $isVisible
     * @return array|false
     */
    public function getNewUidsForFolder(Storage\Folder $folder, $foldersDepth, $accountId, $sinceDate, $isVisible = true)
    {
        // if (!$this->_storage instanceof Imap) {
        //     throw new \Exception('Method getNewUidsForFolder() is compatible with IMAP storage only.');
        // }

        $globalFolderName = Folder::decodeFolderName($folder->getGlobalName());
        $localFolderName  = Folder::decodeFolderName($folder->getLocalName());

        $isFolderSelectable = $folder->isSelectable();
        $booErrorOnSelect   = false;

        try {
            if ($isFolderSelectable) {
                $this->_storage->selectFolder($folder);
            }
        } catch (Exception $e) {
            try {
                $this->_storage->reconnect();
                $this->_storage->selectFolder($folder);
            } catch (Exception $e) {
                if ($e->getMessage() != 'cannot read - connection closed?') {
                    $this->_log->debugErrorToFile($e->getMessage() . ' foldername=' . $folder, $e->getTraceAsString(), 'mail');
                } else {
                    $booErrorOnSelect = true;
                }
            }
        }

        // Check if this is a new folder
        $folderId       = Folder::getIdByFullPath($accountId, $globalFolderName);
        $booIsNewFolder = empty($folderId);

        $folderId   = Folder::createFolder($accountId, 0, $localFolderName, $foldersDepth, $globalFolderName, $isFolderSelectable, $this->_storage, false, $isVisible);
        $folderData = array(
            'id'          => $folderId,
            'isNewFolder' => $booIsNewFolder,
            'globalName'  => $globalFolderName,
            'localName'   => $localFolderName,
            'selectable'  => $isFolderSelectable
        );

        if ($isFolderSelectable && $isVisible && !$booErrorOnSelect) {
            $serverUids = array();
            // fetch emails after specific date

            if ((int)$sinceDate > 0) {
                $preparedSearchQuery[] = array('field' => 'since', 'value' => (int)$sinceDate);
                try {
                    $searchResults = $this->_storage->search($preparedSearchQuery);
                } catch (Exception $e) {
                    if ($e->getMessage() == 'cannot read - connection closed?') {
                        $this->_storage->reconnect();
                    }
                    return false;
                }
                foreach ($searchResults as $uid) {
                    $serverUids[] = $uid['UID'];
                }
            } else { // fetch all emails
                try {
                    $serverUids = $this->_storage->getUniqueId();
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                }
            }

            $notDeletedEmails = Message::getEmailsList(
                $accountId,
                $folderId,
                'id',
                'asc',
                false,
                false,
                false,
                (new Where())->notLike('uid', Message::EMAIL_PREFIX . '%')
            );

            $notDeletedUids = array_map(
                function ($n) {
                    return $n['uid'];
                },
                $notDeletedEmails
            );

            $folderData['not_exists_in_server_uids'] = array_diff($notDeletedUids, $serverUids);

            foreach ($folderData['not_exists_in_server_uids'] as $uid) {
                try {
                    if ($messageModel = Message::createFromMailRemoteId($uid, $accountId, $folderId)) {
                        $messageModel->delete($this->_files, true, false);
                    }
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                }
            }

            $dbUids                    = Message::getRetrievedUids($accountId, $folderId);
            $folderData['uids']        = array_diff($serverUids, $dbUids);
            $folderData['server_uids'] = $serverUids;
            $folderData['db_uids']     = $dbUids;
        }

        return $folderData;
    }

    /**
     * Get location of the 'lock' file for specific account
     * @param int $accountId
     * @return string location to the 'lock' file
     */
    public static function getLockFileLocation($accountId)
    {
        $lockSettings = require 'config/lock.config.php';
        return $lockSettings['tmp_lock'] . DIRECTORY_SEPARATOR . $accountId . '.lc8';
    }

    /**
     * @param $folderId
     * @return string
     */
    public static function getExtJsFolderClass($folderId)
    {
        // preg_match('/inbox|sent|drafts|trash/', $folderId, $matches)
        $suffix = 'other';
        if (preg_match('/' . Folder::INBOX . '|' . Folder::SENT . '|' . Folder::DRAFTS . '|' . Folder::TRASH . '/', $folderId)) {
            $suffix = $folderId;
        }

        return 'mail-folder-' . $suffix;
    }


    /**
     * Load emails for specific IMAP folder in email account
     *
     * @param int $accountId
     * @param int $folderId
     * @return array
     */
    public function syncIMAPFolder($accountId, $folderId)
    {
        $allNewEmails        = 0;
        $allNewEmailsCounter = 0;
        $arrFolders          = array();

        $account = $this->connect($accountId);

        $oFolder       = new Folder($folderId);
        $arrFolderInfo = $oFolder->getFolderInfo();

        if (!is_array($arrFolderInfo) || !count($arrFolderInfo)) {
            return array($allNewEmails, $arrFolders);
        }

        $mailAccount       = new MailAccount($accountId);
        $arrAccountDetails = $mailAccount->getAccountDetails();

        // IMAP is supported only
        if ($arrAccountDetails['inc_type'] != 'imap') {
            return array($allNewEmails, $arrFolders);
        }

        /** @var Storage\Folder $folder */
        $folders = $this->getFoldersListForIMAP();
        foreach ($folders as $folder) {
            $globalFolderName = $oFolder::decodeFolderName($folder->getGlobalName());

            if ($globalFolderName == $arrFolderInfo['full_path']) {
                $folderData = $this->getNewUidsForFolder($folder, $folders->getDepth(), $account['id'], $arrAccountDetails['inc_fetch_from_date']);

                if (!$folderData) {
                    continue;
                }

                if (isset($folderData['uids'])) {
                    $allNewEmails += count($folderData['uids']);

                    $arrAccountDefaultFolders = Folder::getDefaultFolders($accountId);

                    try {
                        $this->syncFolder(
                            $folder,
                            $folderData,
                            $allNewEmailsCounter,
                            '',
                            null,
                            0,
                            $arrAccountDetails,
                            $arrAccountDefaultFolders['inbox'] ?? array(),
                            $arrAccountDetails['inc_only_headers'] == 'Y'
                        );
                    } catch (Exception $e) {
                        if ($e->getMessage() != 'The check was cancelled by user') {
                            throw $e;
                        }
                    }
                }
            } else {
                $fullPathLength = mb_strlen($arrFolderInfo['full_path'], 'UTF-8');

                $parentFolderPath = mb_substr($globalFolderName, 0, $fullPathLength, 'UTF-8');

                if ($parentFolderPath == $arrFolderInfo['full_path']) {
                    $subFolderPath = mb_substr($globalFolderName, $fullPathLength, mb_strlen($globalFolderName), 'UTF-8');

                    $delimCount = mb_substr_count($subFolderPath, '/', 'UTF-8');

                    if (empty($delimCount)) {
                        $delimCount = mb_substr_count($subFolderPath, '.', 'UTF-8');
                    }

                    if ($delimCount == 1 && !Folder::getIdByFullPath($account['id'], $globalFolderName)) {
                        $folderData = $this->getNewUidsForFolder($folder, $folders->getDepth(), $account['id'], $arrAccountDetails['inc_fetch_from_date']);
                        if ($folderData) {
                            $folder = new Folder($folderData['id']);

                            $folderInfo                   = $folder->getFolderInfo();
                            $folderInfo['folder_id']      = $folderInfo['id_folder'];
                            $folderInfo['text']           = $folderInfo['label'];
                            $folderInfo['folder_label']   = $folderInfo['label'];
                            $folderInfo['cls']            = self::getExtJsFolderClass($folderInfo['id_folder']);
                            $folderInfo['real_folder_id'] = $folderInfo['id'];
                            $folderInfo['isTarget']       = $folderInfo['selectable'] > 0;
                            $folderInfo['leaf']           = false;
                            $folderInfo['children']       = array();
                            $folderInfo['expanded']       = true;

                            $arrFolders[] = $folderInfo;
                        }
                    }
                }
            }
        }

        $mailAccountModel = new MailAccount($accountId);
        $mailAccountModel->updateLastManualCheckEmail();

        return array($allNewEmails, $arrFolders);
    }

    /**
     * Fetch emails from account with id $accountID;
     *
     * @param int $accountId
     * @param LoaderDispatcher|null $loaderDispatcher
     * @param bool $booManual
     * @param bool $booCheckInboxOnly
     * @param bool $booDownloadOnlyFolders
     * @return void
     * @throws CancelException
     */
    public function sync($accountId, ?LoaderDispatcher $loaderDispatcher = null, $booManual = false, $booCheckInboxOnly = false, $booDownloadOnlyFolders = false)
    {
        $booRefreshFoldersList = false;
        $accountModel          = new MailAccount($accountId);

        $account = $this->connect($accountId, $loaderDispatcher);

        if ($booManual && !is_null($accountId)) {
            $accountModel->updateLastManualCheckEmail();
        }

        $loaderDispatcher?->change('Preparing to fetch emails...');

        $lockFilePath          = self::getLockFileLocation($accountId);
        $accountDefaultFolders = Folder::getDefaultFolders($accountId);
        $accDetails            = $accountModel->getAccountDetails();

        if ($this->_storage instanceof Imap) {
            $folders   = $this->getFoldersListForIMAP();
            $delimiter = $accountModel->getDelimiter($this->_storage);

            $foldersList         = array();
            $allNewEmails        = 0;
            $allNewEmailsCounter = 0;

            $booOnlyHeaders = $accDetails['inc_only_headers'] == 'Y';

            $booInboxExists = false;

            foreach ($folders as $folder) {
                /** @var Storage\Folder $folder */
                $globalFolderName = strtolower(Folder::decodeFolderName($folder->getGlobalName()));
                if ($globalFolderName == 'inbox') {
                    $booInboxExists = true;
                    break;
                }
            }

            // prefetch new UIDs and count it for whole email-account
            foreach ($folders as $folder) {
                $globalFolderName = Folder::decodeFolderName($folder->getGlobalName());

                $isVisible = true;
                if (strtolower($globalFolderName) != 'inbox' && $booInboxExists) {
                    if ($booCheckInboxOnly) {
                        $parentFolderPath = mb_substr($globalFolderName, 0, 5, 'UTF-8');

                        if ($parentFolderPath == 'inbox') {
                            $subFolderPath = mb_substr($globalFolderName, 5, mb_strlen($globalFolderName), 'UTF-8');

                            $delimCount = mb_substr_count($subFolderPath, $delimiter, 'UTF-8');

                            // Is sub folder of inbox with level more than 1?
                            // Is this sub folder already created?
                            // Skip if yes.
                            if ($delimCount != 1 || Folder::getIdByFullPath($account['id'], $globalFolderName)) {
                                continue;
                            }
                        } else {
                            // We need skip folders/sub folder that are not inbox
                            continue;
                        }
                    } else {
                        $folderId = Folder::getIdByFullPath($account['id'], $globalFolderName);
                        if ($folderId) {
                            $folderModel   = new Folder($folderId);
                            $arrFolderInfo = $folderModel->getFolderInfo();
                            if (isset($arrFolderInfo['visible'])) {
                                $isVisible = (bool)$arrFolderInfo['visible'];
                            }
                        } else {
                            $isVisible = false;
                        }
                    }
                }

                if ($loaderDispatcher && !$booCheckInboxOnly && $isVisible) {
                    $loaderDispatcher->change(sprintf('Processing: %s...', $folder), 1, null, $accountId);
                }

                $folderData = $this->getNewUidsForFolder($folder, $folders->getDepth(), $account['id'], $accDetails['inc_fetch_from_date'], $isVisible);
                if (!$folderData) {
                    continue;
                }

                if (isset($folderData['uids'])) {
                    $allNewEmails                 += count($folderData['uids']);
                    $foldersList[(string)$folder] = $folderData;
                }

                if ($folderData['isNewFolder']) {
                    $booRefreshFoldersList = true;
                }

                if (file_exists($lockFilePath)) {
                    if ($loaderDispatcher) {
                        $totalUnreadCount = $accountModel->getTotalUnreadCount($accountDefaultFolders['inbox']);
                        $loaderDispatcher->change('Cancelled', 100, $totalUnreadCount, false, $booRefreshFoldersList);
                    }

                    unlink($lockFilePath);
                    throw new Exception('The check was cancelled by user');
                }
            }

            if ($booDownloadOnlyFolders) {
                return;
            }

            $loaderDispatcher?->change(null, null, $allNewEmails);

            foreach ($folders as $folder) {
                if (array_key_exists((string)$folder, $foldersList)) {
                    if ($booCheckInboxOnly) {
                        $globalFolderName = strtolower(Folder::decodeFolderName($folder->getGlobalName()));

                        if ($globalFolderName != 'inbox' && $booInboxExists) {
                            continue;
                        }
                    }

                    try {
                        $totalUnreadCountBeforeSync = $accountModel->getTotalUnreadCount($foldersList[(string)$folder]['id']);

                        $booSyncedFolder = $this->syncFolder(
                            $folder,
                            $foldersList[(string)$folder],
                            $allNewEmailsCounter,
                            $lockFilePath,
                            $loaderDispatcher,
                            $allNewEmails,
                            $accDetails,
                            $accountDefaultFolders['inbox'],
                            $booOnlyHeaders,
                            $booRefreshFoldersList
                        );

                        if ($booSyncedFolder) {
                            $totalUnreadCountAfterSync = $accountModel->getTotalUnreadCount($foldersList[(string)$folder]['id']);
                            if ($totalUnreadCountBeforeSync != $totalUnreadCountAfterSync) {
                                $booRefreshFoldersList = true;
                            }
                        }
                    } catch (Exception $e) {
                        // If emails checking was stopped before - don't show anything
                        if ($e->getMessage() == 'The check was cancelled by user') {
                            return;
                        }

                        throw $e;
                    }

                    if (!$booSyncedFolder) {
                        break;
                    }

                    if ($allNewEmailsCounter) {
                        $booRefreshFoldersList = true;
                    }
                }
            }
        } else {
            $booDeleteMessages = $accDetails['inc_leave_messages'] == 'N';

            // Fetch all mails id from db for current user as array
            $dbUids = Message::getRetrievedUids($accountId);

            $serverUids = array();
            try {
                // Fetch all uniq ids from server
                $serverUids = $this->_storage->getUniqueId();
            } catch (Exception $e) {
                $this->_log->debugExceptionToFile($e);
            }
            // Only new messages from mail server
            $newUids = array_diff($serverUids, $dbUids);

            $amountOfNewEmails = count($newUids);
            $loaderDispatcher?->change(null, null, $amountOfNewEmails);

            if ($amountOfNewEmails) {
                $booRefreshFoldersList = true;
            }

            $currentFetchedEmail = 0;

            // In some cases this can fail, so lets use try/catch
            try {
                foreach ($newUids as $emailNumber => $emailUidToDownload) {
                    if (file_exists($lockFilePath)) {
                        if ($loaderDispatcher) {
                            $totalUnreadCount = $accountModel->getTotalUnreadCount($accountDefaultFolders['inbox']);
                            $loaderDispatcher->change('Cancelled', 100, $totalUnreadCount, false, $booRefreshFoldersList);
                        }

                        unlink($lockFilePath);
                        throw new Exception('The check was cancelled by user');
                    }
                    try {
                        $currentFetchedEmail++;
                        $loaderDispatcher?->changeStatus($currentFetchedEmail, $amountOfNewEmails);

                        if ($currentFetchedEmail % 15 == 0) {
                            // If you're using a remote storage and have some long tasks you might need to keep the connection alive via noop:
                            $this->_storage->noop(); // keep alive
                        }

                        // retrieve email by number
                        $booSaved = $this->saveMessageFromServerToFolder($this->_storage[$emailNumber], $emailUidToDownload, $account['id']);

                        // delete this uids from mail server
                        if ($booSaved && $booDeleteMessages) {
                            $this->_storage->removeMessage($emailNumber);
                        }
                    } catch (Exception $e) {
                        // There are emails which we can't parse - let's just mark them as deleted and go ahead
                        // Exceptions due to faulty headers shouldn't be throwed anymore, however list should remain here for reference
                        // Some exceptions should be dealt with in Laminas, so we should track the progress
                        /*
                        if (in_array($e->getMessage(), [
                            'Invalid header value detected', // This comes from GenericHeader (Subject for example) having cyrillic characters
                            'The input is not a valid email address. Use the basic format local-part@hostname',
                            'The input exceeds the allowed length', // Email address it too long
                            'Invalid header line for "Laminas\Mail\Header\AbstractAddressList" string', // ReplyTo header came,
                            'Laminas\Mail\Header\ContentTransferEncoding::setTransferEncoding expects one of "7bit, 8bit, quoted-printable, base64, binary"; received "16bit"' // Not sure 16bit is even legal
                        ])) {
                            $oMessage = new Message($account['member_id'], $accountId, 0);
                            $oMessage->markRemoteIdAsDeleted($emailUidToDownload);
                        }
                        */

                        $this->connectToAccount($this->accountCredentials);
                    }
                }
            } catch (Exception $e) {
                if ($e->getMessage() !== 'The check was cancelled by user') {
                    $this->_log->debugExceptionToFile($e);
                }
            }
        }

        if (file_exists($lockFilePath)) {
            unlink($lockFilePath);
        }

        if ($loaderDispatcher) {
            $totalUnreadCount = $accountModel->getTotalUnreadCount($accountDefaultFolders['inbox']);
            $loaderDispatcher->change(
                'Done!',
                100,
                $totalUnreadCount,
                false,
                $booRefreshFoldersList
            );
        }
    }


    /**
     * Save warning email that specific email wasn't possible to save
     *
     * @param int $accountId
     * @param int $folderId
     * @param string $emailUid
     * @param array $arrEmailDetails
     */
    public function saveWarningEmail($accountId, $folderId, $emailUid, $arrEmailDetails)
    {
        $body = 'You have received a message that contains a large attachment. ' .
            'Officio is unable to download this message. ' .
            '<br/>Please ask the sender to break the attachments into smaller files and resend it to you.';

        // Also try to show additional info about email
        $arrAdditionalInfo = array();
        if (isset($arrEmailDetails['subject']) && !empty($arrEmailDetails['subject'])) {
            $arrAdditionalInfo['Subject'] = $arrEmailDetails['subject'];
        }

        if (isset($arrEmailDetails['from']) && !empty($arrEmailDetails['from'])) {
            $arrAdditionalInfo['From'] = htmlentities($arrEmailDetails['from']);
        }

        if (isset($arrEmailDetails['cc']) && !empty($arrEmailDetails['cc'])) {
            $arrAdditionalInfo['CC'] = htmlentities($arrEmailDetails['cc']);
        }

        if (isset($arrEmailDetails['sent_date']) && !empty($arrEmailDetails['sent_date'])) {
            $arrAdditionalInfo['Sent On'] = date(Settings::DATETIME_UNIX, $arrEmailDetails['sent_date']);
        }

        if (count($arrAdditionalInfo)) {
            $body .= '<br/><br/><b>Email Details:</b><br/>';
            foreach ($arrAdditionalInfo as $key => $val) {
                $body .= "<div><b>$key:</b> $val</div>";
            }
        }

        $mailData = array(
            'from'      => $this->_config['site_version']['support_email'],
            'to'        => $this->accountCredentials['email'],
            'cc'        => '',
            'bcc'       => '',
            'subject'   => 'Warning: Attachment is too large to download',
            'sent_date' => time(),
            'size'      => strlen($body),
            'seen'      => 0,
            'flag'      => 0,
            'priority'  => 1,
            'x_spam'    => 0,
            'replied'   => 0,
            'forwarded' => 0,

            'uid'             => $emailUid,
            'id_account'      => $accountId,
            'has_attachments' => 0,
            'body_html'       => $body,
            'is_downloaded'   => 1,
        );

        $this->saveMessageToFolder($mailData, array(), Folder::INBOX, $folderId);
    }

    public function saveJustCreatedMessageToFolder(array $msg, $attachments = array(), $folder = Folder::SENT)
    {
        $mailData = $this->emailForm2Array($msg);

        $preparedAttachments = array();
        foreach ($attachments as $file) {
            $ap_tp         = getcwd() . DIRECTORY_SEPARATOR; // TP :)
            $booIsFullPath = strpos($file['tmp_name'] ?? '', $ap_tp) === 0;

            $file_path = (!$booIsFullPath && is_file($ap_tp . $file['tmp_name'])) ? $ap_tp . $file['tmp_name'] : $file['tmp_name'];

            $booLocalFile  = is_file($file_path);
            $booIsReadable = $booLocalFile ? is_readable($file_path) : false;

            $data = $booLocalFile ? file_get_contents($file_path) : $this->_files->getCloud()->getFileContent($file_path);
            if (empty($data)) {
                $this->_log->debugErrorToFile('Attempt to save empty attachment.', print_r([$booIsFullPath, $booLocalFile, $booIsReadable, $file_path, $ap_tp, $file], true));
                continue;
            }

            $preparedAttachments[] = array(
                'filename' => $file['name'],
                'data'     => $data
            );
        }

        return $this->saveMessageToFolder($mailData, $preparedAttachments, $folder);
    }

    /**
     * Convert email info retrieved from getBasicHeaders method
     * TODO Probably we should get rid of it in favour of Laminas' code
     * @param int $accountId
     * @param array $arrMessage
     * @return array
     */
    public function parseBasicHeaders($accountId, array $arrMessage)
    {
        if (isset($arrMessage['_basicHeaders']['date'])) {
            $sentDate = strtotime($arrMessage['_basicHeaders']['date']);
            if (!$sentDate) {
                $sentDate = DateTimeTools::convertDateTimeFromStringToTime($arrMessage['_basicHeaders']['date']);
            }
        } else {
            $sentDate = time();
        }

        return array(
            'is_downloaded'   => (int)isset($arrMessage['body_html']),
            'id_account'      => $accountId,
            'uid'             => $arrMessage['UID'],
            'has_attachments' => (int)($arrMessage['attachmentCount'] > 0),
            'subject'         => isset($arrMessage['_basicHeaders']['subject']) && is_string($arrMessage['_basicHeaders']['subject']) ? self::decodeBase64Header($arrMessage['_basicHeaders']['subject']) : '',
            'to'              => $arrMessage['_basicHeaders']['to'] ?? '',
            'from'            => $arrMessage['_basicHeaders']['from'] ?? '',
            'cc'              => $arrMessage['_basicHeaders']['cc'] ?? '',
            'bcc'             => $arrMessage['_basicHeaders']['bcc'] ?? '',
            'sent_date'       => $sentDate,
            'seen'            => (int)array_key_exists('\Seen', $arrMessage['FLAGS']),
            'size'            => $arrMessage['RFC822.SIZE'],
            'flag'            => (int)array_key_exists('\Flagged', $arrMessage['FLAGS']),
        );
    }

    /**
     * Save to folder by folder Id
     *
     * @param array $arrMessages
     * @param $accountId
     * @param string $folder
     * @param null $folderId
     */
    protected function saveMessagesHeadersFromServerToFolder(array $arrMessages, $accountId, $folder = Folder::INBOX, $folderId = null)
    {
        try {
            foreach ($arrMessages as $message) {
                $this->saveMessageToFolder($this->parseBasicHeaders($accountId, $message), array(), $folder, $folderId);
            }
        } catch (Exception $e) {
            if ($e->getMessage() != 'cannot read - connection closed?') {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }
        }
    }

    public function saveMessageFromServerToFolder(\Officio\Email\Storage\Message $msg, $uid, $accountId, $folder = Folder::INBOX, $folderId = null, $booWithoutAttachments = false)
    { // save to folder by folder Id
        $booSuccess = false;
        try {
            try {
                $email = $this->parseEmailMessage($msg);
            } catch (Exception $e) {
                $email = array('error' => 'Error during email parsing.');
            }

            $mailData = $this->zendMailMessage2Array($msg);

            if (!isset($email['error']) && $this->isContentSizeAllowed(strlen($email['html'] ?? ''))) {
                $additionalMailData = array(
                    'uid'             => $uid,
                    'id_account'      => $accountId,
                    'has_attachments' => intval(count($email['attachments']) > 0),
                    'body_html'       => $email['html']
                );

                $mailData = array_merge($mailData, $additionalMailData);

                if (!$booWithoutAttachments) { // with attachments:
                    $this->saveMessageToFolder($mailData, $email['attachments'], $folder, $folderId);
                } else { // without attachments: (only attachments part info)

                    $msgId = $this->saveMessageToFolder($mailData, array(), $folder, $folderId);

                    $mailData['attachments'] = $email['attachments'];
                    $this->appendBodyAndAttachmentsToEmailInDb($mailData, $msgId);
                }

                $booSuccess = true;
            } elseif (isset($email['error']) && $email['error'] == 'body is too long') {
                $this->saveWarningEmail($accountId, $folderId, $uid, $mailData);
                Message::addUIDToDeletedTable($uid, $accountId);
            } else {
                Message::addUIDToDeletedTable($uid, $accountId);
            }
        } catch (Exception $e) {
            if ($e->getMessage() == 'no boundary found in content type to split message') {
                Message::addUIDToDeletedTable($uid, $accountId);
            } elseif (!in_array($e->getMessage(), array('cannot read - connection closed?', 'last request failed'))) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }
        }

        return $booSuccess;
    }

    /**
     * Store prepared message and attachments to tables.
     *
     * @param array $mailDataArray prepared array with keys named as table `eml_messages` columns
     * @param array $attachments array with attachments
     * @param string $folder folder name e.g. Folder::INBOX or Folder::SENT
     * @param null $folderId
     * @return string
     */
    public function saveMessageToFolder(array $mailDataArray, $attachments, $folder, $folderId = null)
    {
        $accountId                  = $mailDataArray['id_account'];
        $mailDataArray['id_folder'] = $folderId !== null
            ? $folderId
            : Folder::getFolderIdByName($accountId, $folder);

        $msgId = '';
        if (!empty($mailDataArray['id_folder'])) {
            // Check if email was already saved in DB (by uid + account + folder)
            if (!$messageModel = Message::createFromMailRemoteId($mailDataArray['uid'], $accountId, $mailDataArray['id_folder'])) {
                $messageModel = Message::addEmail($mailDataArray);
            }
            $msgId = $messageModel->getMailId();

            if (count($attachments) > 0) {
                $htmlBody = $this->saveAttachmentsToDb($attachments, $msgId, $mailDataArray['body_html']);
                // update html-content where all <img/> "src" attributes was relinked to show attachment url
                if ($htmlBody != $mailDataArray['body_html']) {
                    $messageModel->updateEmail(array('body_html' => $htmlBody));
                }
            }
        }

        return $msgId;
    }

    /**
     * @return int
     * @deprecated
     */
    public function count()
    {
        return $this->_storage->countMessages();
    }

    /**
     * Delete email by provided mail id from a specific email account
     *
     * @param int|string|array $mailId
     * @param int $accountId
     * @param bool $booShiftDel
     * @param bool $booSaveToDeletedTable
     * @return string error, empty on success
     */
    public function delete($mailId, $accountId, $booShiftDel = false, $booSaveToDeletedTable = true)
    {
        $strError = '';

        try {
            $mailAccount = new MailAccount($accountId);
            $account     = $mailAccount->getAccountDetails();

            // If account was created in Officio only - don't need to connect to it
            // otherwise generates "Can't connect to email account." exception
            if ($account['inc_enabled'] == 'Y') {
                $this->connect($accountId);
            }

            $arrMailIds = (array)$mailId;
            foreach ($arrMailIds as $mailId) {
                $messageModel = Message::createFromMailId($mailId);
                if ($messageModel !== false) {
                    $messageModel->delete($this->_files, $booShiftDel, $booSaveToDeletedTable, $this->_storage);
                }
            }
        } catch (Exception $e) {
            if (!in_array($e->getMessage(), array("Can't connect to email account.", 'read failed - connection closed?'))) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }
            $strError = $this->_tr->translate('Internal server error.');
        }

        return $strError;
    }

    public function getSqlTimeZone()
    {
        $info = $this->_db2->query("show variables like 'time_zone'", Adapter::QUERY_MODE_EXECUTE)->current();
        return $info['Value'];
    }

    public function setSqlTimeZone($zone)
    {
        $this->_db2->query("SET `time_zone`='$zone'", Adapter::QUERY_MODE_EXECUTE);
    }

    public function moveEmailFromImapToImap($msgNum, $imapToFolder)
    {
        try {
            $this->_storage->moveMessage($msgNum, $imapToFolder);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function moveEmailFromImapToLocal($msgNum, $messageId, $realFolderId)
    {
        try {
            $this->_storage->removeMessage($msgNum);
            $messageModel = Message::createFromMailId($messageId);
            $messageModel->moveToFolder($realFolderId);
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
        }
        return $booSuccess;
    }

    public function moveEmailFromLocalToImap($mailId)
    {
        try {
            $emailFromDb = $this->getEmailDetailById($mailId);

            // create new mail
            $mail = new \Laminas\Mail\Message();

            if (!empty($emailFromDb['email']['sent_date'])) {
                // Set the same date as it is in the original email
                $headers = new Headers();
                $headers->addHeader(Date::fromString('Date: ' . date('r', $emailFromDb['email']['sent_date'])));
                $mail->setHeaders($headers);
            }

            $mail->setEncoding('UTF-8');

            $this->addHeader($emailFromDb['email']['from'], $mail, 'addFrom');

            // set receiver(s)
            $this->addHeader((!empty($emailFromDb['email']['to']) ? $emailFromDb['email']['to'] : 'example@example.com'), $mail, 'addTo');

            if (isset($emailFromDb['email']['cc']) && !empty($emailFromDb['email']['cc'])) {
                $this->addHeader($emailFromDb['email']['cc'], $mail, 'addCc');
            }
            if (isset($emailFromDb['email']['bcc']) && !empty($emailFromDb['email']['bcc'])) {
                $this->addHeader($emailFromDb['email']['cc'], $mail, 'addBcc');
            }

            $mail->setSubject($emailFromDb['email']['subject']);

            $parts              = [];
            $bodyHtml           = new Part($emailFromDb['email']['body_html']);
            $bodyHtml->type     = Mime::TYPE_HTML;
            $bodyHtml->charset  = 'utf-8';
            $bodyHtml->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
            $parts[]            = $bodyHtml;

            if (!empty($emailFromDb['attachments'])) {
                foreach ($emailFromDb['attachments'] as $attach) {
                    $content = file_exists($attach['path']) ? file_get_contents($attach['path']) : false;
                    if ($content !== false) {
                        $attachment              = new Part($content);
                        $attachment->disposition = Mime::DISPOSITION_ATTACHMENT;
                        $attachment->encoding    = Mime::ENCODING_BASE64;
                        $attachment->filename    = $attach['original_file_name'];
                        $parts[]                 = $attachment;
                    }
                }
            }

            $body = new \Laminas\Mime\Message();
            $body->setParts($parts);
            $mail->setBody($body);

            $transport = new Fake();
            $transport->send($mail);
            $emailResult = $transport->getProcessedMessage();

            $msg = $this->constructMessageFromResultOfSending($emailResult);
            $this->_storage->appendMessage($msg);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');

            $booSuccess = false;
        }

        return $booSuccess;
    }

    public function moveToFolder(array $mailIds, $realFolderId, $accountId)
    {
        if (empty($mailIds)) {
            return 'Nothing to move.';
        }

        $mailAccount     = new MailAccount($accountId);
        $mailAccountInfo = $mailAccount->getAccountDetails();
        $defaultFolders  = Folder::getDefaultFolders($accountId);

        $destinationFolderModel = new Folder($realFolderId, $accountId);
        $destionationFolder     = $destinationFolderModel->getFolderInfo();

        $messages = array();

        $sourceFolder = false;
        foreach ($mailIds as $mailId) {
            $messages[$mailId] = Message::createFromMailId($mailId);
            if (!$sourceFolder) {
                // All emails are supposed to be one folder, so we initialize Folder model only once
                $mailInfo          = $messages[$mailId]->getEmailInfo();
                $sourceFolderModel = new Folder($mailInfo['id_folder'], $accountId);
                $sourceFolder      = $sourceFolderModel->getFolderInfo();
            }
        }

        if ($mailAccountInfo['inc_type'] == 'imap') {
            $lockFilePath = Mailer::getLockFileLocation($accountId);
            $this->connect($accountId);
            $imapFolderList = $this->getFoldersListForIMAP();

            $imapToFolder = $this->findStorageFolderByGlobalName($imapFolderList, $destionationFolder['full_path']);

            $rootFolder       = new Folder($destinationFolderModel->getRootParentFolderId());
            $rootInfo         = $rootFolder->getFolderInfo();
            $rootFolderInImap = $rootInfo['id_folder'] == '0';

            if (!$imapToFolder && $rootFolderInImap) {
                $mailAccount = new MailAccount($accountId);
                $delimiter   = $mailAccount->getDelimiter($this->_storage);

                $destionationFolder['full_path'] = str_replace('/', $delimiter, $destionationFolder['full_path']);
                $destinationFolderModel->updateFullPath($destionationFolder['full_path']);

                try {
                    $this->_storage->createFolder($destinationFolderModel::encodeFolderName($destionationFolder['full_path'])); // create folder in remote IMAP-server
                    $imapFolderList = $this->getFoldersListForIMAP();
                    $imapToFolder   = $this->findStorageFolderByGlobalName($imapFolderList, $destionationFolder['full_path']);
                } catch (Exception $e) {
                    return 'Can\'t move message. [0]';
                }
            }

            if (!$imapToFolder instanceof Storage\Folder) {
                return 'Can\'t find target folder in IMAP';
            }

            // get folder id by first email; because all set of emails from one folder and can't be from different folders in same time
            $imapFromFolder = $this->findStorageFolderByGlobalName($imapFolderList, $sourceFolder['full_path']);
            if (!$imapFromFolder instanceof Storage\Folder) {
                return 'Can\'t find source folder in IMAP';
            }

            $folderFromInImap = false;
            try {
                $this->_storage->selectFolder($imapFromFolder);
                $folderFromInImap = $sourceFolder['id_folder'] === '0';
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }

            $booOnlyHeaders = $mailAccountInfo['inc_only_headers'] == 'Y';
            // from IMAP to IMAP
            if ($folderFromInImap && $imapFromFolder->isSelectable() && $rootFolderInImap && $imapToFolder->isSelectable()) {
                // if this is not our 4 magical folders (inbox/sent/drafts/trash)  and  it selectable
                try {
                    $this->_storage->selectFolder($imapFromFolder);
                } catch (Exception $e) {
                    return 'Can\'t select folder.';
                }

                foreach ($mailIds as $messageId) {
                    $messageModel = $messages[$messageId];
                    $messageUid   = $messageModel->getRemoteId();
                    $msgNum       = null;
                    try {
                        $msgNum = $this->_storage->getNumberByUniqueId($messageUid);
                    } catch (Exception $e) {
                        $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                    }

                    if ($msgNum !== null) {
                        if (!$this->moveEmailFromImapToImap($msgNum, $imapToFolder)) {
                            return 'Can\'t move message [1].';
                        }
                    } else {// if we can't find message on server : move as message like from local folder to IMAP
                        try {
                            $this->_storage->selectFolder($imapToFolder);
                        } catch (Exception $e) {
                            return 'Can\'t select folder.';
                        }

                        if (!$this->moveEmailFromLocalToImap($messageId)) {
                            return 'Can\'t move message. [2]';
                        }
                    }
                }

                $this->delete($mailIds, $accountId, true, false);

                $null    = null;
                $newUids = $this->getNewUidsForFolder($imapToFolder, 0, $accountId, $mailAccountInfo['inc_fetch_from_date']);
                if ($newUids) {
                    try {
                        $this->syncFolder(
                            $imapToFolder,
                            $newUids,
                            $null,
                            $lockFilePath,
                            null,
                            count($newUids),
                            $mailAccountInfo,
                            $defaultFolders['inbox'],
                            $booOnlyHeaders
                        );
                    } catch (Exception $e) {
                        if ($e->getMessage() != 'The check was cancelled by user') {
                            throw $e;
                        }
                    }
                }
            }

            // from LOCAL to LOCAL
            if (!$folderFromInImap && $imapFromFolder->isSelectable() && !$rootFolderInImap && $imapToFolder->isSelectable()) {
                foreach ($mailIds as $mailId) {
                    $message = $messages[$mailId];
                    $message->moveToFolder($realFolderId);
                }
            }

            // from IMAP to LOCAL
            if ($folderFromInImap && $imapFromFolder->isSelectable() && !$rootFolderInImap && $imapToFolder->isSelectable()) {
                // if FROM_FOLDER is not our 4 magical folders (inbox/sent/drafts/trash)  and  it selectable
                try {
                    $this->_storage->selectFolder($imapFromFolder);
                } catch (Exception $e) {
                    return 'Can\'t select folder.';
                }

                foreach ($mailIds as $messageId) {
                    $message    = $messages[$messageId];
                    $messageUid = $message->getRemoteId();
                    $msgNum     = null;
                    try {
                        $msgNum = $this->_storage->getNumberByUniqueId($messageUid);
                    } catch (Exception $e) {
                        $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                    }

                    if ($msgNum !== null) {
                        if (!$this->moveEmailFromImapToLocal($msgNum, $messageId, $realFolderId)) {
                            return 'Can\'t move message. [3]';
                        }
                    } else // if we can't find message on server : move as message locally only
                    {
                        $message->moveToFolder($realFolderId);
                    }
                }
            }

            // from LOCAL to IMAP
            if (!$folderFromInImap && $imapFromFolder->isSelectable() && $rootFolderInImap && $imapToFolder->isSelectable()) {
                try {
                    $this->_storage->selectFolder($imapToFolder);
                } catch (Exception $e) {
                    return 'Can\'t select folder.';
                }

                foreach ($mailIds as $mailId) {
                    if ($this->moveEmailFromLocalToImap($mailId)) {
                        $this->delete($mailId, $accountId, true, false);
                    }
                }

                $null    = null;
                $newUids = $this->getNewUidsForFolder($imapToFolder, 0, $accountId, $mailAccountInfo['inc_fetch_from_date']);
                if ($newUids) {
                    try {
                        $this->syncFolder(
                            $imapToFolder,
                            $newUids,
                            $null,
                            $lockFilePath,
                            null,
                            count($newUids),
                            $mailAccountInfo,
                            $defaultFolders['inbox'],
                            $booOnlyHeaders
                        );
                    } catch (Exception $e) {
                        if ($e->getMessage() != 'The check was cancelled by user') {
                            throw $e;
                        }
                    }
                }
            }
        } else {
            // move for POP3
            foreach ($mailIds as $mailId) {
                $message = $messages[$mailId];
                $message->moveToFolder($realFolderId);
            }
        }

        return true;
    }

    public function getEmailDetailById($emailId, $booLocal = null)
    {
        $email       = array();
        $attachments = array();

        try {
            $messageModel = Message::createFromMailId($emailId);
            if ($messageModel !== false) {
                $email = $messageModel->getEmailInfo();
                if ($email['has_attachments'] > 0) {
                    $booLocal          = is_null($booLocal) ? $this->_auth->isCurrentUserCompanyStorageLocal() : $booLocal;
                    $companyEmailsPath = $this->_files->getCompanyEmailAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal);
                    $attachments       = $this->getMailAttachments($emailId, $booLocal, $companyEmailsPath);
                }
            } else {
                $this->_log->debugErrorToFile('Mail not found', print_r($emailId, true), 'mail');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }

        return array('email' => $email, 'attachments' => $attachments);
    }

    public function getMailAttachments($mailId, $booLocal, $companyEmailsPath)
    {
        $mailId = (array)$mailId;

        if (!count($mailId)) {
            return array();
        }

        try {
            $messageModel = Message::createFromMailId($mailId);
            if (!$messageModel) {
                return [];
            }

            $arrAttachments = $messageModel->getMailAttachments();
            foreach ($arrAttachments as $key => $arrAttachmentInfo) {
                $attachment = new Attachment($arrAttachmentInfo['id']);
                $attachInfo = $attachment->getInfo($companyEmailsPath);

                $path = $attachInfo['path'];
                if (!empty($arrAttachmentInfo['size'])) {
                    $size = $arrAttachmentInfo['size'];
                } elseif (!empty($path) && !empty($arrAttachmentInfo['path'])) {
                    if ($booLocal) {
                        $size = is_file($path) ? filesize($path) : 0;
                    } else {
                        $size = $this->_files->getCloud()->getObjectFilesize($path);
                    }

                    $attachment->updateAttachment(array('size' => $size));
                } else {
                    // Attachment path is empty - most likely that attachment record was created, but file wasn't uploaded to S3/local
                    // This can be checked by the "path IS NULL" query in the DB
                    $size = 0;
                }

                $arrAttachments[$key]['path']       = $path;
                $arrAttachments[$key]['plain_size'] = $size;
                $arrAttachments[$key]['size']       = $this->_files->formatFileSize($size);
            }
        } catch (Exception $e) {
            $arrAttachments = [];
            $this->_log->debugExceptionToFile($e, 'mail');
        }

        return $arrAttachments;
    }

    public function markMailAsReadOrNot($arrMailIds, $seen, $accountId)
    {
        $strError = '';

        $mailAccount       = new MailAccount($accountId);
        $arrAccountDetails = $mailAccount->getAccountDetails();

        if (!isset($arrAccountDetails['inc_type']) && empty($strError)) {
            $strError = $this->_tr->translate('Internal server error.');
        }

        if ($arrAccountDetails['inc_type'] == 'imap' && empty($strError)) {
            try {
                $this->connect($accountId);

                $folder     = new Folder(Folder::getFolderIdByMailId($arrMailIds[0]));
                $folderInfo = $folder->getFolderInfo();
                $folderName = $folderInfo['full_path'];

                $isTrash  = $folder->getFolderMachineName() === Folder::TRASH;
                $isDrafts = $folder->getFolderMachineName() === Folder::DRAFTS;
                $isSent   = $folder->getFolderMachineName() === Folder::SENT;

                if (!empty($folderName) && !$isDrafts && !$isTrash && !$isSent) {
                    $this->_storage->selectFolder($folder::encodeFolderName($folderName));

                    foreach ($arrMailIds as $mailId) {
                        $messageModel = Message::createFromMailId($mailId);
                        $mailsInfo    = $messageModel->getEmailInfo();

                        try {
                            $this->_storage->setFlags($this->_storage->getNumberByUniqueId($mailsInfo['uid']), array(Storage::FLAG_SEEN), null, empty($seen) ? '-' : '+');
                        } catch (Exception $e) {
                            if (!in_array($e->getMessage(), array('unique id not found', 'cannot read - connection closed?', 'cannot set flags, have you tried to set the recent flag or special chars?', 'last request failed'))) {
                                throw $e;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                if (!in_array($e->getMessage(), ["Can't connect to email account.", 'cannot read - connection closed?', 'cannot set flags, have you tried to set the recent flag or special chars?'])) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                }
                $strError = $this->_tr->translate('Internal server error.');
            }
        }

        if (empty($strError)) {
            foreach ($arrMailIds as $mailId) {
                $messageModel = Message::createFromMailId($mailId);
                if ($messageModel) {
                    $messageModel->toggleMailRead($seen);
                }
            }
        }

        return $strError;
    }

    /**
     * Mark all mails as read/unread in specific folder for specific account
     *
     * @param $accountId
     * @param $folderId
     * @param $seen
     * @return string
     */
    public function toggleAllMailRead($accountId, $folderId, $seen)
    {
        $strError          = '';
        $mailAccount       = new MailAccount($accountId);
        $arrAccountDetails = $mailAccount->getAccountDetails();

        if (!isset($arrAccountDetails['inc_type']) && empty($strError)) {
            $strError = $this->_tr->translate('Internal server error.');
        }

        // If folder is passed - load info about it and check if it is in the current account
        $oFolder       = new Folder($folderId);
        $arrFolderInfo = array();
        if (!empty($folderId) && empty($strError)) {
            $arrFolderInfo = $oFolder->getFolderInfo();
            if (!isset($arrFolderInfo['id_account']) || $arrFolderInfo['id_account'] != $accountId) {
                $strError = $this->_tr->translate('Internal server error.');
            }
        }

        if ($arrAccountDetails['inc_type'] == 'imap' && empty($strError)) {
            try {
                // Connect to account, select folder and mark specific/all emails as read/unread
                $this->connect($accountId);

                if (isset($arrFolderInfo['full_path'])) {
                    $this->_storage->selectFolder(Folder::encodeFolderName($arrFolderInfo['full_path']));
                }

                $this->_storage->setFlags('1', array(Storage::FLAG_SEEN), INF, empty($seen) ? '-' : '+');
            } catch (Exception $e) {
                if (!in_array($e->getMessage(), ["Can't connect to email account.", 'cannot read - connection closed?', 'cannot set flags, have you tried to set the recent flag or special chars?'])) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                }
                $strError = $this->_tr->translate('Internal server error.');
            }
        }

        if (empty($strError)) {
            Message::toggleAllMailsRead($accountId, $seen);
        }

        return $strError;
    }


    /**
     * @param FileManagerInterface $fileManager
     * @param $accountId
     */
    public function clearTrash(FileManagerInterface $fileManager, $accountId)
    {
        $trashFolderId = Folder::getFolderIdByName($accountId, Folder::TRASH);
        $folder        = new Folder($trashFolderId);
        // Delete all sub folders and mails in Trash folder
        $folder->cleanFolder($fileManager, true);
    }

    /**
     * Send Mail function
     *
     * @param array $form array-from with fields
     * @param array $files array with attached files
     * @param array $senderInfo array with attached files
     * @param bool $booSaveToSent - true to save in Sent folder
     * @param bool $booSend true if we need to send email
     * @param bool $booShowSenderName true if we need to add sender's name to the "From"
     * @param bool $booExport
     * @return array true if message was sent successfully, otherwise return string with error message; array - sent email info;
     */
    public function send($form, $files = array(), $senderInfo = array(), $booSaveToSent = true, $booSend = true, $booShowSenderName = true, $booExport = false)
    {
        try {
            $account = array();
            if (!$this->_auth->isCurrentUserSuperadmin() || $booExport) {
                // Load info about current member's account
                if (!empty($form['from']) && is_numeric($form['from'])) {
                    $mailAccount = new MailAccount($form['from']);
                    $account     = $mailAccount->getAccountDetails(); # $form['from'] = contains account id

                    if (!empty($account) && $account['out_use_own'] == 'Y') {
                        $account['out_password'] = empty($account['out_password']) ? '' : $this->_encryption->decode($account['out_password']);
                    }
                }
            } else {
                if (preg_match('/^(.*)"(.*)"(.*)$/', $form['from'], $regs)) {
                    $namePart1        = $regs[1];
                    $account['email'] = $regs[2];
                    $namePart2        = $regs[3];

                    $account['friendly_name'] = $namePart1 . $namePart2;
                } else {
                    $account['email'] = $form['from'];
                }

                $form['from'] = 0;
            }

            if (array_key_exists('from_email', $form) && !empty($form['from_email'])) {
                $account['email'] = $form['from_email'];
            }

            if ((isset($account['out_use_own']) && $account['out_use_own'] == 'Y') && !empty($form['from'])) {
                $accessToken     = null;
                $booAuthRequired = ($account['out_auth_required'] ?? 'N') == 'Y';
                if ($booAuthRequired && ($account['out_login_type'] ?? '') == 'oauth2') {
                    list($strError, $accessToken) = $this->_oauth2Client->getAccessToken($account['member_id'], $account['out_host'], $account['email'], 'smtp');

                    if (!empty($strError)) {
                        throw new Exception($strError);
                    }
                }
                $transport = MailAccount::createOutboundTransport($account, $accessToken);
            } else {
                $transport = $this->_commsManager->getOfficioSmtpTransport();
            }

            // Create new mail
            // TODO Can we use Officio\Comms\Mailer::composeEmail() method here instead?
            $mail = new \Laminas\Mail\Message();
            $mail->setEncoding('UTF-8');

            // Add the Message-id header - required for gmail
            if (method_exists($transport, 'getOptions')) {
                $host = $transport->getOptions()->getName();
            } else {
                // Sendmail doesn't have the host, try to get one
                $host = isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
            }
            $mail->getHeaders()->addHeader(MessageId::fromString('Message-ID: ' . sprintf('<%s@%s>', md5(uniqid(time() . '')), $host)));

            // set receiver(s)
            $this->addHeader($form['email'], $mail, 'addTo');

            // set sender
            $from = '';
            if (isset($account['email']) && trim($account['email'] ?? '')) {
                $from = trim($account['email'] ?? '');
            } else {
                $emailValidator = new EmailAddress();
                if (isset($form['from']) && !empty($form['from'])) {
                    if (preg_match('/^(.*)"(.*)"(.*)$/', $form['from'], $regs)) {
                        if ($emailValidator->isValid($regs[2])) {
                            $from = $regs[2];

                            if (!empty($regs[1]) || !empty($regs[3])) {
                                $form['friendly_name'] = trim(trim($regs[1]) . ' ' . trim($regs[3]));
                            }
                        }
                    } elseif ($emailValidator->isValid($form['from'])) {
                        $from = $form['from'];
                    }
                }
            }

            if (empty($from)) {
                if (!empty($senderInfo['emailAddress'])) {
                    // Use email from members table
                    // If email account wasn't created yet
                    $from = $senderInfo['emailAddress'];
                } else {
                    $support = $this->_settings->getOfficioSupportEmail();
                    $from    = $support['email'];
                    list($senderInfo['fName'], $senderInfo['lName']) = explode(' ', $support['label'] ?? '');
                }
            }

            if (!$booSend) {
                $this->addHeader($form['from'], $mail, 'addFrom');
            } else {
                if ($booShowSenderName) {
                    if (empty ($account['friendly_name'])) {
                        if (!isset($form['friendly_name']) && isset($senderInfo['fName']) && isset($senderInfo['lName'])) {
                            $from = trim($senderInfo['fName'] . ' ' . $senderInfo['lName']) . " <$from>";

                            $this->addHeader($from, $mail, 'addFrom');
                            $this->addHeader($from, $mail, 'addReplyTo');
                        } else {
                            // Use email address only
                            $from = trim($form['friendly_name'] ?? '') . " <$from>";
                            $this->addHeader($from, $mail, 'addFrom');
                            $this->addHeader($from, $mail, 'addReplyTo');
                        }
                    } else {
                        $from = trim("{$account['friendly_name']} <$from>");

                        $this->addHeader($from, $mail, 'addFrom');
                        $this->addHeader($from, $mail, 'addReplyTo');
                    }
                } else {
                    $this->addHeader($from, $mail, 'addFrom');
                    $this->addHeader($from, $mail, 'addReplyTo');
                    $from = "<$from>";
                }
            }

            if (isset($form['cc']) && !empty($form['cc'])) {
                $this->addHeader($form['cc'], $mail, 'addCc');
            }

            if (isset($form['bcc']) && !empty($form['bcc'])) {
                $this->addHeader($form['bcc'], $mail, 'addBcc');
            }

            $filter = new StripTags();

            // subject & message body
            $subject = empty ($form['subject']) ? ' ' : $form['subject'];
            $mail->setSubject(trim($filter->filter($subject)));

            $parts              = [];
            $htmlPart           = new Part($form['message']);
            $htmlPart->type     = Mime::TYPE_HTML;
            $htmlPart->charset  = 'utf-8';
            $htmlPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
            $parts[]            = $htmlPart;

            // get auto attached files
            if (isset ($form['attached'])) {
                if (!empty ($form['attached'])) {
                    $companyEmailsPath = $this->_files->getCompanyEmailAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $this->_auth->isCurrentUserCompanyStorageLocal());

                    foreach ($form['attached'] as $attached) {
                        if (isset($attached['link'])) {
                            // get path to file
                            $file = false;
                            if (!empty($attached['id']) || !empty($attached['path'])) {
                                $path = !empty($attached['path']) ? $attached['path'] : $attached['id'];
                                $file = $this->_encryption->decode($path);

                                if ($file === false) {
                                    $tmp         = explode('/', $attached['link']);
                                    $pdf_file_id = end($tmp);

                                    // Generate PDF file (merge with xfdf + make it flatten)
                                    $memberId  = $this->_forms->getFormAssigned()->getFormMemberIdById($pdf_file_id);
                                    $userId    = $this->_auth->getCurrentUserId();
                                    $companyId = $this->_company->getMemberCompanyId($memberId);

                                    /** @var Clients $clients */
                                    $clients          = $this->_serviceContainer->get(Clients::class);
                                    $arrFamilyMembers = $clients->getFamilyMembersForClient($memberId);

                                    if (empty($strError) && !$clients->isAlowedClient($memberId)) {
                                        $strError = $this->_tr->translate('Insufficient access rights');
                                    }

                                    $arrFormsFormatted = [
                                        $pdf_file_id => 'read-only'
                                    ];

                                    // Check if this user has access to these forms
                                    if (empty($strError)) {
                                        // Check if current user has access to these forms
                                        $arrFormIds        = array_keys($arrFormsFormatted);
                                        $booHasAccess      = true;
                                        $arrCorrectFormIds = $this->_pdf->filterFormIds($arrFormIds);
                                        // If all ids are correct - check access to each form
                                        if ($arrCorrectFormIds && (count($arrCorrectFormIds) == count($arrFormIds))) {
                                            /** @var array $arrMemberIds */
                                            $arrMemberIds = $this->_forms->getFormAssigned()->getFormMemberIdById($arrCorrectFormIds);
                                            if (count($arrMemberIds)) {
                                                foreach ($arrMemberIds as $memberId) {
                                                    if (!$clients->isAlowedClient($memberId)) {
                                                        $booHasAccess = false;
                                                        break;
                                                    }
                                                }
                                            }
                                        } else {
                                            $booHasAccess = false;
                                        }

                                        if (!$booHasAccess) {
                                            $strError = $this->_tr->translate('Incorrectly selected forms');
                                        }
                                    }

                                    if (empty($strError)) {
                                        $arrResult = $this->_pdf->createPDF(
                                            $companyId,
                                            $this->_forms->getFormAssigned()->getFormMemberIdById($pdf_file_id),
                                            $userId,
                                            $arrFormsFormatted,
                                            $arrFamilyMembers
                                        );

                                        $strError = $arrResult['error'];

                                        if (empty($strError) && count($arrResult['files'])) {
                                            $file = $arrResult['files'][0]['file'];
                                        }
                                    }
                                } elseif (preg_match('/(.*)#(\d+)/', $file, $regs)) {
                                    // File path is in such format: path/to/file#check_id
                                    $file = $regs[1];
                                }
                            }

                            if ($file) {
                                $files[] = array(
                                    'tmp_name' => $file,
                                    'name'     => $attached['original_file_name'],
                                    'type'     => ''
                                );
                            }
                        } else {
                            $attachmentModel = new Attachment($attached['id']);
                            $attachInfo      = $attachmentModel->getInfo($companyEmailsPath);
                            if (!empty($attachInfo) && $this->hasAccessToMail($this->_auth->getCurrentUserId(), $attachInfo['id_message'])) {
                                $files[] = array(
                                    'tmp_name' => $attachInfo['path'],
                                    'name'     => $attachInfo['original_file_name'],
                                    'type'     => ''
                                );
                            }
                        }
                    }
                }
            }

            $totalFilesSize = 0;
            if (is_array($files) && count($files)) {
                foreach ($files as $file) {
                    // sometimes full path can come here, so we ltrim current working directory
                    $fileName = $file['path'] ?? $file['tmp_name'];

                    if (strpos($fileName, getcwd()) !== 0) {
                        $fileName = getcwd() . '/' . $fileName;
                    }

                    $booLocalFile = is_file($fileName);

                    if ($booSend && empty($file['tmp_name'])) {
                        continue;
                    }

                    if ($booSend) {
                        $strFilePath = $booLocalFile ? realpath($fileName) : ($file['path'] ?? $file['tmp_name']);
                    } else {
                        $strFilePath = $file['path'];
                    }

                    $content = $booLocalFile ? file_get_contents($strFilePath) : $this->_files->getCloud()->getFileContent($strFilePath);
                    if ($content !== false) {
                        $attachment              = new Part($content);
                        $attachment->disposition = Mime::DISPOSITION_ATTACHMENT;
                        $attachment->encoding    = Mime::ENCODING_BASE64;
                        $filename                = $booSend ? $file['name'] : $file['original_file_name'];
                        $attachment->filename    = '=?UTF-8?B?' . base64_encode($filename) . '?=';
                        if (!empty ($file['type'])) {
                            $attachment->type = $file['type'];
                        }
                        $parts[]        = $attachment;
                        $totalFilesSize += $booLocalFile ? filesize($strFilePath) : $this->_files->getCloud()->getObjectFilesize($strFilePath);
                    }
                }
            }

            $maxFilesSize = $this->_config['mail']['total_files_size'];
            if ($totalFilesSize > $maxFilesSize * 1024 * 1024) {
                $error = sprintf(
                    'Your recipient cannot receive emails with a total attachment of larger than %dMB.' .
                    ' Please reduce your attachments and try again.',
                    $maxFilesSize
                );
                throw new Exception($error);
            }

            $body = new \Laminas\Mime\Message();
            $body->setParts($parts);
            $mail->setBody($body);

            // Process message
            $fakeTransport = new Fake();
            $fakeTransport->send(clone $mail);
            $emailResult = $fakeTransport->getProcessedMessage();

            // Send email if necessary
            if ($booSend) {
                $transport->send($mail);
            }

            if ($booSend && isset($account['id']) && isset($account['inc_enabled']) && $account['inc_enabled'] == 'Y' && isset($account['inc_type']) && $account['inc_type'] == 'imap' && isset($account['out_save_sent']) && $account['out_save_sent'] == 'Y') {
                $account = $this->connect($account['id']);

                $imapFolderList = $this->getFoldersListForIMAP();
                $mailAccount    = new MailAccount($account['id']);
                $delimiter      = $mailAccount->getDelimiter($this->_storage);

                $imapSentFolder = $this->findStorageFolderByGlobalName($imapFolderList, 'INBOX' . $delimiter . 'Sent');

                if ($imapSentFolder && $imapSentFolder->isSelectable()) {
                    $this->_storage->selectFolder($imapSentFolder);

                    $msg = $this->constructMessageFromResultOfSending($emailResult);

                    $this->_storage->appendMessage($msg);
                }
            }

            // save email into `eml_messages` table to 'SENT' folder:
            if (!empty($form['from']) && array_key_exists('id', $account) && !empty($account['id'])) {
                $form['uid']             = $account['id'] . '-' . md5((string)time());
                $form['id_account']      = $account['id'];
                $form['from']            = $from;
                $form['has_attachments'] = is_array($files) && intval(count($files) > 0);
                if ($booSaveToSent && isset($account['inc_enabled']) && $account['inc_enabled'] == 'Y') {
                    // TODO We have previously filtered out empty files, but now we pass unfiltered $files here?
                    $this->saveJustCreatedMessageToFolder($form, $files);
                }
            }

            // Update forwarded/replied flags
            $arrUpdateOptions = array();
            if (isset($form['forwarded']) && !empty($form['forwarded'])) {
                $arrUpdateOptions['forwarded'] = 1;
            }

            if (isset($form['replied']) && !empty($form['replied'])) {
                $arrUpdateOptions['replied'] = 1;
            }
            if (count($arrUpdateOptions) && !empty($form['original_mail_id'])) {
                $messageModel = Message::createFromMailId($form['original_mail_id']);
                if ($messageModel !== false) {
                    $messageModel->updateEmail($arrUpdateOptions);
                }
            }

            // delete draft
            if (isset($form['draft_id']) && is_numeric($form['draft_id']) && !empty($form['draft_id']) && !empty($account['id'])) {
                $this->delete($form['draft_id'], $account['id'], true);
            }

            return array(true, $emailResult);
        } catch (Exception $e) {
            return array($e->getMessage(), null);
        }
    }

    protected function addHeader($string, \Laminas\Mail\Message $mail, $method)
    {
        if (!empty ($string)) {
            $validator = new EmailAddress();
            if (!$validator->isValid($string)) {
                $list = $this->_commsManager::parseEmails($string);
                foreach ($list as $email) {
                    if (!$validator->isValid($email['email'])) {
                        continue;
                    }

                    if (!empty($email['name'])) {
                        $mail->$method($email['email'], trim($email['name'], '"'));
                    } else {
                        $mail->$method($email['email']);
                    }
                }
            } else {
                $mail->$method($string);
            }
        }
    }

    /**
     * @param array $arrEmailResult
     * @return string
     */
    public function constructMessageFromResultOfSending($arrEmailResult)
    {
        // @Note: 2 "\r\n" must be for correct eml files parsing
        // Add extra headers (to/subject) that were missed

        $msg = $arrEmailResult['header'] . "\r\n" . "\r\n" . $arrEmailResult['body'] . "\r\n";

        $mail = mailparse_msg_create();
        mailparse_msg_parse($mail, $msg);
        $structure = mailparse_msg_get_structure($mail);

        $return = array();
        $utils  = new Utils();
        foreach ($structure as $s) {
            $part     = mailparse_msg_get_part($mail, $s);
            $partData = mailparse_msg_get_part_data($part);
            if ($s == 1) {
                if (isset($partData['headers']['to']) && !empty($partData['headers']['to'])) {
                    $return['to'] = explode(',', $partData['headers']['to']);
                }
                if (isset($partData['headers']['subject']) && !empty($partData['headers']['subject'])) {
                    $return['subject'] = $utils->decodeHeaderString($partData['headers']['subject']);
                }
                break;
            }
        }

        if (!isset($return['subject'])) {
            $msg = 'Subject: ' . str_replace(array("\n", "\r", "\r\n"), ' ', $arrEmailResult['subject']) . "\r\n" . $msg;
        }

        if (!isset($return['to']) && isset($arrEmailResult['recipients'])) {
            $msg = 'To: ' . implode(',', $arrEmailResult['recipients']) . "\r\n" . $msg;
        }

        return $msg;
    }

    /**
     * Save email to client.
     * This business-logic will be called when we reply or forward email and click "Save & Send" instead just click "Send"
     *
     * @param $email
     * @param $subject
     * @param int $emailId
     * @param int $companyId
     * @param int $memberId (client or prospect id)
     * @param $senderInfo
     * @param int $accountId
     * @param $clientFolder
     * @param $booLocal
     * @param bool $saveThisMail
     * @param bool $saveOriginalMail
     * @param bool $removeOriginalMail
     * @param bool $booSaveAttachSeparately
     * @param string $strCreationTime
     * @param bool $isProspects
     * @return bool TRUE if files was saved successfully or 'string' with error message otherwise.
     * @throws Exception
     */
    public function saveRawEmailToClient(
        $email,
        $subject,
        $emailId,
        $companyId,
        $memberId,
        $senderInfo,
        $accountId,
        $clientFolder,
        $booLocal,
        $saveThisMail = true,
        $saveOriginalMail = false,
        $removeOriginalMail = false,
        $booSaveAttachSeparately = false,
        $strCreationTime = '',
        $isProspects = false
    ) {
        if ($booLocal) {
            if (!is_dir($clientFolder)) {
                $this->_files->createFTPDirectory($clientFolder);
            }
        } elseif (!$this->_files->getCloud()->isFolder($clientFolder)) {
            $this->_files->createCloudDirectory($clientFolder);
        }

        if (substr($clientFolder ?? '', -1) !== '/') {
            // Add trailing slash in the end if it's missing
            $clientFolder .= '/';
        }

        if ($saveThisMail) {
            // We need to be sure that file will be not overwritten (if exists)
            // So a new name will be generated
            $subject = empty($subject) ? '(no subject)' : $subject;

            $msg = $this->constructMessageFromResultOfSending($email);

            /**
             * https://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
             *
             * In the Windows API (with some exceptions discussed in the following paragraphs), the maximum length for a
             * path is MAX_PATH, which is defined as 260 characters. A local path is structured in the following order:
             * drive letter, colon, backslash, name components separated by backslashes, and a terminating null character.
             * For example, the maximum path on drive D is "D:\some 256-character path string<NUL>" where "<NUL>" represents
             * the invisible terminating null character for the current system codepage. (The characters < > are used here
             * for visual clarity and cannot be part of a valid path string.)
             *
             **/
            $maxFileNameLength = ($booLocal ? 250 : 1000) - mb_strlen($clientFolder) - mb_strlen('Email - ' . ' - ' . date('Y-m-d H-i') . '.eml');

            $subject = $maxFileNameLength > 0 ? mb_substr($subject, 0, $maxFileNameLength) : null;

            if (is_null($subject)) {
                return false;
            }

            $fileName = FileTools::cleanupFileName('Email - ' . $subject . ' - ' . date('Y-m-d H-i') . '.eml', $maxFileNameLength);
            $emlFile  = $this->_files->generateFileName($clientFolder . $fileName, $booLocal);

            if ($booLocal) {
                $booCreated = $this->_files->createFile($emlFile, $msg);

                // Update file creation time if needed
                if ($booCreated && is_numeric($strCreationTime)) {
                    touch($emlFile, (int)$strCreationTime);
                }
            } else {
                $booCreated = $this->_files->getCloud()->createObject($emlFile, $msg);

                // Update file creation time if needed
                if ($booCreated && !empty($strCreationTime)) {
                    $this->_files->getCloud()->updateObjectCreationTime($emlFile, $strCreationTime);
                }
            }
        }

        $form = array();

        // Make sure that email's content will be downloaded (with all attachments) before saving
        if (!empty($emailId) && ($saveOriginalMail || $booSaveAttachSeparately)) {
            $email = $this->getEmailDetailById($emailId, $booLocal);

            $form = $email['email'];
            if (is_array($form) && !empty($form)) {
                $mailAccount = new MailAccount($accountId);
                $acc_details = $mailAccount->getAccountDetails();

                if ($acc_details['inc_type'] == 'imap') {
                    if (empty($form['is_downloaded'])) {
                        $email = $this->getEmailFromImap($form['uid'], $form['id'], $accountId);
                        if (!empty($email)) {
                            $htmlBody = $email['body_html'] ?? '';

                            $data = array(
                                'body_html'     => self::strip4ByteSequences($htmlBody),
                                'is_downloaded' => 1
                            );

                            $messageModel = Message::createFromMailId($form['id']);
                            $messageModel->updateEmail($data);
                        }
                    }

                    $companyEmailsPath   = $this->_files->getCompanyEmailAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal);
                    $attachmentsForEmail = $this->getMailAttachments($form['id'], $booLocal, $companyEmailsPath);

                    // get all attachs from db
                    foreach ($attachmentsForEmail as $attachment) {
                        if ($attachment['is_downloaded'] == '0') {
                            $fetchAttachmentResult = $this->fetchAttachment($form['id'], array_merge(unserialize($attachment['part_info']), array('id' => $attachment['id'])));
                            if ($fetchAttachmentResult) {
                                $this->saveAttachmentsToDb(array($fetchAttachmentResult), $attachment['id_message'], '');
                            }
                        }
                    }
                    $email = $this->getEmailDetailById($emailId, $booLocal);
                    $form  = $email['email'];
                }
            }
        }

        if ($saveOriginalMail && is_array($form) && count($form)) {
            $form['email']   = $form['to'];
            $form['message'] = self::strip4ByteSequences($form['body_html']);
            // replace to id account
            if (empty($form['from'])) {
                $form['from'] = $accountId;
            }

            list(, $emailMsg) = $this->send($form, $email['attachments'], $senderInfo, false, false);
            $this->saveRawEmailToClient($emailMsg, $form['subject'], 0, $companyId, $memberId, $senderInfo, $accountId, $clientFolder, $booLocal, true, false, false, false, $form['sent_date'], $isProspects);
        }

        if (!empty($emailId) && $booSaveAttachSeparately) {
            $companyEmailsPath = $this->_files->getCompanyEmailAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal);
            $attachments       = $this->getMailAttachments($emailId, $booLocal, $companyEmailsPath);
            foreach ($attachments as $attachment) {
                $fileName = FileTools::cleanupFileName($attachment['original_file_name']);
                $fileName = empty($fileName) ? 'UNKNOWN' : $fileName;
                $file     = $this->_files->generateFileName($clientFolder . $fileName, $booLocal);

                if ($booLocal) {
                    $this->_files->createFTPDirectory(dirname($file));
                    copy($attachment['path'], $file);
                } else {
                    $this->_files->getCloud()->copyObject($attachment['path'], $file);
                }
            }
        }

        if ($removeOriginalMail && !empty($emailId)) {
            $this->delete($emailId, $accountId);
        }

        return true;
    }

    /**
     * TODO Move this out of here
     * @param $arrActiveUsers
     * @return array
     */
    public function getActiveMailAccountsForRabbit($arrActiveUsers)
    {
        if (!is_array($arrActiveUsers) || !count($arrActiveUsers)) {
            return array();
        }

        $select = (new Select())
            ->from('eml_accounts')
            ->columns(['id'])
            ->where(
                [
                    'eml_accounts.member_id' => $arrActiveUsers,
                    (new Where())
                        ->nest()
                        ->nest()
                        ->equalTo('last_rabbit_push', 0)
                        ->and
                        ->equalTo('last_rabbit_pull', 0)
                        ->unnest()
                        ->or
                        ->nest()
                        ->notEqualTo('last_rabbit_push', 0)
                        ->and
                        ->notEqualTo('last_rabbit_pull', 0)
                        ->unnest()
                        ->or
                        ->nest()
                        ->isNull('last_rabbit_push')
                        ->and
                        ->isNull('last_rabbit_pull')
                        ->unnest()
                        ->unnest()
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    public function checkAccount($accountId)
    {
        $mailAccount = new MailAccount($accountId);
        try {
            $mailAccount->setIsChecking(1);
            $this->sync($accountId);
            $mailAccount->setIsChecking(0);
        } catch (Exception $e) {
            $mailAccount->setIsChecking(0);
            if (!in_array($e->getMessage(), array("Can't connect to email account.", 'The check was cancelled by user'))) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }
        }
    }

    public function saveRawEmailToFolder($email, $subject, $clientFolder, $booLocal, $timeForUpdate)
    {
        try {
            $clientFolder = rtrim($clientFolder ?? '', '/') . '/';

            // We need to be sure that file will be not overwritten (if exists)
            // So a new name will be generated
            $subject = empty($subject) ? '(no subject)' : $subject;

            // @Note: 2 "\r\n" must be for correct eml files parsing
            // for windows add extra headers that was missed

            $msg = '';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || !isset($email['headers']['To'])) {
                $msg .= 'To: ' . implode(',', $email['recipients']) . "\r\n";
            }
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || !isset($email['headers']['Subject'])) {
                $msg .= 'Subject: ' . str_replace(array("\n", "\r", "\r\n"), ' ', $email['subject']) . "\r\n";
            }

            $msg .= $email['header'] . "\r\n" . "\r\n" . $email['body'] . "\r\n";

            /**
             * https://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
             *
             * In the Windows API (with some exceptions discussed in the following paragraphs), the maximum length for a
             * path is MAX_PATH, which is defined as 260 characters. A local path is structured in the following order:
             * drive letter, colon, backslash, name components separated by backslashes, and a terminating null character.
             * For example, the maximum path on drive D is "D:\some 256-character path string<NUL>" where "<NUL>" represents
             * the invisible terminating null character for the current system codepage. (The characters < > are used here
             * for visual clarity and cannot be part of a valid path string.)
             *
             **/
            $strCreationTime   = date('Y-m-d H-i-s', $timeForUpdate);
            $maxFileNameLength = ($booLocal ? 250 : 1000) - mb_strlen($clientFolder) - mb_strlen($strCreationTime . ' - ' . 'Email - ' . '.eml');
            $subject           = $maxFileNameLength > 0 ? mb_substr($subject, 0, $maxFileNameLength) : null;

            if (is_null($subject)) {
                return false;
            }

            $fileName = FileTools::cleanupFileName($strCreationTime . ' - ' . 'Email - ' . $subject . '.eml', $maxFileNameLength);
            $emlFile  = $this->_files->generateFileName($clientFolder . $fileName, $booLocal);

            if ($booLocal) {
                $booCreated = $this->_files->createFile($emlFile, $msg);

                // Update file creation time if needed
                if ($booCreated && !empty($strCreationTime)) {
                    touch($emlFile, $timeForUpdate);
                }
            } else {
                $booCreated = $this->_files->getCloud()->createObject($emlFile, $msg);

                // Update file creation time if needed
                if ($booCreated && !empty($strCreationTime)) {
                    $this->_files->getCloud()->updateObjectCreationTime($emlFile, $timeForUpdate);
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create folder and run sync for this folder and each sub-folder to merge local and remote folders
     * @param $accountId
     * @param $folderId
     * @param $folderFullPath
     */
    private function _createFolderForImapFromLocalFolder($accountId, $folderId, $folderFullPath)
    {
        try {
            $folder     = new Folder($folderId);
            $folderInfo = $folder->getFolderInfo();
            $this->connect($accountId);
            $this->_storage->createFolder(Folder::encodeFolderName($folderFullPath));
            $imapFolderList = $this->getFoldersListForIMAP();

            // sync current folder:
            $imapToFolder = $this->findStorageFolderByGlobalName($imapFolderList, $folderFullPath);

            $lockFilePath          = self::getLockFileLocation($accountId);
            $accountDefaultFolders = Folder::getDefaultFolders($accountId);
            $null                  = null;

            $mailAccount    = new MailAccount($accountId);
            $accDetails     = $mailAccount->getAccountDetails();
            $booOnlyHeaders = $accDetails['inc_only_headers'] == 'Y';

            $newUids = $this->getNewUidsForFolder($imapToFolder, 0, $accountId, $accDetails['inc_fetch_from_date']);

            if (!$newUids) {
                return;
            }

            try {
                $this->syncFolder(
                    $imapToFolder,
                    $newUids,
                    $null,
                    $lockFilePath,
                    null,
                    count($newUids),
                    $accDetails,
                    $accountDefaultFolders['inbox'],
                    $booOnlyHeaders
                );
            } catch (Exception $e) {
                if ($e->getMessage() != 'The check was cancelled by user') {
                    throw $e;
                }
            }

            // END sync current folder.

            // sync all sub-folders:
            $subFolders = $folder->getSubfoldersIds(true);

            $subFoldersListToCreate = array();
            foreach ($subFolders as $subFolderId) {
                $subFolder                                           = new Folder($subFolderId);
                $subFolderInfo                                       = $subFolder->getFolderInfo();
                $subFoldersListToCreate[(int)$folderInfo['level']][] = $subFolderInfo['full_path'];
            }
            ksort($subFoldersListToCreate, SORT_NUMERIC);

            foreach ($subFoldersListToCreate as $level) {
                foreach ($level as $subFolderFullPath) {
                    // run sync for this folder and each sub-folder to merge local and remote folders
                    try {
                        $this->_storage->createFolder(Folder::encodeFolderName($subFolderFullPath));
                    } catch (Exception $e) {
                        $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                    }

                    $imapFolderList = $this->getFoldersListForIMAP(); // must be called every time because we create new folder above ^
                    $imapToFolder   = $this->findStorageFolderByGlobalName($imapFolderList, $subFolderFullPath);
                    $null           = null;
                    $newUids        = $this->getNewUidsForFolder($imapToFolder, 0, $accountId, $accDetails['inc_fetch_from_date']);
                    if (!$newUids) {
                        continue;
                    }

                    try {
                        $this->syncFolder(
                            $imapToFolder,
                            $newUids,
                            $null,
                            $lockFilePath,
                            null,
                            count($newUids),
                            $accDetails,
                            $accountDefaultFolders['inbox'],
                            $booOnlyHeaders
                        );
                    } catch (Exception $e) {
                        if ($e->getMessage() != 'The check was cancelled by user') {
                            throw $e;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }
    }

}
