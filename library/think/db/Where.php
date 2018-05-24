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

class Where implements \JsonSerializable, \ArrayAccess
{
    /**
     * 查询表达式
     * @var array
     */
    protected $where = [];

    /**
     * 当前参数绑定
     * @var array
     */
    protected $bind = [];

    /**
     * 创建一个查询表达式
     *
     * @param  array  $where
     * @return void
     */
    public function __construct(array $where)
    {
        $this->where = $where;
    }

    public function getValue()
    {
        return $this->where;
    }

    /**
     * 参数绑定
     * @access public
     * @param  mixed   $key   参数名
     * @param  mixed   $value 绑定变量值
     * @param  integer $type  绑定类型
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
     * @param  string $key 参数名
     * @return bool
     */
    public function isBind(string $key)
    {
        return isset($this->bind[$key]);
    }

    /**
     * 指定AND查询条件
     * @access public
     * @param  mixed $field     查询字段
     * @param  mixed $op        查询表达式
     * @param  mixed $condition 查询条件
     * @return $this
     */
    public function where($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('AND', $field, $op, $condition, $param);
    }

    /**
     * 指定OR查询条件
     * @access public
     * @param  mixed $field     查询字段
     * @param  mixed $op        查询表达式
     * @param  mixed $condition 查询条件
     * @return $this
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('OR', $field, $op, $condition, $param);
    }

    /**
     * 指定XOR查询条件
     * @access public
     * @param  mixed $field     查询字段
     * @param  mixed $op        查询表达式
     * @param  mixed $condition 查询条件
     * @return $this
     */
    public function whereXor($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('XOR', $field, $op, $condition, $param);
    }

    /**
     * 指定Null查询条件
     * @access public
     * @param  mixed  $field 查询字段
     * @param  string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNull(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NULL', null, [], true);
    }

    /**
     * 指定NotNull查询条件
     * @access public
     * @param  mixed  $field 查询字段
     * @param  string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNotNull(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOTNULL', null, [], true);
    }

    /**
     * 指定Exists查询条件
     * @access public
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereExists($condition, string $logic = 'AND')
    {
        if (is_string($condition)) {
            $condition = $this->raw($condition);
        }

        $this->where[strtoupper($logic)][] = ['', 'EXISTS', $condition];
        return $this;
    }

    /**
     * 指定NotExists查询条件
     * @access public
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotExists($condition, string $logic = 'AND')
    {
        if (is_string($condition)) {
            $condition = $this->raw($condition);
        }

        $this->where[strtoupper($logic)][] = ['', 'NOT EXISTS', $condition];
        return $this;
    }

    /**
     * 指定In查询条件
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereIn(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'IN', $condition, [], true);
    }

    /**
     * 指定NotIn查询条件
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotIn(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT IN', $condition, [], true);
    }

    /**
     * 指定Like查询条件
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereLike(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'LIKE', $condition, [], true);
    }

    /**
     * 指定NotLike查询条件
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotLike(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT LIKE', $condition, [], true);
    }

    /**
     * 指定Between查询条件
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereBetween(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'BETWEEN', $condition, [], true);
    }

    /**
     * 指定NotBetween查询条件
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotBetween(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT BETWEEN', $condition, [], true);
    }

    /**
     * 比较两个字段
     * @access public
     * @param  string    $field1     查询字段
     * @param  string    $operator   比较操作符
     * @param  string    $field2     比较字段
     * @param  string    $logic      查询逻辑 and or xor
     * @return $this
     */
    public function whereColumn(string $field1, string $operator,  string $field2 = null, string $logic = 'AND')
    {
        if (is_array($field1)) {
            foreach ($field1 as $item) {
                $this->whereColumn($item[0], $item[1], isset($item[2]) ? $item[2] : null);
            }
            return $this;
        }

        if (is_null($field2)) {
            $field2   = $operator;
            $operator = '=';
        }

        return $this->parseWhereExp($logic, $field1, 'COLUMN', [$operator, $field2], [], true);
    }

    /**
     * 指定Exp查询条件
     * @access public
     * @param  mixed  $field     查询字段
     * @param  string $condition 查询条件
     * @param  array  $bind      参数绑定
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereExp(string $field, $condition, $bind = [], string $logic = 'AND')
    {
        $this->where[$logic][] = [$field, 'EXP', $this->raw($condition)];

        if ($bind) {
            $this->bind($bind);
        }
        return $this;
    }

    /**
     * 指定表达式查询条件
     * @access public
     * @param  string $where  查询条件
     * @param  array  $bind   参数绑定
     * @param  string $logic  查询逻辑 and or xor
     * @return $this
     */
    public function whereRaw(string $where, $bind = [], string $logic = 'AND')
    {
        $this->where[$logic][] = $this->raw($where);

        if ($bind) {
            $this->bind($bind);
        }

        return $this;
    }

    /**
     * 指定表达式查询条件 OR
     * @access public
     * @param  string $where  查询条件
     * @param  array  $bind   参数绑定
     * @return $this
     */
    public function whereOrRaw(string $where, $bind = [])
    {
        return $this->whereRaw($where, $bind, 'OR');
    }

