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

namespace think;

use think\Cache;
use think\Db;
use think\db\Query;
use think\Loader;
use think\model\Relation;
use think\paginator\Collection as PaginatorCollection;

/**
 * Class Model
 * @package think
 * @method PaginatorCollection paginate(integer $listRows = 15, boolean $simple = false, array $config = []) static 分页查询
 */
abstract class Model implements \JsonSerializable, \ArrayAccess
{

    // 数据库对象池
    private static $links = [];
    // 数据库配置
    protected $connection = [];
    // 当前模型名称
    protected $name;
    // 数据表名称
    protected $table;
    // 当前类名称
    protected $class;
    // 回调事件
    protected static $event = [];

    // 数据表主键 复合主键使用数组定义
    protected $pk;
    // 错误信息
    protected $error;
    // 字段验证规则
    protected $validate;

    // 字段属性
    protected $field = [];
    // 隐藏属性
    protected $hidden = [];
    // 追加属性
    protected $append = [];
    // 数据信息
    protected $data = [];
    // 记录改变字段
    protected $change = [];

    // 保存自动完成列表
    protected $auto = [];
    // 新增自动完成列表
    protected $insert = [];
    // 更新自动完成列表
    protected $update = [];
    // 是否需要自动写入时间戳
    protected $autoWriteTimestamp;
    // 创建时间字段
    protected $createTime = 'create_time';
    // 更新时间字段
    protected $updateTime = 'update_time';
    // 删除时间字段
    protected $deleteTime = 'delete_time';
    // 时间字段取出后的默认时间格式
    protected $dateFormat = 'Y-m-d H:i:s';
    // 字段类型或者格式转换
    protected $type = [];
    // 是否为更新数据
    protected $isUpdate = false;
    // 更新条件
    protected $updateWhere;
    // 当前执行的关联对象
    protected $relation;
    // 属性类型
    protected $fieldType = [];

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

        // 当前类名
        $this->class = get_class($this);

        if (empty($this->name)) {
            $this->name = basename(str_replace('\\', '/', $this->class));
        }

        if (is_null($this->autoWriteTimestamp)) {
            $this->autoWriteTimestamp = $this->db()->getConfig('auto_timestamp');
        }

