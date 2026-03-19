<?php

namespace Goletter\Adv\Platforms\TikTok\Exceptions;

class TikTokApiException extends \RuntimeException
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
     * 获取 TikTok API 的原始响应数据
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}

