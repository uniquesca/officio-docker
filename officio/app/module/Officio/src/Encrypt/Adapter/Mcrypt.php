<?php

/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

namespace Officio\Encrypt\Adapter;

use Laminas\Filter\Encrypt\EncryptionAlgorithmInterface;
use Laminas\Filter\Exception\ExtensionNotLoadedException;
use Laminas\Filter\Exception\InvalidArgumentException;
use Laminas\Filter\Exception\RuntimeException;

/**
 * Class Mcrypt
 * This is a port of Zend_Filter_Mcrypt to Laminas with an injection of Zend_Crypt_Math and removed compression option.
 * @deprecated This is to be removed as soon as all migrations using it are executed and archived.
 * @package Officio\Encrypt\Adapter\Mcrypt
 */
class Mcrypt implements EncryptionAlgorithmInterface
{

    /**
     * Definitions for encryption
     * array(
     *     'key' => encryption key string
     *     'algorithm' => algorithm to use
     *     'algorithm_directory' => directory where to find the algorithm
     *     'mode' => encryption mode to use
     *     'modedirectory' => directory where to find the mode
     * )
     */
    protected $_encryption = array(
        'key'                 => 'ZendFramework',
        'algorithm'           => 'blowfish',
        'algorithm_directory' => '',
        'mode'                => 'cbc',
        'mode_directory'      => '',
        'vector'              => null,
        'salt'                => false
    );

    protected static $_srandCalled = false;

    /**
     * Class constructor
     *
     * @param string|array $options Cryption Options
     */
    public function __construct($options)
    {
        if (!extension_loaded('mcrypt')) {
            throw new ExtensionNotLoadedException('This filter needs the mcrypt extension');
        }

        if (is_string($options)) {
            $options = array('key' => $options);
        } elseif (!is_array($options)) {
            throw new InvalidArgumentException('Invalid options argument provided to filter');
        }

        $this->setEncryption($options);
    }

    /**
     * Returns the set encryption options
     *
     * @return array
     */
    public function getEncryption()
    {
        return $this->_encryption;
    }

    /**
     * Sets new encryption options
     *
     * @param string|array $options Encryption options
     * @return $this
     */
    public function setEncryption($options)
    {
        if (is_string($options)) {
            $options = array('key' => $options);
        }

        if (!is_array($options)) {
            throw new InvalidArgumentException('Invalid options argument provided to filter');
        }

        $options    = $options + $this->getEncryption();
        $algorithms = mcrypt_list_algorithms($options['algorithm_directory']);
        if (!in_array($options['algorithm'], $algorithms)) {
            throw new InvalidArgumentException("The algorithm '{$options['algorithm']}' is not supported");
        }

        $modes = mcrypt_list_modes($options['mode_directory']);
        if (!in_array($options['mode'], $modes)) {
            throw new InvalidArgumentException("The mode '{$options['mode']}' is not supported");
        }

        if (!mcrypt_module_self_test($options['algorithm'], $options['algorithm_directory'])) {
            throw new InvalidArgumentException('The given algorithm can not be used due an internal mcrypt problem');
        }

        if (!isset($options['vector'])) {
            $options['vector'] = null;
        }

        $this->_encryption = $options;
        $this->setVector($options['vector']);

        return $this;
    }

    /**
     * Returns the set vector
     *
     * @return string
     */
    public function getVector()
    {
        return $this->_encryption['vector'];
    }

    /**
     * Sets the initialization vector
     *
     * @param string $vector (Optional) Vector to set
     * @return $this
     */
    public function setVector($vector = null)
    {
        $cipher = $this->_openCipher();
        $size   = mcrypt_enc_get_iv_size($cipher);
        if (empty($vector)) {
            $this->_srand();
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && version_compare(PHP_VERSION, '5.3.0', '<')) {
                $method = MCRYPT_RAND;
            } else {
                if (file_exists('/dev/urandom') || (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')) {
                    $method = MCRYPT_DEV_URANDOM;
                } elseif (file_exists('/dev/random')) {
                    $method = MCRYPT_DEV_RANDOM;
                } else {
                    $method = MCRYPT_RAND;
                }
            }
            $vector = mcrypt_create_iv($size, $method);
        } else {
            if (strlen($vector ?? '') != $size) {
                throw new InvalidArgumentException('The given vector has a wrong size for the set algorithm');
            }
        }

        $this->_encryption['vector'] = $vector;
        $this->_closeCipher($cipher);

        return $this;
    }

    /**
     * Defined by Zend_Filter_Interface
     *
     * Encrypts $value with the defined settings
     *
     * @param string $value The content to encrypt
     * @return string The encrypted content
     */
    public function encrypt($value)
    {
        $cipher = $this->_openCipher();
        $this->_initCipher($cipher);
        $encrypted = mcrypt_generic($cipher, $value);
        mcrypt_generic_deinit($cipher);
        $this->_closeCipher($cipher);

        return $encrypted;
    }

