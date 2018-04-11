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

namespace think\cache\driver;

use think\cache\Driver;

/**
 * Wincache缓存驱动
 * @author    liu21st <liu21st@gmail.com>
 */
class Wincache extends Driver
{
    protected $options = [
        'prefix'    => '',
        'expire'    => 0,
        'serialize' => true,
    ];

    /**
     * 架构函数
     * @access public
     * @param  array $options 缓存参数
     * @throws \BadFunctionCallException
     */
    public function __construct(array $options = [])
    {
        if (!function_exists('wincache_ucache_info')) {
            throw new \BadFunctionCallException('not support: WinCache');
        }

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * 判断缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function has(string $name): bool
    {
        $this->readTimes++;

        $key = $this->getCacheKey($name);

        return wincache_ucache_exists($key);
    }

    /**
     * 读取缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get(string $name, $default = false)
    {
        $this->readTimes++;

        $key = $this->getCacheKey($name);

        return wincache_ucache_exists($key) ? $this->unserialize(wincache_ucache_get($key)) : $default;
    }

    /**
     * 写入缓存
     * @access public
     * @param  string            $name 缓存变量名
     * @param  mixed             $value  存储数据
     * @param  integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set(string $name, $value, $expire = null): bool
    {
        $this->writeTimes++;

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

        $key    = $this->getCacheKey($name);
        $expire = $this->getExpireTime($expire);
        $value  = $this->serialize($value);

        if ($this->tag && !$this->has($name)) {
            $first = true;
        }

        if (wincache_ucache_set($key, $value, $expire)) {
            isset($first) && $this->setTagItem($key);
            return true;
        }

        return false;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc(string $name, int $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return wincache_ucache_inc($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec(string $name, int $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return wincache_ucache_dec($key, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return boolean
     */
    public function rm(string $name): bool
    {
        $this->writeTimes++;

        return wincache_ucache_delete($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     * @access public
     * @param  string $tag 标签名
     * @return boolean
     */
    public function clear(? string $tag = null) : bool
    {
        if ($tag) {
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                wincache_ucache_delete($key);
            }
            $this->rm('tag_' . md5($tag));
            return true;
        } else {
            $this->writeTimes++;
            return wincache_ucache_clear();
        }
    }

}
