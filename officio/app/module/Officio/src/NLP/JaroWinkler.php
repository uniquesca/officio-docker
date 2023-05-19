<?php

namespace Officio\NLP;

class JaroWinkler
{

    /**
     * @param string $string1
     * @param string $string2
     *
     * @return int
     */
    protected static function jaro($string1, $string2)
    {
        $str1Len = strlen($string1 ?? '');
        $str2Len = strlen($string2 ?? '');
        // theoretical distance
        $distance = (int)floor(min($str1Len, $str2Len) / 2.0);
        // get common characters
        $commons1 = self::getCommonCharacters($string1, $string2, $distance);
        $commons2 = self::getCommonCharacters($string2, $string1, $distance);
        if (($commons1Len = strlen($commons1 ?? '')) == 0) {
            return 0;
        }

        if (($commons2Len = strlen($commons2 ?? '')) == 0) {
            return 0;
        }

        // calculate transpositions
        $transpositions = 0;
        $upperBound     = min($commons1Len, $commons2Len);
        for ($i = 0; $i < $upperBound; $i++) {
            if ($commons1[$i] != $commons2[$i]) {
                $transpositions++;
            }
        }
        $transpositions /= 2.0;

        // return the Jaro distance
        return ($commons1Len / ($str1Len) + $commons2Len / ($str2Len) +
                ($commons1Len - $transpositions) / ($commons1Len)) / 3.0;
    }

    public static function jaroWinkler($string1, $string2, $PREFIXSCALE = 0.1, $MINPREFIXLENGTH = 4)
    {
        $jaroDistance = self::jaro($string1, $string2);
        $prefixLength = self::getPrefixLength($string1, $string2, $MINPREFIXLENGTH);

        return $jaroDistance + $prefixLength * $PREFIXSCALE * (1.0 - $jaroDistance);
    }

    protected static function getCommonCharacters($string1, $string2, $allowedDistance)
    {
        $str2Len          = strlen($string2 ?? '');
        $commonCharacters = '';
        if (!empty($string1)) {
            foreach (str_split($string1) as $i => $char) {
                $search = strpos($string2 ?? '', $char, $i <= $allowedDistance ? 0 : min($i - $allowedDistance, $str2Len));
                if ($search !== false && $search <= $i + $allowedDistance + 1) {
                    $commonCharacters .= $char;
                }
            }
        }

        return $commonCharacters;
    }

    protected static function getPrefixLength($string1, $string2, $MINPREFIXLENGTH = 4)
    {
        $n = min(array($MINPREFIXLENGTH, strlen($string1 ?? ''), strlen($string2 ?? '')));
        for ($i = 0; $i < $n; $i++) {
            if ($string1[$i] != $string2[$i]) {
                // return index of first occurrence of different characters
                return $i;
            }
        }

        // first n characters are the same
        return $n;
    }

}