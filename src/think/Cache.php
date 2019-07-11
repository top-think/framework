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
use Psr\SimpleCache\CacheInterface;
use think\cache\CacheItem;
use think\cache\Driver;
use think\Container;
use think\exception\InvalidArgumentException;

/**
 * 缓存管理类
 * @mixin Driver
 */
class Cache implements CacheItemPoolInterface, CacheInterface
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

    public static function __make(Config $config)
    {
        $cache = new static();
        $cache->config($config->get('cache'));
        return $cache;
    }

    /**
     * 连接或者切换缓存
     * @access public
     * @param  string $name  连接配置名
     * @param  bool   $force 强制重新连接
     * @return Driver
     */
    public function store(string $name = '', bool $force = false): Driver
    {
        if ('' == $name) {
            $name = $this->config['default'] ?? 'file';
        }

        if ($force || !isset($this->instance[$name])) {
            if (!isset($this->config['stores'][$name])) {
                throw new InvalidArgumentException('Undefined cache config:' . $name);
            }

            $options = $this->config['stores'][$name];

            $this->instance[$name] = $this->connect($options);
        }

        return $this->instance[$name];
    }

    /**
     * 连接缓存
     * @access public
     * @param  array  $options 连接参数
     * @param  string $name  连接配置名
     * @return Driver
     */
    public function connect(array $options, string $name = ''): Driver
    {
        if ($name && isset($this->instance[$name])) {
            return $this->instance[$name];
        }

        $type = !empty($options['type']) ? $options['type'] : 'File';

        $handler = Container::factory($type, '\\think\\cache\\driver\\', $options);

        if ($name) {
            $this->instance[$name] = $handler;
        }

        return $handler;
    }

    /**
     * 设置配置
     * @access public
     * @param  array $config 配置参数
     * @return void
     */
    public function config(array $config): void
    {
        $this->config = array_merge($this->config, $config);
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
        return $this->store()->has($key);
    }

    /**
     * 清空缓冲池
     * @access public
     * @return bool
     */
    public function clear(): bool
    {
        return $this->store()->clear();
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
        return $this->store()->delete($key);
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
            $this->store()->delete($key);
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
            return $this->store()->set($item->getKey(), $item->get(), $item->getExpire());
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

    /**
     * 读取缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get($key, $default = false)
    {
        return $this->store()->get($key, $default);
    }

    /**
     * 写入缓存
     * @access public
     * @param  string        $name 缓存变量名
     * @param  mixed         $value  存储数据
     * @param  int|\DateTime $expire  有效时间 0为永久
     * @return bool
     */
    public function set($key, $value, $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function delete($key): bool
    {
        return $this->store()->delete($key);
    }

    /**
     * 读取缓存
     * @access public
     * @param  iterable $keys 缓存变量名
     * @param  mixed    $default 默认值
     * @return iterable
     * @throws InvalidArgumentException
     */
    public function getMultiple($keys, $default = null): iterable
    {
        return $this->store()->getMultiple($key, $default);
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
        return $this->store()->setMultiple($values, $ttl);
    }

    /**
     * 删除缓存
     * @access public
     * @param iterable $keys 缓存变量名
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteMultiple($keys): bool
    {
        return $this->store()->deleteMultiple($keys);
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function has($key): bool
    {
        return $this->store()->has($key);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->store(), $method], $args);
    }

    public function __destruct()
    {
        if (!empty($this->deferred)) {
            $this->commit();
        }
    }

}
