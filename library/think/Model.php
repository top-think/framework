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
use think\Loader;

abstract class Model implements \JsonSerializable, \ArrayAccess
{
    const HAS_ONE         = 1;
    const HAS_MANY        = 2;
    const BELONGS_TO      = 3;
    const BELONGS_TO_MANY = 4;

    // 当前实例
    private static $instance;
    // 数据库对象池
    private static $links = [];
    // 数据库配置
    protected static $connection;
    // 数据表名称
    protected static $tableName;
    // 回调事件
    protected static $event = [];

    // 数据表主键 复合主键使用数组定义
    protected $pk = 'id';
    // 错误信息
    protected $error;
    // 当前模型名称
    protected $name;
    // 字段验证规则
    protected $validate;

    // 数据信息
    protected $data = [];
    // 缓存数据
    protected $cache = [];
    // 记录改变字段
    protected $change = [];

    // 保存自动完成列表
    protected $auto = [];
    // 新增自动完成列表
    protected $insert = [];
    // 更新自动完成列表
    protected $update = [];
    // 时间戳字段列表
    protected $timestampField = ['create_time', 'update_time'];

    // 字段类型或者格式转换
    protected $type = [];
    // 当前执行的关联类型
    private $relation;
    // 是否为更新数据
    protected $isUpdate;

    /**
     * 架构函数
     * @access public
     * @param array $data 数据
     */
    public function __construct($data = [])
    {
        if (is_object($data)) {
            $this->data = get_object_vars($data);
        } else {
            $this->data = $data;
        }
        $this->name = basename(str_replace('\\', '/', get_class($this)));
    }

