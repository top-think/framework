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
use think\Collection;
use think\Config;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\Loader;
use think\Model;
use think\model\Relation;
use think\Paginator;

class Query
{
    // 数据库Connection对象实例
    protected $connection;
    // 数据库驱动类型
    protected $driver;
    // 当前模型类名称
    protected $model;
    // 当前数据表名称（含前缀）
    protected $table = '';
    // 当前数据表名称（不含前缀）
    protected $name = '';
    // 查询参数
    protected $options = [];
    // 参数绑定
    protected $bind = [];

    /**
     * 架构函数
     * @access public
     * @param \think\db\Connection|string $connection 数据库对象实例
     * @param string $model 模型名
     * @throws Exception
     */
    public function __construct($connection = '', $model = '')
    {
        $this->connection = $connection ?: Db::connect([], true);
        $this->driver     = $this->connection->getDriverName();
        $this->model      = $model;
    }

    /**
     * 利用__call方法实现一些特殊的Model方法
     * @access public
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed
     * @throws DbException
     * @throws Exception
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
     * 获取当前的数据库Connection对象
     * @access public
     * @return \think\db\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * 指定默认的数据表名（不含前缀）
     * @access public
     * @param string $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 指定默认数据表名（含前缀）
     * @access public
     * @param string $table 表名
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * 得到当前的数据表
     * @access public
     * @param string $name
     * @return string
     */
    public function getTable($name = '')
    {
        if ($name || empty($this->table)) {
            $name      = $name ?: $this->name;
            $tableName = $this->connection->getConfig('prefix');
            if ($name) {
                $tableName .= Loader::parseName($name);
            }
        } else {
            $tableName = $this->table;
        }
        return $tableName;
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string $sql sql指令
     * @param array $bind 参数绑定
     * @param boolean $fetch 不执行只是获取SQL
     * @param boolean $master 是否在主服务器读操作
     * @param bool|string $class 指定返回的数据集对象
     * @return mixed
     * @throws DbBindParamException
     * @throws PDOException
     */
    public function query($sql, $bind = [], $fetch = false, $master = false, $class = false)
    {
        return $this->connection->query($sql, $bind, $fetch, $master, $class);
    }

    /**
     * 执行语句
     * @access public
     * @param string $sql sql指令
     * @param array $bind 参数绑定
     * @param boolean $fetch 不执行只是获取SQL
     * @param boolean $getLastInsID 是否获取自增ID
     * @param boolean $sequence 自增序列名
     * @return int
     * @throws DbBindParamException
     * @throws PDOException
     */
    public function execute($sql, $bind = [], $fetch = false, $getLastInsID = false, $sequence = null)
    {
        return $this->connection->execute($sql, $bind, $fetch, $getLastInsID, $sequence);
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID()
    {
        return $this->connection->getLastInsID();
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql()
    {
        return $this->connection->queryStr;
    }

    /**
     * 执行数据库事务
     * @access public
     * @param callable $callback 数据操作方法回调
     * @return mixed
     */
    public function transaction($callback)
    {
        return $this->connection->transaction($callback);
    }

    /**
     * 启动事务
     * @access public
     * @param string $label 事务标识
     * @return bool|null
     */
    public function startTrans($label = '')
    {
        return $this->connection->startTrans($label);
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @param string $label 事务标识
     * @return boolean
     * @throws PDOException
     */
    public function commit($label = '')
    {
        return $this->connection->commit($label);
    }

    /**
     * 事务回滚
     * @access public
     * @return boolean
     * @throws PDOException
     */
    public function rollback()
    {
        return $this->connection->rollback();
    }

    /**
     * 批处理执行SQL语句
     * 批处理的指令都认为是execute操作
     * @access public
     * @param array $sql SQL批处理指令
     * @return boolean
     */
    public function batchQuery($sql = [])
    {
        return $this->connection->batchQuery($sql);
    }

    /**
     * 获取当前的builder实例对象
     * @access protected
     * @return \think\db\Builder
     */
    protected function builder()
    {
        static $builder = [];
        $driver         = $this->driver;
        if (!isset($builder[$driver])) {
            $class            = '\\think\\db\\builder\\' . ucfirst($driver);
            $builder[$driver] = new $class($this->connection);
        }
        // 设置当前查询对象
        $builder[$driver]->setQuery($this);
        return $builder[$driver];
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param string $field 字段名
     * @return mixed
     */
    public function value($field)
    {
        $result = false;
        if (!empty($this->options['cache'])) {
            // 判断查询缓存
            $cache  = $this->options['cache'];
            $key    = is_string($cache['key']) ? $cache['key'] : md5($field . serialize($this->options));
            $result = Cache::get($key);
        }
        if (!$result) {
            if (isset($this->options['field'])) {
                unset($this->options['field']);
            }
            $pdo    = $this->field($field)->fetchPdo(true)->find();
            $result = $pdo->fetchColumn();
            if (isset($cache)) {
                // 缓存数据
                Cache::set($key, $result, $cache['expire']);
            }
        } else {
            // 清空查询条件
            $this->options = [];
        }
        return $result;
    }

    /**
     * 得到某个列的数组
     * @access public
     * @param string $field 字段名 多个字段用逗号分隔
     * @param string $key 索引
     * @return array
     */
    public function column($field, $key = '')
    {
        $result = false;
        if (!empty($this->options['cache'])) {
            // 判断查询缓存
            $cache  = $this->options['cache'];
            $guid   = is_string($cache['key']) ? $cache['key'] : md5($field . serialize($this->options));
            $result = Cache::get($guid);
        }
        if (!$result) {
            if (isset($this->options['field'])) {
                unset($this->options['field']);
            }
            if ($key && '*' != $field) {
                $field = $key . ',' . $field;
            }
            $pdo = $this->field($field)->fetchPdo(true)->select();
            if (1 == $pdo->columnCount()) {
                $result = $pdo->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $resultSet = $pdo->fetchAll(PDO::FETCH_ASSOC);
                if ($resultSet) {
                    $fields = array_keys($resultSet[0]);
                    $count  = count($fields);
                    $key1   = array_shift($fields);
                    $key2   = $fields ? array_shift($fields) : '';
                    $key    = $key ?: $key1;
                    foreach ($resultSet as $val) {
                        if ($count > 2) {
                            $result[$val[$key]] = $val;
                        } elseif (2 == $count) {
                            $result[$val[$key]] = $val[$key2];
                        }
                    }
                } else {
                    $result = [];
                }
            }
            if (isset($cache) && isset($guid)) {
                // 缓存数据
                Cache::set($guid, $result, $cache['expire']);
            }
        } else {
            // 清空查询条件
            $this->options = [];
        }
        return $result;
    }

    /**
     * COUNT查询
     * @access public
     * @param string $field 字段名
     * @return integer
     */
    public function count($field = '*')
    {
        return $this->value('COUNT(' . $field . ') AS tp_count');
    }

    /**
     * SUM查询
     * @access public
     * @param string $field 字段名
     * @return integer
     */
    public function sum($field = '*')
    {
        $result = $this->value('SUM(' . $field . ') AS tp_sum');
        return is_null($result) ? 0 : $result;
    }

    /**
     * MIN查询
     * @access public
     * @param string $field 字段名
     * @return integer
     */
    public function min($field = '*')
    {
        $result = $this->value('MIN(' . $field . ') AS tp_min');
        return is_null($result) ? 0 : $result;
    }

    /**
     * MAX查询
     * @access public
     * @param string $field 字段名
     * @return integer
     */
    public function max($field = '*')
    {
        $result = $this->value('MAX(' . $field . ') AS tp_max');
        return is_null($result) ? 0 : $result;
    }

    /**
     * AVG查询
     * @access public
     * @param string $field 字段名
     * @return integer
     */
    public function avg($field = '*')
    {
        $result = $this->value('AVG(' . $field . ') AS tp_avg');
        return is_null($result) ? 0 : $result;
    }

    /**
     * 设置记录的某个字段值
     * 支持使用数据库字段和方法
     * @access public
     * @param string|array $field 字段名
     * @param string $value 字段值
     * @return integer
     */
    public function setField($field, $value = '')
    {
        if (is_array($field)) {
            $data = $field;
        } else {
            $data[$field] = $value;
        }
        return $this->update($data);
    }

    /**
     * 字段值(延迟)增长
     * @access public
     * @param string $field 字段名
     * @param integer $step 增长值
     * @param integer $lazyTime 延时时间(s)
     * @return integer|true
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
            $guid = md5($this->getTable() . '_' . $field . '_' . serialize($condition));
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
     * @param string $field 字段名
     * @param integer $step 减少值
     * @param integer $lazyTime 延时时间(s)
     * @return integer|true
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
            $guid = md5($this->getTable() . '_' . $field . '_' . serialize($condition));
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
     * @param string $guid 写入标识
     * @param integer $step 写入步进值
     * @param integer $lazyTime 延时时间(s)
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
     * 查询SQL组装 join
     * @access public
     * @param mixed $join 关联的表名
     * @param mixed $condition 条件
     * @param string $type JOIN类型
     * @return $this
     */
    public function join($join, $condition = null, $type = 'INNER')
    {
        if (empty($condition)) {
            // 如果为组数，则循环调用join
            foreach ($join as $key => $value) {
                if (is_array($value) && 2 <= count($value)) {
                    $this->join($value[0], $value[1], isset($value[2]) ? $value[2] : $type);
                }
            }
        } else {
            $prefix = $this->connection->getConfig('prefix');
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
                } elseif (false === strpos($table, '.')) {
                    // 加上默认的表前缀
                    $table = $prefix . $table;
                }
            } else {
                $join = trim($join);
                if (0 === strpos($join, '__')) {
                    $table = $this->connection->parseSqlTable($join);
                } elseif (false === strpos($join, '(') && false === strpos($join, '.') && !empty($prefix) && 0 !== strpos($join, $prefix)) {
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
     * @return $this
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
     * 指定查询字段 支持字段排除和指定数据表
     * @access public
     * @param mixed $field
     * @param boolean $except 是否排除
     * @param string $tableName 数据表名
     * @param string $prefix 字段前缀
     * @param string $alias 别名前缀
     * @return $this
     */
    public function field($field, $except = false, $tableName = '', $prefix = '', $alias = '')
    {
        if (empty($field)) {
            return $this;
        }
        if (is_string($field)) {
            $field = explode(',', $field);
        }
        if (true === $field) {
            // 获取全部字段
            $fields = $this->getTableInfo($tableName, 'fields');
            $field  = $fields ?: '*';
        } elseif ($except) {
            // 字段排除
            $fields = $this->getTableInfo($tableName, 'fields');
            $field  = $fields ? array_diff($fields, $field) : $field;
        }
        if ($tableName) {
            // 添加统一的前缀
            $prefix = $prefix ?: $tableName;
            foreach ($field as $key => $val) {
                if (is_numeric($key)) {
                    $val = $prefix . '.' . $val . ($alias ? ' AS ' . $alias . $val : '');
                }
                $field[$key] = $val;
            }
        }

        if (isset($this->options['field'])) {
            $field = array_merge($this->options['field'], $field);
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
     * @return $this
     */
    public function where($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        $this->parseWhereExp('AND', $field, $op, $condition, $param);
        return $this;
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        $this->parseWhereExp('OR', $field, $op, $condition, $param);
        return $this;
    }

    /**
     * 分析查询表达式
     * @access public
     * @param string|array|\Closure $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @param string $operator and or
     * @return void
     */
    protected function parseWhereExp($operator, $field, $op, $condition, $param = [])
    {
        if ($field instanceof \Closure) {
            call_user_func_array($field, [ & $this]);
            return;
        }

        if (is_string($field) && !empty($this->options['via'])) {
            $field = $this->options['via'] . '.' . $field;
        }
        if (is_string($field) && preg_match('/[,=\>\<\'\"\(\s]/', $field)) {
            $where[] = ['exp', $field];
            if (is_array($op)) {
                // 参数绑定
                $this->bind($op);
            }
        } elseif (is_null($op) && is_null($condition)) {
            if (is_array($field)) {
                // 数组批量查询
                $where = $field;
            } elseif ($field) {
                // 字符串查询
                $where[] = ['exp', $field];
            } else {
                $where = '';
            }
        } elseif (is_array($op)) {
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
        if (!empty($where)) {
            if (!isset($this->options['where'][$operator])) {
                $this->options['where'][$operator] = [];
            }
            $this->options['where'][$operator] = array_merge($this->options['where'][$operator], $where);
        }
    }

    /**
     * 指定查询条件
     * @access public
     * @param mixed $where 条件表达式
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * 分页查询
     * @param int|null $listRows 每页数量
     * @param bool $simple 简洁模式
     * @param array $config 配置参数
     *                      page:当前页,
     *                      path:url路径,
     *                      query:url额外参数,
     *                      fragment:url锚点,
     *                      var_page:分页变量,
     *                      list_rows:每页数量
     *                      type:分页类名,
     *                      namespace:分页类命名空间
     * @return \think\paginator\Collection
     * @throws DbException
     */
    public function paginate($listRows = null, $simple = false, $config = [])
    {
        $config   = array_merge(Config::get('paginate'), $config);
        $listRows = $listRows ?: $config['list_rows'];
        $class    = (!empty($config['namespace']) ? $config['namespace'] : '\\think\\paginator\\driver\\') . ucwords($config['type']);
        $page     = isset($config['page']) ? (int) $config['page'] : call_user_func([
            $class,
            'getCurrentPage',
        ], $config['var_page']);

        $page = $page < 1 ? 1 : $page;

        $config['path'] = isset($config['path']) ? $config['path'] : call_user_func([$class, 'getCurrentPath']);

        /** @var Paginator $paginator */
        if (!$simple) {
            $options = $this->getOptions();
            $total   = $this->count();
            $results = $this->options($options)->page($page, $listRows)->select();
        } else {
            $results = $this->limit(($page - 1) * $listRows, $listRows + 1)->select();
            $total   = null;
        }

        $paginator = new $class($results, $listRows, $page, $simple, $total, $config);
        return $paginator->items();
    }

    /**
     * 指定当前操作的数据表
     * @access public
     * @param string $table 表名
     * @return $this
     */
    public function table($table)
    {
        $this->options['table'] = $table;
        return $this;
    }

    /**
     * USING支持 用于多表删除
     * @access public
     * @param mixed $using
     * @return $this
     */
    public function using($using)
    {
        $this->options['using'] = $using;
        return $this;
    }

    /**
     * 指定排序 order('id','desc') 或者 order(['id'=>'desc','create_time'=>'desc'])
     * @access public
     * @param string|array $field 排序字段
     * @param string $order 排序
     * @return $this
     */
    public function order($field, $order = null)
    {
        if (!empty($field)) {
            if (is_string($field)) {
                if (!empty($this->options['via'])) {
                    $field = $this->options['via'] . '.' . $field;
                }
                $field = empty($order) ? $field : [$field => $order];
            } elseif (!empty($this->options['via'])) {
                foreach ($field as $key => $val) {
                    if (is_numeric($key)) {
                        $field[$key] = $this->options['via'] . '.' . $val;
                    } else {
                        $field[$this->options['via'] . '.' . $key] = $val;
                        unset($field[$key]);
                    }
                }
            }
            $this->options['order'] = $field;
        }
        return $this;
    }

    /**
     * 查询缓存
     * @access public
     * @param mixed $key
     * @param integer $expire
     * @return $this
     */
    public function cache($key = true, $expire = null)
    {
        // 增加快捷调用方式 cache(10) 等同于 cache(true, 10)
        if (is_numeric($key) && is_null($expire)) {
            $expire = $key;
            $key    = true;
        }
        if (false !== $key) {
            $this->options['cache'] = ['key' => $key, 'expire' => $expire];
        }
        return $this;
    }

    /**
     * 指定group查询
     * @access public
     * @param string $group GROUP
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @return $this
     */
    public function fetchPdo($pdo = true)
    {
        $this->options['fetch_class'] = $pdo;
        return $this;
    }

    /**
     * 指定数据集返回对象
     * @access public
     * @param string $class 指定返回的数据集对象类名
     * @return $this
     */
    public function fetchClass($class)
    {
        $this->options['fetch_class'] = $class;
        return $this;
    }

    /**
     * 设置从主服务器读取数据
     * @access public
     * @return $this
     */
    public function master()
    {
        $this->options['master'] = true;
        return $this;
    }

    /**
     * 设置是否严格检查字段名
     * @access public
     * @param bool $strict 是否严格检查字段
     * @return $this
     */
    public function strict($strict = true)
    {
        $this->options['strict'] = $strict;
        return $this;
    }

    /**
     * 设置查询数据不存在是否抛出异常
     * @access public
     * @param bool $fail 是否严格检查字段
     * @return $this
     */
    public function failException($fail = true)
    {
        $this->options['fail'] = $fail;
        return $this;
    }

    /**
     * 设置自增序列名
     * @access public
     * @param string $sequence 自增序列名
     * @return $this
     */
    public function sequence($sequence = null)
    {
        $this->options['sequence'] = $sequence;
        return $this;
    }

    /**
     * 获取数据表信息
     * @access public
     * @param string $tableName 数据表名 留空自动获取
     * @param string $fetch 获取信息类型 包括 fields type bind pk
     * @return mixed
     */
    public function getTableInfo($tableName = '', $fetch = '')
    {
        static $_info = [];
        if (!$tableName) {
            $tableName = $this->getTable();
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
            $info   = $this->connection->getFields($tableName);
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
     * 获取当前模型对象的主键
     * @access public
     * @param string $table 数据表名
     * @return string|array
     */
    public function getPk($table = '')
    {
        return $this->getTableInfo($table, 'pk');
    }

    /**
     * 参数绑定
     * @access public
     * @param mixed $key 参数名
     * @param mixed $value 绑定变量值
     * @param integer $type 绑定类型
     * @return $this
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
     * 检测参数是否已经绑定
     * @access public
     * @param string $key 参数名
     * @return bool
     */
    public function isBind($key)
    {
        return isset($this->bind[$key]);
    }

    /**
     * 查询参数赋值
     * @access protected
     * @param array $options 表达式参数
     * @return $this
     */
    protected function options(array $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 获取当前的查询参数
     * @access public
     * @param string $name 参数名
     * @return mixed
     */
    public function getOptions($name = '')
    {
        return isset($this->options[$name]) ? $this->options[$name] : $this->options;
    }

    /**
     * 设置关联查询JOIN预查询
     * @access public
     * @param string|array $with 关联方法名称
     * @return $this
     */
    public function with($with)
    {
        if (empty($with)) {
            return $this;
        }

        if (is_string($with)) {
            $with = explode(',', $with);
        }

        $i            = 0;
        $currentModel = $this->model;

        /** @var Model $class */
        $class = new $currentModel;
        foreach ($with as $key => $relation) {
            $closure = false;
            if ($relation instanceof \Closure) {
                // 支持闭包查询过滤关联条件
                $closure    = $relation;
                $relation   = $key;
                $with[$key] = $key;
            } elseif (is_string($relation) && strpos($relation, '.')) {
                $with[$key]                   = $relation;
                list($relation, $subRelation) = explode('.', $relation, 2);
            }

            /** @var Relation $model */
            $model = $class->$relation();
            $info  = $model->getRelationInfo();
            if (in_array($info['type'], [Relation::HAS_ONE, Relation::BELONGS_TO])) {
                if (0 == $i) {
                    $name  = Loader::parseName(basename(str_replace('\\', '/', $currentModel)));
                    $table = $this->getTable();
                    $this->table($table)->alias($name)->field(true, false, $table, $name);
                }
                // 预载入封装
                $joinTable = $model->getTable();
                $joinName  = Loader::parseName(basename(str_replace('\\', '/', $info['model'])));
                $this->via($joinName);
                $this->join($joinTable . ' ' . $joinName, $name . '.' . $info['localKey'] . '=' . $joinName . '.' . $info['foreignKey'])->field(true, false, $joinTable, $joinName, $joinName . '__');
                if ($closure) {
                    // 执行闭包查询
                    call_user_func_array($closure, [ & $this]);
                }
                $i++;
            } elseif ($closure) {
                $with[$key] = $closure;
            }
        }
        $this->via();
        $this->options['with'] = $with;
        return $this;
    }

    /**
     * 设置当前字段添加的表别名
     * @access public
     * @param string $via
     * @return $this
     */
    public function via($via = '')
    {
        $this->options['via'] = $via;
        return $this;
    }

    /**
     * 设置关联查询
     * @access public
     * @param string $relation 关联名称
     * @return $this
     */
    public function relation($relation)
    {
        $this->options['relation'] = $relation;
        return $this;
    }

    /**
     * 把主键值转换为查询条件 支持复合主键
     * @access public
     * @param array $data 主键数据
     * @param mixed $options 表达式参数
     * @return void
     * @throws \think\Exception
     */
    protected function parsePkWhere($data, &$options)
    {
        $pk = $this->getPk($options['table']);
        // 获取当前数据表
        if (!empty($options['alias'])) {
            $alias = $options['alias'];
        }
        if (is_string($pk)) {
            $key = isset($alias) ? $alias . '.' . $pk : $pk;
            // 根据主键查询
            if (is_array($data)) {
                $where[$key] = isset($data[$pk]) ? $data[$pk] : ['in', $data];
            } else {
                $where[$key] = strpos($data, ',') ? ['IN', $data] : $data;
            }
            $options['where']['AND'] = $where;
        } elseif (is_array($pk) && is_array($data) && !empty($data)) {
            // 根据复合主键查询
            foreach ($pk as $key) {
                if (isset($data[$key])) {
                    $attr         = isset($alias) ? $alias . '.' . $key : $key;
                    $where[$attr] = $data[$key];
                } else {
                    throw new Exception('miss complex primary data');
                }
            }
            $options['where']['AND'] = $where;
        }
        return;
    }

    /**
     * 插入记录
     * @access public
     * @param mixed $data 数据
     * @param boolean $replace 是否replace
     * @param boolean $getLastInsID 是否获取自增ID
     * @return integer
     */
    public function insert(array $data, $replace = false, $getLastInsID = false)
    {
        // 分析查询表达式
        $options = $this->parseExpress();
        // 生成SQL语句
        $sql = $this->builder()->insert($data, $options, $replace);
        // 执行操作
        return $this->execute($sql, $this->getBind(), $options['fetch_sql'], $getLastInsID, isset($options['sequence']) ? $options['sequence'] : null);
    }

    /**
     * 插入记录并获取自增ID
     * @access public
     * @param mixed $data 数据
     * @param boolean $replace 是否replace
     * @param string $sequence 自增序列名
     * @return integer
     */
    public function insertGetId(array $data, $replace = false, $sequence = null)
    {
        return $this->insert($data, $replace, true, $sequence);
    }

    /**
     * 批量插入记录
     * @access public
     * @param mixed $dataSet 数据集
     * @return integer
     */
    public function insertAll(array $dataSet)
    {
        // 分析查询表达式
        $options = $this->parseExpress();
        if (!is_array($dataSet[0])) {
            return false;
        }
        // 生成SQL语句
        $sql = $this->builder()->insertAll($dataSet, $options);
        // 执行操作
        return $this->execute($sql, $this->getBind(), $options['fetch_sql']);
    }

    /**
     * 通过Select方式插入记录
     * @access public
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @return int
     * @throws \think\exception\PDOException
     */
    public function selectInsert($fields, $table)
    {
        // 分析查询表达式
        $options = $this->parseExpress();
        // 生成SQL语句
        $sql = $this->builder()->selectInsert($fields, $table, $options);
        // 执行操作
        return $this->execute($sql, $this->getBind(), $options['fetch_sql']);
    }

    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @return int
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function update(array $data)
    {
        $options = $this->parseExpress();
        if (empty($options['where'])) {
            $pk = $this->getPk($options['table']);
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
        // 生成UPDATE SQL语句
        $sql = $this->builder()->update($data, $options);
        if ('' == $sql) {
            return 0;
        }
        // 执行操作
        return $this->execute($sql, $this->getBind(), $options['fetch_sql']);
    }

    /**
     * 查找记录
     * @access public
     * @param array $data
     * @return Collection|false|\PDOStatement|string
     * @throws DbException
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function select($data = [])
    {
        if ($data instanceof Query) {
            return $data->select();
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [ & $this]);
        }
        // 分析查询表达式
        $options = $this->parseExpress();

        if (false === $data) {
            // 用于子查询 不查询只返回SQL
            $options['fetch_sql'] = true;
        } elseif (empty($options['where']) && !empty($data)) {
            // 主键条件分析
            $this->parsePkWhere($data, $options);
        }

        $resultSet = false;
        if (!empty($options['cache'])) {
            // 判断查询缓存
            $cache     = $options['cache'];
            $key       = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $resultSet = Cache::get($key);
        }
        if (!$resultSet) {
            // 生成查询SQL
            $sql = $this->builder()->select($options);
            // 执行查询操作
            $resultSet = $this->query($sql, $this->getBind(), $options['fetch_sql'], $options['master'], $options['fetch_class']);

            if (is_string($resultSet)) {
                // 返回SQL
                return $resultSet;
            }
            if ($resultSet instanceof \PDOStatement) {
                // 返回PDOStatement对象
                return $resultSet;
            }

            if (isset($cache)) {
                // 缓存数据集
                Cache::set($key, $resultSet, $cache['expire']);
            }
        }

        // 返回结果处理
        if ($resultSet) {

            // 数据列表读取后的处理
            if (!empty($this->model)) {
                // 生成模型对象
                $model = $this->model;
                foreach ($resultSet as $key => $result) {
                    /** @var Model $result */
                    $result = new $model($result);
                    $result->isUpdate(true);
                    // 关联查询
                    if (!empty($options['relation'])) {
                        $result->relationQuery($options['relation']);
                    }
                    $resultSet[$key] = $result;
                }
                if (!empty($options['with'])) {
                    // 预载入
                    $resultSet = $result->eagerlyResultSet($resultSet, $options['with'], is_object($resultSet) ? get_class($resultSet) : '');
                }
            }
        } elseif (!empty($options['fail'])) {
            throw new DbException('Data not Found', $options, $sql);
        }
        return $resultSet;
    }

    /**
     * 查找单条记录
     * @access public
     * @param array $data 表达式
     * @return array|false|\PDOStatement|string|Model
     * @throws DbException
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function find($data = [])
    {
        if ($data instanceof Query) {
            return $data->find();
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [ & $this]);
        }
        // 分析查询表达式
        $options = $this->parseExpress();

        if (empty($options['where']) && (!empty($data) || 0 == $data)) {
            // AR模式分析主键条件
            $this->parsePkWhere($data, $options);
        }

        $options['limit'] = 1;
        $result           = false;
        if (!empty($options['cache'])) {
            // 判断查询缓存
            $cache  = $options['cache'];
            $key    = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $result = Cache::get($key);
        }
        if (!$result) {
            // 生成查询SQL
            $sql = $this->builder()->select($options);
            // 执行查询
            $result = $this->query($sql, $this->getBind(), $options['fetch_sql'], $options['master'], $options['fetch_class']);

            if (is_string($result)) {
                // 返回SQL
                return $result;
            }

            if ($result instanceof \PDOStatement) {
                // 返回PDOStatement对象
                return $result;
            }

            if (isset($cache)) {
                // 缓存数据
                Cache::set($key, $result, $cache['expire']);
            }
        }

        // 数据处理
        if (!empty($result[0])) {
            $data = $result[0];
            if (!empty($this->model)) {
                // 返回模型对象
                $model = $this->model;
                $data  = new $model($data);
                $data->isUpdate(true, isset($options['where']['AND']) ? $options['where']['AND'] : null);
                // 关联查询
                if (!empty($options['relation'])) {
                    $data->relationQuery($options['relation']);
                }
                if (!empty($options['with'])) {
                    // 预载入
                    $data->eagerlyResult($data, $options['with'], is_object($result) ? get_class($result) : '');
                }
            }
        } elseif (!empty($options['fail'])) {
            throw new DbException('Data not Found', $options, $sql);
        } else {
            $data = false;
        }
        return $data;
    }

    /**
     * 分批数据返回处理
     * @access public
     * @param integer $count 每次处理的数据数量
     * @param callable $callback 处理回调方法
     * @param string $column 分批处理的字段名
     * @return array
     */
    public function chunk($count, $callback, $column = null)
    {
        $column    = $column ?: $this->getPk();
        $options   = $this->getOptions();
        $resultSet = $this->limit($count)->order($column, 'asc')->select();

        while (!empty($resultSet)) {
            if (false === call_user_func($callback, $resultSet)) {
                return false;
            }
            $end       = end($resultSet);
            $lastId    = is_array($end) ? $end[$column] : $end->$column;
            $resultSet = $this->options($options)
                ->limit($count)
                ->where($column, '>', $lastId)
                ->order($column, 'asc')
                ->select();
        }
        return true;
    }

    /**
     * 获取绑定的参数 并清空
     * @access public
     * @return array
     */
    public function getBind()
    {
        $bind       = $this->bind;
        $this->bind = [];
        return $bind;
    }

    /**
     * 创建子查询SQL
     * @access public
     * @param bool $sub
     * @return string
     * @throws DbException
     */
    public function buildSql($sub = true)
    {
        return $sub ? '( ' . $this->select(false) . ' )' : $this->select(false);
    }

    /**
     * 删除记录
     * @access public
     * @param array $data 表达式
     * @return int
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function delete($data = [])
    {
        // 分析查询表达式
        $options = $this->parseExpress();

        if (empty($options['where']) && !empty($data)) {
            // AR模式分析主键条件
            $this->parsePkWhere($data, $options);
        }

        if (empty($options['where'])) {
            // 如果条件为空 不进行删除操作 除非设置 1=1
            throw new Exception('no data to delete without where');
        }
        // 生成删除SQL语句
        $sql = $this->builder()->delete($options);
        // 执行操作
        return $this->execute($sql, $this->getBind(), $options['fetch_sql']);
    }

    /**
     * 分析表达式（可用于查询或者写入操作）
     * @access public
     * @return array
     */
    public function parseExpress()
    {
        $options = $this->options;

        // 获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->getTable();
        }

        if (!isset($options['where'])) {
            $options['where'] = [];
        }

        // 表别名
        if (!empty($options['alias'])) {
            $options['table'] .= ' ' . $options['alias'];
        }

        if (!isset($options['field'])) {
            $options['field'] = '*';
        }

        if (!isset($options['strict'])) {
            $options['strict'] = $this->connection->getConfig('fields_strict');
        }

        foreach (['master', 'lock', 'fetch_class', 'fetch_sql', 'distinct'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }

        foreach (['join', 'union', 'group', 'having', 'limit', 'order', 'force', 'comment'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = '';
            }
        }

        if (isset($options['page'])) {
            // 根据页数计算limit
            list($page, $listRows) = $options['page'];
            $page                  = $page > 0 ? $page : 1;
            $listRows              = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset                = $listRows * ($page - 1);
            $options['limit']      = $offset . ',' . $listRows;
        }

        $this->options = [];
        return $options;
    }

}
