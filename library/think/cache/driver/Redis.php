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
    /**
     * 序列化前缀
     * @var
     */
    protected $serializePrefix;

    /**
     * 序列化数组
     * @var array
     */
    protected static $json = [
        'json_encode',
        'json_decode',
        'think_json:',
        10
    ];

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
        if (isset($serialize_type) && $serialize_type !== 'serialize') {
            self::$serialize_type = 'json';
            $this->serializePrefix = self::$json[2];
            // 默认设置为think_json:前缀，而此处定制序列化前缀为空
            $this->setSerializePrefix('');
            self::registerJson(self::$json[0], self::$json[1], $this->serializePrefix);
        }

        if (!empty($options)) {
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

            if (0 != $this->options['select']) {
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
     * 重新定制json序列化机制
     * @access public
     * @param  callable $serialize      序列化方法
     * @param  callable $unserialize    反序列化方法
     * @param  string   $prefix         序列化前缀标识
     * @return $this
     */
    public static function registerJson($serialize, $unserialize, $prefix = 'think_json:'){
        self::registerSerialize($serialize, $unserialize, $prefix);
    }

    /**
     * 设置序列化前缀
     */
    public function setSerializePrefix($prefix)
    {
        $this->serializePrefix = $prefix;
    }

    /**
     * 选择数据库
     * @param $db
     */
    public function select($db){
       return $this->handler->select($db);
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

        if (is_null($value) || false === $value) {
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
     * @param $name
     * @param $expire
     */
    public function expire($name, $expire)
    {
        return $this->handler->expire($this->getCacheKey($name), $expire);
    }

    /**
     * 获取剩余生命时间
     * @param $name
     * @return mixed
     */
    public function ttl($name)
    {
        return $this->handler->ttl($this->getCacheKey($name));
    }

    /**
     * 获取string值长度
     * @param string $name
     * @return int
     */
    public function strlen($name)
    {
        return $this->handler->strlen($this->getCacheKey($name));
    }


    /**
     * 获取值长度
     * @param string $name
     * @return int
     */
    public function lLen($name)
    {
        return $this->handler->lLen($this->getCacheKey($name));
    }

    /**
     * 在list的左边增加一个$value值
     * @param $name
     * @param $value
     * @return mixed
     */
    public function lPush($name, $value)
    {
        return $this->handler->lPush($this->getCacheKey($name), $value);
    }

    /**
     * 在list的左边弹出一个值
     * @param $name
     */
    public function lPop($name)
    {
        return $this->handler->lPop($this->getCacheKey($name));
    }

    /**
     * 返回名称为key的list有多少个元素
     * @param $name
     * @return mixed
     */
    public function lSize($name)
    {
        return $this->handler->lSize($this->getCacheKey($name));
    }

    /**
     * 向名字叫 'hash' 的 hash表 中添加元素 ['key1' => 'val1']
     * @param string $name
     * @param $key
     * @param $value
     * @return bool
     */
    public function hSet($name = 'hash', $key, $value)
    {
        $this->writeTimes++;

        return $this->handler->hSet($this->getCacheKey($name), $key, $value);
    }

    /**
     * 获取hash表中键名为$key的值
     * @param string $h
     * @param $name
     * @return mixed
     */
    public function hGet($name = 'hash', $key)
    {
        $this->readTimes++;

        return $this->handler->hGet($this->getCacheKey($name), $key);
    }

    /**
     * 获取hash表的元素的数量
     */
    public function hLen($name)
    {
        return $this->handler->hLen($this->getCacheKey($name));
    }

    /**
     * 获取hash表中所有的值
     * @param $h
     */
    public function hKeys($name)
    {
        return $this->handler->hKeys($this->getCacheKey($name));
    }

    /**
     * 获取hash表中的所有值
     * @param $name
     * @return ini
     */
    public function hVals($name)
    {
        return $this->handler->hVals($this->getCacheKey($name));
    }

    /**
     * 获取hash表的元素集合
     * @param $name
     */
    public function hGetAll($name)
    {
        $this->readTimes++;

        return $this->handler->hGetAll($this->getCacheKey($name));
    }

    /**
     * 判断 hash 表中是否存在键名是 $key 的元素
     * @param $name
     * @param $key
     */
    public function hExists($name, $key)
    {
        return $this->handler->hExists($this->getCacheKey($name), $key);
    }

    /**
     *  批量添加元素
     * @param       $name
     * @param array $data
     */
    public function hMset($name, $data = [])
    {
        $this->writeTimes++;

        if ($this->tag && !$this->has($name)) {
            $first = true;
        }

        $key = $this->getCacheKey($name);

        $result = $this->handler->hMset($key, $data);

        isset($first) && $this->setTagItem($key);

        return $result;
    }

    /**
     * 批量获取元素
     * @param       $name
     * @param array $fields
     */
    public function hMGet($name, $fields = [])
    {
        $this->readTimes++;

        return $this->handler->hMGet($this->getCacheKey($name), $fields);
    }

}
