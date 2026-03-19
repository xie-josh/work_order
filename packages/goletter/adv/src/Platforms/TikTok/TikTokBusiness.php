<?php

namespace Goletter\Adv\Platforms\TikTok;

/**
 * TikTok Business Center 服务封装
 *
 * 注意：具体接口路径、请求参数和返回字段需要根据 TikTok Business API 官方文档
 * 进行调整，这里提供的是一个通用的示例结构，方便在业务侧统一调用。
 */
class TikTokBusiness
{
    public function __construct(
        protected TikTokClient $client
    ) {}

    /**
     * 获取可见的 Business Center 列表
     *
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function listBusinessCenters(int $page = 1, int $pageSize = 50): array
    {
        $response = $this->client->get('/open_api/v1.3/business/get/', [
            // 具体参数名称请以官方文档为准
            'page'      => $page,
            'page_size' => $pageSize,
        ]);

        return $response['data']['list'] ?? [];
    }

    /**
     * 流式遍历所有 Business Center（推荐大数据量）
     *
     * @param int $pageSize 每页数量
     * @param int $max 最大条数（安全上限，防止无限遍历）
     * @return \Generator
     */
    public function iterateBusinessCenters(int $pageSize = 50, int $max = 100000): \Generator
    {
        $page  = 1;
        $count = 0;

        do {
            $response = $this->client->get('/open_api/v1.3/business/get/', [
                'page'      => $page,
                'page_size' => $pageSize,
            ]);

            $list = $response['data']['list'] ?? [];

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

    /**
     * 获取指定 Business Center 下的广告主列表
     *
     * @param string $businessCenterId Business Center ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function listAdvertisersByBusinessCenter(
        string $businessCenterId,
        int $page = 1,
        int $pageSize = 50
    ): array {
        $response = $this->client->get('/open_api/v1.3/business/advertiser/get/', [
            // 参数名称以官方文档为准
            'business_center_id' => $businessCenterId,
            'page'               => $page,
            'page_size'          => $pageSize,
        ]);

        return $response['data']['list'] ?? [];
    }

    /**
     * 流式遍历某个 Business Center 下的所有广告主
     *
     * @param string $businessCenterId Business Center ID
     * @param int $pageSize 每页数量
     * @param int $max 最大条数（安全上限）
     * @return \Generator
     */
    public function iterateAdvertisersByBusinessCenter(
        string $businessCenterId,
        int $pageSize = 50,
        int $max = 100000
    ): \Generator {
        $page  = 1;
        $count = 0;

        do {
            $response = $this->client->get('/open_api/v1.3/business/advertiser/get/', [
                'business_center_id' => $businessCenterId,
                'page'               => $page,
                'page_size'          => $pageSize,
            ]);

            $list = $response['data']['list'] ?? [];

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

    /**
     * 获取 bc 下的资产（例如 advertiser 资产）
     */
    public function getBcAssets(string $bcId, string $assetType): array
    {
        $response = $this->client->get('/open_api/v1.3/bc/asset/get/', [
            'bc_id' => $bcId,
            'asset_type' => $assetType,
        ]);

        // 兼容不同返回结构：优先 list，其次 data，再否则整包
        return $response['data']['list'] ?? $response['data'] ?? $response;
    }

    /**
     * 给用户分配 bc 资产（assign）
     *
     * @param array $payload 作为 JSON body 发送的参数
     */
    public function assignBcAsset(array $payload): array
    {
        $response = $this->client->post('/open_api/v1.3/bc/asset/assign/', $payload);

        return $response['data'] ?? $response;
    }

    /**
     * 获得Bc
     * @return array|mixed
     */
    public function getBcs()
    {
        $response = $this->client->get('/open_api/v1.3/bc/asset/get/');

        return $response['data'] ?? $response;
    }
}

