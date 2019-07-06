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

use InvalidArgumentException;
use think\db\BaseQuery;
use think\db\Connection;
use think\db\Raw;

/**
 * Class Db
 * @package think
 * @mixin BaseQuery
 * @mixin Query
 */
class Db
{
    /**
     * 数据库连接实例
     * @var array
     */
    protected $instance = [];

    /**
     * Event对象
     * @var Event
     */
    protected $event;

    /**
     * 数据库配置
     * @var array
     */
    protected $config = [];

    /**
     * SQL监听
     * @var array
     */
    protected $listen = [];

    /**
     * 查询次数
     * @var int
     */
    protected $queryTimes = 0;

    /**
     * 架构函数
     * @param array $config 连接配置
     * @access public
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

    }

    /**
     * @param Event  $event
     * @param Config $config
     * @return Db
     * @codeCoverageIgnore
     */
    public static function __make(Event $event, Config $config)
    {
        $db = new static($config->get('database'));

        $db->setEvent($event);

        return $db;
    }

    /**
     * 创建/切换数据库连接查询
     * @access public
     * @param string|null $name 连接配置标识
     * @param bool        $force 强制重新连接
     * @return BaseQuery
     */
    public function connect(string $name = null, bool $force = false): BaseQuery
    {
        $connection = $this->instance($name, $force);
        $connection->setDb($this);

        $class = $connection->getQueryClass();
        $query = new $class($connection);

        if (!empty($this->config['time_query_rule'])) {
            $query->timeRule($this->config['time_query_rule']);
        }

        return $query;
    }

    /**
     * 创建数据库连接实例
     * @access protected
     * @param string|null $name  连接标识
     * @param bool        $force 强制重新连接
     * @return Connection
     */
    protected function instance(string $name = null, bool $force = false): Connection
    {
        if (empty($name)) {
            $name = $this->config['default'] ?? 'mysql';
        }

        if ($force || !isset($this->instance[$name])) {
            if (!isset($this->config['connections'][$name])) {
                throw new InvalidArgumentException('Undefined db config:' . $name);
            }

            $config = $this->config['connections'][$name];
            $type   = !empty($config['type']) ? $config['type'] : 'mysql';

            $this->instance[$name] = App::factory($type, '\\think\\db\\connector\\', $config);
        }

        return $this->instance[$name];
    }

    /**
     * 使用表达式设置数据
     * @access public
     * @param string $value 表达式
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
     * 重置查询次数
     * @access public
     * @return void
     */
    public function clearQueryTimes(): void
    {
        $this->queryTimes = 0;
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
     * 监听SQL执行
     * @access public
     * @param callable $callback 回调方法
     * @return void
     */
    public function listen(callable $callback): void
    {
        $this->listen[] = $callback;
    }

    /**
     * 获取监听SQL执行
     * @access public
     * @return array
     */
    public function getListen(): array
    {
        return $this->listen;
    }

    /**
     * 设置Event对象
     * @param Event $event
     */
    public function setEvent(Event $event)
    {
        $this->event = $event;
    }

    /**
     * 注册回调方法
     * @access public
     * @param string   $event    事件名
     * @param callable $callback 回调方法
     * @return void
     */
    public function event(string $event, callable $callback): void
    {
        if ($this->event) {
            $this->event->listen('db.' . $event, $callback);
        }
    }

    /**
     * 触发事件
     * @access public
     * @param string $event  事件名
     * @param mixed  $params 传入参数
     * @param bool   $once
     * @return mixed
     */
    public function trigger(string $event, $params = null, bool $once = false)
    {
        if ($this->event) {
            return $this->event->trigger('db.' . $event, $params, $once);
        }
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->connect(), $method], $args);
    }
}
