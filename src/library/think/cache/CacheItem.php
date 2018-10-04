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
use think\exception\InvalidArgumentException;

class CacheItem implements CacheItemInterface
{
    /**
     * 缓存Key
     * @var string
     */
    protected $key;

    /**
     * 缓存内容
     * @var mixed
     */
    protected $value;

    /**
     * 过期时间
     * @var int
     */
    protected $expire;

    protected $isHit = false;

    protected $defaultLifetime = 0;

    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * 返回当前缓存项的「键」
     * @access public
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    public function getExpire(): int
    {
        return $this->expire;
    }

    /**
     * 凭借此缓存项的「键」从缓存系统里面取出缓存项
     * @access public
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * 确认缓存项的检查是否命中
     * @access public
     * @return bool
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * 为此缓存项设置「值」
     * @access public
     * @return $this
     */
    public function set($value)
    {
        $this->value = $value;
        $this->isHit = true;
        return $this;
    }

    /**
     * 设置缓存项的准确过期时间点
     * @access public
     * @param \DateTimeInterface $expiration
     * @return $this
     */
    public function expiresAt(\DateTimeInterface $expiration = null)
    {
        if (null === $expiration) {
            $this->expire = $this->defaultLifetime > 0 ? time() + $this->defaultLifetime : null;
        } else {
            $this->expire = (int) $expiration->format('U');
        }

        return $this;
    }

    /**
     * 设置缓存项的过期时间
     * @access public
     * @param int|\DateInterval $time
     * @return $this
     * @throws InvalidArgumentException
     */
    public function expiresAfter($time = null)
    {
        if (null === $time) {
            $this->expire = $this->defaultLifetime > 0 ? time() + $this->defaultLifetime : null;
        } elseif ($time instanceof \DateInterval) {
            $this->expire = (int) \DateTime::createFromFormat('U', time())->add($time)->format('U');
        } elseif (is_int($time)) {
            $this->expire = $time + time();
        } else {
            throw new InvalidArgumentException('not support datetime');
        }
        return $this;
    }

}
