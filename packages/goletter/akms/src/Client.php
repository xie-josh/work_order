<?php

namespace Goletter\Akms;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Goletter\Akms\Exceptions\AkmsApiException;

class Client
{
    protected HttpClient $http;
    protected string $accessToken;
    protected string $baseUri;

    public function __construct(
        string $accessToken,
        string $baseUri = 'https://akms-openapi.bluemediagroup.cn',
        float $timeout = 30.0
    ) {
        $this->accessToken = $accessToken;
        $this->baseUri = rtrim($baseUri, '/');

        $this->http = new HttpClient([
            'base_uri' => $this->baseUri,
            'timeout'  => $timeout,
        ]);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [
            'query' => $query,
        ]);
    }

    public function postJson(string $path, array $body = [], array $query = []): array
    {
        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($body)) {
            $options['json'] = $body;
        }

        if (!empty($query)) {
            $options['query'] = $query;
        }

        return $this->request('POST', $path, $options);
    }

    /**
     * multipart/form-data 请求（主要用于文件上传）
     */
    public function postMultipart(string $path, array $multipart, array $query = []): array
    {
        $options = [
            'multipart' => $multipart,
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }

        return $this->request('POST', $path, $options);
    }

    /**
     * 底层请求封装，自动携带 Authorization 头，并按文档处理 code/info/data
     */
    protected function request(string $method, string $path, array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        $options['headers'] = $headers;

        // 统一处理前导斜杠
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        try {
            $response = $this->http->request($method, $path, $options);
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                throw new AkmsApiException('Invalid JSON response from AKMS OpenAPI', 0, [
                    'raw' => $body,
                ]);
            }

            // 文档约定：code = 0 表示成功，其余为错误
            $code = $decoded['code'] ?? null;
            if ($code !== 0) {
                $message = $decoded['info'] ?? 'AKMS OpenAPI error';
                throw new AkmsApiException(
                    (string) $message,
                    (int) ($code ?? 0),
                    $decoded
                );
            }

            return $decoded;
        } catch (AkmsApiException $e) {
            throw $e;
        } catch (GuzzleException $e) {
            throw new AkmsApiException(
                $e->getMessage(),
                (int) $e->getCode(),
                [],
                $e
            );
        } catch (\Throwable $e) {
            throw new AkmsApiException(
                $e->getMessage(),
                (int) $e->getCode(),
                [],
                $e
            );
        }
    }
}

