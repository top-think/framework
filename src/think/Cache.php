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

namespace think;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use think\cache\CacheItem;
use think\cache\Driver;
use think\exception\InvalidArgumentException;

/**
 * 缓存管理类
 */
class Cache implements CacheItemPoolInterface
{
    /**
     * 缓存队列
     * @var array
     */
    protected $data = [];

    /**
     * 延期保存的缓存队列
     * @var array
     */
    protected $deferred = [];

    /**
     * 缓存实例
     * @var array
     */
    protected $instance = [];

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 操作句柄
     * @var object
     */
    protected $handler;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->init($config);
    }

    public static function __make(Config $config)
    {
        return new static($config->get('cache'));
    }

    /**
     * 连接缓存
     * @access public
     * @param  array $options  配置数组
     * @param  bool  $force 强制重新连接
     * @return Driver
     */
    public function connect(array $options = [], bool $force = false): Driver
    {
        $name = md5(serialize($options));

        if ($force || !isset($this->instance[$name])) {
            $type = !empty($options['type']) ? $options['type'] : 'File';

            $this->instance[$name] = App::factory($type, '\\think\\cache\\driver\\', $options);
        }

        return $this->instance[$name];
    }

    /**
     * 自动初始化缓存
     * @access public
     * @param  array $options 配置数组
     * @param  bool  $force   强制更新
     * @return Driver
     */
    public function init(array $options = [], bool $force = false): Driver
    {
        if (is_null($this->handler) || $force) {
            if (isset($options['type']) && 'complex' == $options['type']) {
                $default = $options['default'];
                $options = $options[$default['type']] ?? $default;
            }

            $this->handler = $this->connect($options);
        }

        return $this->handler;
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 切换缓存类型 需要配置 cache.type 为 complex
     * @access public
     * @param  string $name 缓存标识
     * @param  bool          $force    强制更新
     * @return Driver
     */
    public function store(string $name = '', $force = false): Driver
    {
        if ('' !== $name && 'complex' == $this->config['type']) {
            return $this->connect($this->config[$name], $force);
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
     * 缓存标签
     * @access public
     * @param  string|array $name 标签名
     * @return Driver
     */
    public function tag($name)
    {
        return $this->init()->tag($name);
    }

    /**
     * 返回「键」对应的一个缓存项。
     * @access public
     * @param  string $key 缓存标识
     * @return CacheItemInterface
     * @throws InvalidArgumentException
     */
    public function getItem($key): CacheItem
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
    public function hasItem($key): bool
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
    public function deleteItem($key): bool
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
    public function deleteItems(array $keys): bool
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
    public function save(CacheItemInterface $item): bool
    {
        if ($item->getKey()) {
            return $this->set($item->getKey(), $item->get(), $item->getExpire());
        }

        return false;
    }

    /**
     * 稍后为「CacheItemInterface」对象做数据持久化。
     * @access public
     * @param  CacheItemInterface $item
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferred[$item->getKey()] = $item;
        return true;
    }

    /**
     * 提交所有的正在队列里等待的请求到数据持久层，配合 `saveDeferred()` 使用
     * @access public
     * @return bool
     */
    public function commit(): bool
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

    public function __call($method, $args)
    {
        return call_user_func_array([$this->init(), $method], $args);
    }

    public function __destruct()
    {
        if (!empty($this->deferred)) {
            $this->commit();
        }
    }

}
