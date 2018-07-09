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
 * Redis缓存驱动，适合单机部署、有前端代理实现高可用的场景，性能最好
 * 有需要在业务层实现读写分离、或者使用RedisCluster的需求，请使用Redisd驱动
 *
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 * @author    尘缘 <130775@qq.com>
 */
class Redis extends Driver
{
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
        'serialize'  => true,
    ];

    /**
     * 架构函数
     * @access public
     * @param  array $options 缓存参数
     */
    public function __construct($options = [])
    {
        $serialize_type = config('cache.default.serialize_type');
        if(isset($serialize_type) && $serialize_type !== 'serialize') {
            self::$serialize_type = 'json';
            self::registerSerialize('json_encode', 'json_decode', 'think_json:');
        }

        if(!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        if (extension_loaded('redis')) {
            $this->handler = new \Redis;

            if ($this->options['persistent']) {
                $this->handler->pconnect($this->options['host'], $this->options['port'], $this->options['timeout'], 'persistent_id_' . $this->options['select']);
            } else {
                $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
            }

            if ('' != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }

            if(0 != $this->options['select']) {
                $this->handler->select($this->options['select']);
            }
        } elseif (class_exists('\Predis\Client')) {
            $params = [];
            foreach ($this->options as $key => $val) {
                if (in_array($key, ['aggregate', 'cluster', 'connections', 'exceptions', 'prefix', 'profile', 'replication'])) {
                    $params[$key] = $val;
                    unset($this->options[$key]);
                }
            }
            $this->handler = new \Predis\Client($this->options, $params);
        } else {
            throw new \BadFunctionCallException('not support: redis');
        }
    }

    /**
     * 判断缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        return $this->handler->exists($this->getCacheKey($name));
    }

    /**
     * 读取缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $this->readTimes++;

        $value = $this->handler->get($this->getCacheKey($name));

        if(is_null($value) || false === $value) {
            return $default;
        }

        return $this->unserialize($value);
    }

    /**
     * 写入缓存
     * @access public
     * @param  string            $name 缓存变量名
     * @param  mixed             $value  存储数据
     * @param  integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
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

        $value = $this->serialize($value);

        if ($expire) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            $result = $this->handler->set($key, $value);
        }

        isset($first) && $this->setTagItem($key);

        return $result;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc($name, $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return $this->handler->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec($name, $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return $this->handler->decrby($key, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        $this->writeTimes++;

        return $this->handler->delete($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     * @access public
     * @param  string $tag 标签名
     * @return boolean
     */
    public function clear($tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);

            foreach ($keys as $key) {
                $this->handler->delete($key);
            }

            $this->rm('tag_' . md5($tag));
            return true;
        }

        $this->writeTimes++;

        return $this->handler->flushDB();
    }

    /**
     * 设置过期时间$expire
     * @param $key
     * @param $expire
     */
    public function expire($key, $expire)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->expire($key, $expire);
    }

    /**
     * 获取剩余生命时间
     * @param $name
     * @return mixed
     */
    public function ttl($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->ttl($key);
    }

    /**
     * 获取string值长度
     * @param string $name
     * @return int
     */
    public function strlen($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->strlen($key);
    }


    /**
     * 获取值长度
     * @param string $name
     * @return int
     */
    public function lLen($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->lLen($key);
    }

    /**
     * 在list的左边增加一个$value值
     * @param $name
     * @param $value
     * @return mixed
     */
    public function lPush($name, $value)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->lPush($key, $value);
    }

    /**
     * 在list的左边弹出一个值
     * @param $name
     */
    public function lPop($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->lPop($key);
    }

    /**
     * 返回名称为key的list有多少个元素
     * @param $name
     * @return mixed
     */
    public function lSize($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->lSize($key);
    }

    /**
     * 向名字叫 'hash' 的 hash表 中添加元素 ['key1' => 'val1']
     * @param string $h
     * @param $key
     * @param $value
     * @return bool
     */
    public function hSet($h = 'hash', $key, $value)
    {
        return $this->handler->hSet($h, $key, $value);
    }

    /**
     * 获取hash表中键名为$key的值
     * @param string $h
     * @param $key
     */
    public function hGet($h = 'hash', $key){
        return $this->handler->hGet($h, $key);
    }

    /**
     * 获取hash表的元素的数量
     */
    public function hLen($h){
        return $this->handler->hLen($h);
    }

    /**
     * 获取hash表中所有的值
     * @param $h
     */
    public function hKeys($h){
        return $this->handler->hKeys($h);
    }

    /**
     * 获取hash表中的所有值
     * @param $h
     */
    public function hVals($h)
    {
        return $this->handler->hVals($h);
    }

    /**
     * 获取hash表的元素集合
     * @param $h
     */
    public function hGetAll($h)
    {
        return $this->handler->hGetAll($h);
    }

    /**
     * 判断 hash 表中是否存在键名是 $key 的元素
     * @param $h
     * @param $key
     */
    public function hExists($h, $key)
    {
        return $this->handler->hExists($h, $key);
    }

    /**
     *  批量添加元素
     * @param       $h
     * @param array $data
     */
    public function hMset($h, $data = [])
    {
        return $this->handler->hMset($h, $data);
    }

    /**
     * 批量获取元素
     * @param       $h
     * @param array $field
     */
    public function hMGet($h, $field = [])
    {
        return $this->handler->hMGet($h, $field);

    }

    /**
     * 删除 hash表
     * @param $h
     */
    public function hDelete($h)
    {
        return $this->handler->delete($h);
    }

}
