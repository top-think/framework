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

namespace think\db;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use think\App;
use think\cache\CacheItem;
use think\Container;
use think\Db;
use think\db\exception\BindParamException;
use think\Debug;
use think\Exception;
use think\exception\PDOException;

abstract class Connection
{
    const PARAM_FLOAT = 21;

    protected static $instance = [];
    /** @var PDOStatement PDO操作实例 */
    protected $PDOStatement;

    /** @var string 当前SQL指令 */
    protected $queryStr = '';
    // 返回或者影响记录数
    protected $numRows = 0;
    // 事务指令数
    protected $transTimes = 0;
    // 错误信息
    protected $error = '';

    /**
     * 查询次数
     * @var integer
     */
    protected static $queryTimes = 0;

    /** @var PDO[] 数据库连接ID 支持多个连接 */
    protected $links = [];

    /** @var PDO 当前连接ID */
    protected $linkID;
    protected $linkRead;
    protected $linkWrite;

    // 查询结果类型
    protected $fetchType = PDO::FETCH_ASSOC;
    // 字段属性大小写
    protected $attrCase = PDO::CASE_LOWER;
    // 监听回调
    protected static $event = [];

    // 数据表信息
    protected static $info = [];

    // 使用Builder类
    protected $builderClassName;
    // Builder对象
    protected $builder;
    // 数据库连接参数配置
    protected $config = [
        // 数据库类型
        'type'            => '',
        // 服务器地址
        'hostname'        => '',
        // 数据库名
        'database'        => '',
        // 用户名
        'username'        => '',
        // 密码
        'password'        => '',
        // 端口
        'hostport'        => '',
        // 连接dsn
        'dsn'             => '',
        // 数据库连接参数
        'params'          => [],
        // 数据库编码默认采用utf8
        'charset'         => 'utf8',
        // 数据库表前缀
        'prefix'          => '',
        // 数据库调试模式
        'debug'           => false,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'          => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'     => false,
        // 读写分离后 主服务器数量
        'master_num'      => 1,
        // 指定从服务器序号
        'slave_no'        => '',
        // 模型写入后自动读取主服务器
        'read_master'     => false,
        // 是否严格检查字段是否存在
        'fields_strict'   => true,
        // 自动写入时间戳字段
        'auto_timestamp'  => false,
        // 时间字段取出后的默认时间格式
        'datetime_format' => 'Y-m-d H:i:s',
        // 是否需要进行SQL性能分析
        'sql_explain'     => false,
        // Builder类
        'builder'         => '',
        // Query类
        'query'           => '\\think\\db\\Query',
        // 是否需要断线重连
        'break_reconnect' => false,
        // 断线标识字符串
        'break_match_str' => [],
    ];