    // JsonSerializable
    public function jsonSerialize()
    {
        return $this->data;
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
     * 设置数据对象值
     * @access public
     * @param mixed $data 数据
     * @return Model
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
     * 转换为数组
     * @access public
     * @return array
     */
    public function toArray()
    {
        return !empty($this->data) ? $this->data : [];
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
        if (is_null($value) && in_array($name, $this->timestampField)) {
            // 如果是时间戳字段 则自动写入
            $value = NOW_TIME;
        } else {
            // 检测修改器
            $method = 'set' . Loader::parseName($name, 1) . 'Attr';
            if (method_exists($this, $method)) {
                $value = $this->$method($value, $this->data);
            }

            // 类型转换 或者 字符串处理
            if (isset($this->type[$name])) {
                $type = $this->type[$name];
                switch ($type) {
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'float':
                        $value = (float) $value;
                        break;
                    case 'boolean':
                        $value = (bool) $value;
                        break;
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

        // 类型转换
        if (!is_null($value) && isset($this->type[$name])) {
            $type = $this->type[$name];
            switch ($type) {
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'float':
                    $value = (float) $value;
                    break;
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'array':
                    $value = json_decode($value, true);
                    break;
            }
        }

        // 检测属性获取器
        $method = 'get' . Loader::parseName($name, 1) . 'Attr';
        if (method_exists($this, $method)) {
            return $this->$method($value, $this->data);
        }

        if (is_null($value) && method_exists($this, $name)) {
            // 执行关联定义方法
            $db = $this->$name();
            // 判断关联类型执行查询
            switch ($this->relation) {
                case self::HAS_ONE:
                    $result = $db->find();
                    break;
                case self::HAS_MANY:
                    $result = $db->select();
                    break;
                case self::BELONGS_TO:
                    $result = $db->find();
                    break;
                case self::BELONGS_TO_MANY:
                    $result = $db->select();
                    break;
                default:
                    // 直接返回
                    $result = $db;
            }
            // 避免影响其它操作方法
            $this->relation = null;
            return $result;
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

    /**
     * 判断一个字段名是否为主键字段
     * @access public
     * @param string $key 名称
     * @return void
     */
    protected function isPk($key)
    {
        $pk = $this->pk;
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
     * @return integer
     */
    public function save($data = [], $where = [])
    {
        if (!empty($data)) {
            // 数据对象赋值
            foreach ($data as $key => $value) {
                $this->__set($key, $value);
            }
        }
        // 数据自动验证
        if (!$this->validateData()) {
            return false;
        }
        if (false === $this->trigger('before_write', $this)) {
            return false;
        }
        // 数据自动完成
        $this->autoCompleteData($this->auto);

        if ($this->isUpdate()) {
            // 更新数据
            if (false === $this->trigger('before_update', $this)) {
                return false;
            }
            // 自动更新
            $this->autoCompleteData($this->update);

            // 去除没有更新的字段
            foreach ($this->data as $key => $val) {
                if (!in_array($key, $this->change) && !$this->isPk($key) && !isset($this->relation[$key])) {
                    unset($this->data[$key]);
                }
            }

            $db = self::db();
            if (!empty($where)) {
                $db->where($where);
            }
            $result = $db->update($this->data);

            // 更新回调
            $this->trigger('after_update', $this);
        } else {
            // 新增数据
            if (false === $this->trigger('before_insert', $this)) {
                return false;
            }

            // 自动写入
            $this->autoCompleteData($this->insert);

            $result = self::db()->insert($this->data);

            // 获取自动增长主键
            if ($result) {
                $insertId = self::db()->getLastInsID();
                if (is_string($this->pk) && $insertId) {
                    $this->data[$this->pk] = $insertId;
                }
            }
            // 新增回调
            $this->trigger('after_insert', $this);
        }
        // 写入回调
        $this->trigger('after_write', $this);

        // 标记为更新
        $this->isUpdate = true;
        return $result;
    }

    /**
     * 保存数据对象
     * @access public
     * @param array $data 数据
     * @param array $where 更新条件
     * @return integer
     */
    public function insert($data = [])
    {
        return $this->isUpdate(false)->save($data);
    }

    /**
     * 更新数据对象
     * @access public
     * @param array $data 数据
     * @param array $where 更新条件
     * @return integer
     */
    public function update($data = [], $where = [])
    {
        return $this->isUpdate(true)->save($data, $where);
    }

    /**
     * 是否为更新数据
     * @access public
     * @param bool|null $update
     * @return bool|Model
     */
    protected function isUpdate($update = null)
    {
        if (is_bool($update)) {
            $this->isUpdate = $update;
            return $this;
        }

        if (isset($this->isUpdate)) {
            return $this->isUpdate;
        }

        // 根据主键判断是否更新
        $data = $this->data;
        $pk   = $this->pk;
        if (is_string($pk) && isset($data[$pk])) {
            return true;
        } elseif (is_array($pk)) {
            foreach ($pk as $field) {
                if (isset($data[$field])) {
                    return true;
                }
            }
        }
        // TODO 完善没有主键或者其他的情况
        return false;
    }

    /**
     * 数据自动完成
     * @access public
     * @param array $auto 要自动更新的字段列表
     * @return void
     */
    protected function autoCompleteData($auto = [])
    {
        foreach ($auto as $field) {
            if (!in_array($field, $this->change)) {
                $this->__set($field, isset($this->data[$field]) ? $this->data[$field] : null);
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

        $result = self::db()->delete($this->data);

        $this->trigger('after_delete', $this);
        return $result;
    }

    /**
     * 设置字段验证
     * @access public
     * @param array|bool $rule 验证规则 true表示自动读取验证器类
     * @param array $msg 提示信息
     * @return Model
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
     * @return void
     */
    public function validateData()
    {
        if (!empty($this->validate)) {
            $info = $this->validate;
            if (is_array($info)) {
                $validate = Loader::validate(Config::get('default_validate'));
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
     */
    public static function event($event, $callback)
    {
        self::$event[$event] = $callback;
    }

    /**
     * 触发事件
     * @access public
     * @param string $event 事件名
     * @param mixed $params 传入参数（引用）
     */
    protected function trigger($event, &$params)
    {
        if (isset(self::$event[$event]) && is_callable(self::$event[$event])) {
            $result = call_user_func_array(self::$event[$event], [ & $params]);
            return false === $result ? false : true;
        }
        return true;
    }

    /**
     * 创建数据并写入
     * @access public
     * @param mixed $data 数据 支持数组或者对象
     * @return void
     */
    public static function create($data = [])
    {
        $model = new static();
        return $model->insert($data);
    }

    /**
     * 查找单条记录
     * @access public
     * @param array $data 主键值
     * @param bool $cache 是否缓存
     * @return mixed
     */
    public static function get($data = '', $cache = false)
    {
        if ($cache) {
            // 查找是否存在缓存
            $name   = basename(str_replace('\\', '/', get_called_class()));
            $guid   = 'model_' . $name . '_' . $data;
            $result = Cache::get($guid);
            if ($result) {
                return new static($result);
            }
        }

        $result = self::db()->find($data);

        if ($cache) {
            // 缓存模型数据
            Cache::set($guid, $result->toArray());
        }
        return $result;
    }

    /**
     * 查找多条记录
     * @access public
     * @param mixed $data 主键列表
     * @return array|string
     */
    public static function all($data = [])
    {
        return self::db()->select($data);
    }

    /**
     * 删除记录
     * @access public
     * @param mixed $data 主键列表
     * @return integer
     */
    public static function destroy($data)
    {
        $model     = new static();
        $resultSet = $model->select($data);
        if ($resultSet) {
            foreach ($resultSet as $data) {
                $result = $data->delete();
            }
        }
        return $result;
    }

    // 解析模型的完整命名空间
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

    // HAS ONE
    public function hasOne($model, $foreignKey = '', $localKey = '')
    {
        $model          = $this->parseModel($model);
        $localKey       = $localKey ?: $this->pk;
        $foreignKey     = $foreignKey ?: Loader::parseName($this->name) . '_id';
        $this->relation = self::HAS_ONE;
        return $model::where($foreignKey, $this->data[$localKey]);
    }

    // BELONGS TO
    public function belongsTo($model, $localKey = '', $foreignKey = '')
    {
        $model          = $this->parseModel($model);
        $foreignKey     = $foreignKey ?: $this->pk;
        $localKey       = $localKey ?: Loader::parseName(basename(str_replace('\\', '/', $model))) . '_id';
        $this->relation = self::BELONGS_TO;
        return $model::where($foreignKey, $this->data[$localKey]);
    }

    // HAS MANY
    public function hasMany($model, $foreignKey = '', $localKey = '')
    {
        $model          = $this->parseModel($model);
        $localKey       = $localKey ?: $this->pk;
        $foreignKey     = $foreignKey ?: Loader::parseName($this->name) . '_id';
        $this->relation = self::HAS_MANY;
        return $model::where($foreignKey, $this->data[$localKey]);
    }

    // BELONGS TO MANY
    public function belongsToMany($model, $localKey = '', $foreignKey = '')
    {
        $model          = $this->parseModel($model);
        $foreignKey     = $foreignKey ?: $this->pk;
        $localKey       = $localKey ?: Loader::parseName(basename(str_replace('\\', '/', $model))) . '_id';
        $this->relation = self::BELONGS_TO_MANY;
        return $model::where($foreignKey, $this->data[$localKey]);
    }

    /**
     * 实例化模型
     * @access public
     * @param array $config  配置参数
     * @return object
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 初始化数据库对象
     * @access protected
     * @return object
     */
    protected static function db()
    {
        $model = get_called_class();
        $name  = basename(str_replace('\\', '/', $model));
        if (!isset(self::$links[$model])) {
            self::$links[$model] = Db::connect(static::$connection);
            self::$links[$model]->name($name);
            if (isset(static::$tableName)) {
                self::$links[$model]->setTable(static::$tableName);
            }
        }
        // 设置当前模型 确保查询返回模型对象
        self::$links[$model]->model($model);
        // 返回当前数据库对象
        return self::$links[$model];
    }

    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::db(), $method], $params);
    }

}
