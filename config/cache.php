<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    'default' => env('cache.driver', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        'redis' => [
            'type'       => env('redis.type', 'Redis'),
            'host'       => env('redis.host', ''),
            'port'       => env('redis.port', 6379),
            'password'   => env('redis.password', ''),
            'select'     => env('redis.select', false),
            'timeout'    => env('redis.timeout', 0),
            'expire'     => env('redis.expire', 0),
            'persistent' => env('redis.persistent', false),
            'prefix' => '',
            'tag_prefix' => 'tag:',
        ]
        // 更多的缓存连接
    ],
];
