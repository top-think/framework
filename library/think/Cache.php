<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\cache\Driver;

if (interface_exists('Psr\Cache\CacheItemPoolInterface')) {
    interface CacheItemPoolInterface extends \Psr\Cache\CacheItemPoolInterface
    {}
} else {
    interface CacheItemPoolInterface
    {}
}

class Cache implements CacheItemPoolInterface
{
    /**
     * 缓存实例
     * @var array
     */
    protected $instance = [];

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    /**
     * 操作句柄
     * @var object
     */
    protected $handler;

    protected $deferred = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 连接缓存
     * @access public
     * @param  array         $options  配置数组
     * @param  bool|string   $name 缓存连接标识 true 强制重新连接
     * @return Driver
     */
    public function connect(array $options = [], $name = false)
    {
        $type = !empty($options['type']) ? $options['type'] : 'File';

        if (false === $name) {
            $name = md5(serialize($options));
        }

        if (true === $name || !isset($this->instance[$name])) {
            $class = false !== strpos($type, '\\') ? $type : '\\think\\cache\\driver\\' . ucwords($type);

            // 记录初始化信息
            $this->app['log']->info('[ CACHE ] INIT ' . $type);

            if (true === $name) {
                $name = md5(serialize($options));
            }

            $this->instance[$name] = new $class($options);
        }

        return $this->instance[$name];
    }

    /**
     * 自动初始化缓存
     * @access public
     * @param  array         $options  配置数组
     * @return Driver
     */
    public function init(array $options = [])
    {
        if (is_null($this->handler)) {
            // 自动初始化缓存
            $config = $this->app['config'];

            if (empty($options) && 'complex' == $config->get('cache.type')) {
                $default = $config->get('cache.default');
                $options = $config->get('cache.' . $default['type']) ?: $default;
            } elseif (empty($options)) {
                $options = $config->pull('cache');
            }

            $this->handler = $this->connect($options);
        }

        return $this->handler;
    }

    /**
     * 切换缓存类型 需要配置 cache.type 为 complex
     * @access public
     * @param  string $name 缓存标识
     * @return Driver
     */
    public function store(string $name = '')
    {
        if ('' !== $name && 'complex' == $this->app['config']->get('cache.type')) {
            return $this->connect($this->app['config']->get('cache.' . $name), strtolower($name));
        }

        return $this->init();
    }

    public function get(string $key)
    {
        return $this->init()->get($key);
    }

    public function set(string $name, $value, $expire = null)
    {
        return $this->init()->set($name, $value, $expire);
    }

    public function delete(string $key)
    {
        return $this->init()->rm($key);
    }

    public function has(string $key)
    {
        return $this->init()->has($key);
    }

    /**
     * 返回「键」对应的一个缓存项。
     * @access public
     * @param  string $key 缓存标识
     * @return CacheItemInterface
     * @throws InvalidArgumentException
     */
    public function getItem(string $key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        $cacheItem = new CacheItem($key);

        if ($this->has($key)) {
            $cacheItem->set($this->get($key));
        }

        $this->data[$key] = $cacheItem;

        return $cacheItem;
    }

    /**
     * 返回一个可供遍历的缓存项集合。
     * @access public
     * @param  array $keys
     * @return array|\Traversable
     * @throws InvalidArgumentException
     */
    public function getItems(array $keys = []): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[] = $this->getItem($key);
        }

        return $result;
    }

    /**
     * 检查缓存系统中是否有「键」对应的缓存项。
     * @access public
     * @param  string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function hasItem(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * 清空缓冲池
     * @access public
     * @return bool
     */
    public function clear(): bool
    {
        return $this->init()->clear();
    }

    /**
     * 从缓冲池里移除某个缓存项
     * @access public
     * @param  string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteItem(string $key)
    {
        return $this->delete($key);
    }

    /**
     * 从缓冲池里移除多个缓存项
     * @access public
     * @param  array $keys
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * 立刻为「CacheItemInterface」对象做数据持久化。
     * @access public
     * @param  CacheItemInterface $item
     * @return bool
     */
    public function save(CacheItemInterface $item)
    {
        if ($item->getKey()) {
            $expire = $item->getExpire();

            if (!is_null($expire)) {
                $expire = $expire - time();
            }

            return $this->set($item->getKey(), $item->get(), $expire);
        }

        return false;
    }

    /**
     * 稍后为「CacheItemInterface」对象做数据持久化。
     * @access public
     * @param  CacheItemInterface $item
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $this->deferred[$item->getKey()] = $item;
        return true;
    }

    /**
     * 提交所有的正在队列里等待的请求到数据持久层，配合 `saveDeferred()` 使用
     * @access public
     * @return bool
     */
    public function commit()
    {
        foreach ($this->deferred as $key => $item) {
            $result = $this->save($item);
            unset($this->deferred[$key]);
            if (false === $result) {
                return false;
            }
        }
        return true;
    }

    public function __destruct()
    {
        if ($this->deferred) {
            $this->commit();
        }
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->init(), $method], $args);
    }

}
