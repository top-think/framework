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
        return new static($config->pull('database'));
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
        } elseif (is_string($config) && false === strpos($config, '/')) {
            // 支持读取配置参数
            $config = $this->config[$config] ?? $this->config;
        }

        return is_string($config) ? $this->parseDsnConfig($config) : $config;
    }

    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     * @access private
     * @param  string $dsnStr
     * @return array
     */
    private function parseDsnConfig(string $dsnStr): array
    {
        $info = parse_url($dsnStr);

        if (!$info) {
            return [];
        }

        $dsn = [
            'type'     => $info['scheme'],
            'username' => $info['user'] ?? '',
            'password' => $info['pass'] ?? '',
            'hostname' => $info['host'] ?? '',
            'hostport' => $info['port'] ?? '',
            'database' => !empty($info['path']) ? ltrim($info['path'], '/') : '',
            'charset'  => $info['fragment'] ?? 'utf8',
        ];

        if (isset($info['query'])) {
            parse_str($info['query'], $dsn['params']);
        } else {
            $dsn['params'] = [];
        }

        return $dsn;
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
    public function buildQuery($query, $connection)
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
