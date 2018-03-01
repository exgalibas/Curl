<?php
/**
 * Date: 2018/2/9
 * @author joker <exgalibas@gmail.com>
 */

namespace Curl;

class XmlDecoder implements Decoder
{
    /**
     * @return mixed|\SimpleXMLElement
     *
     * xml decoder
     */
    public function decode()
    {
        $response = func_get_arg(0);
        $xml_obj = @simplexml_load_string($response);
        if ($xml_obj !== false) {
            $response = $xml_obj;
        }
        return $response;
    }
}