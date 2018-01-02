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

namespace think\db;

use PDO;
use think\Exception;

abstract class Builder
{
    // connection对象实例
    protected $connection;

    // 查询表达式映射
    protected $exp = ['EQ' => '=', 'NEQ' => '<>', 'GT' => '>', 'EGT' => '>=', 'LT' => '<', 'ELT' => '<=', 'NOTLIKE' => 'NOT LIKE', 'NOTIN' => 'NOT IN', 'NOTBETWEEN' => 'NOT BETWEEN', 'NOTEXISTS' => 'NOT EXISTS', 'NOTNULL' => 'NOT NULL', 'NOTBETWEEN TIME' => 'NOT BETWEEN TIME'];

    // 查询表达式解析
    protected $parser = [
        'parseCompare'     => ['=', '<>', '>', '>=', '<', '<='],
        'parseLike'        => ['LIKE', 'NOT LIKE'],
        'parseBetween'     => ['NOT BETWEEN', 'BETWEEN'],
        'parseIn'          => ['NOT IN', 'IN'],
        'parseExp'         => ['EXP'],
        'parseNull'        => ['NOT NULL', 'NULL'],
        'parseBetweenTime' => ['BETWEEN TIME', 'NOT BETWEEN TIME'],
        'parseTime'        => ['< TIME', '> TIME', '<= TIME', '>= TIME'],
        'parseExists'      => ['NOT EXISTS', 'EXISTS'],
    ];

    // SQL表达式
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%UNION%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    protected $insertSql = '%INSERT% INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';

    protected $insertAllSql = '%INSERT% INTO %TABLE% (%FIELD%) %DATA% %COMMENT%';

    protected $updateSql = 'UPDATE %TABLE% SET %SET%%JOIN%%WHERE%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    protected $deleteSql = 'DELETE FROM %TABLE%%USING%%JOIN%%WHERE%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * 架构函数
     * @access public
     * @param  Connection    $connection 数据库连接对象实例
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * 获取当前的连接对象实例
     * @access public
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * 注册查询表达式解析
     * @access public
     * @param  string    $name   解析方法
     * @param  array     $parser 匹配表达式数据
     * @return $this
     */
    public function bindParser($name, $parser)
    {
        $this->parser[$name] = $parser;
        return $this;
    }

    /**
     * 数据分析
     * @access protected
     * @param  Query     $query     查询对象
     * @param  array     $data      数据
     * @param  array     $fields    字段信息
     * @param  array     $bind      参数绑定
     * @param  string    $suffix    参数绑定后缀
     * @return array
     */
    protected function parseData(Query $query, $data = [], $fields = [], $bind = [], $suffix = '')
    {
        if (empty($data)) {
            return [];
        }

        $options = $query->getOptions();

        // 获取绑定信息
        if (empty($bind)) {
            $bind = $this->connection->getFieldsBind($options['table']);
        }

        if (empty($fields)) {
            if ('*' == $options['field']) {
                $fields = array_keys($bind);
            } else {
                $fields = $options['field'];
            }
        }

        $result = [];

        foreach ($data as $key => $val) {
            $item = $this->parseKey($query, $key);

            if (is_object($val) && method_exists($val, '__toString')) {
                // 对象数据写入
                $val = $val->__toString();
            } elseif (is_array($val) && 'json' == $this->connection->getFieldsType($options['table'], $key)) {
                $val = json_encode($val);
            }

            if (false === strpos($key, '.') && !in_array($key, $fields, true)) {
                if ($options['strict']) {
                    throw new Exception('fields not exists:[' . $key . ']');
                }
            } elseif (is_null($val)) {
                $result[$item] = 'NULL';
            } elseif (is_array($val) && !empty($val)) {
                switch ($val[0]) {
                    case 'exp':
                        $result[$item] = $val[1];
                        break;
                    case 'inc':
                        $result[$item] = $this->parseKey($query, $val[1]) . '+' . floatval($val[2]);
                        break;
                    case 'dec':
                        $result[$item] = $this->parseKey($query, $val[1]) . '-' . floatval($val[2]);
                        break;
                }
            } elseif (is_scalar($val)) {
                // 过滤非标量数据
                $result[$item] = $this->parseDataBind($query, $key, $val, $bind, $suffix);
            }
        }

        return $result;
    }

