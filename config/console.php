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
    ],
];
