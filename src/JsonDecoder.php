<?php
/**
 * Date: 2018/2/9
 * @author joker <exgalibas@gmail.com>
 */

namespace exgalibas\curl;

class JsonDecoder implements Decoder
{
    /**
     * @return mixed
     * json decoder
     */
    public function decode()
    {
        //array [json, assoc, depth, options]
        $args = func_get_args();
        $version = phpversion();
        if (version_compare($version, '5.4.0') !== -1) {
            $args = array_slice($args, 0, 4);
        } else if (version_compare($version, '5.3.0') !== -1) {
            $args = array_slice($args, 0, 3);
        } else {
            $args = array_slice($args, 0, 2);
        }

        $response = call_user_func_array('json_decode', $args);
        if ($response === null) {
            $response = $args[0];
        }
        return $response;
    }
}