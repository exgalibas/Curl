<?php
/**
 * Date: 2018/2/1
 * @author joker <exgalibas@gmail.com>
 */

namespace Curl;

class Curl
{
    /**
     * default 30s time out
     */
    const TIME_OUT = 30;

    /**
     * @var $rawResponseHeaders
     *
     * original response header without any dealing
     */
    public $rawResponseHeaders;

    /**
     * @var $responseHeaders
     *
     * response header after dealing
     */
    public $responseHeaders;

    /**
     * @var array $headers
     *
     * array of CURLOPT_HTTPHEADER
     */
    public $headers = [];

    /**
     * @var $curl
     *
     * instance created by curl_init()
     */
    public $curl = null;

    /**
     * @var array $options
     *
     * options set by curl_setopt()
     */
    public $options = [];

    /**
     * @var null $url
     *
     * request url
     */
    public $url = null;

    /**
     * @var int $retryCount
     *
     * retry specified times when curl_exec() fail, default 0 time
     */
    public $retryCount = 0;

    /**
     * @var $curlRawResponse
     *
     * return of curl_exec() without any dealing, include headers
     */
    public $curlRawResponse;

    /**
     * @var $rawResponse
     *
     * the real response without headers
     */
    public $rawResponse;

    /**
     * @var $response
     *
     * standard formatting response, dealing by decoder
     */
    public $response;

    /**
     * @var bool $curlError
     *
     * whether curl_exec succeed, true or false
     */
    public $curlError = false;

    /**
     * @var int $curlErrorCode
     *
     * error code of curl
     */
    public $curlErrorCode = 0;

    /**
     * @var $curlErrorMessage
     *
     * error message of curl
     */
    public $curlErrorMessage;

    /**
     * @var $httpStatusCode
     *
     * http code
     */
    public $httpStatusCode;

    /**
     * @var bool $httpError
     *
     * whether http request succeed, true or false
     */
    public $httpError = false;

    /**
     * @var $httpStatus
     *
     * http status message
     */
    public $httpStatus;

    /**
     * @var bool $error
     *
     * whether Curl succeed, $curlError | $httpError, true or false
     */
    public $error = false;

    /**
     * @var $errorCode
     *
     * Curl error code
     */
    public $errorCode;

    /**
     * @var $errorMessage
     *
     * Curl error message
     */
    public $errorMessage;

    /**
     * @var Decoder $decoder
     *
     * decoder used dealing $rawResponse
     */
    public $decoder;

    /**
     * @var array $decoderArgs
     *
     * args of decoder
     */
    public $decoderArgs = [];

    /**
     * @var array $decoderMap
     *
     * decoder map, [decoder pattern => decoder function]
     */
    public $decoderMap = [
        '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i' => 'JsonDecoder',
        '~^(?:text/|application/(?:atom\+|rss\+)?)xml~i' => 'XmlDecoder',
    ];

    /**
     * Curl constructor.
     * @param null $url
     * @throws \ErrorException
     */
    public function __construct($url = null)
    {
        if (!extension_loaded('curl')) {
            throw new \ErrorException("can not load Curl extension");
        }
        $this->init();
    }

