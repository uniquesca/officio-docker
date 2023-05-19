<?php

namespace Superadmin\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class FormSearchLimit extends AbstractHelper {

    public function __invoke($searchLimit) {
        $arrSrchLimit = array(25, 50, 75);
        $strResult = '';
        
        foreach ($arrSrchLimit as $limitVal) {
            if ($searchLimit == $limitVal) {
                    $limitSelected = "selected='selected'";
            } else {
                    $limitSelected = '';
            }
            $strResult .= "<option value='$limitVal' $limitSelected>$limitVal</option>";
        }
    
        return $strResult;
    }
}