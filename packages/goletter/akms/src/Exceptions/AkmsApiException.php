<?php

namespace Goletter\Akms\Exceptions;

class AkmsApiException extends \RuntimeException
{
    protected array $response;

    public function __construct(
        string $message = '',
        int $code = 0,
        array $response = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * 获取 AKMS OpenAPI 的原始响应数据
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}

