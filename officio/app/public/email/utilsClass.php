<?php

class ap_Utils
{


    /**
     * @param string $password
     * @return string
     */
    public function EncodePassword($password)
    {
        if ($password === '' || $password === null) {
            return $password;
        }

        $plainBytes = $password;
        $encodeByte = $plainBytes[0];
        $result     = bin2hex($encodeByte);

        for ($i = 1, $icount = strlen($plainBytes); $i < $icount; $i++) {
            $plainBytes[$i] = ($plainBytes[$i] ^ $encodeByte);
            $result         .= bin2hex($plainBytes[$i]);
        }

        return $result;
    }

    /**
     * @param string $password
     * @return string
     */
    public function DecodePassword($password)
    {
        $passwordLen = strlen($password);

        if (strlen($password) > 0 && strlen($password) % 2 == 0) {
            $decodeByte  = chr(hexdec(substr($password, 0, 2)));
            $plainBytes  = $decodeByte;
            $startIndex  = 2;
            $currentByte = 1;

            do {
                $hexByte    = substr($password, $startIndex, 2);
                $plainBytes .= (chr(hexdec($hexByte)) ^ $decodeByte);

                $startIndex += 2;
                $currentByte++;
            } while ($startIndex < $passwordLen);

            return $plainBytes;
        }

        return '';
    }

}
