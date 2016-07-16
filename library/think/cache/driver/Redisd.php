<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\cache\driver;

use think\App;
use think\Cache;
use think\Exception;
use think\Log;

/**
配置参数:
'cache' => [
'type'       => 'Redisd'
'host'       => 'A:6379,B:6379', //redis服务器ip，多台用逗号隔开；读写分离开启时，默认写A，当A主挂时，再尝试写B
'slave'      => 'B:6379,C:6379', //redis服务器ip，多台用逗号隔开；读写分离开启时，所有IP随机读，其中一台挂时，尝试读其它节点，可以配置权重
'port'       => 6379,    //默认的端口号
'password'   => '',      //AUTH认证密码，当redis服务直接暴露在外网时推荐
'timeout'    => 10,      //连接超时时间
'expire'     => false,   //默认过期时间，默认为永不过期
'prefix'     => '',      //缓存前缀，不宜过长
'persistent' => false,   //是否长连接 false=短连接，推荐长连接
],

单例获取:
$redis = \think\Cache::connect(Config::get('cache'));
$redis->master(true)->setnx('key');
$redis->master(false)->get('key');
 */

/**
 * ThinkPHP Redis简单主从实现的高可用方案
 *
 * 扩展依赖：https://github.com/phpredis/phpredis
 *
 * 一主一从的实践经验
 * 1, A、B为主从，正常情况下，A写，B读，通过异步同步到B（或者双写，性能有损失）
 * 2, B挂，则读写均落到A
 * 3, A挂，则尝试升级B为主，并断开主从尝试写入(要求开启slave-read-only no)
 * 4, 手工恢复A，并加入B的从
 *
 * 优化建议
 * 1，key不宜过长，value过大时请自行压缩
 * 2，gzcompress在php7下有兼容问题
 *
 * @todo
 * 1, 增加对redisCluster的兼容
 * 2, 增加tp5下的单元测试
 *
 * @author 尘缘 <130775@qq.com>
 */
class Redisd
{
    protected static $redis_rw_handler;
    protected static $redis_err_pool;
    protected $handler = null;

    protected $options = [
        'host'       => '127.0.0.1',
        'slave'      => '',
        'port'       => 6379,
        'password'   => '',
        'timeout'    => 10,
        'expire'     => false,
        'persistent' => false,
        'prefix'     => '',
        'serialize'  => \Redis::SERIALIZER_PHP,
    ];