    /**
     * 数据绑定处理
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  mixed     $data 数据
     * @param  array     $bind 绑定数据
     * @param  string    $suffix
     * @return string
     */
    protected function parseDataBind(Query $query, $key, $data, $bind = [], $suffix = '')
    {
        // 过滤非标量数据
        if (0 === strpos($data, ':') && $query->isBind(substr($data, 1))) {
            return $data;
        } else {
            $key  = str_replace('.', '_', $key);
            $name = 'data__' . $key . $suffix;
            $query->bind($name, $data, isset($bind[$key]) ? $bind[$key] : PDO::PARAM_STR);
            return ':' . $name;
        }
    }

    /**
     * 字段名分析
     * @access protected
     * @param  Query  $query        查询对象
     * @param  string $key
     * @return string
     */
    protected function parseKey(Query $query, $key)
    {
        return $key;
    }

    /**
     * field分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $fields
     * @return string
     */
    protected function parseField(Query $query, $fields)
    {
        if ('*' == $fields || empty($fields)) {
            $fieldsStr = '*';
        } elseif (is_array($fields)) {
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = [];

            foreach ($fields as $key => $field) {
                if (!is_numeric($key)) {
                    $array[] = $this->parseKey($query, $key) . ' AS ' . $this->parseKey($query, $field);
                } else {
                    $array[] = $this->parseKey($query, $field);
                }
            }

            $fieldsStr = implode(',', $array);
        }

        return $fieldsStr;
    }

    /**
     * table分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $tables
     * @return string
     */
    protected function parseTable(Query $query, $tables)
    {
        $item    = [];
        $options = $query->getOptions();
        foreach ((array) $tables as $key => $table) {
            if (!is_numeric($key)) {
                if (strpos($key, '@think')) {
                    $key = strstr($key, '@think', true);
                }

                $key    = $this->connection->parseSqlTable($key);
                $item[] = $this->parseKey($query, $key) . ' ' . $this->parseKey($query, $table);
            } else {
                $table = $this->connection->parseSqlTable($table);

                if (isset($options['alias'][$table])) {
                    $item[] = $this->parseKey($query, $table) . ' ' . $this->parseKey($query, $options['alias'][$table]);
                } else {
                    $item[] = $this->parseKey($query, $table);
                }
            }
        }

        return implode(',', $item);
    }

