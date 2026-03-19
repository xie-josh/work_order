<?php

namespace Goletter\Adv\Platforms\Facebook;

use GuzzleHttp\Client;
use Goletter\Adv\Platforms\Facebook\Exceptions\FacebookApiException;
use Goletter\Adv\Platforms\Facebook\Exceptions\FacebookTokenExpiredException;

class FacebookClient
{
    protected Client $http;
    protected string $accessToken;
    protected string $baseUri;

    protected array $defaultHeaders = [];

    public function __construct(
        string $accessToken,
        string $apiVersion = 'v24.0'
    ) {
        $this->accessToken = $accessToken;
        $this->baseUri = "https://graph.facebook.com/{$apiVersion}";

        $this->http = new Client([
            'base_uri' => $this->baseUri,
            'timeout'  => 60,
        ]);
    }

    /**
     * 设置默认请求头
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;
        return $this;
    }

    /* ========= 基础 HTTP ========= */

    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, $query);
    }

    public function post(string $uri, array $body = [], array $query = []): array
    {
        return $this->request('POST', $uri, $query, $body);
    }

    public function delete(string $uri, array $query = []): array
    {
        return $this->request('DELETE', $uri, $query);
    }

    protected function request(
        string $method,
        string $uri,
        array $query = [],
        array $body = []
    ): array {
        // 记录请求日志
        $startTime = microtime(true);
        try {
            $options = [
                'query' => array_merge($query, [
                    'access_token' => $this->accessToken,
                ]),
            ];

            if (!empty($this->defaultHeaders)) {
                $options['headers'] = $this->defaultHeaders;
            }

            if (!empty($body)) {
                $options['json'] = $body;
            }

            // 获取 base_uri 的路径部分（包含版本号，如 /v24.0）
            $basePath = parse_url($this->baseUri, PHP_URL_PATH);
            // 提取版本号（如 v24.0）
            $apiVersion = $basePath ? trim($basePath, '/') : 'v24.0';
            if (!str_starts_with($uri, '/' . $apiVersion . '/')) {
                $uri = '/' . $apiVersion . $uri;
            }

            $response = $this->http->request($method, $uri, $options);
            $data = json_decode((string) $response->getBody(), true);

            if (isset($data['error'])) {
                $this->handleError($data['error'], $data);
            }

            return $data;
        } catch (FacebookApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // 记录失败日志
            logApiCall($method, $uri, $options, ['error' => $e->getMessage()], microtime(true) - $startTime, false);

            throw new FacebookApiException(
                $e->getMessage(),
                $e->getCode(),
                [],
                $e
            );
        }
    }

    protected function handleError(array $error, array $raw): void
    {
        $code = $error['code'] ?? 0;
        $message = $error['message'] ?? 'Facebook API error';

        // 190 = access token 失效
        if ($code === 190) {
            throw new FacebookTokenExpiredException(
                $message,
                $code,
                $raw
            );
        }

        throw new FacebookApiException(
            $message,
            $code,
            $raw
        );
    }

    /* ========= 分页（核心能力） ========= */

    /**
     * Facebook Cursor / next URL 分页
     * 返回 Generator，流式消费
     */
    public function paginate(
        string $uri,
        array $query = [],
        int $max = 100000
    ): \Generator {
        $next = $uri;
        $params = $query;
        $count = 0;
        $isFirstRequest = true;
        
        // 获取 base_uri 的路径部分（包含版本号，如 /v24.0）
        $basePath = parse_url($this->baseUri, PHP_URL_PATH);
        // 提取版本号（如 v24.0）
        $apiVersion = $basePath ? trim($basePath, '/') : 'v24.0';

        while ($next) {
            // 所以我们需要确保路径包含版本号：/v24.0/act_xxx/insights
            if (!str_starts_with($next, '/' . $apiVersion . '/')) {
                $next = '/' . $apiVersion . $next;
            }
            $response = $this->get($next, $params);
            
            // 确保有数据字段
            if (!isset($response['data'])) {
                break;
            }

            foreach ($response['data'] as $item) {
                yield $item;

                if (++$count >= $max) {
                    return;
                }
            }

            // Facebook 的 next 是完整 URL
            $next = $response['paging']['next'] ?? null;
            
            // 第一次循环后，如果还有 next，使用 next URL 的参数
            if ($next && !$isFirstRequest) {
                // next URL 已包含所有参数，所以清空 params
                $params = [];
            }
            
            $isFirstRequest = false;
        }
    }

    /**
     * 一次性拉全（不推荐大数据量）
     */
    public function getAll(string $uri, array $query = []): array
    {
        return iterator_to_array(
            $this->paginate($uri, $query)
        );
    }
}