    /**
     * 为了在单次php请求中复用redis连接，第一次获取的options会被缓存，第二次使用不同的$options，将会无效
     *
     * @param  array $options 缓存参数
     * @access public
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }

        $this->options         = $options         = array_merge($this->options, $options);
        $this->options['func'] = $options['persistent'] ? 'pconnect' : 'connect';

        $host  = explode(",", trim($this->options['host'], ","));
        $host  = array_map("trim", $host);
        $slave = explode(",", trim($this->options['slave'], ","));
        $slave = array_map("trim", $slave);

        $this->options["server_slave"]           = empty($slave) ? $host : $slave;
        $this->options["servers"]                = count($slave);
        $this->options["server_master"]          = array_shift($host);
        $this->options["server_master_failover"] = $host;
    }

    /**
     * 主从选择器，配置多个Host则自动启用读写分离，默认主写，随机从读
     * 随机从读的场景适合读频繁，且php与redis从位于单机的架构，这样可以减少网络IO
     * 一致Hash适合超高可用，跨网络读取，且从节点较多的情况，本业务不考虑该需求
     *
     * @access public
     * @param  bool $master true 默认主写
     * @return Redisd
     */
    public function master($master = true)
    {
        if (isset(self::$redis_rw_handler[$master])) {
            $this->handler = self::$redis_rw_handler[$master];
            return $this;
        }

        //如果不为主，则从配置的host剔除主，并随机读从，失败以后再随机选择从
        //另外一种方案是根据key的一致性hash选择不同的node，但读写频繁的业务中可能打开大量的文件句柄
        if (!$master && $this->options["servers"] > 1) {
            shuffle($this->options["server_slave"]);
            $host = array_shift($this->options["server_slave"]);
        } else {
            $host = $this->options["server_master"];
        }

        $this->handler = new \Redis();
        $func          = $this->options['func'];

        $parse = parse_url($host);
        $host  = isset($parse['host']) ? $parse['host'] : $host;
        $port  = isset($parse['host']) ? $parse['port'] : $this->options['port'];

        //发生错误则摘掉当前节点
        try {
            $result = $this->handler->$func($host, $port, $this->options['timeout']);
            if (false === $result) {
                $this->handler->getLastError();
            }

            if (null != $this->options['password']) {
                $this->handler->auth($this->options['password']);
            }

            $this->handler->setOption(\Redis::OPT_SERIALIZER, $this->options['serialize']);
            if (strlen($this->options['prefix'])) {
                $this->handler->setOption(\Redis::OPT_PREFIX, $this->options['prefix']);
            }

            App::$debug && Log::record("[ CACHE ] INIT Redisd : {$host}:{$port} master->" . var_export($master, true), Log::ALERT);
        } catch (\RedisException $e) {
            //phpredis throws a RedisException object if it can't reach the Redis server.
            //That can happen in case of connectivity issues, if the Redis service is down, or if the redis host is overloaded.
            //In any other problematic case that does not involve an unreachable server
            //(such as a key not existing, an invalid command, etc), phpredis will return FALSE.

            Log::record(sprintf("redisd->%s:%s:%s:%s", $master ? "master" : "salve", $host, $port, $e->getMessage()), Log::ALERT);

            //主节点挂了以后，尝试连接主备，断开主备的主从连接进行升主
            if ($master) {
                if (!count($this->options["server_master_failover"])) {
                    throw new Exception("redisd master: no more server_master_failover. {$host}:{$port} : " . $e->getMessage());
                    return false;
                }

                $this->options["server_master"] = array_shift($this->options["server_master_failover"]);
                $this->master();

                Log::record(sprintf("master is down, try server_master_failover : %s", $this->options["server_master"]), Log::ERROR);

                //如果是slave，断开主从升主，需要手工同步新主的数据到旧主上
                //目前这块的逻辑未经过严格测试
                //$this->handler->slaveof();
            } else {
                //尝试failover，如果有其它节点则进行其它节点的尝试
                foreach ($this->options["server_slave"] as $k => $v) {
                    if (trim($v) == trim($host)) {
                        unset($this->options["server_slave"][$k]);
                    }
                }

                //如果无可用节点，则抛出异常
                if (!count($this->options["server_slave"])) {
                    Log::record("已无可用Redis读节点", Log::ERROR);
                    throw new Exception("redisd slave: no more server_slave. {$host}:{$port} : " . $e->getMessage());
                    return false;
                } else {
                    Log::record("salve {$host}:{$port} is down, try another one.", Log::ALERT);
                    return $this->master(false);
                }
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        self::$redis_rw_handler[$master] = $this->handler;
        return $this;
    }

    /**
     * 读取缓存
     *
     * @access public
     * @param  string $name 缓存key
     * @param  bool   $master 指定主从节点，可以从主节点获取结果
     * @return mixed
     */
    public function get($name, $master = false)
    {
        $this->master($master);

        try {
            $value = $this->handler->get($name);
        } catch (\RedisException $e) {
            unset(self::$redis_rw_handler[0]);

            $this->master();
            return $this->get($name);
        } catch (\Exception $e) {
            Log::record($e->getMessage(), Log::ERROR);
        }

        return isset($value) ? $value : null;
    }

    /**
     * 写入缓存
     *
     * @access public
     * @param  string  $name   缓存key
     * @param  mixed   $value  缓存value
     * @param  integer $expire 过期时间，单位秒
     * @return boolen
     */
    public function set($name, $value, $expire = null)
    {
        $this->master(true);

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

        try {
            if (null === $value) {
                return $this->handler->delete($name);
            }

            if (is_int($expire) && $expire) {
                $result = $this->handler->setex($name, $expire, $value);
            } else {
                $result = $this->handler->set($name, $value);
            }
        } catch (\RedisException $e) {
            unset(self::$redis_rw_handler[1]);

            $this->master(true);
            return $this->set($name, $value, $expire);
        } catch (\Exception $e) {
            Log::record($e->getMessage());
        }

        return $result;
    }

    /**
     * 删除缓存
     *
     * @access public
     * @param  string $name 缓存变量名
     * @return boolen
     */
    public function rm($name)
    {
        $this->master(true);
        return $this->handler->delete($name);
    }

    /**
     * 清除缓存
     *
     * @access public
     * @return boolen
     */
    public function clear()
    {
        $this->master(true);
        return $this->handler->flushDB();
    }

    /**
     * 返回句柄对象，可执行其它高级方法
     * 需要先执行 $redis->master() 连接到 DB
     *
     * @access public
     * @param  bool   $master 指定主从节点，可以从主节点获取结果
     * @return \Redis
     */
    public function handler($master = true)
    {
        $this->master($master);
        return $this->handler;
    }

    /**
     * 析构释放连接
     *
     * @access public
     */
    public function __destruct()
    {
        //该方法仅在connect连接时有效
        //当使用pconnect时，连接会被重用，连接的生命周期是fpm进程的生命周期，而非一次php的执行。
        //如果代码中使用pconnect， close的作用仅是使当前php不能再进行redis请求，但无法真正关闭redis长连接，连接在后续请求中仍然会被重用，直至fpm进程生命周期结束。

        try {
            if (method_exists($this->handler, "close")) {
                $this->handler->close();
            }

        } catch (\Exception $e) {
        }
    }
}
