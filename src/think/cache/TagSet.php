<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\cache;

use think\Container;

/**
 * 标签集合
 */
class TagSet
{
    /**
     * 标签的缓存Key
     * @var string
     */
    protected $key;

    /**
     * 缓存句柄
     * @var Driver
     */
    protected $handler;

    public function __construct(string $key, Driver $cache)
    {
        $this->key     = $key;
        $this->handler = $cache;
    }

    /**
     * 写入缓存
     * @access public
     * @param  string            $name 缓存变量名
     * @param  mixed             $value  存储数据
     * @param  integer|\DateTime $expire  有效时间（秒）
     * @return bool
     */
    public function set($name, $value, $expire = null): bool
    {
        if (!$this->handler->has($name)) {
            $first = true;
        }

        $this->handler->set($name, $value, $expire);

        $key = $this->handler->getCacheKey($name);

        isset($first) && $this->handler->push($this->key, $key);

        return true;
    }

    /**
     * 写入缓存
     * @access public
     * @param  iterable               $values 缓存数据
     * @param  null|int|\DateInterval $ttl    有效时间 0为永久
     * @return bool
     */
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $val) {
            $result = $this->set($key, $val, $ttl);

            if (false === $result) {
                return false;
            }
        }

        return true;
    }

    /**
     * 如果不存在则写入缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $value  存储数据
     * @param  int    $expire  有效时间 0为永久
     * @return mixed
     */
    public function remember(string $name, $value, $expire = null)
    {
        if ($this->handler->has($name)) {
            return $this->handler->get($name);
        }

        $time = time();

        while ($time + 5 > time() && $this->handler->has($name . '_lock')) {
            // 存在锁定则等待
            usleep(200000);
        }

        try {
            // 锁定
            $this->handler->set($name . '_lock', true);

            if ($value instanceof \Closure) {
                // 获取缓存数据
                $value = Container::getInstance()->invokeFunction($value);
            }

            // 缓存数据
            $this->set($name, $value, $expire);

            // 解锁
            $this->handler->delete($name . '_lock');
        } catch (\Exception | \throwable $e) {
            $this->handler->delete($name . '_lock');
            throw $e;
        }

        return $value;
    }

    /**
     * 清除缓存
     * @access public
     * @return bool
     */
    public function clear(): bool
    {
        // 指定标签清除
        $keys = $this->handler->getTagItems($this->key);

        $this->handler->clearTag($keys);
        $this->handler->delete($this->key);
        return true;
    }
}
