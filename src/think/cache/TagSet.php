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
