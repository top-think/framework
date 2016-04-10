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

namespace think\db;

use PDO;
use think\Cache;
use think\Config;
use think\Db;
use think\Debug;
use think\Exception;
use think\exception\DbBindParamException;
use think\exception\PDOException;
use think\Loader;
use think\Log;

abstract class Driver
{
    // PDO操作实例
    protected $PDOStatement = null;
    // 当前操作的数据表名
    protected $table = '';
    // 当前操作的数据对象名
    protected $name = '';
    // 当前SQL指令
    protected $queryStr = '';
    // 最后插入ID
    protected $lastInsID = null;
    // 返回或者影响记录数
    protected $numRows = 0;
    // 事务指令数
    protected $transTimes = 0;
    // 错误信息
    protected $error = '';
    // 数据库连接ID 支持多个连接
    protected $links = [];
    // 当前连接ID
    protected $linkID = null;
    // 查询参数
    protected $options = [];
    // 监听回调
    protected static $event = [];

    // 数据库连接参数配置
    protected $config = [
        // 数据库类型
        'type'          => '',
        // 服务器地址
        'hostname'      => '',
        // 数据库名
        'database'      => '',
        // 用户名
        'username'      => '',
        // 密码
        'password'      => '',
        // 端口
        'hostport'      => '',
        'dsn'           => '',
        // 数据库连接参数
        'params'        => [],
        // 数据库编码默认采用utf8
        'charset'       => 'utf8',
        // 数据库表前缀
        'prefix'        => '',
        // 数据库调试模式
        'debug'         => false,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'        => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'   => false,
        // 读写分离后 主服务器数量
        'master_num'    => 1,
        // 指定从服务器序号
        'slave_no'      => '',
        // like字段自动替换为%%包裹
        'like_fields'   => '',
        // 是否严格检查字段是否存在
        'fields_strict' => true,
    ];
    // 数据库表达式
    protected $exp = ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'egt' => '>=', 'lt' => '<', 'elt' => '<=', 'notlike' => 'NOT LIKE', 'like' => 'LIKE', 'in' => 'IN', 'exp' => 'EXP', 'notin' => 'NOT IN', 'not in' => 'NOT IN', 'between' => 'BETWEEN', 'not between' => 'NOT BETWEEN', 'notbetween' => 'NOT BETWEEN', 'exists' => 'EXISTS', 'notexists' => 'NOT EXISTS', 'not exists' => 'NOT EXISTS', 'null' => 'NULL', 'notnull' => 'NOT NULL', 'not null' => 'NOT NULL'];
    // 查询表达式
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%LOCK%%COMMENT%';

    // PDO连接参数
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
    // 参数绑定
    protected $bind = [];

    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct($config = '')
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
            if (is_array($this->config['params'])) {
                $this->params = $this->config['params'] + $this->params;
            }
        }
    }

    /**
     * 利用__call方法实现一些特殊的Model方法
     * @access public
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (strtolower(substr($method, 0, 5)) == 'getby') {
            // 根据某个字段获取记录
            $field         = Loader::parseName(substr($method, 5));
            $where[$field] = $args[0];
            return $this->where($where)->find();
        } elseif (strtolower(substr($method, 0, 10)) == 'getfieldby') {
            // 根据某个字段获取记录的某个值
            $name         = Loader::parseName(substr($method, 10));
            $where[$name] = $args[0];
            return $this->where($where)->value($args[1]);
        } else {
            throw new Exception(__CLASS__ . ':' . $method . ' method not exist');
        }
    }

    /**
     * 指定当前数据表
     * @access public
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * 连接数据库方法
     * @access public
     */
    public function connect($config = '', $linkNum = 0, $autoConnection = false)
    {
        if (!isset($this->links[$linkNum])) {
            if (empty($config)) {
                $config = $this->config;
            }

            try {
                if (empty($config['dsn'])) {
                    $config['dsn'] = $this->parseDsn($config);
                }
                $this->links[$linkNum] = new PDO($config['dsn'], $config['username'], $config['password'], $this->params);
                // 记录数据库连接信息
                APP_DEBUG && Log::record('[ DB ] CONNECT: ' . $config['dsn'], 'info');
            } catch (\PDOException $e) {
                if ($autoConnection) {
                    Log::record($e->getMessage(), 'error');
                    return $this->connect($autoConnection, $linkNum);
                } else {
                    throw new Exception($e->getMessage());
                }
            }
        }
        return $this->links[$linkNum];
    }

    /**
     * 解析pdo连接的dsn信息
     * @access public
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {}

    /**
     * 释放查询结果
     * @access public
     */
    public function free()
    {
        $this->PDOStatement = null;
    }

    /**
     * 获取PDO对象
     * @access public
     */
    public function getPdo()
    {
        if (!$this->linkID) {
            return false;
        } else {
            return $this->linkID;
        }
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string $sql  sql指令
     * @param array $bind 参数绑定
     * @param boolean $fetch  不执行只是获取SQL
     * @param boolean $master  是否在主服务器读操作
     * @param bool $returnPdo  是否返回 PDOStatement 对象
     * @return mixed
     */
    public function query($sql, $bind = [], $fetch = false, $master = false, $returnPdo = false)
    {
        $this->initConnect($master);
        if (!$this->linkID) {
            return false;
        }

        // 根据参数绑定组装最终的SQL语句
        $this->queryStr = $this->getBindSql($sql, $bind);

        if ($fetch) {
            return $this->queryStr;
        }
        //释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }

        Db::$queryTimes++;
        try {
            // 调试开始
            $this->debug(true);
            // 预处理
            $this->PDOStatement = $this->linkID->prepare($sql);
            // 参数绑定
            $this->bindValue($bind);
            // 执行查询
            $result = $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);
            return $returnPdo ? $this->PDOStatement : $this->getResult();
        } catch (\PDOException $e) {
            throw new PDOException($e, $this->config, $this->queryStr);
        }
    }

    /**
     * 执行语句
     * @access public
     * @param string $sql  sql指令
     * @param array $bind 参数绑定
     * @param boolean $fetch  不执行只是获取SQL
     * @return integer
     */
    public function execute($sql, $bind = [], $fetch = false)
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }
        // 根据参数绑定组装最终的SQL语句
        $this->queryStr = $this->getBindSql($sql, $bind);

        if ($fetch) {
            return $this->queryStr;
        }
        //释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }

        Db::$executeTimes++;
        try {
            // 调试开始
            $this->debug(true);
            // 预处理
            $this->PDOStatement = $this->linkID->prepare($sql);
            // 参数绑定操作
            $this->bindValue($bind);
            // 执行语句
            $result = $this->PDOStatement->execute();
            // 调试结束
            $this->debug(false);

            $this->numRows = $this->PDOStatement->rowCount();
            if (preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $sql)) {
                $this->lastInsID = $this->linkID->lastInsertId();
            }
            return $this->numRows;
        } catch (\PDOException $e) {
            throw new PDOException($e, $this->config, $this->queryStr);
        }
    }

    /**
     * 组装最终的SQL语句 便于调试
     * @access public
     * @param string $sql 带参数绑定的sql语句
     * @param array $bind 参数绑定列表
     * @return string
     */
    protected function getBindSql($sql, array $bind = [])
    {
        if ($bind) {
            foreach ($bind as $key => $val) {
                $val = $this->parseValue(is_array($val) ? $val[0] : $val);
                // 判断占位符
                $sql = is_numeric($key) ?
                substr_replace($sql, $val, strpos($sql, '?'), 1) :
                str_replace([':' . $key . ')', ':' . $key . ' '], [$val . ')', $val . ' '], $sql . ' ');
            }
        }
        return $sql;
    }

    /**
     * 参数绑定
     * 支持 ['name'=>'value','id'=>123] 对应命名占位符
     * 或者 ['value',123] 对应问号占位符
     * @access public
     * @param array $bind 要绑定的参数列表
     * @return void
     * @throws \think\Exception
     */
    protected function bindValue(array $bind = [])
    {
        foreach ($bind as $key => $val) {
            // 占位符
            $param = is_numeric($key) ? $key + 1 : ':' . $key;
            if (is_array($val)) {
                $result = $this->PDOStatement->bindValue($param, $val[0], $val[1]);
            } else {
                $result = $this->PDOStatement->bindValue($param, $val);
            }
            if (!$result) {
                throw new DbBindParamException(
                    "Error occurred  when binding parameters '{$param}'",
                    $this->config,
                    $this->queryStr,
                    $bind
                );
            }
        }
    }

    /**
     * 获得数据集
     * @access private
     * @return array
     */
    private function getResult()
    {
        $result        = $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->numRows = count($result);
        return $result;
    }

    /**
     * 执行数据库事务
     * @access public
     * @param callable $callback 数据操作方法回调
     * @return void
     */
    public function transaction($callback)
    {
        $this->startTrans();
        try {
            if (is_callable($callback)) {
                call_user_func_array($callback, []);
            }
            $this->commit();
        } catch (\PDOException $e) {
            $this->rollback();
        }
    }

    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans()
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }

        //数据rollback 支持
        if (0 == $this->transTimes) {
            $this->linkID->beginTransaction();
        }
        $this->transTimes++;
        return;
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @return boolen
     */
    public function commit()
    {
        if ($this->transTimes > 0) {
            try {
                $this->linkID->commit();
                $this->transTimes = 0;
            } catch (\PDOException $e) {
                throw new PDOException($e, $this->config, $this->queryStr);
            }
        }
        return true;
    }

    /**
     * 事务回滚
     * @access public
     * @return boolen
     */
    public function rollback()
    {
        if ($this->transTimes > 0) {
            try {
                $this->linkID->rollback();
                $this->transTimes = 0;
            } catch (\PDOException $e) {
                throw new PDOException($e, $this->config, $this->queryStr);
            }
        }
        return true;
    }

    /**
     * 批处理执行SQL语句
     * 批处理的指令都认为是execute操作
     * @access public
     * @param array $sql  SQL批处理指令
     * @return boolean
     */
    public function batchQuery($sql = [])
    {
        if (!is_array($sql)) {
            return false;
        }
        // 自动启动事务支持
        $this->startTrans();
        try {
            foreach ($sql as $_sql) {
                $result = $this->execute($_sql);
            }
            // 提交事务
            $this->commit();
        } catch (\PDOException $e) {
            $this->rollback();
            return false;
        }
        return true;
    }

    /**
     * 将SQL语句中的__TABLE_NAME__字符串替换成带前缀的表名（小写）
     * @access protected
     * @param string $sql sql语句
     * @return string
     */
    protected function parseSqlTable($sql)
    {
        if (false !== strpos($sql, '__')) {
            $prefix = $this->tablePrefix;
            $sql    = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $sql);
        }
        return $sql;
    }

    /**
     * 查询SQL组装 join
     * @access public
     * @param mixed $join 关联的表名
     * @param mixed $condition 条件
     * @param string $type JOIN类型
     * @return Model
     */
    public function join($join, $condition = null, $type = 'INNER')
    {
        if (empty($condition)) {
            if (is_array($join) && is_array($join[0])) {
                // 如果为组数，则循环调用join
                foreach ($join as $key => $value) {
                    if (is_array($value) && 2 <= count($value)) {
                        $this->join($value[0], $value[1], isset($value[2]) ? $value[2] : $type);
                    }
                }
            }
        } else {
            $prefix = $this->config['prefix'];
            // 传入的表名为数组
            if (is_array($join)) {
                if (0 !== $key = key($join)) {
                    // 设置了键名则键名为表名，键值作为表的别名
                    $table = $key . ' ' . array_shift($join);
                } else {
                    $table = array_shift($join);
                }
                if (count($join)) {
                    // 有设置第二个元素则把第二元素作为表前缀
                    $table = (string) current($join) . $table;
                } else {
                    // 加上默认的表前缀
                    $table = $prefix . $table;
                }
            } else {
                $join = trim($join);
                if (0 === strpos($join, '__')) {
                    $table = $this->parseSqlTable($join);
                } elseif (false === strpos($join, '(') && !empty($prefix) && 0 !== strpos($join, $prefix)) {
                    // 传入的表名中不带有'('并且不以默认的表前缀开头时加上默认的表前缀
                    $table = $prefix . $join;
                } else {
                    $table = $join;
                }
            }
            if (is_array($condition)) {
                $condition = implode(' AND ', $condition);
            }
            $this->options['join'][] = strtoupper($type) . ' JOIN ' . $table . ' ON ' . $condition;
        }
        return $this;
    }

    /**
     * 查询SQL组装 union
     * @access public
     * @param mixed $union
     * @param boolean $all
     * @return Model
     */
    public function union($union, $all = false)
    {
        $this->options['union']['type'] = $all ? 'UNION ALL' : 'UNION';

        if (is_array($union)) {
            $this->options['union'] = array_merge($this->options['union'], $union);
        } else {
            $this->options['union'][] = $union;
        }
        return $this;
    }

    /**
     * 获取数据表信息
     * @access public
     * @param string $fetch 获取信息类型 包括 fields type bind pk
     * @param string $tableName  数据表名 留空自动获取
     * @return mixed
     */
    public function getTableInfo($tableName = '', $fetch = '')
    {
        static $_info = [];
        if (!$tableName) {
            $tableName = isset($this->options['table']) ? $this->options['table'] : $this->getTableName();
        }
        if (is_array($tableName)) {
            $tableName = key($tableName) ?: current($tableName);
        }
        if (strpos($tableName, ',')) {
            // 多表不获取字段信息
            return false;
        }
        $guid = md5($tableName);
        if (!isset($_info[$guid])) {
            $info = $this->getFields($tableName);
            // 字段大小写转换
            switch ($this->params[PDO::ATTR_CASE]) {
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

            $fields = array_keys($info);
            $bind   = $type   = [];
            foreach ($info as $key => $val) {
                // 记录字段类型
                $type[$key] = $val['type'];
                if (preg_match('/(int|double|float|decimal|real|numeric|serial)/is', $val['type'])) {
                    $bind[$key] = PDO::PARAM_INT;
                } elseif (preg_match('/bool/is', $val['type'])) {
                    $bind[$key] = PDO::PARAM_BOOL;
                } else {
                    $bind[$key] = PDO::PARAM_STR;
                }
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
            $result       = ['fields' => $fields, 'type' => $type, 'bind' => $bind, 'pk' => $pk];
            $_info[$guid] = $result;
        }
        return $fetch ? $_info[$guid][$fetch] : $_info[$guid];
    }

    /**
     * 指定查询字段 支持字段排除
     * @access public
     * @param mixed $field
     * @param boolean $except 是否排除
     * @return Model
     */
    public function field($field, $except = false)
    {
        if (true === $field) {
            // 获取全部字段
            $fields = $this->getTableInfo('', 'fields');
            $field  = $fields ?: '*';
        } elseif ($except) {
            // 字段排除
            if (is_string($field)) {
                $field = explode(',', $field);
            }
            $fields = $this->getTableInfo('', 'fields');
            $field  = $fields ? array_diff($fields, $field) : $field;
        }
        $this->options['field'] = $field;
        return $this;
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return Db
     */
    public function where($field, $op = null, $condition = null)
    {
        if ($field instanceof Query) {
            // 使用查询对象
            $this->options['where'] = $field;
            return $this;
        }

        $where = $this->parseWhereExp($field, $op, $condition);

        if (!isset($this->options['where']['AND'])) {
            $this->options['where']['AND'] = [];
        }
        $this->options['where']['AND'] = array_merge($this->options['where']['AND'], $where);
        return $this;
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return Db
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        $where = $this->parseWhereExp($field, $op, $condition);
        if (!isset($this->options['where']['OR'])) {
            $this->options['where']['OR'] = [];
        }
        $this->options['where']['OR'] = array_merge($this->options['where']['OR'], $where);
        return $this;
    }

    /**
     * 分析查询表达式
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return Db
     */
    protected function parseWhereExp($field, $op, $condition)
    {
        if ($field instanceof \Closure) {
            $where[] = $field;
        } elseif (is_null($op) && is_null($condition)) {
            if (is_array($field)) {
                // 数组批量查询
                $where = $field;
            } else {
                // 字符串查询
                $where[] = ['exp', $field];
            }
        } elseif (is_array($op)) {
            $param = func_get_args();
            array_shift($param);
            $where[$field] = $param;
        } elseif (in_array(strtolower($op), ['null', 'notnull', 'not null'])) {
            // null查询
            $where[$field] = [$op, ''];
        } elseif (is_null($condition)) {
            // 字段相等查询
            $where[$field] = ['eq', $op];
        } else {
            $where[$field] = [$op, $condition];
        }
        return $where;
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $where 条件表达式
     * @return Db
     */
    public function whereExist($where)
    {
        $this->options['where']['AND'][] = ['EXISTS', $where];
        return $this;
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $where 条件表达式
     * @return Model
     */
    public function whereOrExist($where)
    {
        $this->options['where']['OR'][] = ['EXISTS', $where];
        return $this;
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $where 条件表达式
     * @return Model
     */
    public function whereNotExist($where)
    {
        $this->options['where']['AND'][] = ['NOT EXISTS', $where];
        return $this;
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $where 条件表达式
     * @return Model
     */
    public function whereOrNotExist($where)
    {
        $this->options['where']['OR'][] = ['NOT EXISTS', $where];
        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return Model
     */
    public function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) {
            list($offset, $length) = explode(',', $offset);
        }
        $this->options['limit'] = intval($offset) . ($length ? ',' . intval($length) : '');
        return $this;
    }

    /**
     * 指定分页
     * @access public
     * @param mixed $page 页数
     * @param mixed $listRows 每页数量
     * @return Model
     */
    public function page($page, $listRows = null)
    {
        if (is_null($listRows) && strpos($page, ',')) {
            list($page, $listRows) = explode(',', $page);
        }
        $this->options['page'] = [intval($page), intval($listRows)];
        return $this;
    }

    /**
     * 指定数据表
     * @access public
     * @param string $table 表名
     * @return Model
     */
    public function table($table)
    {
        if (is_array($table)) {
            $this->options['table'] = $table;
        } elseif (!empty($table)) {
            $this->options['table'] = $this->parseSqlTable($table);
        }
        return $this;
    }

    /**
     * USING支持 用于多表删除
     * @access public
     * @param mixed $using
     * @return Model
     */
    public function using($using)
    {
        if (is_array($using)) {
            $this->options['using'] = $using;
        } elseif (!empty($using)) {
            $this->options['using'] = $this->parseSqlTable($using);
        }
        return $this;
    }

    /**
     * 指定排序 order('id','desc') 或者 order(['id'=>'desc','create_time'=>'desc'])
     * @access public
     * @param string|array $field 排序字段
     * @param string $order 排序
     * @return Model
     */
    public function order($field, $order = null)
    {
        if (!empty($field)) {
            if (is_string($field)) {
                $field = empty($order) ? $field : [$field => $order];
            }
            $this->options['order'] = $field;
        }
        return $this;
    }

    /**
     * 指定group查询
     * @access public
     * @param string $group GROUP
     * @return Model
     */
    public function group($group)
    {
        $this->options['group'] = $group;
        return $this;
    }

    /**
     * 指定having查询
     * @access public
     * @param string $having having
     * @return Model
     */
    public function having($having)
    {
        $this->options['having'] = $having;
        return $this;
    }

    /**
     * 指定查询lock
     * @access public
     * @param boolean $lock 是否lock
     * @return Model
     */
    public function lock($lock = false)
    {
        $this->options['lock'] = $lock;
        return $this;
    }

    /**
     * 指定distinct查询
     * @access public
     * @param string $distinct 是否唯一
     * @return Model
     */
    public function distinct($distinct)
    {
        $this->options['distinct'] = $distinct;
        return $this;
    }

    /**
     * 指定数据表别名
     * @access public
     * @param string $alias 数据表别名
     * @return Model
     */
    public function alias($alias)
    {
        $this->options['alias'] = $alias;
        return $this;
    }

    /**
     * 指定强制索引
     * @access public
     * @param string $force 索引名称
     * @return Model
     */
    public function force($force)
    {
        $this->options['force'] = $force;
        return $this;
    }

    /**
     * 查询注释
     * @access public
     * @param string $comment 注释
     * @return Model
     */
    public function comment($comment)
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    /**
     * 获取执行的SQL语句
     * @access public
     * @param boolean $fetch 是否返回sql
     * @return Model
     */
    public function fetchSql($fetch = true)
    {
        $this->options['fetch_sql'] = $fetch;
        return $this;
    }

    /**
     * 不主动获取数据集
     * @access public
     * @param bool $pdo 是否返回 PDOStatement 对象
     * @return Model
     */
    public function fetchPdo($pdo = true)
    {
        $this->options['fetch_pdo'] = $pdo;
        return $this;
    }

    /**
     * 设置从主服务器读取数据
     * @access public
     * @return Model
     */
    public function master()
    {
        $this->options['master'] = true;
        return $this;
    }

    /**
     * 指定当前模型
     * @access public
     * @param string $model  模型类名称
     * @return object
     */
    public function model($model)
    {
        $this->options['model'] = $model;
        return $this;
    }

    /**
     * 参数绑定
     * @access public
     * @param mixed $key  参数名
     * @param mixed $value  绑定变量值
     * @param integer $type 绑定类型
     * @return Model
     */
    public function bind($key, $value = false, $type = PDO::PARAM_STR)
    {
        if (is_array($key)) {
            $this->bind = array_merge($this->bind, $key);
        } else {
            $this->bind[$key] = [$value, $type];
        }
        return $this;
    }

    /**
     * 调用命名范围
     * @access public
     * @param Closure $scope 命名范围 闭包定义
     * @param mixed $args 参数
     * @return Db
     */
    public function scope($scope = '', $args = null)
    {
        if ($scope instanceof \Closure) {
            call_user_func_array($scope, [ & $this, $args]);
        }
        return $this;
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param string $field  字段名
     * @return mixed
     */
    public function value($field)
    {
        // 返回数据个数
        $pdo = $this->field($field)->fetchPdo(true)->find();
        return $pdo->fetchColumn();
    }

    /**
     * 得到某个列的数组
     * @access public
     * @param string $field  字段名 多个字段用逗号分隔
     * @param string $key  索引
     * @return array
     */
    public function column($field, $key = '')
    {
        $key = $key ? $key . ',' : '';
        $pdo = $this->field($key . $field)->fetchPdo(true)->select();
        if (1 == $pdo->columnCount()) {
            return $pdo->fetchAll(PDO::FETCH_COLUMN);
        }
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $fields = array_keys($result[0]);
        $count  = count($fields);
        $key1   = array_shift($fields);
        $key2   = $fields ? array_shift($fields) : '';
        foreach ($result as $val) {
            if ($count > 2) {
                $array[$val[$key1]] = $val;
            } elseif (2 == $count) {
                $array[$val[$key1]] = $val[$key2];
            }
        }
        return $array;
    }

    /**
     * COUNT查询
     * @access public
     * @param string $field  字段名
     * @return integer
     */
    public function count($field = '*')
    {
        return $this->value('COUNT(' . $field . ') AS tp_count');
    }

    /**
     * SUM查询
     * @access public
     * @param string $field  字段名
     * @return integer
     */
    public function sum($field = '*')
    {
        return $this->value('SUM(' . $field . ') AS tp_sum');
    }

    /**
     * MIN查询
     * @access public
     * @param string $field  字段名
     * @return integer
     */
    public function min($field = '*')
    {
        return $this->value('MIN(' . $field . ') AS tp_min');
    }

    /**
     * MAX查询
     * @access public
     * @param string $field  字段名
     * @return integer
     */
    public function max($field = '*')
    {
        return $this->value('MAX(' . $field . ') AS tp_max');
    }

    /**
     * AVG查询
     * @access public
     * @param string $field  字段名
     * @return integer
     */
    public function avg($field = '*')
    {
        return $this->value('AVG(' . $field . ') AS tp_avg');
    }

    /**
     * 设置记录的某个字段值
     * 支持使用数据库字段和方法
     * @access public
     * @param string|array $field  字段名
     * @param string $value  字段值
     * @return boolean
     */
    public function setField($field, $value = '')
    {
        if (is_array($field)) {
            $data = $field;
        } else {
            $data[$field] = $value;
        }
        return $this->save($data);
    }

    /**
     * 字段值(延迟)增长
     * @access public
     * @param string $field  字段名
     * @param integer $step  增长值
     * @param integer $lazyTime  延时时间(s)
     * @return boolean
     * @throws \think\Exception
     */
    public function setInc($field, $step = 1, $lazyTime = 0)
    {
        $condition = !empty($this->options['where']) ? $this->options['where'] : [];
        if (empty($condition)) {
            // 没有条件不做任何更新
            throw new Exception('no data to update');
        }
        if ($lazyTime > 0) {
            // 延迟写入
            $guid = md5($this->name . '_' . $field . '_' . serialize($condition));
            $step = $this->lazyWrite($guid, $step, $lazyTime);
            if (empty($step)) {
                return true; // 等待下次写入
            }
        }
        return $this->setField($field, ['exp', $field . '+' . $step]);
    }

    /**
     * 字段值（延迟）减少
     * @access public
     * @param string $field  字段名
     * @param integer $step  减少值
     * @param integer $lazyTime  延时时间(s)
     * @return boolean
     * @throws \think\Exception
     */
    public function setDec($field, $step = 1, $lazyTime = 0)
    {
        $condition = !empty($this->options['where']) ? $this->options['where'] : [];
        if (empty($condition)) {
            // 没有条件不做任何更新
            throw new Exception('no data to update');
        }
        if ($lazyTime > 0) {
            // 延迟写入
            $guid = md5($this->name . '_' . $field . '_' . serialize($condition));
            $step = $this->lazyWrite($guid, -$step, $lazyTime);
            if (empty($step)) {
                return true; // 等待下次写入
            }
        }
        return $this->setField($field, ['exp', $field . '-' . $step]);
    }

    /**
     * 延时更新检查 返回false表示需要延时
     * 否则返回实际写入的数值
     * @access public
     * @param string $guid  写入标识
     * @param integer $step  写入步进值
     * @param integer $lazyTime  延时时间(s)
     * @return false|integer
     */
    protected function lazyWrite($guid, $step, $lazyTime)
    {
        if (false !== ($value = Cache::get($guid))) {
            // 存在缓存写入数据
            if (NOW_TIME > Cache::get($guid . '_time') + $lazyTime) {
                // 延时更新时间到了，删除缓存数据 并实际写入数据库
                Cache::rm($guid);
                Cache::rm($guid . '_time');
                return $value + $step;
            } else {
                // 追加数据到缓存
                Cache::set($guid, $value + $step, 0);
                return false;
            }
        } else {
            // 没有缓存数据
            Cache::set($guid, $step, 0);
            // 计时开始
            Cache::set($guid . '_time', NOW_TIME, 0);
            return false;
        }
    }

    /**
     * 得到完整的数据表名
     * @access protected
     * @return string
     */
    protected function getTableName()
    {
        if (!$this->table) {
            $tableName = $this->config['prefix'];
            $tableName .= Loader::parseName($this->name);
        } else {
            $tableName = $this->table;
        }
        return $tableName;
    }

    /**
     * 设置当前name
     * @access public
     * @param string $name
     * @return Db
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 得到完整的数据表名
     * @access public
     * @param array $options 表达式参数
     * @return string
     */
    public function options(array $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 分析表达式（可用于查询或者写入操作）
     * @access protected
     * @param array $options 表达式参数
     * @return array
     */
    private function _parseOptions()
    {
        $options = $this->options;

        // 获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->getTableName();
        }

        // 获取字段信息
        $fields = $this->getTableInfo($options['table'], 'fields');

        // 字段类型检查
        if (isset($options['where']) && is_array($options['where']) && !empty($fields)) {
            // 对数组查询条件进行字段类型检查
            if (isset($options['where']['AND'])) {
                foreach ($options['where']['AND'] as $key => $val) {
                    $key = trim($key);
                    if (in_array($key, $fields, true) && is_scalar($val) && empty($this->bind[$key])) {
                        $this->parseTypeBind($options['where']['AND'], $key, $options['table']);
                    }
                }
            }
            if (isset($options['where']['OR'])) {
                foreach ($options['where']['OR'] as $key => $val) {
                    $key = trim($key);
                    if (in_array($key, $fields, true) && is_scalar($val) && empty($this->bind[$key])) {
                        $this->parseTypeBind($options['where']['OR'], $key, $options['table']);
                    }
                }
            }
        }

        // 表别名
        if (!empty($options['alias'])) {
            $options['table'] .= ' ' . $options['alias'];
        }

        // 查询过后清空sql表达式组装 避免影响下次查询
        $this->options = [];
        return $options;
    }

    /**
     * 数据字段自动类型绑定
     * @access protected
     * @param array $data 数据
     * @param string $key 字段名
     * @param string $tableName 表名
     * @return void
     */
    protected function parseTypeBind(&$data, $key, $tableName = '')
    {
        if (':' == substr($data[$key], 0, 1) && isset($this->bind[substr($data[$key], 1)])) {
            // 已经绑定 无需再次绑定 请确保bind方法优先执行
            return;
        }
        $binds            = $this->getTableInfo($tableName, 'bind');
        $this->bind[$key] = [$data[$key], isset($binds[$key]) ? $binds[$key] : PDO::PARAM_STR];
        $data[$key]       = ':' . $key;
    }

    /**
     * 获得查询次数
     * @access public
     * @param boolean $execute 是否包含所有查询
     * @return integer
     */
    public function getQueryTimes($execute = false)
    {
        return $execute ? Db::$queryTimes + Db::$executeTimes : Db::$queryTimes;
    }

    /**
     * 获得执行次数
     * @access public
     * @return integer
     */
    public function getExecuteTimes()
    {
        return Db::$executeTimes;
    }

    /**
     * 关闭数据库
     * @access public
     */
    public function close()
    {
        $this->linkID = null;
    }

    /**
     * 设置锁机制
     * @access protected
     * @return string
     */
    protected function parseLock($lock = false)
    {
        return $lock ? ' FOR UPDATE ' : '';
    }

    /**
     * 数据分析
     * @access protected
     * @param array $data 数据
     * @param array $bind 参数绑定类型
     * @param string $type insert update
     * @return array
     */
    protected function parseData($data, $bind)
    {
        if (empty($data)) {
            return [];
        }
        $fields = array_keys($bind);
        $result = [];
        foreach ($data as $key => $val) {
            if (!in_array($key, $fields, true)) {
                if ($this->config['fields_strict']) {
                    throw new Exception(' fields not exists :[' . $key . ']');
                }
            } else {
                $item = $this->parseKey($key);
                if (isset($val[0]) && 'exp' == $val[0]) {
                    $result[$item] = $val[1];
                } elseif (is_null($val)) {
                    $result[$item] = 'NULL';
                } elseif (is_scalar($val)) {
                    // 过滤非标量数据
                    $this->parseTypeBind($data, $key);
                    $result[$item] = $data[$key];
                }
            }
        }
        return $result;
    }

    /**
     * 字段名分析
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey($key)
    {
        return $key;
    }

    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return string
     */
    protected function parseValue($value)
    {
        if (is_string($value)) {
            $value = strpos($value, ':') === 0 && isset($this->bind[substr($value, 1)]) ? $value : $this->quote($value);
        } elseif (isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp') {
            $value = $this->quote($value[1]);
        } elseif (is_array($value)) {
            $value = array_map([$this, 'parseValue'], $value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $value = 'null';
        }
        return $value;
    }

    /**
     * field分析
     * @access protected
     * @param mixed $fields
     * @return string
     */
    protected function parseField($fields)
    {
        if (is_string($fields) && strpos($fields, ',')) {
            $fields = explode(',', $fields);
        }
        if (is_array($fields)) {
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = [];
            foreach ($fields as $key => $field) {
                if (!is_numeric($key)) {
                    $array[] = $this->parseKey($key) . ' AS ' . $this->parseKey($field);
                } else {
                    $array[] = $this->parseKey($field);
                }
            }
            $fieldsStr = implode(',', $array);
        } elseif (is_string($fields) && !empty($fields)) {
            $fieldsStr = $this->parseKey($fields);
        } else {
            $fieldsStr = '*';
        }
        //TODO 如果是查询全部字段，并且是join的方式，那么就把要查的表加个别名，以免字段被覆盖
        return $fieldsStr;
    }

    /**
     * table分析
     * @access protected
     * @param mixed $table
     * @return string
     */
    protected function parseTable($tables)
    {
        if (is_array($tables)) {
            // 支持别名定义
            foreach ($tables as $table => $alias) {
                $array[] = !is_numeric($table) ?
                $this->parseKey($table) . ' ' . $this->parseKey($alias) :
                $this->parseKey($alias);
            }
            $tables = $array;
        } elseif (is_string($tables)) {
            $tables = array_map([$this, 'parseKey'], explode(',', $tables));
        }
        return implode(',', $tables);
    }

    /**
     * where分析
     * @access protected
     * @param mixed $where
     * @return string
     */
    protected function parseWhere($where)
    {
        $whereStr = $this->buildWhere($where);
        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * 生成查询条件SQL
     * @access public
     * @param mixed $where
     * @return string
     */
    public function buildWhere($where = [])
    {
        if (empty($where) && isset($this->options['where'])) {
            $where = $this->options['where'];
        } elseif (empty($where)) {
            $where = [];
        }
        if ($where instanceof Query) {
            // 使用查询对象
            return $where->buildWhere();
        }
        $whereStr = '';
        foreach ($where as $key => $val) {
            $str = [];
            foreach ($val as $field => $value) {
                if ($value instanceof \Closure) {
                    // 使用闭包查询
                    $class = clone $this;
                    $class->options([]);
                    call_user_func_array($value, [ & $class]);
                    $str[] = ' ' . $key . ' ( ' . $class->buildWhere() . ' )';
                } else {
                    if (strpos($field, '|')) {
                        // 不同字段使用相同查询条件（OR）
                        $array = explode('|', $field);
                        $item  = [];
                        foreach ($array as $k) {
                            $item[] = $this->parseWhereItem($k, $value);
                        }
                        $str[] = ' ' . $key . ' ( ' . implode(' OR ', $item) . ' )';
                    } elseif (strpos($field, '&')) {
                        // 不同字段使用相同查询条件（AND）
                        $array = explode('&', $field);
                        $item  = [];
                        foreach ($array as $k) {
                            $item[] = $this->parseWhereItem($k, $value);
                        }
                        $str[] = ' ' . $key . ' ( ' . implode(' AND ', $item) . ' )';
                    } else {
                        // 对字段使用表达式查询
                        $field = is_string($field) ? $field : '';
                        $str[] = ' ' . $key . ' ' . $this->parseWhereItem($field, $value, $key);
                    }
                }
            }
            $whereStr .= empty($whereStr) ? substr(implode('', $str), strlen($key) + 1) : implode('', $str);
        }
        return $whereStr;
    }

    // where子单元分析
    protected function parseWhereItem($key, $val, $rule = '')
    {
        if ($key) {
            // 字段分析
            $key = $this->parseKey($key);
        }

        // 查询规则和条件
        if (!is_array($val)) {
            $val = ['=', $val];
        }
        list($exp, $value) = $val;

        // 对一个字段使用多个查询条件
        if (is_array($exp)) {
            foreach ($val as $item) {
                $str[] = $this->parseWhereItem($key, $item);
            }
            return '( ' . implode(' ' . $rule . ' ', $str) . ' )';
        }

        // 检测操作符
        if (!in_array($exp, $this->exp)) {
            $exp = strtolower($exp);
            if (isset($this->exp[$exp])) {
                $exp = $this->exp[$exp];
            } else {
                throw new Exception('where express error:' . $exp);
            }
        }

        $whereStr = '';
        if (in_array($exp, ['=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'])) {
            // 比较运算 及 模糊匹配
            $whereStr .= $key . ' ' . $exp . ' ' . $this->parseValue($value);
        } elseif ('EXP' == $exp) {
            // 表达式查询
            $whereStr .= $key . ' ' . $value;
        } elseif (in_array($exp, ['NOT NULL', 'NULL'])) {
            // NULL 查询
            $whereStr .= $key . ' IS ' . $exp;
        } elseif (in_array($exp, ['NOT IN', 'IN'])) {
            // IN 查询
            if ($value instanceof \Closure) {
                $whereStr .= $key . ' ' . $exp . ' ' . $this->parseClosure($value);
            } else {
                $value = is_string($value) ? explode(',', $value) : $value;
                $zone  = implode(',', $this->parseValue($value));
                $whereStr .= $key . ' ' . $exp . ' (' . $zone . ')';
            }
        } elseif (in_array($exp, ['NOT BETWEEN', 'BETWEEN'])) {
            // BETWEEN 查询
            $data = is_string($value) ? explode(',', $value) : $value;
            $whereStr .= $key . ' ' . $exp . ' ' . $this->parseValue($data[0]) . ' AND ' . $this->parseValue($data[1]);
        } elseif (in_array($exp, ['NOT EXISTS', 'EXISTS'])) {
            // EXISTS 查询
            $whereStr .= $exp . ' ' . $this->parseClosure($value);
        }
        return $whereStr;
    }

    // 执行闭包子查询
    protected function parseClosure($call, $show = true)
    {
        $class = clone $this;
        $class->options([]);
        call_user_func_array($call, [ & $class]);
        return $class->buildSql($show);
    }

    /**
     * limit分析
     * @access protected
     * @param mixed $lmit
     * @return string
     */
    protected function parseLimit($limit)
    {
        return (!empty($limit) && false === strpos($limit, '(')) ? ' LIMIT ' . $limit . ' ' : '';
    }

    /**
     * join分析
     * @access protected
     * @param mixed $join
     * @return string
     */
    protected function parseJoin($join)
    {
        $joinStr = '';
        if (!empty($join)) {
            $joinStr = ' ' . implode(' ', $join) . ' ';
        }
        return $joinStr;
    }

    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @return string
     */
    protected function parseOrder($order)
    {
        if (is_array($order)) {
            $array = [];
            foreach ($order as $key => $val) {
                if (is_numeric($key)) {
                    if (false === strpos($val, '(')) {
                        $array[] = $this->parseKey($val);
                    } elseif ('[rand]' == $val) {
                        $array[] = $this->parseRand();
                    }
                } else {
                    $sort    = in_array(strtolower(trim($val)), ['asc', 'desc']) ? ' ' . $val : '';
                    $array[] = $this->parseKey($key) . ' ' . $sort;
                }
            }
            $order = implode(',', $array);
        }
        return !empty($order) ? ' ORDER BY ' . $order : '';
    }

    /**
     * group分析
     * @access protected
     * @param mixed $group
     * @return string
     */
    protected function parseGroup($group)
    {
        return !empty($group) ? ' GROUP BY ' . $group : '';
    }

    /**
     * having分析
     * @access protected
     * @param string $having
     * @return string
     */
    protected function parseHaving($having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * comment分析
     * @access protected
     * @param string $comment
     * @return string
     */
    protected function parseComment($comment)
    {
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

    /**
     * distinct分析
     * @access protected
     * @param mixed $distinct
     * @return string
     */
    protected function parseDistinct($distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }

    /**
     * union分析
     * @access protected
     * @param mixed $union
     * @return string
     */
    protected function parseUnion($union)
    {
        if (empty($union)) {
            return '';
        }
        $type = $union['type'];
        unset($union['type']);
        foreach ($union as $u) {
            if ($u instanceof \Closure) {
                $sql[] = $type . ' ' . $this->parseClosure($u, false);
            } elseif (is_string($u)) {
                $sql[] = $type . ' ' . $this->parseSqlTable($u);
            }
        }
        return implode(' ', $sql);
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引
     * @access protected
     * @param mixed $index
     * @return string
     */
    protected function parseForce($index)
    {
        if (empty($index)) {
            return '';
        }

        if (is_array($index)) {
            $index = join(",", $index);
        }

        return sprintf(" FORCE INDEX ( %s ) ", $index);
    }

    /**
     * 获取参数绑定信息并清空
     * @access protected
     * @param bool $reset 获取后清空
     * @return array
     */
    protected function getBindParams()
    {
        $bind       = $this->bind;
        $this->bind = [];
        return $bind;
    }

    /**
     * 插入记录
     * @access public
     * @param mixed $data 数据
     * @param boolean $replace 是否replace
     * @return integer
     */
    public function insert(array $data, $replace = false)
    {
        $options = $this->_parseOptions();
        $bind    = $this->getTableInfo($options['table'], 'bind');

        $data = $this->parseData($data, $bind);
        if (empty($data)) {
            return 0;
        }
        $fields = array_keys($data);
        $values = array_values($data);
        // 兼容数字传入方式
        $sql = ($replace ? 'REPLACE' : 'INSERT') . ' INTO ' . $this->parseTable($options['table']) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        $result = $this->execute($sql, $this->getBindParams(), !empty($options['fetch_sql']) ? true : false);
        return $result;
    }

    /**
     * 批量插入记录
     * @access public
     * @param mixed $dataSet 数据集
     * @return integer
     */
    public function insertAll(array $dataSet)
    {
        $options = $this->_parseOptions();
        if (!is_array($dataSet[0])) {
            return false;
        }
        $bind   = $this->getTableInfo($options['table'], 'bind');
        $fields = array_map([$this, 'parseKey'], array_keys($dataSet[0]));
        foreach ($dataSet as $data) {
            //$data     = $this->parseData($data, $bind);
            $value    = array_values($data);
            $values[] = 'SELECT ' . implode(',', $value);
        }
        $sql = 'INSERT INTO ' . $this->parseTable($options['table']) . ' (' . implode(',', $fields) . ') ' . implode(' UNION ALL ', $values);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, $this->getBindParams(), !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 通过Select方式插入记录
     * @access public
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $option  查询数据参数
     * @return integer
     */
    public function selectInsert($fields, $table)
    {
        $options = $this->_parseOptions();
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        $fields = array_map([$this, 'parseKey'], $fields);
        $sql    = 'INSERT INTO ' . $this->parseTable($table) . ' (' . implode(',', $fields) . ') ';
        $sql .= $this->buildSelectSql($options);
        return $this->execute($sql, $this->getBindParams(), !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @return integer
     */
    public function update(array $data)
    {
        $options = $this->_parseOptions();
        if (empty($options['where'])) {
            $pk = $this->getTableInfo($options['table'], 'pk');
            // 如果存在主键数据 则自动作为更新条件
            if (is_string($pk) && isset($data[$pk])) {
                $where[$pk] = $data[$pk];
                unset($data[$pk]);
            } elseif (is_array($pk)) {
                // 增加复合主键支持
                foreach ($pk as $field) {
                    if (isset($data[$field])) {
                        $where[$field] = $data[$field];
                    } else {
                        // 如果缺少复合主键数据则不执行
                        throw new Exception('miss pk data');
                    }
                    unset($data[$field]);
                }
            }
            if (!isset($where)) {
                // 如果没有任何更新条件则不执行
                throw new Exception('miss update condition');
            } else {
                $options['where']['AND'] = $where;
            }
        }

        $bind  = $this->getTableInfo($options['table'], 'bind');
        $table = $this->parseTable($options['table']);
        $data  = $this->parseData($data, $bind);
        if (empty($data)) {
            return 0;
        }
        foreach ($data as $key => $val) {
            $set[] = $key . '=' . $val;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $set);
        if (strpos($table, ',')) {
            // 多表更新支持JOIN操作
            $sql .= $this->parseJoin(!empty($options['join']) ? $options['join'] : '');
        }
        $sql .= $this->parseWhere(!empty($options['where']) ? $options['where'] : '');
        if (!strpos($table, ',')) {
            //  单表更新支持order和lmit
            $sql .= $this->parseOrder(!empty($options['order']) ? $options['order'] : '')
            . $this->parseLimit(!empty($options['limit']) ? $options['limit'] : '');
        }
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, $this->getBindParams(), !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 删除记录
     * @access public
     * @param array $data 表达式
     * @return integer
     */
    public function delete($data = [])
    {
        if (!empty($data)) {
            // AR模式分析主键条件
            $this->parsePkWhere($data);
        }
        $options = $this->_parseOptions();

        if (empty($options['where'])) {
            // 如果条件为空 不进行删除操作 除非设置 1=1
            throw new Exception('no data to delete without where');
        }

        $table = $this->parseTable($options['table']);
        $sql   = 'DELETE FROM ' . $table;
        if (strpos($table, ',')) {
            // 多表删除支持USING和JOIN操作
            if (!empty($options['using'])) {
                $sql .= ' USING ' . $this->parseTable($options['using']) . ' ';
            }
            $sql .= $this->parseJoin(!empty($options['join']) ? $options['join'] : '');
        }
        $sql .= $this->parseWhere(!empty($options['where']) ? $options['where'] : '');
        if (!strpos($table, ',')) {
            // 单表删除支持order和limit
            $sql .= $this->parseOrder(!empty($options['order']) ? $options['order'] : '')
            . $this->parseLimit(!empty($options['limit']) ? $options['limit'] : '');
        }
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, $this->getBindParams(), !empty($options['fetch_sql']) ? true : false);
    }

    public function buildSql($sub = true)
    {
        return $sub ? '( ' . $this->select(false) . ' )' : $this->select(false);
    }

    /**
     * 查找记录
     * @access public
     * @param array $options 表达式
     * @return array|string
     */
    public function select($data = [])
    {
        if (false === $data) {
            // 用于子查询 不查询只返回SQL
            $this->options['fetch_sql'] = true;
        } elseif (!empty($data)) {
            // AR模式主键条件分析
            $this->parsePkWhere($data);
        }

        $options   = $this->_parseOptions();
        $sql       = $this->buildSelectSql($options);
        $resultSet = $this->query($sql, $this->getBindParams(), !empty($options['fetch_sql']) ? true : false, !empty($options['master']) ? true : false, isset($options['fetch_pdo']) ? $options['fetch_pdo'] : false);

        if (!empty($resultSet)) {
            if (is_string($resultSet)) {
                // 返回SQL
                return $resultSet;
            }
            if ($resultSet instanceof \PDOStatement) {
                // 返回PDOStatement对象
                return $resultSet;
            }

            // 数据列表读取后的处理
            if (!empty($options['model'])) {

                foreach ($resultSet as $key => $result) {
                    if (!empty($options['model'])) {
                        // 返回模型对象
                        $result = new $options['model']($result);
                        $result->isUpdate(true);
                        // 关联查询
                        if (!empty($options['relation'])) {
                            $result->relationQuery($options['relation']);
                        }
                    }
                    $resultSet[$key] = $result;
                }
                if (!empty($options['with'])) {
                    // 预载入
                    $result = new $options['model']();
                    return $result->eagerly($resultSet, $options['with']);
                }
            }
        }
        return $resultSet;
    }

    /**
     * 设置关联查询预载入
     * @access public
     * @param string $with 关联名称
     * @return Db
     */
    public function with($with)
    {
        $this->options['with'] = $with;
        return $this;
    }

    /**
     * 设置关联查询
     * @access public
     * @param string $relation 关联名称
     * @return Db
     */
    public function relation($relation)
    {
        $this->options['relation'] = $relation;
        return $this;
    }

    /**
     * 把主键值转换为查询条件 支持复合主键
     * @access public
     * @param mixed $options 表达式参数
     * @return void
     * @throws \think\Exception
     */
    protected function parsePkWhere($data)
    {
        $pk = $this->getTableInfo('', 'pk');
        if (is_string($pk)) {
            // 根据主键查询
            if (is_array($data)) {
                $where[$pk] = ['in', $data];
            } else {
                $where[$pk] = strpos($data, ',') ? ['IN', $data] : $data;
            }
            $this->options['where']['AND'] = $where;
        } elseif (is_array($pk) && is_array($data) && !empty($data)) {
            // 根据复合主键查询
            foreach ($pk as $key) {
                if (isset($data[$key])) {
                    $where[$key] = $data[$key];
                } else {
                    throw new Exception('miss complex primary data');
                }
            }
            $this->options['where']['AND'] = $where;
        }
        return;
    }

    /**
     * 查找单条记录
     * @access public
     * @param array $options 表达式
     * @return mixed
     */
    public function find($data = [])
    {
        if (!empty($data)) {
            // AR模式分析主键条件
            $this->parsePkWhere($data);
        }
        $options          = $this->_parseOptions();
        $options['limit'] = 1;
        $sql              = $this->buildSelectSql($options);
        $result           = $this->query($sql, $this->getBindParams(), !empty($options['fetch_sql']) ? true : false, !empty($options['master']) ? true : false, isset($options['fetch_pdo']) ? $options['fetch_pdo'] : false);

        // 数据处理
        if (!empty($result)) {
            if (is_string($result)) {
                // 返回SQL
                return $result;
            }

            if ($result instanceof \PDOStatement) {
                // 返回PDOStatement对象
                return $result;
            }

            $data = $result[0];
            if (!empty($options['model'])) {
                // 返回模型对象
                $data = new $options['model']($data);
                $data->isUpdate(true);
                // 关联查询
                if (!empty($options['relation'])) {
                    $data->relationQuery($options['relation']);
                }
            }
        } else {
            $data = false;
        }
        return $data;
    }

    /**
     * 生成查询SQL
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function buildSelectSql($options = [])
    {
        if (isset($options['page'])) {
            // 根据页数计算limit
            list($page, $listRows) = $options['page'];
            $page                  = $page > 0 ? $page : 1;
            $listRows              = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset                = $listRows * ($page - 1);
            $options['limit']      = $offset . ',' . $listRows;
        }
        $sql = $this->parseSql($this->selectSql, $options);
        return $sql;
    }

    /**
     * 替换SQL语句中表达式
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function parseSql($sql, $options = [])
    {
        $sql = str_replace(
            ['%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%', '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%'],
            [
                $this->parseTable($options['table']),
                $this->parseDistinct(isset($options['distinct']) ? $options['distinct'] : false),
                $this->parseField(!empty($options['field']) ? $options['field'] : '*'),
                $this->parseJoin(!empty($options['join']) ? $options['join'] : ''),
                $this->parseWhere(!empty($options['where']) ? $options['where'] : ''),
                $this->parseGroup(!empty($options['group']) ? $options['group'] : ''),
                $this->parseHaving(!empty($options['having']) ? $options['having'] : ''),
                $this->parseOrder(!empty($options['order']) ? $options['order'] : ''),
                $this->parseLimit(!empty($options['limit']) ? $options['limit'] : ''),
                $this->parseUnion(!empty($options['union']) ? $options['union'] : ''),
                $this->parseLock(isset($options['lock']) ? $options['lock'] : false),
                $this->parseComment(!empty($options['comment']) ? $options['comment'] : ''),
                $this->parseForce(!empty($options['force']) ? $options['force'] : ''),
            ], $sql);
        return $sql;
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql()
    {
        return $this->queryStr;
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID()
    {
        return $this->lastInsID;
    }

    /**
     * 获取最近的错误信息
     * @access public
     * @return string
     */
    public function getError()
    {
        if ($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            $error = $error[1] . ':' . $error[2];
        } else {
            $error = '';
        }
        if ('' != $this->queryStr) {
            $error .= "\n [ SQL语句 ] : " . $this->queryStr;
        }
        return $error;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function quote($str)
    {
        $this->initConnect();
        return $this->linkID ? $this->linkID->quote($str) : $str;
    }

    /**
     * 数据库调试 记录当前SQL
     * @access protected
     * @param boolean $start  调试开始标记 true 开始 false 结束
     */
    protected function debug($start)
    {
        if (!empty($this->config['debug'])) {
            // 开启数据库调试模式
            if ($start) {
                Debug::remark('queryStartTime', 'time');
            } else {
                // 记录操作结束时间
                Debug::remark('queryEndTime', 'time');
                $runtime = Debug::getRangeTime('queryStartTime', 'queryEndTime');
                $log     = $this->queryStr . ' [ RunTime:' . $runtime . 's ]';
                $result  = [];
                // SQL性能分析
                if (0 === stripos(trim($this->queryStr), 'select')) {
                    $result = $this->getExplain($this->queryStr);
                }
                // SQL监听
                $this->trigger($this->queryStr, $runtime, $result);
            }
        }
    }

    /**
     * 监听SQL执行
     * @access public
     * @param callable $callback 回调方法
     * @return void
     */
    public function listen($callback)
    {
        self::$event[] = $callback;
    }

    /**
     * 触发SQL事件
     * @access protected
     * @param string $sql SQL语句
     * @param float $runtime SQL运行时间
     * @param mixed $explain SQL分析
     * @return bool
     */
    protected function trigger($sql, $runtime, $explain = [])
    {
        if (!empty(self::$event)) {
            foreach (self::$event as $callback) {
                if (is_callable($callback)) {
                    call_user_func_array($callback, [$sql, $runtime, $explain]);
                }
            }
        } else {
            // 未注册监听则记录到日志中
            Log::record('[ SQL ] ' . $this->queryStr . ' [ RunTime:' . $runtime . 's ]', 'sql');
            if (!empty($explain)) {
                Log::record('[ EXPLAIN : ' . var_export($explain, true) . ' ]', 'sql');
            }
        }
    }

    /**
     * 初始化数据库连接
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    protected function initConnect($master = true)
    {
        if (!empty($this->config['deploy'])) {
            // 采用分布式数据库
            $this->linkID = $this->multiConnect($master);
        } elseif (!$this->linkID) {
            // 默认单数据库
            $this->linkID = $this->connect();
        }
    }

    /**
     * 连接分布式服务器
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    protected function multiConnect($master = false)
    {
        // 分布式数据库配置解析
        $_config['username'] = explode(',', $this->config['username']);
        $_config['password'] = explode(',', $this->config['password']);
        $_config['hostname'] = explode(',', $this->config['hostname']);
        $_config['hostport'] = explode(',', $this->config['hostport']);
        $_config['database'] = explode(',', $this->config['database']);
        $_config['dsn']      = explode(',', $this->config['dsn']);
        $_config['charset']  = explode(',', $this->config['charset']);

        $m = floor(mt_rand(0, $this->config['master_num'] - 1));
        // 数据库读写是否分离
        if ($this->config['rw_separate']) {
            // 主从式采用读写分离
            if ($master)
            // 主服务器写入
            {
                $r = $m;
            } else {
                if (is_numeric($this->config['slave_no'])) {
                    // 指定服务器读
                    $r = $this->config['slave_no'];
                } else {
                    // 读操作连接从服务器
                    $r = floor(mt_rand($this->config['master_num'], count($_config['hostname']) - 1)); // 每次随机连接的数据库
                }
            }
        } else {
            // 读写操作不区分服务器
            $r = floor(mt_rand(0, count($_config['hostname']) - 1)); // 每次随机连接的数据库
        }

        if ($m != $r) {
            $db_master = [
                'username' => isset($_config['username'][$m]) ? $_config['username'][$m] : $_config['username'][0],
                'password' => isset($_config['password'][$m]) ? $_config['password'][$m] : $_config['password'][0],
                'hostname' => isset($_config['hostname'][$m]) ? $_config['hostname'][$m] : $_config['hostname'][0],
                'hostport' => isset($_config['hostport'][$m]) ? $_config['hostport'][$m] : $_config['hostport'][0],
                'database' => isset($_config['database'][$m]) ? $_config['database'][$m] : $_config['database'][0],
                'dsn'      => isset($_config['dsn'][$m]) ? $_config['dsn'][$m] : $_config['dsn'][0],
                'charset'  => isset($_config['charset'][$m]) ? $_config['charset'][$m] : $_config['charset'][0],
            ];
        }
        $db_config = [
            'username' => isset($_config['username'][$r]) ? $_config['username'][$r] : $_config['username'][0],
            'password' => isset($_config['password'][$r]) ? $_config['password'][$r] : $_config['password'][0],
            'hostname' => isset($_config['hostname'][$r]) ? $_config['hostname'][$r] : $_config['hostname'][0],
            'hostport' => isset($_config['hostport'][$r]) ? $_config['hostport'][$r] : $_config['hostport'][0],
            'database' => isset($_config['database'][$r]) ? $_config['database'][$r] : $_config['database'][0],
            'dsn'      => isset($_config['dsn'][$r]) ? $_config['dsn'][$r] : $_config['dsn'][0],
            'charset'  => isset($_config['charset'][$r]) ? $_config['charset'][$r] : $_config['charset'][0],
        ];
        return $this->connect($db_config, $r, $r == $m ? false : $db_master);
    }

    /**
     * 析构方法
     * @access public
     */
    public function __destruct()
    {
        // 释放查询
        if ($this->PDOStatement) {
            $this->free();
        }
        // 关闭连接
        $this->close();
    }
}
