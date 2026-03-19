<?php

namespace Goletter\Adv\Platforms\TikTok;

/**
 * TikTok 报表服务
 *
 * 该类只封装一个比较通用的「综合报表」查询，
 * 你可以根据具体业务再扩展更多方法（按广告系列 / 广告组 / 广告维度等）。
 */
class TikTokReport
{
    public function __construct(
        protected TikTokClient $client
    ) {}

    /**
     * 通用报表迭代器（流式处理）
     *
     * @param string $advertiserId TikTok 广告主 ID
     * @param string $start 起始日期 Y-m-d
     * @param string $end 结束日期 Y-m-d
     * @param array $dimensions 维度，例如 ['stat_time_day', 'campaign_id']
     * @param array $metrics 指标，例如 ['spend', 'impressions', 'clicks']
     * @param int $pageSize 每页条数，默认 100
     */
    public function iterateReport(
        string $advertiserId,
        string $start,
        string $end,
        array $dimensions = ['stat_time_day'],
        array $metrics = ['spend', 'impressions', 'clicks'],
        int $pageSize = 100
    ): \Generator {
        $page = 1;

        do {
            $response = $this->client->get('/open_api/v1.3/report/integrated/get/', [
                'advertiser_id' => $advertiserId,
                'report_type'   => 'BASIC',
                'data_level'    => 'AUCTION_ADVERTISER',
                'dimensions'    => json_encode($dimensions),
                'metrics'       => json_encode($metrics),
                'start_date'    => $start,
                'end_date'      => $end,
                'page'          => $page,
                'page_size'     => $pageSize,
            ]);

            $data = $response['data']['list'] ?? [];

            foreach ($data as $row) {
                yield $row;
            }

            $pageInfo = $response['data']['page_info'] ?? [];
            $totalPage = (int) ($pageInfo['total_page'] ?? $page);
            $page++;
        } while ($page <= $totalPage);
    }

    /**
     * 一次性获取所有报表数据（不推荐大数据量）
     */
    public function getAllReport(
        string $advertiserId,
        string $start,
        string $end,
        array $dimensions = ['stat_time_day'],
        array $metrics = ['spend', 'impressions', 'clicks'],
        int $pageSize = 100
    ): array {
        return iterator_to_array(
            $this->iterateReport($advertiserId, $start, $end, $dimensions, $metrics, $pageSize)
        );
    }
}

