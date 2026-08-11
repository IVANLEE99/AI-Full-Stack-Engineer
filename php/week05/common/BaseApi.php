<?php

namespace common;

use common\components\CurlClient;
use fecshop\app\operateapi\modules\TrafficSource;
use Yii;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;
use yii\httpclient\Exception;

/**
 * Class BaseApi
 * 通过curl调用内部子系统，需继承此类
 *
 * @package common
 */
abstract class BaseApi
{
    /**
     * @var array 共享头部
     */
    protected $headers = [];
    /**
     * @var array 共享配置
     */
    protected $options = [
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 60,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    ];
    /**
     * @var CurlClient
     */
    private $_client;

    /**
     * 获取api域名地址
     *
     * @return string
     */
    abstract protected function getBaseUrl();

    /**
     * 获取client实例
     *
     * @return CurlClient
     */
    protected function getClient()
    {
        if ($this->_client === null) {
            $client          = new CurlClient();
            $client->baseUrl = $this->getBaseUrl();

            $this->_client = $client;
        }

        return $this->_client;
    }

    /**
     * 发送GET请求
     *
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @param array  $options
     *
     * @return array|bool|mixed
     */
    public function get($url, $data = null, $headers = [], $options = [])
    {
        $this->generateRequestId($headers, $data);
        try {
            $response = $this->createRequest('GET', $url, $data, $headers, $options)->send();

            if (!$response->getIsOk()) {
                $message = 'Status Code: ' . $response->getStatusCode() . ', Content: ' . mb_substr($response->getContent(), 0, 255);
                //记录日志
                Yii::error(sprintf('GET请求出错，url：%s，参数：%s，错误码：%s，错误信息：%s', $url, json_encode($data), $response->getStatusCode(), $response->getContent()));
                throw new Exception($message);
            }

            $result = $response->getData();
            return $this->checkResult($result);

        } catch (Exception $e) {
            Yii::error($e, __METHOD__);
            return $this->returnError(404, $e->getMessage());
        }
    }

    /**
     * 发送POST请求
     *
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @param array  $options
     * @param string $format
     *
     * @return array|bool|mixed
     */
    public function post($url, $data = null, $headers = [], $options = [], $format = Client::FORMAT_URLENCODED)
    {
        $this->generateRequestId($headers, $data);
        try {
            $response = $this->createRequest('POST', $url, $data, $headers, $options, $format)->send();
            if (!$response->getIsOk()) {
                $message = 'Status Code: ' . $response->getStatusCode() . ', Content: ' . mb_substr($response->getContent(), 0, 255);
                //记录日志
                Yii::error(sprintf('POST请求出错，url：%s，参数：%s，错误码：%s，错误信息：%s', $url, json_encode($data), $response->getStatusCode(), $response->getContent()));
                throw new Exception($message);
            }

            $result = $response->getData();
            return $this->checkResult($result);

        } catch (Exception $e) {
            Yii::error($e, __METHOD__);
            return $this->returnError(404, $e->getMessage());
        }
    }