    /**
     * @param null $url
     * @return $this
     */
    public function init($url = null)
    {
        $this->curl = curl_init();
        $this->setDefaultUserAgent();
        $this->setDefaultTransfer();
        $this->setDefaultTimeout();
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);
        $this->setOpt(CURLOPT_HEADER, true);
        $this->setOpt(CURLOPT_NOBODY, false);
        $this->setOpt(CURLINFO_HEADER_OUT, true);
        $this->setUrl($url);
        return $this;
    }

    /**
     * @return $this
     *
     * set default user agent
     */
    public function setDefaultUserAgent()
    {
        $agent = "exgalibas/Curl (https://github.com/exgalibas/Curl.git)";
        $this->setUserAgent($agent);
        return $this;
    }

    /**
     * @param $agent
     * @return Curl
     *
     * set user agent
     */
    public function setUserAgent($agent)
    {
        return $this->setOpt(CURLOPT_USERAGENT, $agent);
    }

    /**
     * @return Curl
     *
     * set CURLOPT_RETURNTRANSFER true
     */
    public function setDefaultTransfer()
    {
        return $this->setOpt(CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * @return Curl
     *
     * set default timeout
     */
    public function setDefaultTimeOut()
    {
        return $this->setTimeOut(self::TIME_OUT);
    }

    /**
     * @param $time
     * @return Curl
     *
     * set timeout
     */
    public function setTimeOut($time)
    {
        return $this->setOpt(CURLOPT_TIMEOUT, $time);
    }

    /**
     * @param $option
     * @param $value
     * @return $this
     *
     * set curl option
     */
    public function setOpt($option, $value)
    {
        if (curl_setopt($this->curl, $option, $value)) {
            $this->options[$option] = $value;
        }
        return $this;
    }

    /**
     * @param $opt
     * @return null
     *
     * get specified option
     */
    public function getOpt($opt)
    {
        return $this->options[$opt] ?? null;
    }

    /**
     * @return array
     *
     * get all options
     */
    public function getOpts()
    {
        return $this->options;
    }

    /**
     * @param $url
     * @param null $data
     * @return Curl
     *
     * set request url
     */
    public function setUrl($url, $data = null)
    {
        if ($url) {
            $url = strval($url);
            $this->url = Helper::formatUrl($url, $data);
        }
        return $this->setOpt(CURLOPT_URL, $this->url);
    }

    /**
     * @param $pattern
     * @param $decoder
     *
     * add decoder into array map
     */
    public function setDecoderMap($pattern, $decoder)
    {
        $this->decoderMap[$pattern] = $decoder;
    }

    /**
     * @param Decoder $decoder
     *
     * specify decoder
     */
    public function setDecoder(Decoder $decoder)
    {
        $this->decoder = $decoder;
    }

    /**
     * @param $url
     * @param null $data
     *
     * get request
     */
    public function get($url, $data = null)
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->url;
        }
        $this->setUrl($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'GET');
        $this->setOpt(CURLOPT_HTTPGET, true);
        $this->exec();
    }

    /**
     * @param $url
     * @param array $data
     *
     * post request
     */
    public function post($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->url;
        }
        $this->setUrl($url);
        $this->setHeader('Expect', '');
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'POST');
        $this->setOpt(CURLOPT_POST, true);
        $this->setOpt(CURLOPT_SAFE_UPLOAD, true);
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        $this->exec();
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     *
     * set curl header
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        $headers = $this->dealHeaders();
        $this->setOpt(CURLOPT_HTTPHEADER, $headers);
        return $this;
    }

    /**
     * @param array $head
     * @return $this
     *
     * set curl headers
     */
    public function setHeaders(array $head)
    {
        $this->headers = array_merge($this->headers, $head);
        $headers = $this->dealHeaders();
        $this->setOpt(CURLOPT_HTTPHEADER, $headers);
        return $this;
    }

    /**
     * @return array
     *
     * deal headers
     */
    protected function dealHeaders()
    {
        return array_map(function($k, $v) {
            return $k . ': ' . $v;
        }, array_keys($this->headers), $this->headers);
    }

    /**
     * @param $data
     * @param null $prefix
     * @return array|string
     *
     * deal post data
     */
    protected function buildPostData($data, $prefix = null)
    {
        if (!is_array($data)) return $data;

        $json_pattern = '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';
        if (isset($this->headers['Content-Type']) &&
            preg_match($json_pattern, $this->headers['Content-Type']) &&
            ($json_str = json_encode($data)) !== false)
        {
            return $json_str;
        }

        $data = Helper::demotion($data, $prefix);
        foreach ($data as $key => $value)
        {
            if (is_string($value) && strpos($value, '@') === 0 && is_file(substr($value, 1))) {
                $has_file = true;
                $data[$key] = new CURLFile(substr($value, 1));
            } elseif ($value instanceof CURLFile) {
                $has_file = true;
            }
        }

        if (!$has_file) {
            $data = http_build_query($data, '', '&');
        }

        return $data;
    }

    /**
     * @param $url
     * @param array $data
     *
     * options request
     */
    public function options($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->url;
        }
        $this->setUrl($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        $this->exec();
    }

    /**
     * @param $url
     * @param array $data
     *
     * head request
     */
    public function head($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->url;
        }
        $this->setUrl($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'HEAD');
        $this->setOpt(CURLOPT_NOBODY, true);
        $this->exec();
    }

    /**
     * exec Curl request
     */
    public function exec()
    {
        if ($this->retryCount < 0) return;
        $this->retryCount--;

        $this->run();
        $this->checkHttpStatus();
        $this->dealCurlRawResponse();
        $this->checkError();
        $this->close();
    }

    /**
     * run curl_exec
     */
    protected function run()
    {
        if (($curl_ret = curl_exec($this->curl)) === false) {
            $this->curlError = true;
            $this->curlErrorCode = curl_errno($this->curl);
            $this->curlErrorMessage = curl_error($this->curl);
        }

        $this->curlRawResponse = $this->curlError ? null : $curl_ret;
    }

    /**
     * init http status and code
     */
    protected function checkHttpStatus()
    {
        if (!$this->curlError) {
            $this->httpStatusCode = $this->info(CURLINFO_HTTP_CODE);
            $this->httpError = in_array(floor($this->httpStatusCode / 100), [4, 5]);
        }
    }

    /**
     * @param int $opt
     * @return mixed
     *
     * get information of curl
     */
    public function info($opt = 0)
    {
        return curl_getinfo($this->curl, $opt);
    }

    /**
     * deal with $curlRawResponse
     */
    protected function dealCurlRawResponse()
    {
        if ($this->curlRawResponse === null) return;
        $header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);

        if ($this->getOpt(CURLOPT_HEADER)) {
            $this->rawResponseHeaders = substr($this->curlRawResponse, 0, $header_size);
            list($this->httpStatus, $this->responseHeaders) = $this->parseHeaders($this->rawResponseHeaders);
        }
        $this->rawResponse = substr($this->curlRawResponse, $header_size);
        $this->response = $this->parseResponse();
    }

    /**
     * @param $raw_headers
     * @return array|null
     *
     * deal with $rawResponseHeaders
     */
    protected function parseHeaders($raw_headers)
    {
        $raw_headers = preg_split('/\r\n/', $raw_headers, null, PREG_SPLIT_NO_EMPTY);
        if (empty($raw_headers)) return null;

        $http_headers = [];
        $status_line = array_shift($raw_headers);
        foreach ($raw_headers as $header) {
            if (strpos($header, ':') !== false) {
                list ($key, $value) = explode(':', $header);
                $key = strtolower(trim($key));
                $value = trim($value);
                if (isset($http_headers[$key])) {
                    !is_array($http_headers[$key]) && $http_headers[$key] = [$http_headers[$key]];
                    $http_headers[$key][] = $value;
                } else {
                    $http_headers[$key] = $value;
                }
            }
        }

        return [$status_line, $http_headers];
    }

    /**
     * @return mixed
     *
     * deal with rawResponse
     */
    protected function parseResponse()
    {
        if (!($response = $this->rawResponse)){
            return $this->rawResponse;
        }
        array_unshift($this->decoderArgs, $response);
        if ($this->decoder instanceof Decoder) {
            return call_user_func_array([$this->decoder, 'decode'], $this->decoderArgs);
        }
        $type = $this->responseHeaders['content-type'];
        foreach ($this->decoderMap as $pattern => $decode) {
            if (preg_match($pattern, $type)) {
                return call_user_func_array([$decode, 'decode'], $this->decoderArgs);
            }
        }

        return $response;
    }

    /**
     * check Curl error
     */
    protected function checkError()
    {
        $this->error = $this->curlError || $this->httpError;
        $this->errorCode = $this->error ? ($this->curlError ? $this->curlErrorCode : $this->httpStatusCode) : 0;
        $this->errorMessage = $this->error ? ($this->curlError? $this->curlErrorMessage : $this->httpStatus) : null;
    }

    /**
     * close Curl
     */
    protected function close()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }

    /**
     * destruct
     */
    public function __destruct()
    {
       $this->close();
    }

    /**
     * @param $args
     * @param bool $merge
     * @return $this
     *
     * set decoder args
     */
    public function setDecodeArgs($args, $merge = true)
    {
        !is_array($args) && $args = [$args];
        if (!empty($args)) {
            if ($merge) {
                $this->decoderArgs = array_merge($this->decoderArgs, $args);
            } else {
                $this->decoderArgs = $args;
            }
        }
        return $this;
    }
}