    /**
     * 分析查询表达式
     * @access protected
     * @param  string   $logic     查询逻辑 and or xor
     * @param  mixed    $field     查询字段
     * @param  mixed    $op        查询表达式
     * @param  mixed    $condition 查询条件
     * @param  array    $param     查询参数
     * @param  bool     $strict    严格模式
     * @return $this
     */
    protected function parseWhereExp(string $logic, $field, $op, $condition, array $param = [], bool $strict = false)
    {
        $logic = strtoupper($logic);

        if ($field instanceof Expression) {
            return $this->whereRaw($field, is_array($op) ? $op : []);
        } elseif ($strict) {
            // 使用严格模式查询
            $where = [$field, $op, $condition];
        } elseif (is_array($field)) {
            // 解析数组批量查询
            return $this->parseArrayWhereItems($field, $logic);
        } elseif ($field instanceof \Closure) {
            $where = $field;
            $field = '';
        } elseif (is_string($field)) {
            if (preg_match('/[,=\<\'\"\(\s]/', $field)) {
                return $this->whereRaw($field, $op);
            } elseif (is_string($op) && strtolower($op) == 'exp') {
                $bind = isset($param[2]) && is_array($param[2]) ? $param[2] : null;
                return $this->whereExp($field, $condition, $bind, $logic);
            }

            $where = $this->parseWhereItem($logic, $field, $op, $condition, $param);
        }

        if (!empty($where)) {
            $this->where[$logic][$field] = $where;
        }

        return $this;
    }

    /**
     * 分析查询表达式
     * @access protected
     * @param  string   $logic     查询逻辑 and or xor
     * @param  mixed    $field     查询字段
     * @param  mixed    $op        查询表达式
     * @param  mixed    $condition 查询条件
     * @param  array    $param     查询参数
     * @return mixed
     */
    protected function parseWhereItem(string $logic, $field, $op, $condition, array $param = [])
    {
        if (is_array($op)) {
            // 同一字段多条件查询
            array_unshift($param, $field);
            $where = $param;
        } elseif ($field && is_null($condition)) {
            if (in_array(strtoupper($op), ['NULL', 'NOTNULL', 'NOT NULL'], true)) {
                // null查询
                $where = [$field, $op, ''];
            } elseif (in_array($op, ['=', 'eq', 'EQ', null], true)) {
                $where = [$field, 'NULL', ''];
            } elseif (in_array($op, ['<>', 'neq', 'NEQ'], true)) {
                $where = [$field, 'NOTNULL', ''];
            } else {
                // 字段相等查询
                $where = [$field, '=', $op];
            }
        } elseif (in_array(strtoupper($op), ['REGEXP', 'NOT REGEXP', 'EXISTS', 'NOT EXISTS', 'NOTEXISTS'], true)) {
            $where = [$field, $op, is_string($condition) ? $this->raw($condition) : $condition];
        } else {
            $where = $field ? [$field, $op, $condition, isset($param[2]) ? $param[2] : null] : null;
        }

        return $where;
    }

    /**
     * 数组批量查询
     * @access protected
     * @param  array    $field     批量查询
     * @param  string   $logic     查询逻辑 and or xor
     * @return $this
     */
    protected function parseArrayWhereItems($field, string $logic)
    {
        if (key($field) !== 0) {
            $where = [];
            foreach ($field as $key => $val) {
                $where[$key] = is_null($val) ? [$key, 'NULL', ''] : [$key, '=', $val];
            }
        } else {
            // 数组批量查询
            $where = $field;
        }

        if (!empty($where)) {
            $this->where[$logic] = isset($this->where[$logic]) ? array_merge($this->where[$logic], $where) : $where;
        }

        return $this;
    }

    /**
     * 修改器 设置数据对象的值
     * @access public
     * @param  string $name  名称
     * @param  mixed  $value 值
     * @return void
     */
    public function __set($name, $value)
    {
        $this->where['AND'][$name] = $value;
    }

    /**
     * 获取器 获取数据对象的值
     * @access public
     * @param  string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return $this->where['AND'][$name] ?? null;
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param  string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        foreach ($this->where as $logic => $where) {
            if (isset($where[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 销毁数据对象的值
     * @access public
     * @param  string $name 名称
     * @return void
     */
    public function __unset($name)
    {
        foreach ($this->where as $logic => $where) {
            if (isset($where[$name])) {
                unset($this->where[$logic][$name]);
            }
        }
    }

    // ArrayAccess
    public function offsetSet($name, $value)
    {
        $this->__set($name, $value);
    }

    public function offsetExists($name)
    {
        return $this->__isset($name);
    }

    public function offsetUnset($name)
    {
        $this->__unset($name);
    }

    public function offsetGet($name)
    {
        return $this->__get($name);
    }

    // JsonSerializable
    public function jsonSerialize()
    {
        return json_encode($this->where);
    }

    public function __toString()
    {
        return $this->jsonSerialize();
    }
}
