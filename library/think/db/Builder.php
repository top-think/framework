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
use think\Db;
use think\db\Connection;
use think\db\Query;
use think\Exception;

abstract class Builder
{
    // connection对象实例
    protected $connection;
    // 查询对象实例
    protected $query;

    // 查询参数
    protected $options = [];

    // 数据库表达式
    protected $exp = ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'egt' => '>=', 'lt' => '<', 'elt' => '<=', 'notlike' => 'NOT LIKE', 'like' => 'LIKE', 'in' => 'IN', 'exp' => 'EXP', 'notin' => 'NOT IN', 'not in' => 'NOT IN', 'between' => 'BETWEEN', 'not between' => 'NOT BETWEEN', 'notbetween' => 'NOT BETWEEN', 'exists' => 'EXISTS', 'notexists' => 'NOT EXISTS', 'not exists' => 'NOT EXISTS', 'null' => 'NULL', 'notnull' => 'NOT NULL', 'not null' => 'NOT NULL'];

    // SQL表达式
    protected $selectSql    = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%LOCK%%COMMENT%';
    protected $insertSql    = '%INSERT% INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';
    protected $insertAllSql = 'INSERT INTO %TABLE% (%FIELD%) %DATA% %COMMENT%';
    protected $updateSql    = 'UPDATE %TABLE% SET %SET% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';
    protected $deleteSql    = 'DELETE FROM %TABLE% %USING% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * 架构函数
     * @access public
     * @param Connection $connection 数据库连接对象实例
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * 设置当前的Query对象实例
     * @access protected
     * @param Query $query 当前查询对象实例
     * @return void
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    /**
     * 将SQL语句中的__TABLE_NAME__字符串替换成带前缀的表名（小写）
     * @access protected
     * @param string $sql sql语句
     * @return string
     */
    protected function parseSqlTable($sql)
    {
        return $this->query->parseSqlTable($sql);
    }

    /**
     * 数据分析
     * @access protected
     * @param array $data 数据
     * @param array $options 查询参数
     * @return array
     */
    protected function parseData($data, $options)
    {
        if (empty($data)) {
            return [];
        }

        // 获取绑定信息
        $bind = $this->query->getTableInfo($options['table'], 'bind');
        if ('*' == $options['field']) {
            $fields = array_keys($bind);
        } else {
            $fields = $options['field'];
        }

        $result = [];
        foreach ($data as $key => $val) {
            if (!in_array($key, $fields, true)) {
                if ($options['strict']) {
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
                    if ($this->query->isBind(substr($val, 1))) {
                        $result[$item] = $val;
                    } else {
                        $this->query->bind($key, $val, isset($bind[$key]) ? $bind[$key] : PDO::PARAM_STR);
                        $result[$item] = ':' . $key;
                    }
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
     * @return string|array
     */
    protected function parseValue($value)
    {
        if (is_string($value)) {
            $value = strpos($value, ':') === 0 && $this->query->isBind(substr($value, 1)) ? $value : $this->connection->quote($value);
        } elseif (is_array($value) && is_string($value[0]) && strtolower($value[0]) == 'exp') {
            $value = $value[1];
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
        if ('*' == $fields || empty($fields)) {
            $fieldsStr = '*';
        } elseif (is_array($fields)) {
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
        }
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
            $tables = $this->parseSqlTable($tables);
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
    protected function parseWhere($where, $table)
    {
        $whereStr = $this->buildWhere($where, $table);
        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * 生成查询条件SQL
     * @access public
     * @param mixed $where
     * @return string
     */
    public function buildWhere($where, $table)
    {
        if (empty($where)) {
            $where = [];
        }

        if ($where instanceof Query) {
            return $this->buildWhere($where->getOptions('where'), $table);
        }

        $whereStr = '';
        // 获取字段信息
        $fields = $this->query->getTableInfo($table, 'fields');
        $binds  = $this->query->getTableInfo($table, 'bind');
        foreach ($where as $key => $val) {
            $str = [];
            foreach ($val as $field => $value) {
                if ($fields && in_array($field, $fields, true) && is_scalar($value) && !$this->query->isBind($field)) {
                    $this->query->bind($field, $value, isset($binds[$field]) ? $binds[$field] : PDO::PARAM_STR);
                    $value = ':' . $field;
                }
                if ($value instanceof \Closure) {
                    // 使用闭包查询
                    $query = new Query($this->connection);
                    call_user_func_array($value, [ & $query]);
                    $str[] = ' ' . $key . ' ( ' . $this->buildWhere($query->getOptions('where'), $table) . ' )';
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

            $whereStr .= empty($whereStr) ? substr(implode(' ', $str), strlen($key) + 1) : implode(' ', $str);
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
            $item = array_pop($val);
            // 传入 or 或者 and
            if (is_string($item) && in_array($item, ['AND', 'and', 'OR', 'or'])) {
                $rule = $item;
            } else {
                array_push($val, $item);
            }
            foreach ($val as $item) {
                $str[] = $this->parseWhereItem($key, $item, $rule);
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
            $whereStr .= '( ' . $key . ' ' . $value . ' )';
        } elseif (in_array($exp, ['NOT NULL', 'NULL'])) {
            // NULL 查询
            $whereStr .= $key . ' IS ' . $exp;
        } elseif (in_array($exp, ['NOT IN', 'IN'])) {
            // IN 查询
            if ($value instanceof \Closure) {
                $whereStr .= $key . ' ' . $exp . ' ' . $this->parseClosure($value);
            } else {
                $value = is_array($value) ? $value : explode(',', $value);
                $zone  = implode(',', $this->parseValue($value));
                $whereStr .= $key . ' ' . $exp . ' (' . $zone . ')';
            }
        } elseif (in_array($exp, ['NOT BETWEEN', 'BETWEEN'])) {
            // BETWEEN 查询
            $data = is_array($value) ? $value : explode(',', $value);
            $whereStr .= $key . ' ' . $exp . ' ' . $this->parseValue($data[0]) . ' AND ' . $this->parseValue($data[1]);
        } elseif (in_array($exp, ['NOT EXISTS', 'EXISTS'])) {
            // EXISTS 查询
            if ($value instanceof \Closure) {
                $whereStr .= $exp . ' ' . $this->parseClosure($value);
            } else {
                $whereStr .= $exp . ' (' . $value . ')';
            }
        }
        return $whereStr;
    }

    // 执行闭包子查询
    protected function parseClosure($call, $show = true)
    {
        $query = new Query($this->connection);
        call_user_func_array($call, [ & $query]);
        return $query->buildSql($show);
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
     * 设置锁机制
     * @access protected
     * @param bool $locl
     * @return string
     */
    protected function parseLock($lock = false)
    {
        return $lock ? ' FOR UPDATE ' : '';
    }

    /**
     * 生成查询SQL
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function select($options = [])
    {
        $sql = str_replace(
            ['%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%', '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%'],
            [
                $this->parseTable($options['table']),
                $this->parseDistinct($options['distinct']),
                $this->parseField($options['field']),
                $this->parseJoin($options['join']),
                $this->parseWhere($options['where'], $options['table']),
                $this->parseGroup($options['group']),
                $this->parseHaving($options['having']),
                $this->parseOrder($options['order']),
                $this->parseLimit($options['limit']),
                $this->parseUnion($options['union']),
                $this->parseLock($options['lock']),
                $this->parseComment($options['comment']),
                $this->parseForce($options['force']),
            ], $this->selectSql);
        return $sql;
    }

    /**
     * 生成insert SQL
     * @access public
     * @param array $data 数据
     * @param array $options 表达式
     * @param bool $replace 是否replace
     * @return string
     */
    public function insert(array $data, $options = [], $replace = false)
    {
        // 分析并处理数据
        $data = $this->parseData($data, $options);
        if (empty($data)) {
            return 0;
        }
        $fields = array_keys($data);
        $values = array_values($data);

        $sql = str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseTable($options['table']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($options['comment']),
            ], $this->insertSql);

        return $sql;
    }

    /**
     * 生成insertall SQL
     * @access public
     * @param array $dataSet 数据集
     * @param array $options 表达式
     * @return string
     */
    public function insertAll($dataSet, $options)
    {
        // 获取合法的字段
        if ('*' == $options['field']) {
            $fields = $this->query->getTableInfo($options['table'], 'fields');
        } else {
            $fields = $options['field'];
        }

        foreach ($dataSet as &$data) {
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields, true)) {
                    if ($options['strict']) {
                        throw new Exception(' fields not exists :[' . $key . ']');
                    }
                    unset($data[$key]);
                } elseif (is_scalar($val)) {
                    $data[$key] = $this->parseValue($val);
                } else {
                    // 过滤掉非标量数据
                    unset($data[$key]);
                }
            }
            $value    = array_values($data);
            $values[] = 'SELECT ' . implode(',', $value);
        }
        $fields = array_map([$this, 'parseKey'], array_keys($dataSet[0]));
        $sql    = str_replace(
            ['%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $this->parseTable($options['table']),
                implode(' , ', $fields),
                implode(' UNION ALL ', $values),
                $this->parseComment($options['comment']),
            ], $this->insertAllSql);

        return $sql;
    }

    /**
     * 生成slectinsert SQL
     * @access public
     * @param array $fields 数据
     * @param string $table 数据表
     * @param array $options 表达式
     * @return string
     */
    public function selectInsert($fields, $table, $options)
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        $fields = array_map([$this, 'parseKey'], $fields);
        $sql    = 'INSERT INTO ' . $this->parseTable($table) . ' (' . implode(',', $fields) . ') ' . $this->select($options);
        return $sql;
    }

    /**
     * 生成update SQL
     * @access public
     * @param array $fields 数据
     * @param array $options 表达式
     * @return string
     */
    public function update($data, $options)
    {
        $table = $this->parseTable($options['table']);
        $data  = $this->parseData($data, $options);
        if (empty($data)) {
            return '';
        }
        foreach ($data as $key => $val) {
            $set[] = $key . '=' . $val;
        }

        $sql = str_replace(
            ['%TABLE%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($options['table']),
                implode(',', $set),
                $this->parseJoin($options['join']),
                $this->parseWhere($options['where'], $options['table']),
                $this->parseOrder($options['order']),
                $this->parseLimit($options['limit']),
                $this->parseLimit($options['lock']),
                $this->parseComment($options['comment']),
            ], $this->updateSql);

        return $sql;
    }

    /**
     * 生成delete SQL
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function delete($options)
    {
        $sql = str_replace(
            ['%TABLE%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($options['table']),
                !empty($options['using']) ? ' USING ' . $this->parseTable($options['using']) . ' ' : '',
                $this->parseJoin($options['join']),
                $this->parseWhere($options['where'], $options['table']),
                $this->parseOrder($options['order']),
                $this->parseLimit($options['limit']),
                $this->parseLimit($options['lock']),
                $this->parseComment($options['comment']),
            ], $this->deleteSql);

        return $sql;
    }
}
