<?php

namespace Officio\View\Helper;
use Laminas\View\Helper\AbstractHelper;

class FormDropdown extends AbstractHelper {

    public function __invoke($name = '', $options = array(), $selected = '', $extra = '')
    {
        $form = "<select name='$name' id='$name' $extra>\n";
        
        foreach ($options as $key => $val) {
            if(is_array($selected)) {
                $booSelected = in_array($key, $selected);
            } else {
                $booSelected = $selected === $key;
            }

            $arrExtraOption = array();
            if ($booSelected) {
                $arrExtraOption[] = 'selected="selected"';
            }

            if (is_array($val) && isset($val['data'])) {
                $arrExtraOption[] = 'data-val="' . $val['data'] . '"';
            }

            $arrExtraOption = implode(' ', $arrExtraOption);

            $label = is_array($val) ? $val['label'] : $val;
            $form .= "<option value='$key' $arrExtraOption>".htmlspecialchars($label)."</option>\n";
        }

        $form .= '</select>';

        return $form;
    }
}