    // PDO连接参数
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];

    // 参数绑定类型映射
    protected $bindType = [
        'string'  => PDO::PARAM_STR,
        'str'     => PDO::PARAM_STR,
        'integer' => PDO::PARAM_INT,
        'int'     => PDO::PARAM_INT,
        'boolean' => PDO::PARAM_BOOL,
        'bool'    => PDO::PARAM_BOOL,
        'float'   => self::PARAM_FLOAT,
    ];

    // 服务器断线标识字符
    protected $breakMatchStr = [
        'server has gone away',
        'no connection to the server',
        'Lost connection',
        'is dead or not enabled',
        'Error while sending',
        'decryption failed or bad record mac',
        'server closed the connection unexpectedly',
        'SSL connection has been closed unexpectedly',
        'Error writing data to the connection',
        'Resource deadlock avoided',
        'failed with errno',
    ];

    // 绑定参数
    protected $bind = [];

    // 缓存对象
    protected $cache;

    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param  array $config 数据库配置数组
     */
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }

        // 创建Builder对象
        $class = $this->getBuilderClass();

        $this->builder = new $class($this);
        $this->cache   = Container::pull('cache');

        // 执行初始化操作
        $this->initialize();
    }

    /**
     * 初始化
     * @access protected
     * @return void
     */
    protected function initialize(): void
    {}

    /**
     * 取得数据库连接类实例
     * @access public
     * @param  mixed         $config 连接配置
     * @param  bool|string   $name 连接标识 true 强制重新连接
     * @return Connection
     * @throws Exception
     */
    public static function instance($config = [], $name = false): Connection
    {
        if (false === $name) {
            $name = md5(serialize($config));
        }

        if (true === $name || !isset(self::$instance[$name])) {

            if (empty($config['type'])) {
                throw new InvalidArgumentException('Undefined db type');
            }

            // 记录初始化信息
            Container::pull('log')->record('[ DB ] INIT ' . $config['type']);

            if (true === $name) {
                $name = md5(serialize($config));
            }

            self::$instance[$name] = App::factory($config['type'], '\\think\\db\\connector\\', $config);
        }

        return self::$instance[$name];
    }

    /**
     * 获取当前连接器类对应的Builder类
     * @access public
     * @return string
     */
    public function getBuilderClass(): string
    {
        if (!empty($this->builderClassName)) {
            return $this->builderClassName;
        }

        return $this->getConfig('builder') ?: '\\think\\db\\builder\\' . ucfirst($this->getConfig('type'));
    }

    /**
     * 设置当前的数据库Builder对象
     * @access protected
     * @param  Builder    $builder
     * @return void
     */
    protected function setBuilder(Builder $builder)
    {
        $this->builder = $builder;

        return $this;
    }

    /**
     * 获取当前的builder实例对象
     * @access public
     * @return Builder
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param  array $config 连接信息
     * @return string
     */
    abstract protected function parseDsn(array $config);

    /**
     * 取得数据表的字段信息
     * @access public
     * @param  string $tableName
     * @return array
     */
    abstract public function getFields(string $tableName);

    /**
     * 取得数据库的表信息
     * @access public
     * @param string $dbName
     * @return array
     */
    abstract public function getTables(string $dbName);

    /**
     * SQL性能分析
     * @access protected
     * @param  string $sql
     * @return array
     */
    abstract protected function getExplain(string $sql);

    /**
     * 对返数据表字段信息进行大小写转换出来
     * @access public
     * @param  array $info 字段信息
     * @return array
     */
    public function fieldCase(array $info): array
    {
        // 字段大小写转换
        switch ($this->attrCase) {
            case PDO::CASE_LOWER:
                $info = array_change_key_case($info);
                break;
            case PDO::CASE_UPPER:
                $info = array_change_key_case($info, CASE_UPPER);
                break;
            case PDO::CASE_NATURAL:
            default:
                // 不做转换
        }

        return $info;
    }

    /**
     * 获取字段绑定类型
     * @access public
     * @param  string $type 字段类型
     * @return integer
     */
    public function getFieldBindType(string $type): int
    {
        if (in_array($type, ['integer', 'string', 'float', 'boolean', 'bool', 'int', 'str'])) {
            $bind = $this->bindType[$type];
        } elseif (0 === strpos($type, 'set') || 0 === strpos($type, 'enum')) {
            $bind = PDO::PARAM_STR;
        } elseif (preg_match('/(double|float|decimal|real|numeric)/is', $type)) {
            $bind = self::PARAM_FLOAT;
        } elseif (preg_match('/(int|serial|bit)/is', $type)) {
            $bind = PDO::PARAM_INT;
        } elseif (preg_match('/bool/is', $type)) {
            $bind = PDO::PARAM_BOOL;
        } else {
            $bind = PDO::PARAM_STR;
        }

        return $bind;
    }

    /**
     * 获取数据表信息
     * @access public
     * @param  mixed  $tableName 数据表名 留空自动获取
     * @param  string $fetch     获取信息类型 包括 fields type bind pk
     * @return mixed
     */
    public function getTableInfo($tableName, string $fetch = '')
    {
        if (is_array($tableName)) {
            $tableName = key($tableName) ?: current($tableName);
        }

        if (strpos($tableName, ',')) {
            // 多表不获取字段信息
            return false;
        }

        // 修正子查询作为表名的问题
        if (strpos($tableName, ')')) {
            return [];
        }

        list($tableName) = explode(' ', $tableName);

        if (!strpos($tableName, '.')) {
            $schema = $this->getConfig('database') . '.' . $tableName;
        } else {
            $schema = $tableName;
        }

        if (!isset(self::$info[$schema])) {
            // 读取缓存
            $cacheFile = Container::pull('app')->getRuntimePath() . 'schema' . DIRECTORY_SEPARATOR . $schema . '.php';

            if (!$this->config['debug'] && is_file($cacheFile)) {
                $info = include $cacheFile;
            } else {
                $info = $this->getFields($tableName);
            }

            $fields = array_keys($info);
            $bind   = $type   = [];

            foreach ($info as $key => $val) {
                // 记录字段类型
                $type[$key] = $val['type'];
                $bind[$key] = $this->getFieldBindType($val['type']);

                if (!empty($val['primary'])) {
                    $pk[] = $key;
                }
            }

            if (isset($pk)) {
                // 设置主键
                $pk = count($pk) > 1 ? $pk : $pk[0];
            } else {
                $pk = null;
            }

            self::$info[$schema] = ['fields' => $fields, 'type' => $type, 'bind' => $bind, 'pk' => $pk];
        }

        return $fetch ? self::$info[$schema][$fetch] : self::$info[$schema];
    }

    /**
     * 获取数据表的主键
     * @access public
     * @param  string $tableName 数据表名
     * @return string|array
     */
    public function getPk($tableName)
    {
        return $this->getTableInfo($tableName, 'pk');
    }

    /**
     * 获取数据表字段信息
     * @access public
     * @param  string $tableName 数据表名
     * @return array
     */
    public function getTableFields($tableName): array
    {
        return $this->getTableInfo($tableName, 'fields');
    }

    /**
     * 获取数据表字段类型
     * @access public
     * @param  string $tableName 数据表名
     * @param  string $field    字段名
     * @return array|string
     */
    public function getFieldsType($tableName, string $field = null)
    {
        $result = $this->getTableInfo($tableName, 'type');

        if ($field && isset($result[$field])) {
            return $result[$field];
        }

        return $result;
    }

    /**
     * 获取数据表绑定信息
     * @access public
     * @param  string $tableName 数据表名
     * @return array
     */
    public function getFieldsBind($tableName): array
    {
        return $this->getTableInfo($tableName, 'bind');
    }

    /**
     * 获取数据库的配置参数
     * @access public
     * @param  string $config 配置名称
     * @return mixed
     */
    public function getConfig(string $config = '')
    {
        return $config ? $this->config[$config] : $this->config;
    }

    /**
     * 设置数据库的配置参数
     * @access public
     * @param  array      $config 配置
     * @return void
     */
    public function setConfig($config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 连接数据库方法
     * @access public
     * @param  array         $config 连接参数
     * @param  integer       $linkNum 连接序号
     * @param  array|bool    $autoConnection 是否自动连接主数据库（用于分布式）
     * @return PDO
     * @throws Exception
     */
    public function connect(array $config = [], $linkNum = 0, $autoConnection = false): PDO
    {
        if (isset($this->links[$linkNum])) {
            return $this->links[$linkNum];
        }

        if (!$config) {
            $config = $this->config;
        } else {
            $config = array_merge($this->config, $config);
        }

        // 连接参数
        if (isset($config['params']) && is_array($config['params'])) {
            $params = $config['params'] + $this->params;
        } else {
            $params = $this->params;
        }

        // 记录当前字段属性大小写设置
        $this->attrCase = $params[PDO::ATTR_CASE];

        if (!empty($config['break_match_str'])) {
            $this->breakMatchStr = array_merge($this->breakMatchStr, (array) $config['break_match_str']);
        }

        try {
            if (empty($config['dsn'])) {
                $config['dsn'] = $this->parseDsn($config);
            }

            if ($config['debug']) {
                $startTime = microtime(true);
            }

            $this->links[$linkNum] = new PDO($config['dsn'], $config['username'], $config['password'], $params);

            if ($config['debug']) {
                // 记录数据库连接信息
                $this->log('[ DB ] CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn']);
            }

            return $this->links[$linkNum];
        } catch (\PDOException $e) {
            if ($autoConnection) {
                $this->log($e->getMessage(), 'error');
                return $this->connect($autoConnection, $linkNum);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free(): void
    {
        $this->PDOStatement = null;
    }

    /**
     * 获取PDO对象
     * @access public
     * @return \PDO|false
     */
    public function getPdo()
    {
        if (!$this->linkID) {
            return false;
        }

        return $this->linkID;
    }

    /**
     * 执行查询 使用生成器返回数据
     * @access public
     * @param  Query     $query 查询对象
     * @param  string    $sql sql指令
     * @param  array     $bind 参数绑定
     * @param  Model     $model 模型对象实例
     * @param  array     $condition 查询条件
     * @return \Generator
     */
    public function getCursor(Query $query, string $sql, array $bind = [], $model = null, $condition = null)
    {
        $this->queryPDOStatement($query, $sql, $bind);

        // 返回结果集
        while ($result = $this->PDOStatement->fetch($this->fetchType)) {
            if ($model) {
                yield $model->newInstance($result, $condition);
            } else {
                yield $result;
            }
        }
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param  Query     $query 查询对象
     * @param  string    $sql sql指令
     * @param  array     $bind 参数绑定
     * @param  bool      $cache 是否支持缓存
     * @return array
     * @throws BindParamException
     * @throws \PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    public function query(Query $query, string $sql, array $bind = [], bool $cache = false): array
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        if ($cache && !empty($options['cache'])) {
            $cacheItem = $options['cache'];
            $resultSet = $this->cache->get($cacheItem->getKey());

            if (false !== $resultSet) {
                return $resultSet;
            }
        }

        $master    = !empty($options['master']) ? true : false;
        $procedure = !empty($options['procedure']) ? true : in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);

        $this->getPDOStatement($sql, $bind, $master, $procedure);

        $resultSet = $this->getResult($procedure);

        if (isset($cacheItem) && $resultSet) {
            // 缓存数据集
            $cacheItem->set($resultSet);
            $this->cacheData($cacheItem);
        }

        return $resultSet;
    }

    /**
     * 执行查询但只返回PDOStatement对象
     * @access public
     * @param  Query         $query 查询对象
     * @return \PDOStatement
     */
    public function pdo(Query $query)
    {
        $bind = $query->getBind();
        // 生成查询SQL
        $sql = $this->builder->select($query);

        return $this->queryPDOStatement($query, $sql, $bind);
    }

    /**
     * 执行查询但只返回PDOStatement对象
     * @access public
     * @param  string    $sql sql指令
     * @param  array     $bind 参数绑定
     * @param  bool      $master 是否在主服务器读操作
     * @param  bool      $procedure 是否为存储过程调用
     * @return PDOStatement
     * @throws BindParamException
     * @throws \PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    public function getPDOStatement(string $sql, array $bind = [], bool $master = false, bool $procedure = false): PDOStatement
    {
        $this->initConnect($master);

        // 记录SQL语句
        $this->queryStr = $sql;

        $this->bind = $bind;

        self::$queryTimes++;

        try {
            // 调试开始
            $this->debug(true);

            // 预处理
            $this->PDOStatement = $this->linkID->prepare($sql);

            // 参数绑定
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }

            // 执行查询
            $this->PDOStatement->execute();

            // 调试结束
            $this->debug(false, '', $master);

            return $this->PDOStatement;
        } catch (\Throwable | \Exception $e) {
            if ($this->isBreak($e)) {
                return $this->close()->getPDOStatement($sql, $bind, $master, $procedure);
            }

            if ($e instanceof \PDOException) {
                throw new PDOException($e, $this->config, $this->getLastsql());
            } else {
                throw $e;
            }
        }
    }

    /**
     * 执行语句
     * @access public
     * @param  Query         $query 查询对象
     * @param  string        $sql sql指令
     * @param  array         $bind 参数绑定
     * @return int
     * @throws BindParamException
     * @throws \PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    public function execute(Query $query, string $sql, array $bind = []): int
    {
        $this->queryPDOStatement($query->master(true), $sql, $bind);

        $this->numRows = $this->PDOStatement->rowCount();

        return $this->numRows;
    }

    protected function queryPDOStatement(Query $query, string $sql, array $bind = [])
    {
        $options   = $query->parseOptions();
        $master    = !empty($options['master']) ? true : false;
        $procedure = !empty($options['procedure']) ? true : in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);

        return $this->getPDOStatement($sql, $bind, $master, $procedure);
    }

    /**
     * 查找单条记录
     * @access public
     * @param  Query  $query        查询对象
     * @return array|null
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function find(Query $query)
    {
        // 分析查询表达式
        $options = $query->parseOptions();
        $pk      = $query->getPk($options);
        $data    = $options['data'];

        if (!empty($options['cache'])) {
            // 判断查询缓存
            $cacheItem = $options['cache'];

            if (!$cacheItem->getKey()) {
                $key = $this->getCacheKey($query, $data);
                $cacheItem->setKey($key);
            }

            $options['key'] = $cacheItem->getKey();
        }

        if (is_string($pk) && !is_array($data)) {
            if (isset($key) && strpos($key, '|')) {
                list($a, $val) = explode('|', $key);
                $item[$pk]     = $val;
            } else {
                $item[$pk] = $data;
            }
            $data = $item;
        }

        $query->setOption('data', $data);

        if (isset($options['key'])) {
            $result = $this->cache->get($options['key']);

            if (false !== $result) {
                return $result;
            }
        }

        // 生成查询SQL
        $sql = $this->builder->select($query, true);

        // 事件回调
        $result = $query->trigger('before_find');

        if (!$result) {
            // 执行查询
            $resultSet = $this->query($query, $sql, $query->getBind());

            $result = $resultSet[0] ?? null;
        }

        if (isset($cacheItem) && $result) {
            // 缓存数据
            $cacheItem->set($result);
            $this->cacheData($cacheItem);
        }

        return $result;
    }

    /**
     * 使用游标查询记录
     * @access public
     * @param  Query   $query        查询对象
     * @return \Generator
     */
    public function cursor(Query $query)
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        // 生成查询SQL
        $sql = $this->builder->select($query);

        $condition = $options['where']['AND'] ?? null;

        // 执行查询操作
        return $this->getCursor($query, $sql, $query->getBind(), $options['master'], $query->getModel(), $condition);
    }

    /**
     * 查找记录
     * @access public
     * @param  Query   $query        查询对象
     * @return array
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function select(Query $query): array
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        if (!empty($options['cache'])) {
            $cacheItem = $options['cache'];
            $resultSet = $this->getCacheData($cacheItem);

            if (false !== $resultSet) {
                return $resultSet;
            }
        }

        // 生成查询SQL
        $sql = $this->builder->select($query);

        $resultSet = $query->trigger('before_select');

        if (!$resultSet) {
            // 执行查询操作
            $resultSet = $this->query($query, $sql, $query->getBind());
        }

        if (isset($cacheItem) && false !== $resultSet) {
            // 缓存数据集
            $cacheItem->set($resultSet);
            $this->cacheData($cacheItem);
        }

        return $resultSet;
    }

    /**
     * 插入记录
     * @access public
     * @param  Query   $query        查询对象
     * @param  boolean $replace      是否replace
     * @param  boolean $getLastInsID 返回自增主键
     * @param  string  $sequence     自增序列名
     * @return integer
     */
    public function insert(Query $query, bool $replace = false, bool $getLastInsID = false, string $sequence = null)
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        // 生成SQL语句
        $sql = $this->builder->insert($query, $replace);

        // 执行操作
        $result = '' == $sql ? 0 : $this->execute($query, $sql, $query->getBind());

        if ($result) {
            $sequence  = $sequence ?: ($options['sequence'] ?? null);
            $lastInsId = $this->getLastInsID($sequence);

            $data = $options['data'];

            if ($lastInsId) {
                $pk = $query->getPk($options);
                if (is_string($pk)) {
                    $data[$pk] = $lastInsId;
                }
            }

            $query->setOption('data', $data);

            $query->trigger('after_insert');

            if ($getLastInsID) {
                return $lastInsId;
            }
        }

        return $result;
    }

    /**
     * 批量插入记录
     * @access public
     * @param  Query     $query      查询对象
     * @param  mixed     $dataSet    数据集
     * @param  bool      $replace    是否replace
     * @param  integer   $limit      每次写入数据限制
     * @return integer
     * @throws \Exception
     * @throws \Throwable
     */
    public function insertAll(Query $query, array $dataSet = [], bool $replace = false, int $limit = null): int
    {
        if (!is_array(reset($dataSet))) {
            return 0;
        }

        $options = $query->parseOptions();

        if ($limit) {
            // 分批写入 自动启动事务支持
            $this->startTrans();

            try {
                $array = array_chunk($dataSet, $limit, true);
                $count = 0;

                foreach ($array as $item) {
                    $sql = $this->builder->insertAll($query, $item, $replace);
                    $count += $this->execute($query, $sql, $query->getBind());
                }

                // 提交事务
                $this->commit();
            } catch (\Exception | \Throwable $e) {
                $this->rollback();
                throw $e;
            }

            return $count;
        }

        $sql = $this->builder->insertAll($query, $dataSet, $replace);

        return $this->execute($query, $sql, $query->getBind());
    }

    /**
     * 通过Select方式插入记录
     * @access public
     * @param  Query     $query      查询对象
     * @param  array     $fields     要插入的数据表字段名
     * @param  string    $table      要插入的数据表名
     * @return integer
     * @throws PDOException
     */
    public function selectInsert(Query $query, array $fields, string $table): int
    {
        // 分析查询表达式
        $sql = $this->builder->selectInsert($query, $fields, $table);

        return $this->execute($query, $sql, $query->getBind());
    }

    /**
     * 更新记录
     * @access public
     * @param  Query     $query  查询对象
     * @return integer
     * @throws Exception
     * @throws PDOException
     */
    public function update(Query $query): int
    {
        $options = $query->parseOptions();

        if (isset($options['cache'])) {
            $cacheItem = $options['cache'];
            $key       = $cacheItem->getKey();
        }

        $pk   = $query->getPk($options);
        $data = $options['data'];

        if (empty($options['where'])) {
            // 如果存在主键数据 则自动作为更新条件
            if (is_string($pk) && isset($data[$pk])) {
                $where[$pk] = [$pk, '=', $data[$pk]];
                if (!isset($key)) {
                    $key = $this->getCacheKey($query, $data[$pk]);

                }
                unset($data[$pk]);
            } elseif (is_array($pk)) {
                // 增加复合主键支持
                foreach ($pk as $field) {
                    if (isset($data[$field])) {
                        $where[$field] = [$field, '=', $data[$field]];
                    } else {
                        // 如果缺少复合主键数据则不执行
                        throw new Exception('miss complex primary data');
                    }
                    unset($data[$field]);
                }
            }

            if (!isset($where)) {
                // 如果没有任何更新条件则不执行
                throw new Exception('miss update condition');
            } else {
                $options['where']['AND'] = $where;
                $query->setOption('where', ['AND' => $where]);
            }
        } elseif (!isset($key) && is_string($pk) && isset($options['where']['AND'])) {
            foreach ($options['where']['AND'] as $val) {
                if (is_array($val) && $val[0] == $pk) {
                    $key = $this->getCacheKey($query, $val);
                }
            }
        }

        // 更新数据
        $query->setOption('data', $data);

        // 生成UPDATE SQL语句
        $sql = $this->builder->update($query);

        // 检测缓存

        if (isset($key) && $this->cache->get($key)) {
            // 删除缓存
            $this->cache->rm($key);
        } elseif (isset($cacheItem) && $cacheItem->getTag()) {
            $this->cache->clear($cacheItem->getTag());
        }

        // 执行操作
        $result = '' == $sql ? 0 : $this->execute($query, $sql, $query->getBind());

        if ($result) {
            if (is_string($pk) && isset($options['where']['AND'][$pk])) {
                $data[$pk] = $options['where']['AND'][$pk];
            } elseif (is_string($pk) && isset($key) && strpos($key, '|')) {
                list($a, $val) = explode('|', $key);
                $data[$pk]     = $val;
            }

            $query->setOption('data', $data);
            $query->trigger('after_update');
        }

        return $result;
    }

    /**
     * 删除记录
     * @access public
     * @param  Query $query 查询对象
     * @return int
     * @throws Exception
     * @throws PDOException
     */
    public function delete(Query $query): int
    {
        // 分析查询表达式
        $options = $query->parseOptions();
        $pk      = $query->getPk($options);
        $data    = $options['data'];

        if (isset($options['cache'])) {
            $cacheItem = $options['cache'];
            $key       = $cacheItem->getKey();
        } elseif (!is_null($data) && true !== $data && !is_array($data)) {
            $key = $this->getCacheKey($query, $data);
        } elseif (is_string($pk) && isset($options['where']['AND'][$pk])) {
            $key = $this->getCacheKey($query, $options['where']['AND'][$pk]);
        }

        if (true !== $data && empty($options['where'])) {
            // 如果条件为空 不进行删除操作 除非设置 1=1
            throw new Exception('delete without condition');
        }

        // 生成删除SQL语句
        $sql = $this->builder->delete($query);

        // 检测缓存
        if (isset($key) && $this->cache->get($key)) {
            // 删除缓存
            $this->cache->rm($key);
        } elseif (isset($cacheItem) && $cacheItem->getTag()) {
            $this->cache->clear($cacheItem->getTag());
        }

        // 执行操作
        $result = $this->execute($query, $sql, $query->getBind());

        if ($result) {
            if (!is_array($data) && is_string($pk) && isset($key) && strpos($key, '|')) {
                list($a, $val) = explode('|', $key);
                $item[$pk]     = $val;
                $data          = $item;
            }

            $options['data'] = $data;

            $query->trigger('after_delete');
        }

        return $result;
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param  Query     $query 查询对象
     * @param  string    $field   字段名
     * @param  mixed     $default   默认值
     * @return mixed
     */
    public function value(Query $query, string $field, $default = null)
    {
        $options = $query->parseOptions();

        if (isset($options['field'])) {
            $query->removeOption('field');
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        $query->setOption('field', $field);

        if (!empty($options['cache'])) {
            $cacheItem = $options['cache'];
            $result    = $this->getCacheData($cacheItem);

            if (false !== $result) {
                return $result;
            }
        }

        // 生成查询SQL
        $sql = $this->builder->select($query, true);

        if (isset($options['field'])) {
            $query->setOption('field', $options['field']);
        } else {
            $query->removeOption('field');
        }

        // 执行查询操作
        $pdo = $this->getPDOStatement($sql, $query->getBind(), $options['master']);

        $result = $pdo->fetchColumn();

        if (isset($cacheItem) && false !== $result) {
            // 缓存数据
            $cacheItem->set($result);
            $this->cacheData($cacheItem);
        }

        return false !== $result ? $result : $default;
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param  Query     $query     查询对象
     * @param  string    $aggregate 聚合方法
     * @param  mixed     $field     字段名
     * @param  bool      $force     强制转为数字类型
     * @return mixed
     */
    public function aggregate(Query $query, string $aggregate, $field, bool $force = false)
    {
        $field = $aggregate . '(' . $this->builder->parseKey($query, $field) . ') AS tp_' . strtolower($aggregate);

        $result = $this->value($query, $field, 0);

        return $force ? (float) $result : $result;
    }

    /**
     * 得到某个列的数组
     * @access public
     * @param  Query     $query 查询对象
     * @param  string    $field 字段名 多个字段用逗号分隔
     * @param  string    $key   索引
     * @return array
     */
    public function column(Query $query, string $field, string $key = ''): array
    {
        $options = $query->parseOptions();

        if (isset($options['field'])) {
            $query->removeOption('field');
        }

        if ($key && '*' != $field) {
            $field = $key . ',' . $field;
        }

        $field = array_map('trim', explode(',', $field));

        $query->setOption('field', $field);

        if (!empty($options['cache'])) {
            // 判断查询缓存
            $cacheItem = $options['cache'];
            $result    = $this->getCacheData($cacheItem);

            if (false !== $result) {
                return $result;
            }
        }

        // 生成查询SQL
        $sql = $this->builder->select($query);

        if (isset($options['field'])) {
            $query->setOption('field', $options['field']);
        } else {
            $query->removeOption('field');
        }

        // 执行查询操作
        $pdo = $this->getPDOStatement($sql, $query->getBind(), $options['master']);

        if (1 == $pdo->columnCount()) {
            $result = $pdo->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $resultSet = $pdo->fetchAll(PDO::FETCH_ASSOC);

            if ('*' == $field && $key) {
                $result = array_column($resultSet, null, $key);
            } elseif ($resultSet) {
                $fields = array_keys($resultSet[0]);
                $count  = count($fields);
                $key1   = array_shift($fields);
                $key2   = $fields ? array_shift($fields) : '';
                $key    = $key ?: $key1;

                if (strpos($key, '.')) {
                    list($alias, $key) = explode('.', $key);
                }

                if (2 == $count) {
                    $column = $key2;
                } elseif (1 == $count) {
                    $column = $key1;
                } else {
                    $column = null;
                }

                $result = array_column($resultSet, $column, $key);
            } else {
                $result = [];
            }
        }

        if (isset($cacheItem)) {
            // 缓存数据
            $cacheItem->set($result);
            $this->cacheData($cacheItem);
        }

        return $result;
    }

    /**
     * 根据参数绑定组装最终的SQL语句 便于调试
     * @access public
     * @param  string    $sql 带参数绑定的sql语句
     * @param  array     $bind 参数绑定列表
     * @return string
     */
    public function getRealSql(string $sql, array $bind = []): string
    {
        foreach ($bind as $key => $val) {
            $value = is_array($val) ? $val[0] : $val;
            $type  = is_array($val) ? $val[1] : PDO::PARAM_STR;

            if (self::PARAM_FLOAT == $type) {
                $value = (float) $value;
            } elseif (PDO::PARAM_STR == $type && is_string($value)) {
                $value = '\'' . addslashes($value) . '\'';
            } elseif (PDO::PARAM_INT == $type && '' === $value) {
                $value = 0;
            }

            // 判断占位符
            $sql = is_numeric($key) ?
            substr_replace($sql, $value, strpos($sql, '?'), 1) :
            substr_replace($sql, $value, strpos($sql, ':' . $key), strlen(':' . $key));
        }

        return rtrim($sql);
    }

    /**
     * 参数绑定
     * 支持 ['name'=>'value','id'=>123] 对应命名占位符
     * 或者 ['value',123] 对应问号占位符
     * @access public
     * @param  array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    protected function bindValue(array $bind = []): void
    {
        foreach ($bind as $key => $val) {
            // 占位符
            $param = is_numeric($key) ? $key + 1 : ':' . $key;

            if (is_array($val)) {
                if (PDO::PARAM_INT == $val[1] && '' === $val[0]) {
                    $val[0] = 0;
                } elseif (self::PARAM_FLOAT == $val[1]) {
                    $val[0] = (float) $val[0];
                    $val[1] = PDO::PARAM_STR;
                }

                $result = $this->PDOStatement->bindValue($param, $val[0], $val[1]);
            } else {
                $result = $this->PDOStatement->bindValue($param, $val);
            }

            if (!$result) {
                throw new BindParamException(
                    "Error occurred  when binding parameters '{$param}'",
                    $this->config,
                    $this->getLastsql(),
                    $bind
                );
            }
        }
    }

    /**
     * 存储过程的输入输出参数绑定
     * @access public
     * @param  array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    protected function bindParam(array $bind): void
    {
        foreach ($bind as $key => $val) {
            $param = is_numeric($key) ? $key + 1 : ':' . $key;

            if (is_array($val)) {
                array_unshift($val, $param);
                $result = call_user_func_array([$this->PDOStatement, 'bindParam'], $val);
            } else {
                $result = $this->PDOStatement->bindValue($param, $val);
            }

            if (!$result) {
                $param = array_shift($val);

                throw new BindParamException(
                    "Error occurred  when binding parameters '{$param}'",
                    $this->config,
                    $this->getLastsql(),
                    $bind
                );
            }
        }
    }

    /**
     * 获得数据集数组
     * @access protected
     * @param  bool   $procedure 是否存储过程
     * @return array
     */
    protected function getResult(bool $procedure = false): array
    {
        if ($procedure) {
            // 存储过程返回结果
            return $this->procedure();
        }

        $result = $this->PDOStatement->fetchAll($this->fetchType);

        $this->numRows = count($result);

        return $result;
    }

    /**
     * 获得存储过程数据集
     * @access protected
     * @return array
     */
    protected function procedure(): array
    {
        $item = [];

        do {
            $result = $this->getResult();
            if ($result) {
                $item[] = $result;
            }
        } while ($this->PDOStatement->nextRowset());

        $this->numRows = count($item);

        return $item;
    }

    /**
     * 执行数据库事务
     * @access public
     * @param  callable $callback 数据操作方法回调
     * @return mixed
     * @throws PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    public function transaction(callable $callback)
    {
        $this->startTrans();

        try {
            $result = null;
            if (is_callable($callback)) {
                $result = call_user_func_array($callback, [$this]);
            }

            $this->commit();
            return $result;
        } catch (\Exception | \Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 启动事务
     * @access public
     * @return void
     * @throws \PDOException
     * @throws \Exception
     */
    public function startTrans(): void
    {
        $this->initConnect(true);

        ++$this->transTimes;

        try {
            if (1 == $this->transTimes) {
                $this->linkID->beginTransaction();
            } elseif ($this->transTimes > 1 && $this->supportSavepoint()) {
                $this->linkID->exec(
                    $this->parseSavepoint('trans' . $this->transTimes)
                );
            }
        } catch (\Exception $e) {
            if ($this->isBreak($e)) {
                --$this->transTimes;
                $this->close()->startTrans();
            }
            throw $e;
        }
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @return void
     * @throws PDOException
     */
    public function commit(): void
    {
        $this->initConnect(true);

        if (1 == $this->transTimes) {
            $this->linkID->commit();
        }

        --$this->transTimes;
    }

    /**
     * 事务回滚
     * @access public
     * @return void
     * @throws PDOException
     */
    public function rollback(): void
    {
        $this->initConnect(true);

        if (1 == $this->transTimes) {
            $this->linkID->rollBack();
        } elseif ($this->transTimes > 1 && $this->supportSavepoint()) {
            $this->linkID->exec(
                $this->parseSavepointRollBack('trans' . $this->transTimes)
            );
        }

        $this->transTimes = max(0, $this->transTimes - 1);
    }

    /**
     * 是否支持事务嵌套
     * @return bool
     */
    protected function supportSavepoint(): bool
    {
        return false;
    }

    /**
     * 生成定义保存点的SQL
     * @access protected
     * @param  $name
     * @return string
     */
    protected function parseSavepoint(string $name): string
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * 生成回滚到保存点的SQL
     * @access protected
     * @param  $name
     * @return string
     */
    protected function parseSavepointRollBack(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    /**
     * 批处理执行SQL语句
     * 批处理的指令都认为是execute操作
     * @access public
     * @param  array $sqlArray   SQL批处理指令
     * @param  array $bind       参数绑定
     * @return bool
     */
    public function batchQuery(array $sqlArray = [], array $bind = []): bool
    {
        if (!is_array($sqlArray)) {
            return false;
        }

        // 自动启动事务支持
        $this->startTrans();

        try {
            foreach ($sqlArray as $sql) {
                $this->execute($query, $sql, $bind);
            }
            // 提交事务
            $this->commit();
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }

        return true;
    }

    /**
     * 获得查询次数
     * @access public
     * @return integer
     */
    public function getQueryTimes(): int
    {
        return self::$queryTimes;
    }

    /**
     * 关闭数据库（或者重新连接）
     * @access public
     * @return $this
     */
    public function close()
    {
        $this->linkID    = null;
        $this->linkWrite = null;
        $this->linkRead  = null;
        $this->links     = [];

        $this->free();

        return $this;
    }

    /**
     * 是否断线
     * @access protected
     * @param  \PDOException|\Exception  $e 异常对象
     * @return bool
     */
    protected function isBreak($e): bool
    {
        if (!$this->config['break_reconnect']) {
            return false;
        }

        $error = $e->getMessage();

        foreach ($this->breakMatchStr as $msg) {
            if (false !== stripos($error, $msg)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql(): string
    {
        return $this->getRealSql($this->queryStr, $this->bind);
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @param  string  $sequence     自增序列名
     * @return string
     */
    public function getLastInsID(string $sequence = null): string
    {
        return $this->linkID->lastInsertId($sequence);
    }

    /**
     * 获取返回或者影响的记录数
     * @access public
     * @return integer
     */
    public function getNumRows(): int
    {
        return $this->numRows;
    }

    /**
     * 获取最近的错误信息
     * @access public
     * @return string
     */
    public function getError(): string
    {
        if ($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            $error = $error[1] . ':' . $error[2];
        } else {
            $error = '';
        }

        if ('' != $this->queryStr) {
            $error .= "\n [ SQL语句 ] : " . $this->getLastsql();
        }

        return $error;
    }

    /**
     * 数据库调试 记录当前SQL及分析性能
     * @access protected
     * @param  boolean $start 调试开始标记 true 开始 false 结束
     * @param  string  $sql 执行的SQL语句 留空自动获取
     * @param  bool    $master 主从标记
     * @return void
     */
    protected function debug(bool $start, string $sql = '', bool $master = false): void
    {
        if (!empty($this->config['debug'])) {
            // 开启数据库调试模式
            $debug = Container::pull('debug');

            if ($start) {
                $debug->remark('queryStartTime', 'time');
            } else {
                // 记录操作结束时间
                $debug->remark('queryEndTime', 'time');
                $runtime = $debug->getRangeTime('queryStartTime', 'queryEndTime');
                $sql     = $sql ?: $this->getLastsql();
                $result  = [];

                // SQL性能分析
                if ($this->config['sql_explain'] && 0 === stripos(trim($sql), 'select')) {
                    $result = $this->getExplain($sql);
                }

                // SQL监听
                $this->triggerSql($sql, $runtime, $result, $master);
            }
        }
    }

    /**
     * 监听SQL执行
     * @access public
     * @param  callable $callback 回调方法
     * @return void
     */
    public function listen(callable $callback): void
    {
        self::$event[] = $callback;
    }

    /**
     * 触发SQL事件
     * @access protected
     * @param  string    $sql SQL语句
     * @param  string    $runtime SQL运行时间
     * @param  mixed     $explain SQL分析
     * @param  bool      $master 主从标记
     * @return void
     */
    protected function triggerSql(string $sql, string $runtime, array $explain = [], bool $master = false): void
    {
        if (!empty(self::$event)) {
            foreach (self::$event as $callback) {
                if (is_callable($callback)) {
                    call_user_func_array($callback, [$sql, $runtime, $explain, $master]);
                }
            }
        } else {
            if ($this->config['deploy']) {
                // 分布式记录当前操作的主从
                $master = $master ? 'master|' : 'slave|';
            } else {
                $master = '';
            }

            // 未注册监听则记录到日志中
            $this->log('[ SQL ] ' . $sql . ' [ ' . $master . 'RunTime:' . $runtime . 's ]');

            if (!empty($explain)) {
                $this->log('[ EXPLAIN : ' . var_export($explain, true) . ' ]');
            }
        }
    }

    public function log($log, string $type = 'sql'): void
    {
        $this->config['debug'] && Container::pull('log')->record($log, $type);
    }

    /**
     * 初始化数据库连接
     * @access protected
     * @param  boolean $master 是否主服务器
     * @return void
     */
    protected function initConnect(bool $master = true): void
    {
        if (!empty($this->config['deploy'])) {
            // 采用分布式数据库
            if ($master || $this->transTimes) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }

                $this->linkID = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }

                $this->linkID = $this->linkRead;
            }
        } elseif (!$this->linkID) {
            // 默认单数据库
            $this->linkID = $this->connect();
        }
    }

    /**
     * 连接分布式服务器
     * @access protected
     * @param  boolean $master 主服务器
     * @return PDO
     */
    protected function multiConnect(bool $master = false): PDO
    {
        $_config = [];

        // 分布式数据库配置解析
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $_config[$name] = is_string($this->config[$name]) ? explode(',', $this->config[$name]) : $this->config[$name];
        }

        // 主服务器序号
        $m = floor(mt_rand(0, $this->config['master_num'] - 1));

        if ($this->config['rw_separate']) {
            // 主从式采用读写分离
            if ($master) // 主服务器写入
            {
                $r = $m;
            } elseif (is_numeric($this->config['slave_no'])) {
                // 指定服务器读
                $r = $this->config['slave_no'];
            } else {
                // 读操作连接从服务器 每次随机连接的数据库
                $r = floor(mt_rand($this->config['master_num'], count($_config['hostname']) - 1));
            }
        } else {
            // 读写操作不区分服务器 每次随机连接的数据库
            $r = floor(mt_rand(0, count($_config['hostname']) - 1));
        }
        $dbMaster = false;

        if ($m != $r) {
            $dbMaster = [];
            foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
                $dbMaster[$name] = $_config[$name][$m] ?? $_config[$name][0];
            }
        }

        $dbConfig = [];

        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $dbConfig[$name] = $_config[$name][$r] ?? $_config[$name][0];
        }

        return $this->connect($dbConfig, $r, $r == $m ? false : $dbMaster);
    }

    /**
     * 析构方法
     * @access public
     */
    public function __destruct()
    {
        // 释放查询
        $this->free();

        // 关闭连接
        $this->close();
    }

    /**
     * 缓存数据
     * @access protected
     * @param  CacheItem    $cacheItem   缓存Item
     */
    protected function cacheData(CacheItem $cacheItem): void
    {
        if ($cacheItem->getTag()) {
            $this->cache->tag($cacheItem->getTag());
        }

        $this->cache->set($cacheItem->getKey(), $cacheItem->get(), $cacheItem->getExpire());

    }

    /**
     * 获取缓存数据
     * @access protected
     * @param  Query     $query   查询对象
     * @param  mixed     $cache   缓存设置
     * @param  array     $data    缓存数据
     * @param  string    $key     缓存Key
     * @return mixed
     */
    protected function getCacheData(CacheItem $cacheItem)
    {
        // 判断查询缓存
        return $this->cache->get($cacheItem->getKey());
    }

    /**
     * 生成缓存标识
     * @access protected
     * @param  Query     $query   查询对象
     * @param  mixed     $value   缓存数据
     * @return string
     */
    protected function getCacheKey(Query $query, $value): string
    {
        if (is_scalar($value)) {
            $data = $value;
        } elseif (is_array($value) && isset($value[1], $value[2]) && '=' == $value[1]) {
            $data = $value[2];
        }

        $prefix = 'think:' . $this->getConfig('database') . '.';

        if (isset($data)) {
            return $prefix . $query->getTable() . '|' . $data;
        }

        try {
            return md5($prefix . serialize($query->getOptions()) . serialize($query->getBind(false)));
        } catch (\Exception $e) {
            throw new Exception('closure not support cache(true)');
        }
    }

}
