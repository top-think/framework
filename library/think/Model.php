<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use InvalidArgumentException;
use think\db\Query;
use think\model\Collection as ModelCollection;
use think\model\Relation;

/**
 * Class Model
 * @package think
 * @mixin Query
 */
abstract class Model implements \JsonSerializable, \ArrayAccess
{
    use model\concern\RelationShip;
    use model\concern\ModelEvent;
    use model\concern\Getter;
    use model\concern\Setter;
    use model\concern\TimeStamp;
    use model\concern\Scope;
    use model\concern\Serialize;
    use model\concern\ValidateData;

    // 数据库对象池
    private static $links = [];
    // 当前数据库实例
    private static $db;
    // 是否为更新数据
    private $isUpdate = false;
    // 更新条件
    private $updateWhere;
    // 当前数据
    private $data = [];
    // 原始数据
    private $origin = [];
    // 数据库配置
    protected $connection = [];
    // 数据库查询对象
    protected $query;
    // 当前模型名称
    protected $name;
    // 数据表名称
    protected $table;
    // 当前类名称
    protected $class;

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk;
    // 数据表字段信息 留空则自动获取
    protected $field = [];
    // 只读字段
    protected $readonly = [];
    // 保存自动完成列表
    protected $auto = [];
    // 新增自动完成列表
    protected $insert = [];
    // 更新自动完成列表
    protected $update = [];

    // 字段类型或者格式转换
    protected $type = [];

    // 全局查询范围
    protected $useGlobalScope = true;

    // 查询数据集对象
    protected $resultSetType;

    /**
     * 初始化过的模型.
     *
     * @var array
     */
    protected static $initialized = [];

