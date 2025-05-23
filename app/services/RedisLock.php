<?php
namespace app\services;

use think\facade\Cache;

class RedisLock
{
    protected $redis;

    public function __construct()
    {
        $this->redis = Cache::store('redis')->handler();
    }

    /**
     * 获取锁
     *
     * @param string $key 锁键名
     * @param string $value 锁值（唯一标识）
     * @param int $expire 锁过期时间（秒）
     * @return bool 是否获取成功
     */
    public function acquire(string $key, string $value, int $expire = 10): bool
    {
        return $this->redis->set($key, $value, ['NX', 'EX' => $expire]);
    }

    /**
     * 释放锁（仅当前 value 才能释放，防止误删）
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function release(string $key, string $value): bool
    {
        // 使用 Lua 脚本原子性释放锁
        $luaScript = <<<LUA
            if redis.call("get", KEYS[1]) == ARGV[1]
            then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
            LUA;

        return (bool) $this->redis->eval($luaScript, [$key, $value], 1);
    }
}
