<?php

namespace Officio\Service\Payment\PayWay;

# Compares versions of software
# versions must must use the format ' x.y.z... '
# where (x, y, z) are numbers in [0-9]
class Qvalent_PayWayAPICompare
{

    public static function check_version($currentversion, $requiredversion)
    {
        list($majorC, $minorC, $editC) = preg_split('/[\/.-]/', $currentversion);
        list($majorR, $minorR, $editR) = preg_split('/[\/.-]/', $requiredversion);

        if ($majorC < $majorR) {
            return false;
        }
        // same major - check ninor
        if ($minorC < $minorR) {
            return false;
        }
        // and same minor
        return true;
    }
}