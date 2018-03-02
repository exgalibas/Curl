<?php
/**
 * Date: 2018/3/1
 * @author joker <exgalibas@gmail.com>
 */

namespace exgalibas\curl;

class Helper
{
    /**
     * @param array $array
     * @param null $prefix
     * @return array
     *
     * deal with multidimensional array
     */
    public static function demotion(array $array, $prefix = null)
    {
        $ret = [];
        foreach ($array as $key => $value)
        {
            $key = $prefix ? "{$prefix}[$key]" : $key;
            if (is_array($value)) {
                $ret = array_merge(
                    $ret,
                    self::demotion($value, $key)
                );
            } else {
                $ret[$key] = $value;
            }
        }
        return $ret;
    }

    /**
     * @param $url
     * @param null $data
     * @param bool $append
     * @return mixed|string
     *
     * format url
     */
    public static function formatUrl($url, $data = null, $append = true)
    {
        if (empty($data)) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $data = is_array($data) ? http_build_query($data, null, '&') : $data;
        if (!$query) {
            return $url . '?' . $data;
        }

        if ($append) {
            return $url . '&' . $data;
        }

        return str_replace($query, $data, $url);
    }
}