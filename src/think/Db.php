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
     * 数据库配置
     * @var array
     */
    protected $config = [];

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

        $this->config = $config;

        $this->connect($config);
    }

    public static function __make(Config $config)
    {
        return new static($config->get('database', []));
    }

    /**
     * 切换数据库连接
     * @access public
     * @param  mixed         $config 连接配置
     * @param  bool|string   $name 连接标识 true 强制重新连接
     * @return $this|object
     * @throws Exception
     */
    public function connect($config = [], $name = false)
    {
        $this->connection = Connection::instance($this->parseConfig($config), $name);

        return $this;
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
     * 获得查询次数
     * @access public
     * @return integer
     */
    public function getQueryTimes(): int
    {
        return $this->connection->getQueryTimes();
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
            $config = $this->config;
        } elseif (is_string($config)) {
            // 支持读取配置参数
            $config = $this->config[$config] ?? null;
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
        return $name ? ($this->config[$name] ?? null) : $this->config;
    }

    /**
     * 创建一个新的查询对象
     * @access public
     * @param  string $query        查询对象类名
     * @param  mixed  $connection   连接配置信息
     * @return mixed
     */
    public function buildQuery(string $query, $connection)
    {
        $connection = Connection::instance($this->parseConfig($connection));
        return new $query($connection);
    }

    public function __call($method, $args)
    {
        $class = $this->config['query'];

        $query = new $class($this->connection);

        return call_user_func_array([$query, $method], $args);
    }
}
