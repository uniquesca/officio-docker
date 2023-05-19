<?php

namespace Officio\Service\Payment\Paymentech;

class XmlHelper
{

    ###################################################################################
    # XML_unserialize: takes raw XML as a parameter (a string)
    # and returns an equivalent PHP data structure
    ###################################################################################
    public function & XML_unserialize($xml)
    {
        $xml_parser = new XmlParser();
        $data       = $xml_parser->parse($xml);
        $xml_parser->destruct();
        return $data;
    }
    ###################################################################################
    # XML_serialize: serializes any PHP data structure into XML
    # Takes one parameter: the data to serialize. Must be an array.
    ###################################################################################
    public function XML_serialize(&$data, $level = 0, $prior_key = null)
    {
        if ($level == 0) {
            ob_start();
            echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
        }
        foreach ($data as $key => $value) {
            if (!strpos($key, ' attr')) #if it's not an attribute
                #we don't treat attributes by themselves, so for an empty element
                # that has attributes you still need to set the element to NULL

            {
                if (is_array($value) and array_key_exists(0, $value)) {
                    $this->XML_serialize($value, $level, $key);
                } else {
                    $tag = $prior_key ?: $key;
                    echo str_repeat("\t", $level), '<', $tag;
                    if (array_key_exists("$key attr", $data)) { #if there's an attribute for this element
                        foreach ($data["$key attr"] as $attr_name => $attr_value) {
                            echo ' ', $attr_name, '="', $this->xmlEscape($attr_value), '"';
                        }
                        reset($data["$key attr"]);
                    }

                    if (is_null($value)) {
                        echo " />\n";
                    } elseif (!is_array($value)) {
                        echo '>', $this->xmlEscape($value), "</$tag>\n";
                    } else {
                        echo ">\n", $this->XML_serialize($value, $level + 1), str_repeat("\t", $level), "</$tag>\n";
                    }
                }
            }
        }
        reset($data);
        if ($level == 0) {
            $str = ob_get_contents();
            ob_end_clean();
            return $str;
        }
    }

    /**
     * Filter not allowed chars in xml
     *
     * @param $str
     * @return array|string|string[]
     */
    public function xmlEscape($str)
    {
        return str_replace(
            array("&", "<", ">", '"', "'"),
            array("&#x26;", "&#x3c;", "&#x3e;", "&quot;", "&#39;"),
            $str ?? ''
        );
    }
}
