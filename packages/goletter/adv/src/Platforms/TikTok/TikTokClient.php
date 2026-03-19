<?php

namespace Goletter\Adv\Platforms\TikTok;

use GuzzleHttp\Client;
use Goletter\Adv\Platforms\TikTok\Exceptions\TikTokApiException;
use Goletter\Adv\Platforms\TikTok\Exceptions\TikTokTokenExpiredException;

class TikTokClient
{
    protected Client $http;
    protected string $accessToken;
    protected string $baseUri;

    protected array $defaultHeaders = [];

    public function __construct(
        string $accessToken,
        string $baseUri = 'https://business-api.tiktok.com'
    ) {
        $this->accessToken = $accessToken;
        $this->baseUri = rtrim($baseUri, '/');

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
        try {
            $options = [
                'query' => $query,
                'headers' => array_merge(
                    [
                        // TikTok Business API 使用 Access-Token 作为鉴权头
                        'Access-Token' => $this->accessToken,
                        'Content-Type' => 'application/json',
                        'Accept-Encoding' => 'identity',
                    ],
                    $this->defaultHeaders
                ),
            ];

            if (!empty($body)) {
                $options['json'] = $body;
            }

            // 统一补全前导斜杠
            if (!str_starts_with($uri, '/')) {
                $uri = '/' . $uri;
            }

            $response = $this->http->request($method, $uri, $options);
            $data = json_decode((string) $response->getBody(), true);

            $this->handleErrorIfNeeded($data);

            return $data;
        } catch (TikTokApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TikTokApiException(
                $e->getMessage(),
                $e->getCode(),
                [],
                $e
            );
        }
    }

    /**
     * TikTok API 的错误处理
     *
     * 不同版本返回结构略有差异，一般会包含 code / message / data 等字段。
     * 这里做一个比较通用的处理，具体码表交由业务层决定是否细分。
     */
    protected function handleErrorIfNeeded(?array $data): void
    {
        if ($data === null) {
            throw new TikTokApiException('TikTok API 返回空响应或非 JSON 内容', 0, []);
        }

        // 常见模式：{"code":0,"message":"OK","data":{...}}
        $code = $data['code'] ?? null;
        if ($code === null) {
            // 若没有 code 字段，认为是正常数据
            return;
        }

        if ((int) $code === 0) {
            return;
        }

        $message = $data['message'] ?? 'TikTok API error';

        // 示例：假定 401xx/403xx 等为 token 问题，可视具体文档调整
        if (in_array((int) $code, [40100, 40101, 40102], true)) {
            throw new TikTokTokenExpiredException($message, (int) $code, $data);
        }

        throw new TikTokApiException($message, (int) $code, $data);
    }
}