    protected function isBot($userAgent): bool
    {
        try {
            if (empty($userAgent) || !is_string($userAgent)) {
                return false;
            }
            $userAgent       = strtolower($userAgent);
            $allowUserAgents = ['bot', 'google', 'spider', 'slurp', 'facebook', 'pinterest', 'crawler', 'crawling', 'externalhit'];
            foreach ($allowUserAgents as $allowUserAgent) {
                if (stripos($userAgent, $allowUserAgent) !== false) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getPartition($userAgent): string
    {
        if ($this->isBot($userAgent)) {
            return TrafficSource::B;
        }

        return TrafficSource::getValue() ?: TrafficSource::C;
    }

    /**
     * 生成 request_id
     *
     * @param $header
     * @param $data
     *
     * @todo 在白名单中，才设置hm-request-id，在internal（所有internal，包括售后也需要新增）中，
     * 只要有hm-request-id就记录request/response日志
     */
    private function generateRequestId(&$header, $data)
    {
        // 请求头上传递ua
        if (Yii::$app->request->isConsoleRequest) {
            $userAgent = "cli";
        } else {
            $userAgent = Yii::$app->request->userAgent;
        }
        $header['user-agent']    = $userAgent ?: '';
        $header['ip_address']    = $this->JPGetRealIp();
        $header['partition-for'] = $this->getPartition($userAgent);

        //非frontapi不设置
        $isFecApp = defined('FEC_APP') && FEC_APP == 'frontapi';
        if (!$isFecApp) {
            return;
        }

        //非白名单不设置
        $pathInfo = \Yii::$app->request->getPathInfo();
        $requestIdUrls = \Yii::$app->params["request_id_urls"];
        if (!array_key_exists($pathInfo, $requestIdUrls)) {
            return;
        }

        //从前端获取参数
        $requestId = $_SERVER['HTTP_HM_REQUEST_ID'] ?? '';
        if ($requestId == '') {
            $md5       = md5(json_encode($data));
            $microTime = microtime(true);
            $microTime = str_replace('.', '', $microTime);
            $requestId = substr($md5, 0, 8) . $microTime;
        }
        $header['hm-request-id'] = $requestId;
        $header['hm-referer']    = $_SERVER["HTTP_REFERER"] ?? '';
        $header['hm-user-agent'] = $_SERVER["HTTP_USER_AGENT"] ?? '';
        header("hm-request-id: {$requestId}");
        return;
    }

    private function JPGetRealIp()
    {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
        if (empty($ip)) {
            $ip = getenv("REMOTE_ADDR");
        }

        $ip = explode(',', $ip);
        return trim($ip[0] ?? '') ?? '';
    }

    private function checkResult($result)
    {
        if (!isset($result['code'])) {
            return $this->returnError(400, '', $result);
        }
        if (!isset($result['info'])) {
            $result['info'] = '';
        }
        if (!isset($result['data'])) {
            $result['data'] = null;
        }
        return $result;
    }

    /**
     * 发送PUT请求
     *
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @param array  $options
     *
     * @return array|bool|mixed
     */
    protected function put($url, $data = null, $headers = [], $options = [])
    {
        try {
            $response = $this->createRequest('PUT', $url, $data, $headers, $options)->send();

            if (!$response->getIsOk()) {
                $message = 'Status Code: ' . $response->getStatusCode() . ', Content: ' . mb_substr($response->getContent(), 0, 255);
                throw new Exception($message);
            }
            $result = $response->getData();
            return $this->checkResult($result);
        } catch (Exception $e) {
            Yii::error($e, __METHOD__);
            return $this->returnError(404, $e->getMessage());
        }
    }

    /**
     * 发送DELETE请求
     *
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @param array  $options
     *
     * @return array|bool|mixed
     */
    protected function delete($url, $data = null, $headers = [], $options = [])
    {
        try {
            $response = $this->createRequest('DELETE', $url, $data, $headers, $options)->send();
            if (!$response->getIsOk()) {
                $message = 'Status Code: ' . $response->getStatusCode() . ', Content: ' . mb_substr($response->getContent(), 0, 255);
                throw new Exception($message);
            }
            $result = $response->getData();
            return $this->checkResult($result);
        } catch (Exception $e) {
            Yii::error($e, __METHOD__);
            return $this->returnError(404, $e->getMessage());
        }
    }

    /**
     * 创建Request请求对象
     *
     * @param string $method
     * @param string $url
     * @param mixed  $data
     * @param array  $headers
     * @param array  $options
     *
     * @return \yii\httpclient\Request
     */
    protected function createRequest($method, $url, $data = null, $headers = [], $options = [], $format = Client::FORMAT_URLENCODED)
    {
        $request = $this->getClient()->createRequest()
            ->setMethod($method)
            ->setUrl($url)
            ->setFormat($format)
            ->addHeaders($this->headers)
            ->addHeaders($headers)
            ->addOptions($this->options)
            ->addOptions($options);

        if (is_array($data)) {
            $request->setData($data);
        } else {
            $request->setContent($data);
        }

        return $request;
    }

    protected function returnFormat($code, $data = null, $info = '')
    {
        return ['code' => (int)$code, 'data' => $data, 'info' => $info];
    }

    protected function returnSuccess($data = null, $info = '')
    {
        return ['code' => 1, 'data' => $data, 'info' => $info];
    }

    protected function returnError($code = 0, $info = '', $data = null)
    {
        return ['code' => $code, 'info' => $info, 'data' => $data];
    }

    /**
     * @return static
     */
    public static function instance()
    {
        $container = Yii::$container;
        if (!$container->hasSingleton(static::class)) {
            $container->setSingleton(static::class);
        }
        return $container->get(static::class);
    }

    /**
     * 使用 Guzzle 实现内网流式消费
     */
    public function curlStream($url, callable $callback, $data = null, $headers = [])
    {
        $this->generateRequestId($headers, $data);
        $baseUrl = rtrim($this->getBaseUrl(), '/');
        $fullUrl = $baseUrl . '/' . ltrim($url, '/');
        $client = new \GuzzleHttp\Client(['timeout' => 0]);
        $options = [
            'headers' => array_merge($headers, [
                'Content-Type'  => 'application/json',
                'Accept'        => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]),
            'json'   => $data,
            'stream' => true,
        ];
        $startTime = microtime(true);
        try {
            $response = $client->request('POST', $fullUrl, $options);
            $body = $response->getBody();
            $logEnabled = getenv('SSE_STREAM_LOG_ENABLED',0);
            while (!$body->eof()) {
                $line = \GuzzleHttp\Psr7\Utils::readline($body);
                if ($logEnabled) {
                    if ($logEnabled && $line !== "") {
                        $duration = round(microtime(true) - $startTime, 3);
                        \Yii::info([
                            'url'      => $fullUrl,
                            'request'  => $data,
                            'response' => $line,
                            'duration' => $duration . 's',
                        ], 'sse_stream');
                    }
                }
                if ($line !== "") {
                    // 2. 执行原始回调
                    $callback($line);
                }
            }

        } catch (\Exception $e) {
            // 异常捕获：即使出错，也要记录已经收到的部分内容
            $duration = round(microtime(true) - $startTime, 3);
            \Yii::error([
                'msg'      => $e->getMessage(),
                'url'      => $fullUrl,
                'request'  => $data,
                'duration' => $duration . 's',
            ], 'sse_stream');
            throw $e;
        }
    }
}
