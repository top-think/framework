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

class Memcached extends Driver
{
    protected $options = [
        'host'      => '127.0.0.1',
        'port'      => 11211,
        'expire'    => 0,
        'timeout'   => 0, // 超时时间（单位：毫秒）
        'prefix'    => '',
        'username'  => '', //账号
        'password'  => '', //密码
        'option'    => [],
        'serialize' => true,
    ];

    /**
     * 架构函数
     * @access public
     * @param  array $options 缓存参数
     */
    public function __construct(array $options = [])
    {
        if (!extension_loaded('memcached')) {
            throw new \BadFunctionCallException('not support: memcached');
        }

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $this->handler = new \Memcached;

        if (!empty($this->options['option'])) {
            $this->handler->setOptions($this->options['option']);
        }

        // 设置连接超时时间（单位：毫秒）
        if ($this->options['timeout'] > 0) {
            $this->handler->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $this->options['timeout']);
        }

        // 支持集群
        $hosts = explode(',', $this->options['host']);
        $ports = explode(',', $this->options['port']);
        if (empty($ports[0])) {
            $ports[0] = 11211;
        }

        // 建立连接
        $servers = [];
        foreach ((array) $hosts as $i => $host) {
            $servers[] = [$host, (isset($ports[$i]) ? $ports[$i] : $ports[0]), 1];
        }

        $this->handler->addServers($servers);

        if ('' != $this->options['username']) {
            $this->handler->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->handler->setSaslAuthData($this->options['username'], $this->options['password']);
        }
    }

    /**
     * 判断缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function has(string $name)
    {
        $key = $this->getCacheKey($name);

        return $this->handler->get($key) ? true : false;
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

        $result = $this->handler->get($this->getCacheKey($name));

        return false !== $result ? $this->unserialize($result) : $default;
    }

    /**
     * 写入缓存
     * @access public
     * @param  string            $name 缓存变量名
     * @param  mixed             $value  存储数据
     * @param  integer|\DateTime $expire  有效时间（秒）
     * @return bool
     */
    public function set(string $name, $value, $expire = null)
    {
        $this->writeTimes++;

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

        if ($this->tag && !$this->has($name)) {
            $first = true;
        }

        $key    = $this->getCacheKey($name);
        $expire = $this->getExpireTime($expire);
        $value  = $this->serialize($value);

        if ($this->handler->set($key, $value, $expire)) {
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

        if ($this->handler->get($key)) {
            return $this->handler->increment($key, $step);
        }

        return $this->handler->set($key, $step);
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

        $key   = $this->getCacheKey($name);
        $value = $this->handler->get($key) - $step;
        $res   = $this->handler->set($key, $value);

        return !$res ? false : $value;
    }

    /**
     * 删除缓存
     * @access public
     * @param  string       $name 缓存变量名
     * @param  bool|false   $ttl
     * @return bool
     */
    public function rm(string $name, $ttl = false)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return false === $ttl ?
        $this->handler->delete($key) :
        $this->handler->delete($key, $ttl);
    }

    /**
     * 清除缓存
     * @access public
     * @param  string $tag 标签名
     * @return bool
     */
    public function clear(? string $tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);

            $this->handler->deleteMulti($keys);
            $this->rm('tag_' . md5($tag));

            return true;
        }

        $this->writeTimes++;

        return $this->handler->flush();
    }
}
