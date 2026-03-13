<?php

namespace Goletter\Akms;

/**
 * 数据概览相关接口封装（brand_dash）
 */
class DashboardApi
{
    public function __construct(
        protected Client $client
    ) {
    }

    /**
     * 消耗概览（昨天、前天、近 7 天、近 28 天、近 3 个月）
     * GET /open_api/v1.0/dashboard/brand_dash?module=base_spend
     */
    public function getBaseSpend(array $params = []): array
    {
        $params['module'] = 'base_spend';

        return $this->client->get('/open_api/v1.0/dashboard/brand_dash', $params);
    }

    /**
     * 媒体消耗趋势
     * GET /open_api/v1.0/dashboard/brand_dash?module=spend_trend
     *
     * 需要携带 dash_time（起止日期），可选 medium/accounts
     */
    public function getSpendTrend(array $params): array
    {
        $params['module'] = 'spend_trend';

        return $this->client->get('/open_api/v1.0/dashboard/brand_dash', $params);
    }

    /**
     * 媒体详情（总消耗、CPC、CPM 等聚合）
     * GET /open_api/v1.0/dashboard/brand_dash?module=medium_detail
     */
    public function getMediumDetail(array $params): array
    {
        $params['module'] = 'medium_detail';

        return $this->client->get('/open_api/v1.0/dashboard/brand_dash', $params);
    }

    /**
     * 广告账户天级消耗导出
     * GET /open_api/v1.0/dashboard/brand_dash?module=export_detail
     */
    public function getExportDetail(array $params): array
    {
        $params['module'] = 'export_detail';

        return $this->client->get('/open_api/v1.0/dashboard/brand_dash', $params);
    }
}

