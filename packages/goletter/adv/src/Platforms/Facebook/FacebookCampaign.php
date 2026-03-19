<?php

namespace Goletter\Adv\Platforms\Facebook;

class FacebookCampaign
{
    public function __construct(
        protected FacebookClient $client
    ) {}

    /**
     * 获取广告账户下的所有广告系列
     * 
     * @param string $accountId 账户ID（不需要 act_ 前缀）
     * @param array $fields 要获取的字段列表
     * @param array $filters 过滤条件，例如：['status' => ['ACTIVE'], 'effective_status' => ['ACTIVE']]
     * @param int $limit 每页限制
     * @return array
     */
    public function listCampaigns(
        string $accountId,
        array $fields = [
            'id',
            'name',
            'objective',
            'status',
            'effective_status',
            'daily_budget',
            'lifetime_budget',
            'budget_remaining',
            'created_time',
            'updated_time'
        ],
        array $filters = [],
        int $limit = 1000
    ): array {
        $accountId = str_replace('act_', '', $accountId);
        
        $params = [
            'fields' => implode(',', $fields),
            'limit' => $limit,
        ];
        
        // 添加过滤条件（Facebook API 格式）
        if (!empty($filters)) {
            $filtering = [];
            foreach ($filters as $key => $value) {
                if (is_array($value)) {
                    $filtering[] = [
                        'field' => $key,
                        'operator' => 'IN',
                        'value' => $value,
                    ];
                } else {
                    $params[$key] = $value;
                }
            }
            if (!empty($filtering)) {
                $params['filtering'] = json_encode($filtering);
            }
        }
        $campaigns = $this->client->getAll("/act_{$accountId}/campaigns", $params);
        // 基于广告系列 ID 去重
        return $this->deduplicateCampaigns($campaigns);
    }

    /**
     * 流式处理广告系列（推荐大数据量）
     * 自动去重，基于广告系列 ID
     */
    public function iterateCampaigns(
        string $accountId,
        array $fields = [
            'id',
            'name',
            'objective',
            'status',
            'effective_status',
            'daily_budget',
            'lifetime_budget',
            'budget_remaining',
            'created_time',
            'updated_time'
        ],
        array $filters = [],
        int $limit = 1000
    ): \Generator {
        $accountId = str_replace('act_', '', $accountId);
        
        $params = [
            'fields' => implode(',', $fields),
            'limit' => $limit,
        ];
        
        // 添加过滤条件（Facebook API 格式）
        if (!empty($filters)) {
            $filtering = [];
            foreach ($filters as $key => $value) {
                if (is_array($value)) {
                    $filtering[] = [
                        'field' => $key,
                        'operator' => 'IN',
                        'value' => $value,
                    ];
                } else {
                    $params[$key] = $value;
                }
            }
            if (!empty($filtering)) {
                $params['filtering'] = json_encode($filtering);
            }
        }
        
        $seenIds = [];
        
        foreach ($this->client->paginate("/act_{$accountId}/campaigns", $params) as $campaign) {
            $campaignId = $campaign['id'] ?? null;
            
            // 如果广告系列 ID 不存在或已处理过，跳过
            if (!$campaignId || isset($seenIds[$campaignId])) {
                continue;
            }
            
            // 标记为已处理
            $seenIds[$campaignId] = true;
            
            yield $campaign;
        }
    }