    /**
     * where分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $where   查询条件
     * @return string
     */
    protected function parseWhere(Query $query, $where)
    {
        $options  = $query->getOptions();
        $whereStr = $this->buildWhere($query, $where);

        if (!empty($options['soft_delete'])) {
            // 附加软删除条件
            list($field, $condition) = $options['soft_delete'];

            $binds    = $this->connection->getFieldsBind($options['table']);
            $whereStr = $whereStr ? '( ' . $whereStr . ' ) AND ' : '';
            $whereStr = $whereStr . $this->parseWhereItem($query, $field, $condition, '', $binds);
        }

        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * 生成查询条件SQL
     * @access public
     * @param  Query     $query        查询对象
     * @param  mixed     $where
     * @param  array     $options
     * @return string
     */
    public function buildWhere(Query $query, $where)
    {
        if (empty($where)) {
            $where = [];
        }

        $whereStr = '';
        $binds    = $this->connection->getFieldsBind($query->getOptions('table'));

        foreach ($where as $logic => $val) {
            $str = [];

            foreach ($val as $value) {
                if (is_array($value)) {
                    if (key($value) !== 0) {
                        throw new Exception('where express error:' . var_export($value, true));
                    }
                    $field = array_shift($value);
                } elseif (!($value instanceof \Closure)) {
                    throw new Exception('where express error:' . var_export($value, true));
                }

                if ($value instanceof \Closure) {
                    // 使用闭包查询
                    $newQuery = $query->newQuery()->setConnection($this->connection);
                    $value($newQuery);
                    $whereClause = $this->buildWhere($query, $newQuery->getOptions('where'));

                    if (!empty($whereClause)) {
                        $str[] = ' ' . $logic . ' ( ' . $whereClause . ' )';
                    }
                } elseif (is_array($field)) {
                    array_unshift($value, $field);
                    $str2 = [];
                    foreach ($value as $item) {
                        $str2[] = $this->parseWhereItem($query, array_shift($item), $item, $logic, $binds);
                    }

                    $str[] = ' ' . $logic . ' ( ' . implode(' AND ', $str2) . ' )';
                } elseif (strpos($field, '|')) {
                    // 不同字段使用相同查询条件（OR）
                    $array = explode('|', $field);
                    $item  = [];

                    foreach ($array as $k) {
                        $item[] = $this->parseWhereItem($query, $k, $value, '', $binds);
                    }

                    $str[] = ' ' . $logic . ' ( ' . implode(' OR ', $item) . ' )';
                } elseif (strpos($field, '&')) {
                    // 不同字段使用相同查询条件（AND）
                    $array = explode('&', $field);
                    $item  = [];

                    foreach ($array as $k) {
                        $item[] = $this->parseWhereItem($query, $k, $value, '', $binds);
                    }

                    $str[] = ' ' . $logic . ' ( ' . implode(' AND ', $item) . ' )';
                } else {
                    // 对字段使用表达式查询
                    $field = is_string($field) ? $field : '';
                    $str[] = ' ' . $logic . ' ' . $this->parseWhereItem($query, $field, $value, $logic, $binds);
                }
            }

            $whereStr .= empty($whereStr) ? substr(implode(' ', $str), strlen($logic) + 1) : implode(' ', $str);
        }

        return $whereStr;
    }

    // where子单元分析
    protected function parseWhereItem(Query $query, $field, $val, $rule = '', $binds = [], $bindName = null)
    {
        // 字段分析
        $key = $field ? $this->parseKey($query, $field) : '';

        // 查询规则和条件
        if (!is_array($val)) {
            $val = is_null($val) ? ['null', ''] : ['=', $val];
        }

        list($exp, $value) = $val;

        // 对一个字段使用多个查询条件
        if (is_array($exp)) {
            $item = array_pop($val);

            // 传入 or 或者 and
            if (is_string($item) && in_array($item, ['AND', 'and', 'OR', 'or'])) {
                $rule = $item;
            } else {
                array_push($val, $item);
            }

            foreach ($val as $k => $item) {
                $bindName = 'where_' . str_replace('.', '_', $field) . '_' . $k;
                $str[]    = $this->parseWhereItem($query, $field, $item, $rule, $binds, $bindName);
            }

            return '( ' . implode(' ' . $rule . ' ', $str) . ' )';
        }

        // 检测操作符
        $exp = strtoupper($exp);
        if (isset($this->exp[$exp])) {
            $exp = $this->exp[$exp];
        }

        $bindName = $bindName ?: 'where_' . str_replace(['.', '-'], '_', $field);

        if (preg_match('/\W/', $bindName)) {
            // 处理带非单词字符的字段名
            $bindName = md5($bindName);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            // 对象数据写入
            $value = $value->__toString();
        }

        $bindType = isset($binds[$field]) ? $binds[$field] : PDO::PARAM_STR;

        if (is_scalar($value) && !in_array($exp, ['EXP', 'NOT NULL', 'NULL', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN']) && strpos($exp, 'TIME') === false) {
            if (strpos($value, ':') !== 0 || !$query->isBind(substr($value, 1))) {
                if ($query->isBind($bindName)) {
                    $bindName .= '_' . str_replace('.', '_', uniqid('', true));
                }

                $query->bind($bindName, $value, $bindType);
                $value = ':' . $bindName;
            }
        }

        // 解析查询表达式
        foreach ($this->parser as $fun => $parse) {
            if (in_array($exp, $parse)) {
                $whereStr = $this->$fun($query, $key, $exp, $value, $field, $bindName, $bindType, isset($val[2]) ? $val[2] : 'AND');
                break;
            }
        }

        if (!isset($whereStr)) {
            throw new Exception('where express error:' . $exp);
        }

        return $whereStr;
    }

    /**
     * 模糊查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @param  string    $logic
     * @return string
     */
    protected function parseLike(Query $query, $key, $exp, $value, $field, $bindName, $bindType, $logic)
    {
        // 模糊匹配
        if (is_array($value)) {
            foreach ($value as $k => $item) {
                $bindKey        = $bindName . '_' . $k;
                $bind[$bindKey] = [$item, $bindType];
                $array[]        = $key . ' ' . $exp . ' :' . $bindKey;
            }

            $query->bind($bind);

            $whereStr = '(' . implode($array, ' ' . strtoupper($logic) . ' ') . ')';
        } else {
            $whereStr = $key . ' ' . $exp . ' ' . $value;
        }

        return $whereStr;
    }

    /**
     * 表达式查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseExp(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        // 表达式查询
        return '( ' . $key . ' ' . $value . ' )';
    }

    /**
     * Null查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseNull(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        // NULL 查询
        return $key . ' IS ' . $exp;
    }

    /**
     * 范围查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseBetween(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        // BETWEEN 查询
        $data = is_array($value) ? $value : explode(',', $value);

        if ($query->isBind($bindName . '_between_1')) {
            $bindKey1 = $bindName . '_between_1' . uniqid();
            $bindKey2 = $bindName . '_between_2' . uniqid();
        } else {
            $bindKey1 = $bindName . '_between_1';
            $bindKey2 = $bindName . '_between_2';
        }

        $bind = [
            $bindKey1 => [$data[0], $bindType],
            $bindKey2 => [$data[1], $bindType],
        ];

        $query->bind($bind);

        $between = ':' . $bindKey1 . ' AND :' . $bindKey2;

        return $key . ' ' . $exp . ' ' . $between;
    }

    /**
     * Exists查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseExists(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        // EXISTS 查询
        if ($value instanceof \Closure) {
            $value = $this->parseClosure($query, $value, false);
        }

        return $exp . ' (' . $value . ')';
    }

    /**
     * 时间比较查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseTime(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        return $key . ' ' . substr($exp, 0, 2) . ' ' . $this->parseDateTime($query, $value, $field, $bindName, $bindType);
    }

    /**
     * 大小比较查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseCompare(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        if (is_array($value)) {
            throw new Exception('where express error:' . $exp . var_export($value, true));
        }

        // 比较运算
        if ($value instanceof \Closure) {
            $value = $this->parseClosure($query, $value);
        }

        return $key . ' ' . $exp . ' ' . $value;
    }

    /**
     * 时间范围查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseBetweenTime(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return $key . ' ' . substr($exp, 0, -4)
        . $this->parseDateTime($query, $value[0], $field, $bindName . '_between_1', $bindType)
        . ' AND '
        . $this->parseDateTime($query, $value[1], $field, $bindName . '_between_2', $bindType);

    }

    /**
     * IN查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseIn(Query $query, $key, $exp, $value, $field, $bindName, $bindType)
    {
        // IN 查询
        if ($value instanceof \Closure) {
            $value = $this->parseClosure($query, $value, false);
        } else {
            $value = array_unique(is_array($value) ? $value : explode(',', $value));

            $bind  = [];
            $array = [];
            $i     = 0;

            foreach ($value as $k => $v) {
                $i++;
                if ($query->isBind($bindName . '_in_' . $i)) {
                    $bindKey = $bindName . '_in_' . uniqid() . '_' . $i;
                } else {
                    $bindKey = $bindName . '_in_' . $i;
                }
                $bind[$bindKey] = [$v, $bindType];
                $array[]        = ':' . $bindKey;
            }

            $zone = implode(',', $array);
            $query->bind($bind);

            $value = empty($zone) ? "''" : $zone;
        }

        return $key . ' ' . $exp . ' (' . $value . ')';
    }

    /**
     * 闭包子查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  \Closure  $call
     * @param  bool      $show
     * @return string
     */
    protected function parseClosure(Query $query, $call, $show = true)
    {
        $newQuery = $query->newQuery()->setConnection($this->connection);
        $call($newQuery);

        return $newQuery->buildSql($show);
    }

    /**
     * 日期时间条件解析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $value
     * @param  string    $key
     * @param  array     $options
     * @param  string    $bindName
     * @param  integer   $bindType
     * @return string
     */
    protected function parseDateTime(Query $query, $value, $key, $bindName = null, $bindType = null)
    {
        $options = $query->getOptions();

        // 获取时间字段类型
        if (strpos($key, '.')) {
            list($table, $key) = explode('.', $key);

            if (isset($options['alias']) && $pos = array_search($table, $options['alias'])) {
                $table = $pos;
            }
        } else {
            $table = $options['table'];
        }

        $type = $this->connection->getTableInfo($table, 'type');

        if (isset($type[$key])) {
            $info = $type[$key];
        }

        if (isset($info)) {
            if (is_string($value)) {
                $value = strtotime($value) ?: $value;
            }

            if (preg_match('/(datetime|timestamp)/is', $info)) {
                // 日期及时间戳类型
                $value = date('Y-m-d H:i:s', $value);
            } elseif (preg_match('/(date)/is', $info)) {
                // 日期及时间戳类型
                $value = date('Y-m-d', $value);
            }
        }

        $bindName = $bindName ?: $key;

        $query->bind($bindName, $value, $bindType);

        return ':' . $bindName;
    }

    /**
     * limit分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $limit
     * @return string
     */
    protected function parseLimit(Query $query, $limit)
    {
        return (!empty($limit) && false === strpos($limit, '(')) ? ' LIMIT ' . $limit . ' ' : '';
    }

    /**
     * join分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  array     $join
     * @return string
     */
    protected function parseJoin(Query $query, $join)
    {
        $joinStr = '';

        if (!empty($join)) {
            foreach ($join as $item) {
                list($table, $type, $on) = $item;

                $condition = [];

                foreach ((array) $on as $val) {
                    if (strpos($val, '=')) {
                        list($val1, $val2) = explode('=', $val, 2);
                        $condition[]       = $this->parseKey($query, $val1) . '=' . $this->parseKey($query, $val2);
                    } else {
                        $condition[] = $val;
                    }
                }

                $table = $this->parseTable($query, $table);

                $joinStr .= ' ' . $type . ' JOIN ' . $table . ' ON ' . implode(' AND ', $condition);
            }
        }

        return $joinStr;
    }

    /**
     * order分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $order
     * @return string
     */
    protected function parseOrder(Query $query, $order)
    {
        if (is_array($order)) {
            $array = [];

            foreach ($order as $key => $val) {
                if (is_numeric($key)) {
                    if ('[rand]' == $val) {
                        $array[] = $this->parseRand($query);
                    } elseif (false === strpos($val, '(')) {
                        $array[] = $this->parseKey($query, $val);
                    } else {
                        $array[] = $val;
                    }
                } else {
                    $sort    = in_array(strtolower(trim($val)), ['asc', 'desc']) ? ' ' . $val : '';
                    $array[] = $this->parseKey($query, $key) . ' ' . $sort;
                }
            }

            $order = implode(',', $array);
        }

        return !empty($order) ? ' ORDER BY ' . $order : '';
    }

    /**
     * group分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $group
     * @return string
     */
    protected function parseGroup(Query $query, $group)
    {
        return !empty($group) ? ' GROUP BY ' . $this->parseKey($query, $group) : '';
    }

    /**
     * having分析
     * @access protected
     * @param  Query  $query        查询对象
     * @param  string $having
     * @return string
     */
    protected function parseHaving(Query $query, $having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * comment分析
     * @access protected
     * @param  Query  $query        查询对象
     * @param  string $comment
     * @return string
     */
    protected function parseComment(Query $query, $comment)
    {
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

    /**
     * distinct分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $distinct
     * @return string
     */
    protected function parseDistinct(Query $query, $distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }

    /**
     * union分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $union
     * @return string
     */
    protected function parseUnion(Query $query, $union)
    {
        if (empty($union)) {
            return '';
        }

        $type = $union['type'];
        unset($union['type']);

        foreach ($union as $u) {
            if ($u instanceof \Closure) {
                $sql[] = $type . ' ' . $this->parseClosure($query, $u);
            } elseif (is_string($u)) {
                $sql[] = $type . ' ( ' . $this->connection->parseSqlTable($u) . ' )';
            }
        }

        return ' ' . implode(' ', $sql);
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $index
     * @return string
     */
    protected function parseForce(Query $query, $index)
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
     * 设置锁机制
     * @access protected
     * @param  Query         $query        查询对象
     * @param  bool|string   $lock
     * @return string
     */
    protected function parseLock(Query $query, $lock = false)
    {
        if (is_bool($lock)) {
            return $lock ? ' FOR UPDATE ' : '';
        } elseif (is_string($lock) && !empty($lock)) {
            return ' ' . trim($lock) . ' ';
        }
    }

    /**
     * 生成查询SQL
     * @access public
     * @param  Query  $query  查询对象
     * @return string
     */
    public function select(Query $query)
    {
        $options = $query->getOptions();

        return str_replace(
            ['%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%', '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%'],
            [
                $this->parseTable($query, $options['table']),
                $this->parseDistinct($query, $options['distinct']),
                $this->parseField($query, $options['field']),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseGroup($query, $options['group']),
                $this->parseHaving($query, $options['having']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseUnion($query, $options['union']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
                $this->parseForce($query, $options['force']),
            ],
            $this->selectSql);
    }

    /**
     * 生成Insert SQL
     * @access public
     * @param  Query     $query   查询对象
     * @param  bool      $replace 是否replace
     * @return string
     */
    public function insert(Query $query, $replace = false)
    {
        $options = $query->getOptions();

        // 分析并处理数据
        $data = $this->parseData($query, $options['data']);
        if (empty($data)) {
            return 0;
        }

        $fields = array_keys($data);
        $values = array_values($data);

        return str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseTable($query, $options['table']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertSql);
    }

    /**
     * 生成insertall SQL
     * @access public
     * @param  Query     $query   查询对象
     * @param  array     $dataSet 数据集
     * @param  bool      $replace 是否replace
     * @return string
     */
    public function insertAll(Query $query, $dataSet, $replace = false)
    {
        $options = $query->getOptions();

        // 获取合法的字段
        if ('*' == $options['field']) {
            $allowFields = $this->connection->getTableFields($options['table']);
        } else {
            $allowFields = $options['field'];
        }

        // 获取绑定信息
        $bind = $this->connection->getFieldsBind($options['table']);

        foreach ($dataSet as $k => $data) {
            $data = $this->parseData($query, $data, $allowFields, $bind, '_' . $k);

            $values[] = 'SELECT ' . implode(',', array_values($data));

            if (!isset($insertFields)) {
                $insertFields = array_keys($data);
            }
        }

        $fields = [];

        foreach ($insertFields as $field) {
            $fields[] = $this->parseKey($query, $field);
        }

        return str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseTable($query, $options['table']),
                implode(' , ', $fields),
                implode(' UNION ALL ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql);
    }

    /**
     * 生成slect insert SQL
     * @access public
     * @param  Query     $query  查询对象
     * @param  array     $fields 数据
     * @param  string    $table  数据表
     * @return string
     */
    public function selectInsert(Query $query, $fields, $table)
    {
        $options = $query->getOptions();

        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        foreach ($fields as &$field) {
            $field = $this->parseKey($query, $field);
        }

        return 'INSERT INTO ' . $this->parseTable($query, $table, $options) . ' (' . implode(',', $fields) . ') ' . $this->select($options);
    }

    /**
     * 生成update SQL
     * @access public
     * @param  Query     $query  查询对象
     * @return string
     */
    public function update(Query $query)
    {
        $options = $query->getOptions();

        $table = $this->parseTable($query, $options['table']);
        $data  = $this->parseData($query, $options['data']);

        if (empty($data)) {
            return '';
        }

        foreach ($data as $key => $val) {
            $set[] = $key . '=' . $val;
        }

        return str_replace(
            ['%TABLE%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                implode(',', $set),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->updateSql);
    }

    /**
     * 生成delete SQL
     * @access public
     * @param  Query  $query  查询对象
     * @return string
     */
    public function delete(Query $query)
    {
        $options = $query->getOptions();

        return str_replace(
            ['%TABLE%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                !empty($options['using']) ? ' USING ' . $this->parseTable($query, $options['using']) . ' ' : '',
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->deleteSql);
    }
}
