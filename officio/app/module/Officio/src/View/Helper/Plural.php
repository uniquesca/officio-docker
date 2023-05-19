<?php

namespace Officio\View\Helper;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\Helper\AbstractHelper;

class Plural extends AbstractHelper
{

    /** @var TranslatorInterface */
    protected $_translator;

    /**
     * @param TranslatorInterface $translate
     */
    public function __construct($translate)
    {
        $this->_translator = $translate;
    }

    /**
     * Translate a message
     * You can give multiple params or an array of params.
     * If you want to output another locale just set it as last single parameter
     * Example 1: translate('%1\$s + %2\$s', $value1, $value2, $locale);
     * Example 2: translate('%1\$s + %2\$s', array($value1, $value2), $locale);
     *
     * @param $messageSingle
     * @param $messagePlural
     * @param $count
     * @return string Translated message
     */
    public function __invoke($messageSingle, $messagePlural, $count)
    {
        return $this->_translator->translatePlural($messageSingle, $messagePlural, $count);
    }

}