    /**
     * 获取单个广告系列详情
     * 
     * @param string $campaignId 广告系列ID
     * @param array $fields 要获取的字段列表
     * @return array
     */
    public function getCampaign(
        string $campaignId,
        array $fields = [
            'id',
            'name',
            'objective',
            'status',
            'effective_status',
            'daily_budget',
            'lifetime_budget',
            'budget_remaining',
            'created_time',
            'updated_time',
            'start_time',
            'stop_time'
        ]
    ): array {
        return $this->client->get("/{$campaignId}", [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * 创建广告系列
     * 
     * @param string $accountId 账户ID（不需要 act_ 前缀）
     * @param string $name 广告系列名称
     * @param string $objective 广告目标，例如：OUTCOME_TRAFFIC, OUTCOME_ENGAGEMENT, OUTCOME_LEADS, OUTCOME_AWARENESS, OUTCOME_SALES
     * @param string $status 状态：ACTIVE, PAUSED, DELETED, ARCHIVED
     * @param array $options 其他选项，例如：daily_budget, lifetime_budget, start_time, stop_time
     * @return array 创建的广告系列信息
     */
    public function createCampaign(
        string $accountId,
        string $name,
        string $objective,
        string $status = 'PAUSED',
        array $options = []
    ): array {
        $accountId = str_replace('act_', '', $accountId);
        
        $body = array_merge([
            'name' => $name,
            'objective' => $objective,
            'status' => $status,
        ], $options);
        
        return $this->client->post("/act_{$accountId}/campaigns", $body);
    }

    /**
     * 更新广告系列
     * 
     * @param string $campaignId 广告系列ID
     * @param array $data 要更新的数据，例如：['name' => '新名称', 'status' => 'ACTIVE', 'daily_budget' => 10000]
     * @return array 更新后的广告系列信息
     */
    public function updateCampaign(string $campaignId, array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('至少需要提供一个要更新的字段');
        }
        
        return $this->client->post("/{$campaignId}", $data);
    }

    /**
     * 更新广告系列状态
     * 
     * @param string $campaignId 广告系列ID
     * @param string $status 新状态：ACTIVE, PAUSED, DELETED, ARCHIVED
     * @return array 更新后的广告系列信息
     */
    public function updateCampaignStatus(string $campaignId, string $status): array
    {
        $validStatuses = ['ACTIVE', 'PAUSED', 'DELETED', 'ARCHIVED'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status. Must be one of: " . implode(', ', $validStatuses));
        }
        
        return $this->client->post("/{$campaignId}", [
            'status' => $status,
        ]);
    }

    /**
     * 暂停广告系列
     * 
     * @param string $campaignId 广告系列ID
     * @return array 更新后的广告系列信息
     */
    public function pauseCampaign(string $campaignId): array
    {
        return $this->updateCampaignStatus($campaignId, 'PAUSED');
    }

    /**
     * 启用广告系列
     * 
     * @param string $campaignId 广告系列ID
     * @return array 更新后的广告系列信息
     */
    public function activateCampaign(string $campaignId): array
    {
        return $this->updateCampaignStatus($campaignId, 'ACTIVE');
    }

    /**
     * 删除广告系列（软删除，状态变为 DELETED）
     * 
     * @param string $campaignId 广告系列ID
     * @return array 更新后的广告系列信息
     */
    public function deleteCampaign(string $campaignId): array
    {
        return $this->client->delete("/{$campaignId}", [
            'status' => 'DELETED',
        ]);
    }

    /**
     * 归档广告系列
     * 
     * @param string $campaignId 广告系列ID
     * @return array 更新后的广告系列信息
     */
    public function archiveCampaign(string $campaignId): array
    {
        return $this->updateCampaignStatus($campaignId, 'ARCHIVED');
    }

    /**
     * 批量更新广告系列状态
     * 
     * @param array $campaignIds 广告系列ID数组
     * @param string $status 新状态
     * @return array 更新结果
     */
    public function batchUpdateStatus(array $campaignIds, string $status): array
    {
        $results = [];
        
        foreach ($campaignIds as $campaignId) {
            try {
                $results[$campaignId] = [
                    'success' => true,
                    'data' => $this->updateCampaignStatus($campaignId, $status),
                ];
            } catch (\Exception $e) {
                $results[$campaignId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * 基于广告系列 ID 去重广告系列列表
     * 
     * @param array $campaigns 广告系列列表
     * @return array 去重后的广告系列列表
     */
    protected function deduplicateCampaigns(array $campaigns): array
    {
        $uniqueCampaigns = [];
        $seenIds = [];
        
        foreach ($campaigns as $campaign) {
            $campaignId = $campaign['id'] ?? null;
            
            // 如果广告系列 ID 不存在或已处理过，跳过
            if (!$campaignId || isset($seenIds[$campaignId])) {
                continue;
            }
            
            // 标记为已处理并添加到结果
            $seenIds[$campaignId] = true;
            $uniqueCampaigns[] = $campaign;
        }
        
        return $uniqueCampaigns;
    }
}
