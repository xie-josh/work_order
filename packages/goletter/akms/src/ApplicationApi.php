<?php

namespace Goletter\Akms;

/**
 * 账户管理 & 需求申请相关接口封装（/open_api/v1.0/application/...）
 */
class ApplicationApi
{
    public function __construct(
        protected Client $client
    ) {
    }

    /* ====== 大媒体账户查询 ====== */

    /**
     * Facebook 账户列表
     * GET /open_api/v1.0/application/facebook/list
     */
    public function facebookAccounts(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/application/facebook/list', $query);
    }

    /**
     * Facebook 账户清零记录查询
     * POST /open_api/v1.0/application/facebook/get_account_clear_record
     */
    public function facebookAccountClearRecords(array $body): array
    {
        return $this->client->postJson(
            '/open_api/v1.0/application/facebook/get_account_clear_record',
            $body
        );
    }

    /**
     * Google 账户列表
     * GET /open_api/v1.0/application/google/list
     */
    public function googleAccounts(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/application/google/list', $query);
    }

    /**
     * TikTok 账户列表
     * GET /open_api/v1.0/application/tiktok/list
     */
    public function tiktokAccounts(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/application/tiktok/list', $query);
    }

    /**
     * 新兴媒体账户需求列表
     * GET /open_api/v1.0/application/small_medium/list
     */
    public function smallMediumList(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/application/small_medium/list', $query);
    }

    /**
     * 新兴媒体账户需求详情
     * GET /open_api/v1.0/application/small_medium/list/{id}
     */
    public function smallMediumDetail(int $id): array
    {
        return $this->client->get("/open_api/v1.0/application/small_medium/list/{$id}");
    }

    /**
     * 大媒体账户管理需求申请记录
     * GET /open_api/v1.0/application/operate_record
     */
    public function operateRecords(array $query = []): array
    {
        return $this->client->get('/open_api/v1.0/application/operate_record', $query);
    }

    /* ====== Facebook 需求申请 ====== */

    /**
     * Facebook 账户额度调整申请
     * POST /open_api/v1.0/application/facebook/amount_spend
     */
    public function facebookAmountSpend(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/facebook/amount_spend', $body);
    }

    /**
     * Facebook 绑定/解绑 BM 申请
     * POST /open_api/v1.0/application/facebook/bind_bm
     */
    public function facebookBindBm(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/facebook/bind_bm', $body);
    }

    /**
     * Facebook 绑定/解绑 Pixel 申请
     * POST /open_api/v1.0/application/facebook/bind_pixel
     */
    public function facebookBindPixel(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/facebook/bind_pixel', $body);
    }

    /**
     * Facebook 账户更名申请
     * POST /open_api/v1.0/application/facebook/rename
     */
    public function facebookRename(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/facebook/rename', $body);
    }

    /* ====== Google 需求申请 ====== */

    /**
     * Google 账户额度变更申请
     * POST /open_api/v1.0/application/google/amount_spend
     */
    public function googleAmountSpend(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/google/amount_spend', $body);
    }

    /**
     * Google 授权个人邮箱（绑定/解绑）
     * POST /open_api/v1.0/application/google/user_role
     */
    public function googleUserRole(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/google/user_role', $body);
    }

    /**
     * Google 接受/拒绝/移除 MCC 邀请
     * POST /open_api/v1.0/application/google/link_mcc
     */
    public function googleLinkMcc(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/google/link_mcc', $body);
    }

    /**
     * Google 账户更名申请
     * POST /open_api/v1.0/application/google/rename
     */
    public function googleRename(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/google/rename', $body);
    }

    /* ====== TikTok 需求申请 ====== */

    /**
     * TikTok 账户额度变更申请
     * POST /open_api/v1.0/application/tiktok/amount_spend
     */
    public function tiktokAmountSpend(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/tiktok/amount_spend', $body);
    }

    /**
     * TikTok 绑定/解绑 BC_ID
     * POST /open_api/v1.0/application/tiktok/bind_bc
     */
    public function tiktokBindBc(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/tiktok/bind_bc', $body);
    }

    /**
     * TikTok 绑定/解绑邮箱
     * POST /open_api/v1.0/application/tiktok/bind_email
     */
    public function tiktokBindEmail(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/tiktok/bind_email', $body);
    }

    /**
     * TikTok 账户更名申请
     * POST /open_api/v1.0/application/tiktok/rename
     */
    public function tiktokRename(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/tiktok/rename', $body);
    }

    /* ====== 新兴媒体（小媒体）需求申请 ====== */

    /**
     * 新兴媒体额度变更申请
     * POST /open_api/v1.0/application/small_medium/amount_spend
     */
    public function smallMediumAmountSpend(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/small_medium/amount_spend', $body);
    }

    /**
     * 新兴媒体绑定申请
     * POST /open_api/v1.0/application/small_medium/bind
     */
    public function smallMediumBind(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/small_medium/bind', $body);
    }

    /**
     * 新兴媒体更名申请
     * POST /open_api/v1.0/application/small_medium/rename
     */
    public function smallMediumRename(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/small_medium/rename', $body);
    }

    /**
     * 新兴媒体其他申请
     * POST /open_api/v1.0/application/small_medium/other
     */
    public function smallMediumOther(array $body): array
    {
        return $this->client->postJson('/open_api/v1.0/application/small_medium/other', $body);
    }
}

