<?php
/**
 * Date: 2018/2/1
 * @author joker <exgalibas@gmail.com>
 */

namespace exgalibas\curl;

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
     * @var null
     */
    public $data = null;

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
        '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i' => 'exgalibas\curl\JsonDecoder',
        '~^(?:text/|application/(?:atom\+|rss\+)?)xml~i' => 'exgalibas\curl\XmlDecoder',
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
        $this->init($url);
    }

    /**
     * @param null $url
     * @return $this
     */
    protected function init($url = null)
    {
        $this->curl = curl_init();
        $this->setDefaultUserAgent();
        $this->setDefaultTimeout();
        $this->opt(CURLOPT_RETURNTRANSFER, true);
        $this->opt(CURLOPT_HEADER, true);
        $this->opt(CURLOPT_NOBODY, false);
        $this->opt(CURLINFO_HEADER_OUT, true);
        $this->url($url);
        return $this;
    }

    /**
     * @return $this
     *
     * set default user agent
     */
    protected function setDefaultUserAgent()
    {
        $agent = "exgalibas/Curl (https://github.com/exgalibas/Curl.git)";
        $this->agent($agent);
        return $this;
    }

    /**
     * @param $agent
     * @return Curl
     *
     * set user agent
     */
    public function agent($agent)
    {
        return $this->opt(CURLOPT_USERAGENT, $agent);
    }

    /**
     * @return Curl
     *
     * set default timeout
     */
    protected function setDefaultTimeOut()
    {
        return $this->expire(self::TIME_OUT);
    }

    /**
     * @param $time
     * @return Curl
     *
     * set timeout
     */
    public function expire($time)
    {
        return $this->opt(CURLOPT_TIMEOUT, $time);
    }

    /**
     * @param $option
     * @param $value
     * @return $this
     *
     * set curl option
     */
    public function opt($option, $value)
    {
        if (curl_setopt($this->curl, $option, $value)) {
            $this->options[$option] = $value;
        }
        return $this;
    }

    /**
     * @param array $options
     * @return mixed
     *
     * set curl options
     */
    public function opts(array $options)
    {
        foreach ($options as $option => $value) {
            $this->opt($option, $value);
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
     * @param $data
     * @return $this
     *
     * format request url with data
     */
    protected function formatUrl($data)
    {
        if ($this->url) {
            $this->url = Helper::formatUrl($this->url, $data);
        }
        return $this;
    }

    /**
     * @param $url
     * @return $this
     *
     * set request url
     */
    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param $pattern
     * @param $decoder
     * @return $this
     *
     * add decoder into array map
     */
    public function map($pattern, $decoder)
    {
        $this->decoderMap[$pattern] = $decoder;
        return $this;
    }

    /**
     * @param Decoder $decoder
     * @return $this
     *
     * specify decoder
     */
    public function decoder(Decoder $decoder)
    {
        $this->decoder = $decoder;
        return $this;
    }

    /**
     * @param array $data
     *
     * get request
     */
    public function get($data = [])
    {

        $this->formatUrl($data);
        $this->opt(CURLOPT_CUSTOMREQUEST, 'GET');
        $this->opt(CURLOPT_HTTPGET, true);
        $this->exec();
    }

    /**
     * @param array $data
     *
     * post request
     */
    public function post($data = [])
    {
        $this->header('Expect', '');
        $this->opt(CURLOPT_CUSTOMREQUEST, 'POST');
        $this->opt(CURLOPT_POST, true);
        $this->opt(CURLOPT_SAFE_UPLOAD, true);
        $this->opt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        $this->exec();
    }


    /**
     * @return $this
     *
     * set json
     */
    public function json()
    {
        $this->header('Content-Type', 'application/json');
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     *
     * set curl header
     */
    public function header($key, $value)
    {
        $this->headers[$key] = $value;
        $headers = $this->dealHeaders();
        $this->opt(CURLOPT_HTTPHEADER, $headers);
        return $this;
    }

    /**
     * @param array $head
     * @return $this
     *
     * set curl headers
     */
    public function headers(array $head)
    {
        $this->headers = array_merge($this->headers, $head);
        $headers = $this->dealHeaders();
        $this->opt(CURLOPT_HTTPHEADER, $headers);
        return $this;
    }

    /**
     * @param $key
     *
     * remove specified header
     */
    public function removeHeader($key)
    {
        $this->header($key, '');
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
        $has_file = false;
        foreach ($data as $key => $value)
        {
            if (is_string($value) && strpos($value, '@') === 0 && is_file(substr($value, 1))) {
                $has_file = true;
                $data[$key] = new \CURLFile(substr($value, 1));
            } elseif ($value instanceof \CURLFile) {
                $has_file = true;
            }
        }

        if (!$has_file) {
            $data = http_build_query($data, '', '&');
        }

        return $data;
    }

    /**
     * @param array $data
     *
     * options request
     */
    public function options($data = [])
    {
        $this->formatUrl($data);
        $this->opt(CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        $this->exec();
    }

    /**
     * @param array $data
     *
     * head request
     */
    public function head($data = [])
    {
        $this->formatUrl($data);
        $this->opt(CURLOPT_CUSTOMREQUEST, 'HEAD');
        $this->opt(CURLOPT_NOBODY, true);
        $this->exec();
    }

    /**
     * @param array $data
     *
     * patch request
     */
    public function patch($data = [])
    {
        if (is_array($data) && empty($data)) {
            $this->removeHeader('Content-Length');
        }

        $this->opt(CURLOPT_CUSTOMREQUEST, 'PATCH');
        $this->opt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        $this->exec();
    }

    /**
     * @param array $data
     *
     * put request
     */
    public function put($data = [])
    {
        $this->opt(CURLOPT_CUSTOMREQUEST, 'PUT');
        $put_data = $this->buildPostData($data);
        if (empty($this->options[CURLOPT_INFILE]) && empty($this->options[CURLOPT_INFILESIZE])) {
            if (is_string($put_data)) {
                $this->header('Content-Length', strlen($put_data));
            }
        }
        if (!empty($put_data)) {
            $this->opt(CURLOPT_POSTFIELDS, $put_data);
        }
        $this->exec();
    }

    /**
     * @param array $data
     * @param array $query
     *
     * delete request
     */
    public function delete($data = [], $query = [])
    {
        $this->formatUrl($query);
        $this->opt(CURLOPT_CUSTOMREQUEST, 'DELETE');
        $this->opt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        $this->exec();
    }

    /**
     * @param $count
     * @return $this
     *
     * set retry count
     */
    public function retry($count)
    {
        $this->retryCount = intval($count);
        return $this;
    }

    protected function attemptRetry()
    {
        if ($this->retryCount > 0) return true;
        return false;
    }

    /**
     * exec Curl request
     */
    protected function exec()
    {
        $this->run();
        $this->checkHttpStatus();
        $this->dealCurlRawResponse();
        $this->checkError();
        if ($this->error && $this->attemptRetry()) {
            $this->retryCount--;
            $this->exec();
        }
    }

    /**
     * run curl_exec
     */
    protected function run()
    {
        $this->opt(CURLOPT_URL, $this->url);
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
        $this->options = null;
        $this->decoder = null;
        $this->decoderArgs = null;
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
    public function decoderArgs($args, $merge = true)
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

