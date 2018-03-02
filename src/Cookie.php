<?php
/**
 * Date: 2018/2/8
 * @author joker <exgalibas@gmail.com>
 */
namespace exgalibas\curl;

class Cookie
{
    public $name;
    public $value;
    public $expire;
    public $path = '';
    public $domain = '';
    public $secure = false;
    public $httpOnly = false;
}