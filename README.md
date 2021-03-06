# Curl
Curl is a wrapper of php cURL,chainable and simple

# Installation

    composer require exgalibas/curl

# Request Methods
GET|POST|OPTIONS|HEAD|PATCH|PUT|DELETE

# Usage
### Basic
```php
$curl = new exgalibas\curl\Curl("http://example.com/index");
$curl->get(["name" => "exgalibas"]);

// check curl error
if (!$curl->error) {
    // output original curl response with header
    var_dump($curl->curlRawResponse);

    // output original curl response without header
    var_dump($curl->rawResponse);

    // output response processed by decoder
    var_dump($curl->response);

    // output real url
    var_dump($curl->url);

    // output request headers
    var_dump($curl->headers);

    // output curl options
    var_dump($curl->options);

    // output original curl response header
    var_dump($curl->rawResponseHeaders);

    // output processed response header
    var_dump($curl->responseHeaders);
} else {
    // output error
    var_dump($curl->error);

    // output error code
    var_dump($curl->errorCode);

    // output error message
    var_dump($curl->errorMessage);

    // output curl error
    var_dump($curl->curlError);

    // output curl error code
    var_dump($curl->curlErrorCode);

    // output curl error message
    var_dump($curl->curlErrorMessage);

    // output http error
    var_dump($curl->httpError);

    // output http status code
    var_dump($curl->httpStatusCode);

    // output http status
    var_dump($curl->httpStatus);
}

// another style
$curl = new curl\Curl();
$curl->url("http://example.com/index")->get(["name" => "exgalibas"]);

// transfer raw string
$curl->url("http://example.com/index")->get("a=b&c=d");
```

### Set Timeout
```php
$curl->url("http://example.com/index")->expire(10)->get(["name" => "exgalibas"]);
```

### Set Retry
```php
$curl->url("http://example.com/index")->retry(4)->get(["name" => "exgalibas"]);
```

### Set Header
```php
$curl->url("http://example.com/index")->header("Content-Type", "application/json")->post(["name" => "exgalibas"]);
```

### POST
```php
$curl->url("http://example.com/index")->post(["name" => "exgalibas"]);

// multidimensional array
$curl->url("http://example.com/index")->post([
    "message" => [
        "name" => "exgalibas",
        "emial" => "exgalibas@gmail.com"
    ]
]);

// raw string
$curl->url("http://example.com/index")->post("abc"); //if server want to get the post string,do not use $_POST,file_get_contents("php://input") will work

// post file
$curl->url("http://example.com/index")->post([
    "file" => new CURLFile(__DIR__ . '/index.php')
]);

// another post file
$curl->url("http://example.com/index")->post([
    "file" => '@' . __DIR__ . '/index.php'
])

// post complicated data
$curl->url("http://example.com/index")->post([
    "name" => "exgalibas",
    "message" => [
        "age" => 18,
        "size" => 18
    ],
    "file" => [
        "file1" => new CURLFile(__DIR__ . '/index.php'),
        "file2" => '@' . __DIR__ . '/index1.php'
    ],
])

// post json data
$curl->url("http://example.com/index")->json()->post(["name" => "exgalibas"]);

// another post json data
$data = json_encode(["name" => "exgalibas"]);
$curl->url("http://example.com/index")->post($data);
```

### Processing Response
There are two default decoders in the map, jsonDecoder and xmlDecoder,the pattern of map is [regex string => decoder].if you want to use default decoders,the Content-Type of response header must be the right type,like application/json,you can also specify other decoder or add new rules in the map.
```php
// specify decoder
// $decoder must implements Decoder interface,$args will be transferred to $decoder
$curl->url("http://example.com/index")->decoder($decoder)->decoderArgs($args)->get();

// jsonDecoder
$args = [true, 12, JSON_BIGINT_AS_STRING]; //args will be used by json_decode()
$curl->url("http://example.com/index")->decoderArgs($args)->get();


// add new rules, it will search the right decoder by comparing the Content-Type of response and the regex of map automatically
$curl->url("http://example.com/index")->map('~^(?:text/|application/(?:atom\+|rss\+)?)xml~i', 'exgalibas\curl\XmlDecoder')->get();
```

### Todo
add flexible mullti curl

