<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'CardListTast' => 'app\command\CardListTask',
        'CardInfoTask' => 'app\command\CardInfoTask',
        'AccountTask' => 'app\command\AccountTask',
        'CardTransactionsTask' => 'app\command\CardTransactionsTask',
        'LampayCardListTask' => 'app\command\LampayCardListTask',
        'FbAccountUpdateTask' => 'app\command\FbAccountUpdateTask',
        'FbAccountUnUpdateTask' => 'app\command\FbAccountUnUpdateTask',
        'FbAccountConsumptionTask' => 'app\command\FbAccountConsumptionTask',
        'FbAccountConsumptionTest2Task' => 'app\command\FbAccountConsumptionTest2Task',
        'AccountListBackupTask' => 'app\command\AccountListBackupTask',
        'AuditExceptionTask' => 'app\command\AuditExceptionTask',
        'GetRateTask' => 'app\command\GetRateTask',
        'SettlementTask' => 'app\command\SettlementTask',
        'MonthConsumptionTotalTask' => 'app\command\MonthConsumptionTotalTask',
        'CopyYesterdayConsumptionTask' => 'app\command\CopyYesterdayConsumptionTask',
        'AccountPendingRecycleTask' => 'app\command\AccountPendingRecycleTask',
    ],
];