    /**
     * 架构函数
     * @access public
     * @param array|object $data 数据
     */
    public function __construct($data = [])
    {
        if (is_object($data)) {
            $this->data = get_object_vars($data);
        } else {
            $this->data = $data;
        }

        // 记录原始数据
        $this->origin = $this->data;

        // 当前类名
        $this->class = get_class($this);

        if (empty($this->name)) {
            // 当前模型名
            $name       = str_replace('\\', '/', $this->class);
            $this->name = basename($name);
            if (Facade::make('config')->get('class_suffix')) {
                $suffix     = basename(dirname($name));
                $this->name = substr($this->name, 0, -strlen($suffix));
            }
        }

        if (is_null($this->autoWriteTimestamp)) {
            // 自动写入时间戳
            $this->autoWriteTimestamp = $this->db(false)->getConfig('auto_timestamp');
        }

        if (is_null($this->dateFormat)) {
            // 设置时间戳格式
            $this->dateFormat = $this->db(false)->getConfig('datetime_format');
        }

        if (is_null($this->resultSetType)) {
            $this->resultSetType = $this->db(false)->getConfig('resultset_type');
        }

        // 执行初始化操作
        $this->initialize();
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @param bool $baseQuery 是否调用全局查询范围
     * @return Query
     */
    public function db($baseQuery = true)
    {
        $model = $this->class;

        if (!isset(self::$links[$model])) {
            // 合并数据库配置
            if (!empty($this->connection)) {
                if (is_array($this->connection)) {
                    $connection = array_merge(Facade::make('config')->pull('database'), $this->connection);
                } else {
                    $connection = $this->connection;
                }
            } else {
                $connection = [];
            }

            // 设置当前模型 确保查询返回模型对象
            $query = Db::connect($connection)->getQuery($model, $this->query);

            // 设置当前数据表和模型名
            if (!empty($this->table)) {
                $query->setTable($this->table);
            } else {
                $query->name($this->name);
            }

            if (!empty($this->pk)) {
                $query->pk($this->pk);
            }

            self::$links[$model] = $query;
        }

        // 全局作用域
        if ($baseQuery && method_exists($this, 'base')) {
            call_user_func_array([$this, 'base'], [ & self::$links[$model]]);
        }

        // 返回当前模型的数据库查询对象
        return self::$links[$model];
    }

    /**
     *  初始化模型
     * @access protected
     * @return void
     */
    protected function initialize()
    {
        $class = get_class($this);

        if (!isset(static::$initialized[$class])) {
            static::$initialized[$class] = true;
            static::init();
        }
    }

    /**
     * 初始化处理
     * @access protected
     * @return void
     */
    protected static function init()
    {}

    /**
     * 设置数据对象值
     * @access public
     * @param mixed $data  数据或者属性名
     * @param mixed $value 值
     * @return $this
     */
    public function data($data, $value = null)
    {
        if (is_string($data)) {
            $this->data[$data] = $value;
        } else {
            // 清空数据
            $this->data = [];

            if (is_object($data)) {
                $data = get_object_vars($data);
            }

            if (true === $value) {
                // 数据对象赋值
                foreach ($data as $key => $value) {
                    $this->setAttr($key, $value, $data);
                }
            } else {
                $this->data = $data;
            }
        }

        return $this;
    }

    /**
     * 批量设置数据对象值
     * @access public
     * @param mixed $data  数据
     * @param bool  $set   是否需要进行数据处理
     * @return $this
     */
    public function appendData($data, $set = false)
    {
        if ($set) {
            // 进行数据处理
            foreach ($data as $key => $value) {
                $this->setAttr($key, $value, $data);
            }
        } else {
            if (is_object($data)) {
                $data = get_object_vars($data);
            }

            $this->data = array_merge($this->data, $data);
        }

        return $this;
    }

    /**
     * 获取对象原始数据 如果不存在指定字段返回null
     * @access public
     * @param string $name 字段名 留空获取全部
     * @return mixed
     */
    public function getOrigin($name = null)
    {
        if (is_null($name)) {
            return $this->origin;
        } else {
            return array_key_exists($name, $this->origin) ? $this->origin[$name] : null;
        }
    }

    /**
     * 获取对象原始数据 如果不存在指定字段返回false
     * @access public
     * @param string $name 字段名 留空获取全部
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getData($name = null)
    {
        if (is_null($name)) {
            return $this->data;
        } elseif (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        } elseif (array_key_exists($name, $this->relation)) {
            return $this->relation[$name];
        } else {
            throw new InvalidArgumentException('property not exists:' . $this->class . '->' . $name);
        }
    }

    /**
     * 获取变化的数据
     * @access public
     * @return array
     */
    public function getChangeData()
    {
        return array_udiff_assoc($this->data, $this->origin, function ($a, $b) {
            return $a === $b ? 0 : 1;
        });
    }

    /**
     * 转换当前模型数据集为数据集对象
     * @access public
     * @param array|\think\Collection $collection 数据集
     * @return \think\Collection
     */
    public function toCollection($collection)
    {
        if ($this->resultSetType && false !== strpos($this->resultSetType, '\\')) {
            $class      = $this->resultSetType;
            $collection = new $class($collection);
        } else {
            $collection = new ModelCollection($collection);
        }

        return $collection;
    }

    /**
     * 获取模型对象的主键
     * @access public
     * @param string $name 模型名
     * @return mixed
     */
    public function getPk($name = '')
    {
        if (!empty($name)) {
            $table = $this->db(false)->getTable($name);

            return $this->db(false)->getPk($table);
        } elseif (empty($this->pk)) {
            $this->pk = $this->db(false)->getPk();
        }

        return $this->pk;
    }

    /**
     * 判断一个字段名是否为主键字段
     * @access public
     * @param string $key 名称
     * @return bool
     */
    protected function isPk($key)
    {
        $pk = $this->getPk();
        if (is_string($pk) && $pk == $key) {
            return true;
        } elseif (is_array($pk) && in_array($key, $pk)) {
            return true;
        }

        return false;
    }

    /**
     * 保存当前数据对象
     * @access public
     * @param array  $data     数据
     * @param array  $where    更新条件
     * @param string $sequence 自增序列名
     * @return integer|false
     */
    public function save($data = [], $where = [], $sequence = null)
    {
        if (!empty($data)) {
            // 数据自动验证
            if (!$this->validateData($data)) {
                return false;
            }
            // 数据对象赋值
            foreach ($data as $key => $value) {
                $this->setAttr($key, $value, $data);
            }
            if (!empty($where)) {
                $this->isUpdate = true;
            }
        }

        // 自动关联写入
        if (!empty($this->relationWrite)) {
            $relation = [];
            foreach ($this->relationWrite as $key => $name) {
                if (!is_numeric($key)) {
                    $relation[$key] = [];

                    foreach ($name as $val) {
                        if (isset($this->data[$val])) {
                            $relation[$key][$val] = $this->data[$val];
                            unset($this->data[$val]);
                        }
                    }
                } elseif (isset($this->data[$name])) {
                    $relation[$name] = $this->data[$name];

                    if (!$this->isUpdate) {
                        unset($this->data[$name]);
                    }
                }
            }
        }

        // 检测字段
        if (!empty($this->field)) {
            if (true === $this->field) {
                $this->field = $this->db(false)->getTableInfo('', 'fields');
            }
            foreach ($this->data as $key => $val) {
                if (!in_array($key, $this->field)) {
                    unset($this->data[$key]);
                }
            }
        }

        // 数据自动完成
        $this->autoCompleteData($this->auto);

        // 事件回调
        if (false === $this->trigger('before_write')) {
            return false;
        }

        $pk = $this->getPk();

        if ($this->isUpdate) {
            // 自动更新
            $this->autoCompleteData($this->update);

            // 事件回调
            if (false === $this->trigger('before_update')) {
                return false;
            }

            // 去除没有更新的字段
            $data = $this->getChangeData();

            // 保留主键数据
            foreach ($this->data as $key => $val) {
                if ($this->isPk($key)) {
                    $data[$key] = $val;
                }
            }

            if (!empty($this->readonly)) {
                // 只读字段不允许更新
                foreach ($this->readonly as $key => $field) {
                    if (isset($data[$field])) {
                        unset($data[$field]);
                    }
                }
            }

            if (empty($data) || (count($data) == 1 && is_string($pk) && isset($data[$pk]))) {
                // 没有更新
                return 0;
            } elseif ($this->autoWriteTimestamp && $this->updateTime && !isset($data[$this->updateTime])) {
                // 自动写入更新时间
                $data[$this->updateTime] = $this->autoWriteTimestamp($this->updateTime);

                $this->data[$this->updateTime] = $data[$this->updateTime];
            }

            if (empty($where) && !empty($this->updateWhere)) {
                $where = $this->updateWhere;
            }

            if (is_string($pk) && isset($data[$pk])) {
                if (!isset($where[$pk])) {
                    unset($where);
                    $where[$pk] = $data[$pk];
                }
                unset($data[$pk]);
            }

            // 关联更新
            if (isset($relation)) {
                foreach ($relation as $name => $val) {
                    if (isset($data[$name])) {
                        unset($data[$name]);
                    }
                }
            }

            // 模型更新
            $result = $this->db()->where($where)->update($data);

            // 关联更新
            if (isset($relation)) {
                foreach ($relation as $name => $val) {
                    if ($val instanceof Model) {
                        $val->save();
                    } else {
                        unset($this->data[$name]);
                        $model = $this->getAttr($name);
                        if ($model instanceof Model) {
                            $model->save($val);
                        }
                    }
                }
            }

            // 更新回调
            $this->trigger('after_update');
        } else {
            // 自动写入
            $this->autoCompleteData($this->insert);
            // 自动写入创建时间和更新时间
            if ($this->autoWriteTimestamp) {
                if ($this->createTime && !isset($this->data[$this->createTime])) {
                    $this->data[$this->createTime] = $this->autoWriteTimestamp($this->createTime);
                }
                if ($this->updateTime && !isset($this->data[$this->updateTime])) {
                    $this->data[$this->updateTime] = $this->autoWriteTimestamp($this->updateTime);
                }
            }

            if (false === $this->trigger('before_insert')) {
                return false;
            }

            $result = $this->db()->insert($this->data);

            // 获取自动增长主键
            if ($result && is_string($pk) && (!isset($this->data[$pk]) || '' == $this->data[$pk])) {
                $insertId = $this->db()->getLastInsID($sequence);
                if ($insertId) {
                    $this->data[$pk] = $insertId;
                }
            }

            // 关联写入
            if (isset($relation)) {
                foreach ($relation as $name => $val) {
                    $method = Loader::parseName($name, 1, false);
                    $this->$method()->save($val);
                }
            }

            // 标记为更新
            $this->isUpdate = true;
            // 新增回调
            $this->trigger('after_insert');
        }
        // 写入回调
        $this->trigger('after_write');

        // 记录原始数据
        $this->origin = $this->data;

        return $result;
    }

    /**
     * 保存多个数据到当前数据对象
     * @access public
     * @param array   $dataSet 数据
     * @param boolean $replace 是否自动识别更新和写入
     * @return array|false
     * @throws \Exception
     */
    public function saveAll($dataSet, $replace = true)
    {
        if ($this->validate) {
            // 数据批量验证
            $validate = $this->validate;

            foreach ($dataSet as $data) {
                if (!$this->validateData($data, $validate)) {
                    return false;
                }
            }
        }

        $result = [];

        $db = $this->db();

        $db->startTrans();

        try {
            $pk = $this->getPk();

            if (is_string($pk) && $replace) {
                $auto = true;
            }

            foreach ($dataSet as $key => $data) {
                if (!empty($auto) && isset($data[$pk])) {
                    $result[$key] = self::update($data, [], $this->field);
                } else {
                    $result[$key] = self::create($data, $this->field);
                }
            }

            $db->commit();

            return $result;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 设置允许写入的字段
     * @access public
     * @param mixed $field 允许写入的字段 如果为true只允许写入数据表字段
     * @return $this
     */
    public function allowField($field)
    {
        if (is_string($field)) {
            $field = explode(',', $field);
        }

        $this->field = $field;

        return $this;
    }

    /**
     * 设置只读字段
     * @access public
     * @param mixed $field 只读字段
     * @return $this
     */
    public function readonly($field)
    {
        if (is_string($field)) {
            $field = explode(',', $field);
        }

        $this->readonly = $field;

        return $this;
    }

    /**
     * 是否为更新数据
     * @access public
     * @param bool  $update
     * @param mixed $where
     * @return $this
     */
    public function isUpdate($update = true, $where = null)
    {
        $this->isUpdate = $update;

        if (!empty($where)) {
            $this->updateWhere = $where;
        }

        return $this;
    }

    /**
     * 数据自动完成
     * @access public
     * @param array $auto 要自动更新的字段列表
     * @return void
     */
    protected function autoCompleteData($auto = [])
    {
        foreach ($auto as $field => $value) {
            if (is_integer($field)) {
                $field = $value;
                $value = null;
            }

            if (!isset($this->data[$field])) {
                $this->setAttr($field, !is_null($value) ? $value : null);
            } elseif (isset($this->origin[$field]) && $this->data[$field] === $this->origin[$field]) {
                $this->setAttr($field, !is_null($value) ? $value : $this->data[$field]);
            }
        }
    }

    /**
     * 删除当前的记录
     * @access public
     * @return integer
     */
    public function delete()
    {
        if (false === $this->trigger('before_delete')) {
            return false;
        }

        // 删除条件
        $pk = $this->getPk();

        if (is_string($pk) && isset($this->data[$pk])) {
            $where = [$pk => $this->data[$pk]];
        } elseif (!empty($this->updateWhere)) {
            $where = $this->updateWhere;
        } else {
            $where = null;
        }

        // 删除当前模型数据
        $result = $this->db()->where($where)->delete();

        // 关联删除
        if (!empty($this->relationWrite)) {
            foreach ($this->relationWrite as $key => $name) {
                $name  = is_numeric($key) ? $name : $key;
                $model = $this->getAttr($name);
                if ($model instanceof Model) {
                    $model->delete();
                }
            }
        }

        $this->trigger('after_delete');

        // 清空原始数据
        $this->origin = [];

        return $result;
    }

    /**
     * 设置自动完成的字段（ 规则通过修改器定义）
     * @access public
     * @param array $fields 需要自动完成的字段
     * @return $this
     */
    public function auto($fields)
    {
        $this->auto = $fields;

        return $this;
    }

    /**
     * 写入数据
     * @access public
     * @param array      $data  数据数组
     * @param array|true $field 允许字段
     * @return $this
     */
    public static function create($data = [], $field = null)
    {
        $model = new static();

        if (!empty($field)) {
            $model->allowField($field);
        }

        $model->isUpdate(false)->save($data, []);

        return $model;
    }

    /**
     * 更新数据
     * @access public
     * @param array      $data  数据数组
     * @param array      $where 更新条件
     * @param array|true $field 允许字段
     * @return $this
     */
    public static function update($data = [], $where = [], $field = null)
    {
        $model = new static();

        if (!empty($field)) {
            $model->allowField($field);
        }

        $result = $model->isUpdate(true)->save($data, $where);

        return $model;
    }

    /**
     * 查找单条记录
     * @access public
     * @param mixed        $data  主键值或者查询条件（闭包）
     * @param array|string $with  关联预查询
     * @param bool         $cache 是否缓存
     * @return static
     * @throws exception\DbException
     */
    public static function get($data, $with = [], $cache = false)
    {
        if (is_null($data)) {
            return;
        }

        if (true === $with || is_int($with)) {
            $cache = $with;
            $with  = [];
        }

        $query = static::parseQuery($data, $with, $cache);

        return $query->find($data);
    }

    /**
     * 查找所有记录
     * @access public
     * @param mixed        $data  主键列表或者查询条件（闭包）
     * @param array|string $with  关联预查询
     * @param bool         $cache 是否缓存
     * @return static[]|false
     * @throws exception\DbException
     */
    public static function all($data = null, $with = [], $cache = false)
    {
        if (true === $with || is_int($with)) {
            $cache = $with;
            $with  = [];
        }

        $query = static::parseQuery($data, $with, $cache);

        return $query->select($data);
    }

    /**
     * 分析查询表达式
     * @access public
     * @param mixed  $data  主键列表或者查询条件（闭包）
     * @param string $with  关联预查询
     * @param bool   $cache 是否缓存
     * @return Query
     */
    protected static function parseQuery(&$data, $with, $cache)
    {
        $result = self::with($with)->cache($cache);

        if (is_array($data) && key($data) !== 0) {
            $result = $result->where($data);
            $data   = null;
        } elseif ($data instanceof \Closure) {
            $data($result);
            $data = null;
        } elseif ($data instanceof Query) {
            $result = $data->with($with)->cache($cache);
            $data   = null;
        }

        return $result;
    }

    /**
     * 删除记录
     * @access public
     * @param mixed $data 主键列表 支持闭包查询条件
     * @return integer 成功删除的记录数
     */
    public static function destroy($data)
    {
        $model = new static();

        $query = $model->db();
        if (is_array($data) && key($data) !== 0) {
            $query->where($data);
            $data = null;
        } elseif ($data instanceof \Closure) {
            $data($query);
            $data = null;
        } elseif (is_null($data)) {
            return 0;
        }

        $resultSet = $query->select($data);
        $count     = 0;

        if ($resultSet) {
            foreach ($resultSet as $data) {
                $result = $data->delete();
                $count += $result;
            }
        }

        return $count;
    }

    /**
     * 解析模型的完整命名空间
     * @access public
     * @param string $model 模型名（或者完整类名）
     * @return string
     */
    protected function parseModel($model)
    {
        if (false === strpos($model, '\\')) {
            $path = explode('\\', get_called_class());
            array_pop($path);
            array_push($path, Loader::parseName($model, 1));
            $model = implode('\\', $path);
        }

        return $model;
    }

    public function __call($method, $args)
    {
        if (isset(static::$db)) {
            $query      = static::$db;
            static::$db = null;
        } else {
            $query = $this->db();
        }

        if (method_exists($this, 'scope' . $method)) {
            // 动态调用命名范围
            $method = 'scope' . $method;
            array_unshift($args, $query);
            call_user_func_array([$this, $method], $args);

            return $this;
        } else {
            return call_user_func_array([$query, $method], $args);
        }
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();

        if (isset(static::$db)) {
            $query      = static::$db;
            static::$db = null;
        } else {
            $query = $model->db();
        }

        if (method_exists($model, 'scope' . $method)) {
            // 动态调用命名范围
            $method = 'scope' . $method;
            array_unshift($args, $query);

            call_user_func_array([$model, $method], $args);
            return $query;
        } else {
            return call_user_func_array([$query, $method], $args);
        }
    }

    /**
     * 修改器 设置数据对象的值
     * @access public
     * @param string $name  名称
     * @param mixed  $value 值
     * @return void
     */
    public function __set($name, $value)
    {
        $this->setAttr($name, $value);
    }

    /**
     * 获取器 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttr($name);
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        try {
            if (array_key_exists($name, $this->data)) {
                return true;
            } else {
                $this->getAttr($name);
                return true;
            }
        } catch (InvalidArgumentException $e) {
            return false;
        }

    }

    /**
     * 销毁数据对象的值
     * @access public
     * @param string $name 名称
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    public function __toString()
    {
        return $this->toJson();
    }

    // JsonSerializable
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    // ArrayAccess
    public function offsetSet($name, $value)
    {
        $this->setAttr($name, $value);
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
        return $this->getAttr($name);
    }

    /**
     * 解序列化后处理
     */
    public function __wakeup()
    {
        $this->initialize();
    }

    public function __debugInfo()
    {
        return [
            'data' => $this->data,
        ];
    }

}