    /**
     * Defined by Zend_Filter_Interface
     *
     * Decrypts $value with the defined settings
     *
     * @param string $value Content to decrypt
     * @return string The decrypted content
     */
    public function decrypt($value)
    {
        $cipher = $this->_openCipher();
        $this->_initCipher($cipher);
        $decrypted = mdecrypt_generic($cipher, $value);
        mcrypt_generic_deinit($cipher);
        $this->_closeCipher($cipher);

        return $decrypted;
    }

    /**
     * Returns the adapter name
     *
     * @return string
     */
    public function toString()
    {
        return 'Mcrypt';
    }

    /**
     * Open a cipher
     *
     * @return resource Returns the opened cipher
     * @throws RuntimeException When the cipher can not be opened
     */
    protected function _openCipher()
    {
        $cipher = mcrypt_module_open(
            $this->_encryption['algorithm'],
            $this->_encryption['algorithm_directory'],
            $this->_encryption['mode'],
            $this->_encryption['mode_directory']
        );

        if ($cipher === false) {
            throw new RuntimeException('Mcrypt can not be opened with your settings');
        }

        return $cipher;
    }

    /**
     * Close a cipher
     *
     * @param resource $cipher Cipher to close
     * @return $this
     */
    protected function _closeCipher($cipher)
    {
        mcrypt_module_close($cipher);

        return $this;
    }

    /**
     * Initialises the cipher with the set key
     *
     * @param resource $cipher
     * @return $this
     * @throws
     */
    protected function _initCipher($cipher)
    {
        $key = $this->_encryption['key'] ?? '';

        $keysizes = mcrypt_enc_get_supported_key_sizes($cipher);
        if (empty($keysizes) || $this->_encryption['salt']) {
            $this->_srand();
            $keysize = mcrypt_enc_get_key_size($cipher);
            $key     = substr(md5($key), 0, $keysize);
        } else {
            if (!in_array(strlen($key), $keysizes)) {
                throw new InvalidArgumentException('The given key has a wrong size for the set algorithm');
            }
        }

        $result = mcrypt_generic_init($cipher, $key, $this->_encryption['vector']);
        if ($result < 0) {
            throw new InvalidArgumentException('Mcrypt could not be initialize with the given setting');
        }

        return $this;
    }

    /**
     * Return a random strings of $length bytes
     *
     * @param int $length
     * @param bool $strong
     * @return string
     */
    protected function _randBytes($length, $strong = false)
    {
        $length = (int)$length;
        if ($length <= 0) {
            return false;
        }
        if (function_exists('random_bytes')) { // available in PHP 7
            return random_bytes($length);
        }
        if (function_exists('mcrypt_create_iv')) {
            $bytes = mcrypt_create_iv($length);
            if ($bytes !== false && strlen($bytes) === $length) {
                return $bytes;
            }
        }
        if (file_exists('/dev/urandom') && is_readable('/dev/urandom')) {
            $frandom = fopen('/dev/urandom', 'r');
            if ($frandom !== false) {
                return fread($frandom, $length);
            }
        }
        if (true === $strong) {
            throw new RuntimeException(
                'This PHP environment doesn\'t support secure random number generation. ' .
                'Please consider installing the OpenSSL and/or Mcrypt extensions'
            );
        }
        $rand = '';
        for ($i = 0; $i < $length; $i++) {
            $rand .= chr(mt_rand(0, 255));
        }
        return $rand;
    }

    /**
     * Return a random integer between $min and $max
     *
     * @param int $min
     * @param int $max
     * @param bool $strong
     * @return int
     */
    protected function _randInteger($min, $max, $strong = false)
    {
        if ($min > $max) {
            throw new RuntimeException(
                'The min parameter must be lower than max parameter'
            );
        }
        $range = $max - $min;
        if ($range == 0) {
            return $max;
        } elseif ($range > PHP_INT_MAX || is_float($range)) {
            throw new RuntimeException(
                'The supplied range is too great to generate'
            );
        }
        if (function_exists('random_int')) { // available in PHP 7
            return random_int($min, $max);
        }
        // calculate number of bits required to store range on this machine
        $r    = $range;
        $bits = 0;
        while ($r) {
            $bits++;
            $r >>= 1;
        }
        $bits   = (int)max($bits, 1);
        $bytes  = (int)max(ceil($bits / 8), 1);
        $filter = (1 << $bits) - 1;
        do {
            $rnd = hexdec(bin2hex($this->_randBytes($bytes, $strong)));
            $rnd &= $filter;
        } while ($rnd > $range);
        return ($min + $rnd);
    }

    /**
     * _srand() interception
     *
     * @see ZF-8742
     */
    protected function _srand()
    {
        if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
            return;
        }
        if (!self::$_srandCalled) {
            srand($this->_randInteger(0, PHP_INT_MAX));
            self::$_srandCalled = true;
        }
    }

}