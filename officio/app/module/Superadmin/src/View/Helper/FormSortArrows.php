<?php

namespace Superadmin\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class FormSortArrows extends AbstractHelper {

    // function for getting the up and down arrows
    public function __invoke($column, $imgUp = '/images/up_arrow.gif', $imgDown = '/images/down-arrow.gif')
    {
        $baseUrl = $this->getView()->layout()->getVariable('topBaseUrl') . '/superadmin';

        $anchor = "#" . $column;
        return "<a href='" . $this->getQueryString(
                array('order_by', 'order_by2'),
                array($column, 'asc')
            ) . $anchor . "'><img src='" . $baseUrl . $imgUp . "' BORDER='0' alt='sort asc' title='sort asc' width='10' height='6' /></a> " .
            "<a href='" . $this->getQueryString(
                array('order_by', 'order_by2'),
                array($column, 'desc')
            ) . $anchor . "'><img src='" . $baseUrl . $imgDown . "' BORDER='0' alt='sort desc' title='sort desc' width='10' height='6' /></a>";
    }

    // function will fetch the image name after appending $append parameter
    private function getQueryString($over_write_key = array(), $over_write_value = array()) {
        global $_GET; // TODO PHP7 Of man we gotta not do that!
        $arr = $_GET;
        if (is_array($over_write_key)) {
            $i = 0;
            foreach ($over_write_key as $key) {
                $arr[$key] = $over_write_value[$i];
                $i ++;
            }
        } else {
            $arr[$over_write_key] = $over_write_value;
        }

        $s = "?";
        $i = 0;
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $value2) {
                    if ($i == 0) {
                        $s .= "$key%5B%5D=$value2";
                        $i = 1;
                    } else {
                        $s .= "&$key%5B%5D=$value2";
                    }
                }
            } else {
                if ($i == 0) {
                    $s .= "$key=$value";
                    $i = 1;
                } else {
                    $s .= "&amp;$key=$value";
                }
            }
        }
        return $s;
    }
}