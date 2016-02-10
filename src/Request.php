<?php
/**
 * Created by PhpStorm.
 * User: lejla
 * Date: 2016.02.09.
 * Time: 17:34
 */

namespace CurlX;

/**
 * Class Request
 * @package CurlX
 *
 * @property string $url
 * @property array $post
 * @property float $time
 * @property int $timeout
 * @property array $options
 * @property array $headers
 * @property resource $handle
 * @property mixed $response
 * @property callable[] $listeners
 */
class Request implements RequestInterface
{
    protected $url;
    protected $post = [];
    protected $startTime;
    protected $endTime;
    protected $result;
    protected $listeners = [];
    protected $timeout;
    protected $curlHandle;
    protected $headers = [];
    protected $options = [];
    protected $success;
    protected $response;

    /**
     * @param $str
     * @return mixed
     */
    private function camelize($str)
    {
        return str_replace('_', '', ucwords($str, '_'));
    }

    public function __set($name, $value)
    {
        $c = $this->camelize($name);
        $m = "set$c";
        if (method_exists($this, $m)) {
            return $this->$m($value);
        } else {
            user_error("undefined property $name");
        }
    }

    public function __get($name)
    {
        $c = $this->camelize($name);
        $m = "get$c";
        if (method_exists($this, $m)) {
            return $this->$m();
        } else {
            user_error("undefined property $name");
        }
    }

    /**
     * Request constructor.
     * @param string $url optional url
     */
    public function __construct($url = null)
    {
        $this->setUrl($url);

        // Defaults
        $this->options[CURLOPT_RETURNTRANSFER] = true;
        $this->options[CURLOPT_NOSIGNAL] = 1;
    }

    /**
     * Normalize an array
     * change from ['key' => 'value'] format to ['key: value']
     * @param array $array array to normalize
     * @return array normalized array
     */
    private function normalize(array $array)
    {
        $normalized = [];
        foreach($array as $key => $value) {
            if(is_string($key)) {
                $normalized[] = $key . ': ' . $value;
            } else {
                $normalized[] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Setter for the url field
     * @param string $url url
     * @return void
     */
    public function setUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->url = $url;
        }
    }

    /**
     * Getter for url field
     * @return string url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Setter for the post data array
     * @param array $postData post data
     * @return void
     */
    public function setPostData(array $postValues)
    {
        $this->post += $postValues;
        $this->options[CURLOPT_POST] = 1;
        if (!empty($this->post)) {
            $this->options[CURLOPT_POSTFIELDS] = http_build_query($this->post);
        }
    }

    /**
     * Getter for the post data array
     * @return array post data
     */
    public function getPostData()
    {
        return $this->post;
    }

    /**
     * Returns the time (msec) it took to make the request
     * @return float time
     */
    public function getTime()
    {
        return $this->endTime - $this->startTime;
    }

    /**
     * Start the request's internal timer
     * @return void
     */
    public function startTimer()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Stops the request's internal timer
     * @return void
     */
    public function stopTimer()
    {
        $this->endTime = microtime(true);
    }

    /**
     * Get the result of a query
     * @return mixed result
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * This gets called by an agent when a request has completed
     * @param mixed $result result
     * @return void
     */
    public function callBack($result)
    {
        $this->stopTimer();
        $this->result = $result;

        $requestInfo = curl_getinfo($this->curlHandle);

        if (curl_errno($this->curlHandle) !== 0 || intval($requestInfo['http_code']) !== 200) {
            $this->success = false;
        } else {
            $this->success = true;
            $this->response = curl_multi_getcontent($this->ch);
        }

        $this->notify();
    }

    /**
     * Add a listener that gets notified when the Request has completed
     * @param callable $function callback function
     * @return void
     */
    public function addListener(callable $function)
    {
        if (is_callable($function)) {
            $this->listeners += $function;
        }
    }

    /**
     * Notify all listeners of request completion
     * @return void
     */
    protected function notify()
    {
        foreach ($this->listeners as $listener) {
            call_user_func($listener, $this);
        }
    }

    /**
     * Set a timeout value for the request
     * @param float $timeout timeout (msec)
     * @return void
     */
    public function setTimeout($timeout)
    {
        if ($timeout > 0) {
            $this->timeout = $timeout;
            $this->options[CURLOPT_TIMEOUT_MS] = $this->timeout;
        }
    }

    /**
     * Get the timeout value registered for the request
     * @return float timeout
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Get the cUrl handle for the request
     * @return resource cUrl handle
     */
    public function getHandle()
    {
        if (!isset($this->curlHandle)) {
            $this->curlHandle = curl_init($this->url);
            curl_setopt_array($this->curlHandle, $this->options);
        }

        return $this->curlHandle;
    }

    /**
     * Add headers to the request
     * @param array $headers headers in ['key' => 'value] or ['key: value'] format
     * @return void
     */
    public function setHeaders(array $headers)
    {
        $this->headers += $this->normalize($headers);
        $this->options[CURLOPT_HTTPHEADER] = $this->headers;
    }

    /**
     * Get headers set for the request
     * @return array headers in ['key' => 'value'] format
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Add cUrl options to the request
     * @param array $options options in ['key' => 'value'] format
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->options += $options;
    }

    /**
     * Get cUrl options set for the request
     * @return array options in ['key' => 'value'] format
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get the response for the finished query
     * @return mixed response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
