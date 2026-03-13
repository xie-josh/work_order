<?php

namespace Goletter\Akms;

/**
 * 开户管理相关接口封装（/open_api/v1.0/user_open/...）
 */
class OpenAccountApi
{
    public function __construct(
        protected Client $client
    ) {
    }

    /* ===== Facebook 开户 ===== */

    /**
     * Facebook 开户申请（通过 request_id 提交）
     * POST /open_api/v1.0/user_open/fb_list
     */
    public function facebookApply(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/user_open/fb_list', $body);
    }

    /**
     * Facebook 开户列表
     * GET /open_api/v1.0/user_open/fb_list
     */
    public function facebookList(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/user_open/fb_list', $query);
    }

    /**
     * Facebook 开户详情
     * GET /open_api/v1.0/user_open/fb_list/{request_id}
     */
    public function facebookDetail(string $requestId): array
    {
        return $this->client->get("/open_api/v1.0/user_open/fb_list/{$requestId}");
    }

    /* ===== Google 开户 ===== */

    /**
     * Google 开户申请
     * POST /open_api/v1.0/user_open/gg_accounts
     */
    public function googleApply(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/user_open/gg_accounts', $body);
    }

    /**
     * Google 开户列表
     * GET /open_api/v1.0/user_open/gg_accounts
     */
    public function googleList(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/user_open/gg_accounts', $query);
    }

    /**
     * Google 开户详情
     * GET /open_api/v1.0/user_open/gg_accounts/{id}
     */
    public function googleDetail(int $id): array
    {
        return $this->client->get("/open_api/v1.0/user_open/gg_accounts/{$id}");
    }

    /**
     * Google 申请撤回
     * POST /open_api/v1.0/user_open/gg_accounts_back
     */
    public function googleWithdraw(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/user_open/gg_accounts_back', $body);
    }

    /**
     * Google 申请时区列表
     * GET /open_api/v1.0/user_open/get_time_zone
     */
    public function googleTimeZones(): array
    {
        return $this->client->get('/open_api/v1.0/user_open/get_time_zone');
    }

    /* ===== TikTok 开户 ===== */

    /**
     * TikTok 营业执照上传
     * POST /open_api/v1.0/user_open/tt_upload_br (multipart/form-data)
     *
     * @param string $filePath 本地文件路径
     */
    public function tiktokUploadBusinessRegistration(string $filePath): array
    {
        $multipart = [
            [
                'name'     => 'br_file',
                'contents' => fopen($filePath, 'rb'),
                'filename' => basename($filePath),
            ],
        ];

        return $this->client->postMultipart('/open_api/v1.0/user_open/tt_upload_br', $multipart);
    }

    /**
     * TikTok 开户申请
     * POST /open_api/v1.0/user_open/tt_oa
     */
    public function tiktokApply(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/user_open/tt_oa', $body);
    }

    /**
     * TikTok 开户列表
     * GET /open_api/v1.0/user_open/tt_list
     */
    public function tiktokList(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/user_open/tt_list', $query);
    }

    /**
     * TikTok 开户详情
     * GET /open_api/v1.0/user_open/tt_list/{id}
     */
    public function tiktokDetail(int $id): array
    {
        return $this->client->get("/open_api/v1.0/user_open/tt_list/{$id}");
    }

    /**
     * TikTok 开户申请撤回
     * POST /open_api/v1.0/user_open/tt_oa_revoke
     */
    public function tiktokWithdraw(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/user_open/tt_oa_revoke', $body);
    }

    /**
     * TikTok 申请时区列表
     * GET /open_api/v1.0/user_open/get_tt_timezone
     */
    public function tiktokTimeZones(): array
    {
        return $this->client->get('/open_api/v1.0/user_open/get_tt_timezone');
    }

    /**
     * TikTok 申请注册地列表
     * GET /open_api/v1.0/user_open/get_tt_registered_area
     */
    public function tiktokRegisteredAreas(): array
    {
        return $this->client->get('/open_api/v1.0/user_open/get_tt_registered_area');
    }

    /**
     * TikTok 申请行业列表
     * GET /open_api/v1.0/user_open/get_tt_vertical
     */
    public function tiktokIndustries(): array
    {
        return $this->client->get('/open_api/v1.0/user_open/get_tt_vertical');
    }
}

