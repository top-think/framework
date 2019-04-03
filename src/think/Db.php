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

use think\db\Connection;
use think\db\Raw;

class Db
{
    /**
     * 当前数据库连接对象
     * @var Connection
     */
    protected $connection;

    /**
     * 数据库连接实例
     * @var array
     */
    protected $instance = [];

    /**
     * 配置对象
     * @var Config
     */
    protected $config;

    /**
     * Event对象
     * @var Event
     */
    protected $event;

    /**
     * 数据库配置
     * @var array
     */
    protected $option = [];

    /**
     * 读取主库
     * @var array
     */
    protected $readMaster = [];

    /**
     * 查询次数
     * @var int
     */
    protected $queryTimes = 0;

    /**
     * 架构函数
     * @param  array         $config 连接配置
     * @access public
     */
    public function __construct(array $config = [])
    {
        if (empty($config['query'])) {
            $config['query'] = '\\think\\db\\Query';
        }

        $this->option = $config;

        $this->connect($config);
    }

    public static function __make(Event $event, Config $config)
    {
        $db = new static($config->get('database'));

        $db->event  = $event;
        $db->config = $config;

        return $db;
    }

    /**
     * 切换数据库连接
     * @access public
     * @param  mixed       $config 连接配置
     * @param  bool|string $name 连接标识 true 强制重新连接
     * @return $this
     */
    public function connect($config = [], $name = false)
    {
        $this->connection = $this->instance($this->parseConfig($config), $name);
        return $this;
    }

    /**
     * 取得数据库连接类实例
     * @access public
     * @param  array       $config 连接配置
     * @param  bool|string $name 连接标识 true 强制重新连接
     * @return Connection
     * @throws Exception
     */
    public function instance(array $config = [], $name = false)
    {
        if (false === $name) {
            $name = md5(serialize($config));
        }

        if (true === $name || !isset($this->instance[$name])) {

            if (empty($config['type'])) {
                throw new InvalidArgumentException('Undefined db type');
            }

            if (true === $name) {
                $name = md5(serialize($config));
            }

            $this->instance[$name] = App::factory($config['type'], '\\think\\db\\connector\\', $config);
        }

        return $this->instance[$name];
    }

    /**
     * 设置从主库读取数据
     * @access public
     * @param  string $table 数据表
     * @return $this
     */
    public function readMaster(string $table = '*')
    {
        $this->readMaster[$table] = true;

        return $this;
    }

    /**
     * 是否从主库读取数据
     * @access public
     * @param  string $table 数据表
     * @return bool
     */
    public function isReadMaster(string $table): bool
    {
        return isset($this->readMaster['*']) || isset($this->readMaster[$table]);
    }

    /**
     * 使用表达式设置数据
     * @access public
     * @param  string $value 表达式
     * @return Raw
     */
    public function raw(string $value): Raw
    {
        return new Raw($value);
    }

    /**
     * 更新查询次数
     * @access public
     * @return void
     */
    public function updateQueryTimes(): void
    {
        $this->queryTimes++;
    }

    /**
     * 获得查询次数
     * @access public
     * @return integer
     */
    public function getQueryTimes(): int
    {
        return $this->queryTimes;
    }

    /**
     * 数据库连接参数解析
     * @access private
     * @param  mixed $config
     * @return array
     */
    private function parseConfig($config): array
    {
        if (empty($config)) {
            $config = $this->option;
        } elseif (is_string($config)) {
            // 支持读取配置参数
            $config = $this->option[$config] ?? null;
        }

        return $config;
    }

    /**
     * 获取数据库的配置参数
     * @access public
     * @param  string $name 参数名称
     * @return mixed
     */
    public function getConfig(string $name = '')
    {
        return $name ? ($this->option[$name] ?? null) : $this->option;
    }

    /**
     * 创建一个新的查询对象
     * @access public
     * @param  string       $query      查询对象类名
     * @param  string|array $connection 连接配置信息
     * @return mixed
     */
    public function buildQuery(string $query, $connection = [])
    {
        return $this->connect($connection)->newQuery($query);
    }

    /**
     * 注册回调方法
     * @access public
     * @param  string   $event    事件名
     * @param  callable $callback 回调方法
     * @return void
     */
    public function event(string $event, callable $callback): void
    {
        $this->event->listen('db.' . $event, $callback);
    }

    /**
     * 创建一个新的查询对象
     * @access protected
     * @param  string     $query      查询对象类名
     * @param  Connection $connection 连接对象
     * @return mixed
     */
    protected function newQuery(string $class, $connection = null)
    {
        $query = new $class($connection ?: $this->connection);

        $query->setEvent($this->event);
        $query->setConfig($this->config);
        $query->setDb($this);

        return $query;
    }

    public function __call($method, $args)
    {
        $query = $this->newQuery($this->option['query'], $this->connection);

        return call_user_func_array([$query, $method], $args);
    }
}
