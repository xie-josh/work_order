<?php

namespace Goletter\Adv\Platforms\TikTok;

/**
 * TikTok 广告系列（Campaign）服务
 *
 * 注意：具体接口路径、请求参数和返回字段需要根据 TikTok Business API 文档确认。
 * 这里实现一个通用的获取 Campaign 列表 / 流式遍历能力，方便业务侧统一调用。
 */
class TikTokCampaign
{
    public function __construct(
        protected TikTokClient $client
    ) {}

    /**
     * 获取广告主下的一页 Campaign 列表
     *
     * @param string $advertiserId 广告主 ID
     * @param array $filters 过滤条件（按官方文档结构传入）
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function listCampaigns(
        string $advertiserId,
        array $filters = [],
        int $page = 1,
        int $pageSize = 50
    ): array {
        $body = array_merge([
            'advertiser_id' => $advertiserId,
            'page'          => $page,
            'page_size'     => $pageSize,
        ], $filters);

        // 具体接口路径请根据 Business API 文档确认
        $response = $this->client->post('/open_api/v1.3/campaign/get/', $body);

        return $response['data']['list'] ?? [];
    }

    /**
     * 流式遍历广告主下的所有 Campaign（推荐大数据量）
     *
     * @param string $advertiserId 广告主 ID
     * @param array $filters 过滤条件（按官方文档结构传入）
     * @param int $pageSize 每页数量
     * @param int $max 最大条数（安全上限）
     * @return \Generator
     */
    public function iterateCampaigns(
        string $advertiserId,
        array $filters = [],
        int $pageSize = 50,
        int $max = 100000
    ): \Generator {
        $page  = 1;
        $count = 0;

        do {
            $body = array_merge([
                'advertiser_id' => $advertiserId,
                'page'          => $page,
                'page_size'     => $pageSize,
            ], $filters);

            $response = $this->client->post('/open_api/v1.3/campaign/get/', $body);
            $list     = $response['data']['list'] ?? [];

            foreach ($list as $item) {
                yield $item;

                if (++$count >= $max) {
                    return;
                }
            }

            $pageInfo  = $response['data']['page_info'] ?? [];
            $totalPage = (int) ($pageInfo['total_page'] ?? $page);
            $page++;
        } while ($page <= $totalPage);
    }
}

