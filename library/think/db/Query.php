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
use think\Config;
use think\Db;
use think\Exception;
use think\Loader;
use think\Model;

class Query
{
    // 数据库Connection对象实例
    protected $connection = null;
    protected $driver;

    // 查询参数
    protected $options = [];
    // 参数绑定
    protected $bind = [];

    /**
     * 架构函数
     * @access public
     * @param object $connection 数据库对象实例
     */
    public function __construct($connection = '')
    {
        if ($connection) {
            $this->connection = $connection;
        } else {
            $this->connection = Db::connect();
        }

        $this->driver = $this->connection->getDriverName();
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
     * 获取当前的builder
     * @access protected
     * @return \think\db\Builder
     */
    protected function builder()
    {
        static $builder = [];
        $driver         = $this->driver;
        if (!isset($builder[$driver])) {
            $class            = '\\think\\db\\builder\\' . ucfirst($driver);
            $builder[$driver] = new $class($this->connection, $this);
        }
        return $builder[$driver];
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
     * @return $this
     */
    public function field($field, $except = false, $tableName = '', $prefix = '')
    {
        if (is_string($field)) {
            $field = explode(',', $field);
        }
        if (true === $field) {
            // 获取全部字段
            $fields = $this->connection->getTableInfo($tableName, 'fields');
            $field  = $fields ?: '*';
        } elseif ($except) {
            // 字段排除
            $fields = $this->connection->getTableInfo($tableName, 'fields');
            $field  = $fields ? array_diff($fields, $field) : $field;
        }
        if ($tableName) {
            // 添加统一的前缀
            $prefix = $prefix ?: $tableName;
            foreach ($field as $key => $val) {
                if (is_numeric($key)) {
                    $val = $prefix . '.' . $val . ' AS ' . $prefix . '__' . $val;
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
        if ($field instanceof Query) {
            // 使用查询对象
            $this->options['where'] = $field;
            return $this;
        }

        $where = $this->parseWhereExp($field, $op, $condition);
        if (!empty($where)) {
            if (!isset($this->options['where']['AND'])) {
                $this->options['where']['AND'] = [];
            }
            $this->options['where']['AND'] = array_merge($this->options['where']['AND'], $where);
        }
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
        $where = $this->parseWhereExp($field, $op, $condition);
        if (!empty($where)) {
            if (!isset($this->options['where']['OR'])) {
                $this->options['where']['OR'] = [];
            }
            $this->options['where']['OR'] = array_merge($this->options['where']['OR'], $where);
        }
        return $this;
    }

    /**
     * 分析查询表达式
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    protected function parseWhereExp($field, $op, $condition)
    {
        if (is_string($field) && !empty($this->options['via'])) {
            $field = $this->options['via'] . '.' . $field;
        }
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
     * 指定数据表
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
                    $field[$this->options['via'] . '.' . $key] = $val;
                    unset($field[$key]);
                }
            }
            $this->options['order'] = $field;
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
        $this->options['fetch_pdo'] = $pdo;
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
     * 指定当前模型
     * @access public
     * @param string $model  模型类名称
     * @return $this
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

    public function isBind($key)
    {
        return isset($this->bind[$key]);
    }

    /**
     * 调用命名范围
     * @access public
     * @param Closure $scope 命名范围 闭包定义
     * @param mixed $args 参数
     * @return $this
     */
    public function scope($scope = '', $args = null)
    {
        if ($scope instanceof \Closure) {
            call_user_func_array($scope, [ & $this, $args]);
        } elseif ($scope instanceof Query) {
            return $scope;
        }
        return $this;
    }

    /**
     * 查询参数赋值
     * @access public
     * @param array $options 表达式参数
     * @return $this
     */
    public function options(array $options)
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions($name = '')
    {
        return empty($name) ? $this->options : $this->options[$name];
    }

    /**
     * 设置关联查询JOIN预查询
     * @access public
     * @param string|array $with 关联方法名称
     * @return Db
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
        $currentModel = $this->options['model'];
        $class        = new $this->options['model'];
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

            $model                              = $class->$relation();
            list($type, $foreignKey, $localKey) = $class->getRelationInfo();
            if (in_array($type, [Model::HAS_ONE, Model::BELONGS_TO])) {
                if (0 == $i) {
                    $joinName  = strtolower(basename(str_replace('\\', '/', $this->options['model'])));
                    $joinTable = $this->getTableName();
                    $this->table($joinTable)->alias($joinName)->field(true, false, $joinTable, $joinName);
                }
                // 预载入封装

                $table = $model::getTableName();
                $name  = strtolower(basename(str_replace('\\', '/', $model)));
                $this->via($name);
                $this->join($table . ' ' . $name, $joinName . '.' . $localKey . '=' . $name . '.' . $foreignKey)->field(true, false, $table, $name);
                if ($closure) {
                    // 执行闭包查询
                    call_user_func_array($closure, [ & $this]);
                }
                $i++;
            }
        }
        $this->via();
        $this->model($currentModel);
        $this->options['with'] = $with;
        return $this;
    }

    /**
     * 设置当前字段添加的表别名
     * @access public
     * @param string $relation 关联名称
     * @return Db
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
     * @param array $data 主键数据
     * @param mixed $options 表达式参数
     * @return void
     * @throws \think\Exception
     */
    protected function parsePkWhere($data, &$options)
    {
        $pk = $this->connection->getTableInfo($options['table'], 'pk');
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
     * @return integer
     */
    public function insert(array $data, $replace = false)
    {
        // 分析查询表达式
        $options = $this->parseExpress();
        //$data    = $this->parseData($data, $options);
        // 生成SQL语句
        $sql = $this->builder()->insert($data, $options, $replace);
        // 执行操作
        return $this->connection->execute($sql, $this->getBind(), $options['fetch_sql']);
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
        return $this->connection->execute($sql, $this->getBind(), $options['fetch_sql']);
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
        // 分析查询表达式
        $options = $this->parseExpress();
        // 生成SQL语句
        $sql = $this->builder()->selectInsert($fields, $table, $options);
        // 执行操作
        return $this->connection->execute($sql, $this->getBind(), $options['fetch_sql']);
    }

    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @return integer
     */
    public function update(array $data)
    {
        $options = $this->parseExpress();
        if (empty($options['where'])) {
            $pk = $this->connection->getTableInfo($options['table'], 'pk');
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
        return $this->connection->execute($sql, $this->getBind(), $options['fetch_sql']);
    }

    /**
     * 查找记录
     * @access public
     * @param array $options 表达式
     * @return \PDOStatement|array|string|false
     */
    public function select($data = [])
    {
        // 分析查询表达式
        $options = $this->parseExpress();

        if (false === $data) {
            // 用于子查询 不查询只返回SQL
            $options['fetch_sql'] = true;
        } elseif (empty($options['where']) && !empty($data)) {
            // 主键条件分析
            $this->parsePkWhere($data, $options);
        }

        // 生成查询SQL
        $sql = $this->builder()->select($options);
        // 执行查询操作
        $resultSet = $this->connection->query($sql, $this->getBind(), $options['fetch_sql'], $options['master'], $options['fetch_pdo']);

        // 返回结果处理
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
                    $resultSet = $result->eagerlyResultSet($resultSet, $options['with']);
                }
            }
        }
        return $resultSet;
    }

    /**
     * 查找单条记录
     * @access public
     * @param array $data 表达式
     * @return \think\Model|\PDOStatement|array|string|false
     */
    public function find($data = [])
    {
        // 分析查询表达式
        $options = $this->parseExpress();

        if (empty($options['where']) && !empty($data)) {
            // AR模式分析主键条件
            $this->parsePkWhere($data, $options);
        }

        $options['limit'] = 1;
        $sql              = $this->builder()->select($options);
        $result           = $this->connection->query($sql, $this->getBind(), $options['fetch_sql'], $options['master'], $options['fetch_pdo']);

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
                if (!empty($options['with'])) {
                    // 预载入
                    $data->eagerlyResult($data, $options['with']);
                }
            }
        } else {
            $data = false;
        }
        return $data;
    }

    public function getBind()
    {
        $bind       = $this->bind;
        $this->bind = [];
        return $bind;
    }

    public function buildSql($sub = true)
    {
        return $sub ? '( ' . $this->select(false) . ' )' : $this->select(false);
    }

    /**
     * 删除记录
     * @access public
     * @param array $data 表达式
     * @return integer
     */
    public function delete($data)
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
     * @param array $options 表达式参数
     * @return array
     */
    public function parseExpress()
    {
        $options = $this->options;

        // 获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->connection->getTableName();
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

        foreach (['master', 'lock', 'fetch_pdo', 'fetch_sql', 'distinct'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }

        foreach (['join', 'union', 'group', 'having', 'limit', 'order', 'force', 'comment'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = '';
            }
        }

        $this->options = [];
        return $options;
    }

}