        // 执行初始化操作
        $this->initialize();
    }

    /**
     *  获取关联模型实例
     *
     * @return \think\model\Relation
     */
    protected function relation()
    {
        if (is_null($this->relation)) {
            $this->relation = new Relation($this);
        }
        return $this->relation;
    }

    /**
     *  初始化模型
     *
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
     *
     * @return void
     */
    protected static function init()
    {}

    /**
     * 设置数据对象值
     * @access public
     * @param mixed $data 数据
     * @return $this
     */
    public function data($data = '')
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        } elseif (!is_array($data)) {
            throw new Exception('data type invalid', 10300);
        }
        $this->data = $data;
        return $this;
    }

    /**
     * 获取对象原始数据
     * @access public
     * @param string $name 字段名 留空获取全部
     * @return array
     */
    public function getData($name = '')
    {
        return array_key_exists($name, $this->data) ? $this->data[$name] : $this->data;
    }

    /**
     * 设置需要追加的输出属性
     * @access public
     * @param array $append 属性列表
     * @return $this
     */
    public function append($append = [])
    {
        $this->append = $append;
        return $this;
    }

    /**
     * 设置需要隐藏的属性
     * @access public
     * @param array $hidden 属性列表
     * @return $this
     */
    public function hidden($hidden = [])
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * 转换当前模型对象为数组
     * @access public
     * @return array
     */
    public function toArray()
    {
        $item = [];
        if (!empty($this->append)) {
            foreach ($this->append as $name) {
                $item[$name] = $this->__get($name);
            }
        }
        foreach ($this->data as $key => $val) {
            // 如果是隐藏属性不输出
            if (in_array($key, $this->hidden)) {
                continue;
            }

            if ($val instanceof Model || $val instanceof Collection) {
                // 关联模型对象
                $item[$key] = $val->toArray();
            } elseif (is_array($val) && isset($val[0]) && $val[0] instanceof Model) {
                // 关联模型数据集
                $data = [];
                foreach ($val as $k => $value) {
                    $data[$k] = $value->toArray();
                }
                $item[$key] = $data;
            } else {
                // 模型属性
                $item[$key] = $this->__get($key);
            }
        }
        return !empty($item) ? $item : [];
    }

    /**
     * 转换当前模型对象为JSON字符串
     * @access public
     * @param integer $options json参数
     * @return string
     */
    public function toJson($options = JSON_UNESCAPED_UNICODE)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * 获取当前模型对象的主键
     * @access public
     * @param string $table 数据表名
     * @return mixed
     */
    public function getPk($table = '')
    {
        if (empty($this->pk)) {
            $this->pk = $this->db()->getTableInfo($table, 'pk');
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
     * @param array $data 数据
     * @param array $where 更新条件
     * @param bool $getId 新增的时候是否获取id
     * @return integer
     */
    public function save($data = [], $where = [], $getId = true)
    {
        if (!empty($data)) {
            // 数据对象赋值
            foreach ($data as $key => $value) {
                $this->__set($key, $value);
            }
            if (!empty($where)) {
                $this->isUpdate = true;
            }
        }
        // 数据自动验证
        if (!$this->validateData()) {
            return false;
        }

        // 检测字段
        if (!empty($this->field)) {
            foreach ($this->data as $key => $val) {
                if (!in_array($key, $this->field)) {
                    unset($this->data[$key]);
                }
            }
        }

        // 数据自动完成
        $this->autoCompleteData($this->auto);

        // 自动写入更新时间
        if ($this->autoWriteTimestamp && $this->updateTime) {
            $this->__set($this->updateTime, null);
        }

        // 事件回调
        if (false === $this->trigger('before_write', $this)) {
            return false;
        }

        if ($this->isUpdate) {
            // 自动更新
            $this->autoCompleteData($this->update);

            // 事件回调
            if (false === $this->trigger('before_update', $this)) {
                return false;
            }

            // 去除没有更新的字段
            $data = [];
            foreach ($this->data as $key => $val) {
                if (in_array($key, $this->change) || $this->isPk($key)) {
                    $data[$key] = $val;
                }
            }

            if (empty($where) && !empty($this->updateWhere)) {
                $where = $this->updateWhere;
            }

            if (!empty($where)) {
                $pk = $this->getPk();
                if (is_string($pk) && isset($data[$pk])) {
                    unset($data[$pk]);
                }
            }

            $result = $this->db()->where($where)->update($data);

            // 更新回调
            $this->trigger('after_update', $this);
        } else {
            // 自动写入
            $this->autoCompleteData($this->insert);

            // 自动写入创建时间
            if ($this->autoWriteTimestamp && $this->createTime) {
                $this->__set($this->createTime, null);
            }

            if (false === $this->trigger('before_insert', $this)) {
                return false;
            }

            $result = $this->db()->insert($this->data);

            // 获取自动增长主键
            if ($result && $getId) {
                $insertId = $this->db()->getLastInsID();
                $pk       = $this->getPk();
                if (is_string($pk) && $insertId) {
                    $this->data[$pk] = $insertId;
                }
                $result = $insertId;
            }
            // 新增回调
            $this->trigger('after_insert', $this);
        }
        // 写入回调
        $this->trigger('after_write', $this);

        // 标记为更新
        $this->isUpdate = true;
        // 清空change
        $this->change = [];
        return $result;
    }

    /**
     * 保存多个数据到当前数据对象
     * @access public
     * @param array $data 数据
     * @return integer
     */
    public function saveAll($dataSet)
    {
        foreach ($dataSet as $data) {
            $result = $this->isUpdate(false)->save($data, [], false);
        }
        return $result;
    }

    /**
     * 设置允许写入的字段
     * @access public
     * @param bool $update
     * @return $this
     */
    public function allowField($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * 是否为更新数据
     * @access public
     * @param bool $update
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
            if (!in_array($field, $this->change)) {
                $this->__set($field, isset($this->data[$field]) ? $this->data[$field] : $value);
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
        if (false === $this->trigger('before_delete', $this)) {
            return false;
        }

        $result = $this->db()->delete($this->data);

        $this->trigger('after_delete', $this);
        return $result;
    }

    /**
     * 设置自动完成的字段
     * @access public
     * @param array $fields 需要自动完成的字段（ 规则通过修改器定义）
     * @return $this
     */
    public function auto($fields)
    {
        $this->auto = $fields;
        return $this;
    }

    /**
     * 设置字段验证
     * @access public
     * @param array|string|bool $rule 验证规则 true表示自动读取验证器类
     * @param array $msg 提示信息
     * @return $this
     */
    public function validate($rule = true, $msg = [])
    {
        if (is_array($rule)) {
            $this->validate = [
                'rule' => $rule,
                'msg'  => $msg,
            ];
        } else {
            $this->validate = true === $rule ? $this->name : $rule;
        }
        return $this;
    }

    /**
     * 自动验证当前数据对象值
     * @access public
     * @return bool
     */
    public function validateData()
    {
        if (!empty($this->validate)) {
            $info = $this->validate;
            if (is_array($info)) {
                $validate = Loader::validate();
                $validate->rule($info['rule']);
                $validate->message($info['msg']);
            } else {
                $name = is_string($info) ? $info : $this->name;
                if (strpos($name, '.')) {
                    list($name, $scene) = explode('.', $name);
                }
                $validate = Loader::validate($name);
                if (!empty($scene)) {
                    $validate->scene($scene);
                }
            }
            if (!$validate->check($this->data)) {
                $this->error = $validate->getError();
                return false;
            }
            $this->validate = null;
        }
        return true;
    }

    /**
     * 返回模型的错误信息
     * @access public
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 注册回调方法
     * @access public
     * @param string $event 事件名
     * @param callable $callback 回调方法
     * @param bool $override 是否覆盖
     * @return void
     */
    public static function event($event, $callback, $override = false)
    {
        if ($override) {
            static::$event[$event] = [];
        }
        static::$event[$event][] = $callback;
    }

    /**
     * 触发事件
     * @access protected
     * @param string $event 事件名
     * @param mixed $params 传入参数（引用）
     * @return bool
     */
    protected function trigger($event, &$params)
    {
        if (isset(static::$event[$event])) {
            foreach (static::$event[$event] as $callback) {
                if (is_callable($callback)) {
                    $result = call_user_func_array($callback, [ & $params]);
                    if (false === $result) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * 写入数据
     * @access public
     * @param array $data 数据数组
     * @return $this
     */
    public static function create($data = [])
    {
        $model = new static();
        $model->isUpdate(false)->save($data);
        return $model;
    }

    /**
     * 更新数据
     * @access public
     * @param array $data 数据数组
     * @param array $where 更新条件
     * @return $this
     */
    public static function update($data = [], $where = [])
    {
        $model = new static();
        $model->isUpdate(true)->save($data, $where);
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
    public static function get($data = '', $with = [], $cache = false)
    {
        $query = self::parseQuery($data, $with, $cache);
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
    public static function all($data = [], $with = [], $cache = false)
    {
        $query = self::parseQuery($data, $with, $cache);
        return $query->select($data);
    }

    /**
     * 分析查询表达式
     * @access public
     * @param mixed $data 主键列表或者查询条件（闭包）
     * @param string $with 关联预查询
     * @param bool $cache 是否缓存
     * @return \think\db\Query
     */
    protected static function parseQuery(&$data, $with, $cache)
    {
        $result = self::with($with)->cache($cache);
        if (is_array($data) && key($data) !== 0) {
            $result = $result->where($data);
            $data   = [];
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [ & $result]);
            $data = [];
        } elseif ($data instanceof Query) {
            $result = $data->with($with)->cache($cache);
            $data   = [];
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
            $data = [];
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [ & $query]);
            $data = [];
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
     * 命名范围
     * @access public
     * @param string|Closure $name 命名范围名称 逗号分隔
     * @param mixed $params 参数调用
     * @return \think\Model
     */
    public static function scope($name, $params = [])
    {
        $model = new static();
        $query = $model->db();
        if ($name instanceof \Closure) {
            call_user_func_array($name, [ & $query, $params]);
        } elseif ($name instanceof Query) {
            return $name;
        } else {
            $names = explode(',', $name);
            foreach ($names as $scope) {
                $method = 'scope' . $scope;
                if (method_exists($model, $method)) {
                    $model->$method($query, $params);
                }
            }
        }
        return $model;
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param string $relation 关联方法名
     * @param string $operator 比较操作符
     * @param integer $count 个数
     * @param string $id 关联表的统计字段
     * @return \think\Model
     */
    public static function has($relation, $operator = '>=', $count = 1, $id = '*')
    {
        $model = new static();
        $info  = $model->$relation()->getRelationInfo();
        $table = $info['model']::getTable();
        switch ($info['type']) {
            case Relation::HAS_MANY:
                return $model->db()->alias('a')
                    ->join($table . ' b', 'a.' . $info['localKey'] . '=b.' . $info['foreignKey'])
                    ->group('b.' . $info['foreignKey'])
                    ->having('count(' . $id . ')' . $operator . $count);
            case Relation::HAS_MANY_THROUGH:
                // TODO
        }

    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param string $relation 关联方法名
     * @param mixed $where 查询条件（数组或者闭包）
     * @return \think\Model
     */
    public static function hasWhere($relation, $where = [])
    {
        $model = new static();
        $info  = $model->$relation()->getRelationInfo();
        switch ($info['type']) {
            case Relation::HAS_MANY:
                $table = $info['model']::getTable();
                if (is_array($where)) {
                    foreach ($where as $key => $val) {
                        if (false === strpos($key, '.')) {
                            $where['b.' . $key] = $val;
                            unset($where[$key]);
                        }
                    }
                }
                return $model->db()->alias('a')
                    ->field('a.*')
                    ->join($table . ' b', 'a.' . $info['localKey'] . '=b.' . $info['foreignKey'])
                    ->where($where);
            case Relation::HAS_MANY_THROUGH:
                // TODO
        }
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

    /**
     * 查询当前模型的关联数据
     * @access public
     * @param string|array $relations 关联名
     * @return $this
     */
    public function relationQuery($relations)
    {
        if (is_string($relations)) {
            $relations = explode(',', $relations);
        }
        $this->relation();
        foreach ($relations as $relation) {
            $this->data[$relation] = $this->relation->getRelation($relation);
        }
        return $this;
    }

    /**
     * 预载入关联查询 返回数据集
     * @access public
     * @param array $resultSet 数据集
     * @param string $relation 关联名
     * @return array
     */
    public function eagerlyResultSet($resultSet, $relation)
    {
        return $this->relation()->eagerlyResultSet($resultSet, $relation);
    }

    /**
     * 预载入关联查询 返回模型对象
     * @access public
     * @param Model $result 数据对象
     * @param string $relation 关联名
     * @return \think\Model
     */
    public function eagerlyResult($result, $relation)
    {
        return $this->relation()->eagerlyResult($result, $relation);
    }

    /**
     * HAS ONE 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @return \think\db\Query|string
     */
    public function hasOne($model, $foreignKey = '', $localKey = '', $alias = [])
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: Loader::parseName($this->name) . '_id';
        return $this->relation()->hasOne($model, $foreignKey, $localKey, $alias);
    }

    /**
     * BELONGS TO 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $otherKey 关联主键
     * @param array  $alias 别名定义
     * @return \think\db\Query|string
     */
    public function belongsTo($model, $foreignKey = '', $otherKey = '', $alias = [])
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $foreignKey = $foreignKey ?: Loader::parseName(basename(str_replace('\\', '/', $model))) . '_id';
        $otherKey   = $otherKey ?: (new $model)->getPk();
        return $this->relation()->belongsTo($model, $foreignKey, $otherKey, $alias);
    }

    /**
     * HAS MANY 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @return \think\db\Query|string
     */
    public function hasMany($model, $foreignKey = '', $localKey = '', $alias = [])
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: Loader::parseName($this->name) . '_id';
        return $this->relation()->hasMany($model, $foreignKey, $localKey, $alias);
    }

    /**
     * HAS MANY 远程关联定义
     * @access public
     * @param string $model 模型名
     * @param string $through 中间模型名
     * @param string $foreignKey 关联外键
     * @param string $throughKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @return \think\db\Query|string
     */
    public function hasManyThrough($model, $through, $foreignKey = '', $throughKey = '', $localKey = '', $alias = [])
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $through    = $this->parseModel($through);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: Loader::parseName($this->name) . '_id';
        $name       = Loader::parseName(basename(str_replace('\\', '/', $through)));
        $throughKey = $throughKey ?: $name . '_id';
        return $this->relation()->hasManyThrough($model, $through, $foreignKey, $throughKey, $localKey, $alias);
    }

    /**
     * BELONGS TO MANY 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $table 中间表名
     * @param string $foreignKey 关联外键
     * @param string $localKey 当前模型关联键
     * @param array  $alias 别名定义
     * @return \think\db\Query|string
     */
    public function belongsToMany($model, $table = '', $foreignKey = '', $localKey = '', $alias = [])
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $name       = Loader::parseName(basename(str_replace('\\', '/', $model)));
        $table      = $table ?: Db::name(Loader::parseName($this->name) . '_' . $name)->getTable();
        $foreignKey = $foreignKey ?: $name . '_id';
        $localKey   = $localKey ?: Loader::parseName($this->name) . '_id';
        return $this->relation()->belongsToMany($model, $table, $foreignKey, $localKey, $alias);
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @return \think\db\Query
     */
    public function db()
    {
        $model = $this->class;
        if (!isset(self::$links[$model])) {
            // 设置当前模型 确保查询返回模型对象
            $query = Db::connect($this->connection)->model($model);

            // 设置当前数据表和模型名
            if (!empty($this->table)) {
                $query->setTable($this->table);
            } else {
                $query->name($this->name);
            }

            self::$links[$model] = $query;
        }
        // 返回当前模型的数据库查询对象
        return self::$links[$model];
    }

    public function __call($method, $args)
    {
        if (method_exists($this, 'scope' . $method)) {
            // 动态调用命名范围
            $method = 'scope' . $method;
            array_unshift($args, $this->db());
            call_user_func_array([$this, $method], $args);
            return $this;
        } else {
            return call_user_func_array([$this->db(), $method], $args);
        }
    }

    public static function __callStatic($method, $params)
    {
        $model = get_called_class();
        if (!isset(self::$links[$model])) {
            self::$links[$model] = (new static())->db();
        }
        $query = self::$links[$model];
        return call_user_func_array([$query, $method], $params);
    }

    /**
     * 修改器 设置数据对象的值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     */
    public function __set($name, $value)
    {
        if (empty($this->fieldType)) {
            // 获取字段类型信息并缓存
            $this->fieldType = $this->db()->getTableInfo('', 'type');
        }
        if (is_null($value) && $this->autoWriteTimestamp && in_array($name, [$this->createTime, $this->updateTime, $this->deleteTime])) {
            // 自动写入的时间戳字段
            if (isset($this->type[$name])) {
                $type = $this->type[$name];
                if (strpos($type, ':')) {
                    list($type, $param) = explode(':', $type, 2);
                }
                switch ($type) {
                    case 'timestamp':
                        $format = !empty($param) ? $param : $this->dateFormat;
                        $value  = date($format, NOW_TIME);
                        break;
                    case 'datetime':
                        $value = NOW_TIME;
                        break;
                }
            } elseif (isset($this->fieldType[$name]) && preg_match('/(datetime|timestamp)/is', $this->fieldType[$name])) {
                $value = date($this->dateFormat, NOW_TIME);
            } else {
                $value = NOW_TIME;
            }
        } else {
            // 检测修改器
            $method = 'set' . Loader::parseName($name, 1) . 'Attr';
            if (method_exists($this, $method)) {
                $value = $this->$method($value, $this->data);
            } elseif (isset($this->type[$name])) {
                // 类型转换
                $type = $this->type[$name];
                if (strpos($type, ':')) {
                    list($type, $param) = explode(':', $type, 2);
                }
                switch ($type) {
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'float':
                        if (empty($param)) {
                            $value = (float) $value;
                        } else {
                            $value = (float) number_format($value, $param);
                        }
                        break;
                    case 'boolean':
                        $value = (bool) $value;
                        break;
                    case 'datetime':
                        if (!is_numeric($value)) {
                            $value = strtotime($value);
                        }
                        break;
                    case 'timestamp':
                        $format = !empty($param) ? $param : $this->dateFormat;
                        $value  = date($format, is_numeric($value) ? $value : strtotime($value));
                        break;
                    case 'object':
                        if (is_object($value)) {
                            $value = json_encode($value, JSON_FORCE_OBJECT);
                        }
                        break;
                    case 'json':
                    case 'array':
                        if (is_array($value)) {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                        }
                        break;
                }
            }
        }

        // 标记字段更改
        if (!isset($this->data[$name]) || ($this->data[$name] != $value && !in_array($name, $this->change))) {
            $this->change[] = $name;
        }
        // 设置数据对象属性
        $this->data[$name] = $value;
    }

    /**
     * 获取器 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        $value = isset($this->data[$name]) ? $this->data[$name] : null;

        // 检测属性获取器
        $method = 'get' . Loader::parseName($name, 1) . 'Attr';
        if (method_exists($this, $method)) {
            return $this->$method($value, $this->data);
        } elseif (!is_null($value) && isset($this->type[$name])) {
            // 类型转换
            $type = $this->type[$name];
            if (strpos($type, ':')) {
                list($type, $param) = explode(':', $type, 2);
            }
            switch ($type) {
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'float':
                    if (empty($param)) {
                        $value = (float) $value;
                    } else {
                        $value = (float) number_format($value, $param);
                    }
                    break;
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'datetime':
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value  = date($format, $value);
                    break;
                case 'timestamp':
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value  = date($format, strtotime($value));
                    break;
                case 'json':
                case 'array':
                    $value = json_decode($value, true);
                    break;
                case 'object':
                    $value = json_decode($value);
                    break;
            }
        } elseif (is_null($value) && method_exists($this, $name)) {
            // 获取关联数据
            $value = $this->relation()->getRelation($name);
            // 保存关联对象值
            $this->data[$name] = $value;
        }
        return $value;
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
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

    /**
     * 解序列化后处理
     */
    public function __wakeup()
    {
        $this->initialize();
    }

}
