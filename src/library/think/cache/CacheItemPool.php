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
declare (strict_types = 1);

namespace think\cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use think\exception\InvalidArgumentException;

class CacheItemPool implements CacheItemPoolInterface
{
    /**
     * 延期保存的缓存队列
     * @var array
     */
    protected $deferred = [];

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

    public function __destruct()
    {
        if ($this->deferred) {
            $this->commit();
        }
    }
}
