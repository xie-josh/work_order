<?php

namespace Goletter\Adv\Platforms\Facebook;

class FacebookBusiness
{
    public function __construct(
        protected FacebookClient $client
    ) {}

    /**
     * 获取当前用户的所有 Business Manager
     * 
     * @param array $fields 要获取的字段列表
     * @param int $limit 每页限制
     * @return array
     */
    public function listBusinesses(
        array $fields = ['id', 'name', 'timezone_id', 'verification_status'],
        int $limit = 100
    ): array {
        return $this->client->getAll('/me/businesses', [
            'fields' => implode(',', $fields),
            'limit' => $limit,
        ]);
    }

    /**
     * 流式处理 Business Manager（推荐大数据量）
     */
    public function iterateBusinesses(
        array $fields = ['id', 'name', 'timezone_id', 'verification_status'],
        int $limit = 100
    ): \Generator {
        return $this->client->paginate('/me/businesses', [
            'fields' => implode(',', $fields),
            'limit' => $limit,
        ]);
    }

    /**
     * 获取单个 Business Manager 详情
     * 
     * @param string $businessId Business Manager ID
     * @param array $fields 要获取的字段列表
     * @return array
     */
    public function getBusiness(
        string $businessId,
        array $fields = ['id', 'name', 'timezone_id', 'verification_status']
    ): array {
        return $this->client->get("/{$businessId}", [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * 获取 Business Manager 下的客户广告账户（client_ad_accounts）
     * 自动去重，基于账户 ID
     * 
     * @param string $businessId Business Manager ID
     * @param array $fields 要获取的字段列表
     * @param int $limit 每页限制
     * @return array 已去重的账户列表
     */
    public function listAdAccounts(
        string $businessId,
        array $fields = \Goletter\Adv\Platforms\Facebook\FacebookAccount::DEFAULT_ACCOUNT_FIELDS,
        int $limit = 1000
    ): array {
        return $this->listAccountsByType($businessId, 'client_ad_accounts', $fields, $limit);
    }

    /**
     * 流式处理 Business Manager 下的客户广告账户（推荐大数据量）
     * 自动去重，基于账户 ID
     */
    public function iterateAdAccounts(
        string $businessId,
        array $fields = \Goletter\Adv\Platforms\Facebook\FacebookAccount::DEFAULT_ACCOUNT_FIELDS,
        int $limit = 1000
    ): \Generator {
        yield from $this->iterateAccountsByType($businessId, 'client_ad_accounts', $fields, $limit);
    }

    /**
     * 获取 Business Manager 拥有的广告账户（owned_ad_accounts）
     * 自动去重，基于账户 ID
     * 
     * @param string $businessId Business Manager ID
     * @param array $fields 要获取的字段列表
     * @param int $limit 每页限制
     * @return array 已去重的账户列表
     */
    public function listOwnedAdAccounts(
        string $businessId,
        array $fields = \Goletter\Adv\Platforms\Facebook\FacebookAccount::DEFAULT_ACCOUNT_FIELDS,
        int $limit = 1000
    ): array {
        return $this->listAccountsByType($businessId, 'owned_ad_accounts', $fields, $limit);
    }

    /**
     * 流式处理 Business Manager 拥有的广告账户（推荐大数据量）
     * 自动去重，基于账户 ID
     */
    public function iterateOwnedAdAccounts(
        string $businessId,
        array $fields = \Goletter\Adv\Platforms\Facebook\FacebookAccount::DEFAULT_ACCOUNT_FIELDS,
        int $limit = 1000
    ): \Generator {
        yield from $this->iterateAccountsByType($businessId, 'owned_ad_accounts', $fields, $limit);
    }

    /**
     * 获取 Business Manager 下的业务用户列表
     * 
     * @param string $businessId Business Manager ID
     * @param array $fields 要获取的字段列表
     * @return array
     */
    public function listBusinessUsers(
        string $businessId,
        array $fields = ['id', 'name', 'email', 'role']
    ): array {
        return $this->client->getAll("/{$businessId}/business_users", [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * 流式处理 Business Manager 下的业务用户
     */
    public function iterateBusinessUsers(
        string $businessId,
        array $fields = ['id', 'name', 'email', 'role']
    ): \Generator {
        return $this->client->paginate("/{$businessId}/business_users", [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * 获取 Business Manager 下的 Pages
     * 
     * @param string $businessId Business Manager ID
     * @param array $fields 要获取的字段列表
     * @return array
     */
    public function listPages(
        string $businessId,
        array $fields = ['id', 'name', 'category']
    ): array {
        return $this->client->getAll("/{$businessId}/owned_pages", [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * 流式处理 Business Manager 下的 Pages
     */
    public function iteratePages(
        string $businessId,
        array $fields = ['id', 'name', 'category']
    ): \Generator {
        return $this->client->paginate("/{$businessId}/owned_pages", [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * 从 Business Manager 移除广告账户
     * 注意：此操作会从 Business Manager 中移除广告账户的访问权限，并不会真正删除账户
     * 
     * @param string $businessId Business Manager ID
     * @param string $accountId 账户ID（不需要 act_ 前缀）
     * @return array API 响应
     */
    public function removeAdAccount(string $businessId, string $accountId): array
    {
        $account = new FacebookAccount($this->client);
        return $account->removeAccountFromBusiness($businessId, $accountId);
    }

    /**
     * 批量从 Business Manager 移除广告账户
     * 
     * @param string $businessId Business Manager ID
     * @param array $accountIds 账户ID数组（不需要 act_ 前缀）
     * @return array 移除结果，格式：['account_id' => ['success' => true/false, 'data'/'error' => ...]]
     */
    public function batchRemoveAdAccounts(
        string $businessId,
        array $accountIds
    ): array {
        $results = [];
        
        foreach ($accountIds as $accountId) {
            try {
                $results[$accountId] = [
                    'success' => true,
                    'data' => $this->removeAdAccount($businessId, $accountId),
                ];
            } catch (\Exception $e) {
                $results[$accountId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * 基于账户类型获取账户列表（内部方法）
     */
    protected function listAccountsByType(
        string $businessId,
        string $type,
        array $fields,
        int $limit
    ): array {
        $accounts = $this->client->getAll("/{$businessId}/{$type}", [
            'fields' => implode(',', $fields),
            'limit' => $limit,
        ]);
        
        return $this->deduplicateAccounts($accounts);
    }

    /**
     * 基于账户类型流式处理账户（内部方法）
     */
    protected function iterateAccountsByType(
        string $businessId,
        string $type,
        array $fields,
        int $limit
    ): \Generator {
        $seenIds = [];
        
        foreach ($this->client->paginate("/{$businessId}/{$type}", [
            'fields' => implode(',', $fields),
            'limit' => $limit,
        ]) as $account) {
            $accountId = $account['id'] ?? null;
            
            if (!$accountId || isset($seenIds[$accountId])) {
                continue;
            }
            
            $seenIds[$accountId] = true;
            yield $account;
        }
    }

    /**
     * 基于账户 ID 去重账户列表
     */
    protected function deduplicateAccounts(array $accounts): array
    {
        return (new FacebookAccount($this->client))
            ->deduplicateByField($accounts, 'id');
    }
}
