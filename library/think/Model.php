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

    // 数据信息
    protected $data = [];
    // 缓存数据
    protected $cache = [];
    // 记录改变字段
    protected $change = [];
    // 数据表主键
    protected $pk = 'id';
    // 错误信息
    protected $error;
    // 当前模型名称
    protected $name;
    // 字段验证规则
    protected $validate;
    // 字段完成规则
    protected $auto = [];
    // 新增的字段完成
    protected $insert = [
        'create_time' => 'time',
    ];
    // 更新的字段完成
    protected $update = [
        'update_time' => 'time',
    ];

    // 当前执行的关联类型
    private $relation;

    /**
     * 架构函数
     * @access public
     * @param array $data 数据
     */
    public function __construct($data = [])
    {
        $this->data = $data;
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
     * @return Model
     */
    public function toArray()
    {
        if (!empty($this->data)) {
            return $this->data;
        } else {
            return [];
        }
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
        // 检测修改器
        $method = 'set' . Loader::parseName($name, 1) . 'Attr';
        if (method_exists($this, $method)) {
            $value = $this->$method($value, $this->data);
        }

        // 设置数据对象属性
        if (isset($this->data[$name]) && $this->data[$name] != $value && !in_array($name, $this->change)) {
            $this->change[] = $name;
        }
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
     * 保存当前数据对象的值（自动识别新增或者更新）
     * @access public
     * @param array $data 数据
     * @param array $where 更新条件
     * @return void
     */
    public function save($data = [], $where = [])
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $this->__set($key, $value);
            }
        }
        $data = $this->data;

        // 数据自动验证
        if (!$this->dataValidate($data)) {
            return false;
        }

        // 数据自动完成
        foreach ($this->auto as $name => $rule) {
            if (!in_array($name, $this->change)) {
                $this->change[] = $name;
            }
            $data[$name] = is_callable($rule) ? call_user_func_array($rule, [ & $data]) : $rule;
        }

        // 检测是否为更新数据
        if ($this->isUpdate($data)) {

            if (false === $this->trigger('before_update', $this)) {
                return false;
            }
            // 更新的时候检测字段更改
            if (!empty($this->change)) {
                foreach ($data as $key => $val) {
                    if (!in_array($key, $this->change) && !$this->isPk($key) && !isset($this->relation[$key])) {
                        unset($data[$key]);
                    }
                }
            }

            // 自动更新
            foreach ($this->update as $name => $rule) {
                $data[$name] = is_callable($rule) ? call_user_func_array($rule, [ & $data]) : $rule;
            }

            $db = self::db();
            if (!empty($where)) {
                $db->where($where);
            }
            $result = $db->update($data);

            $this->trigger('after_update', $this);
            return $result;
        } else {

            if (false === $this->trigger('before_insert', $this)) {
                return false;
            }

            // 自动写入
            foreach ($this->insert as $name => $rule) {
                $data[$name] = is_callable($rule) ? call_user_func_array($rule, [ & $data]) : $rule;
            }

            $result = self::db()->insert($data);

            // 获取自动增长主键
            $insertId = self::db()->getLastInsID();
            if (is_string($this->pk) && $insertId) {
                $data[$this->pk]       = $insertId;
                $this->data[$this->pk] = $insertId;
            }

            $this->trigger('after_insert', $this);
            return $insertId ?: $result;
        }
    }

    /**
     * 删除当前的记录
     * @access public
     * @return integer
     */
    public function delete()
    {
        $data = $this->data;

        if (false === $this->trigger('before_delete', $this)) {
            return false;
        }

        $result = self::db()->delete($data);

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
    public function validate($rule = true, $msg = null)
    {
        if (true === $rule) {
            $this->validate = $this->name;
        } elseif (is_array($rule)) {
            $this->validate = [
                'rule' => $rule,
                'msg'  => is_array($msg) ? $msg : [],
            ];
        } else {
            $this->validate = $rule;
        }
        return $this;
    }

    /**
     * 设置字段自动完成（包括新增和更新）
     * @access public
     * @param string $field 字段名或者数组规则
     * @param array|null $rule 完成规则
     * @return Model
     */
    public function auto($field, $rule = null)
    {
        if (is_array($field)) {
            $this->auto = array_merge($this->auto, $field);
        } else {
            $this->auto[$field] = $rule;
        }
        return $this;
    }

    /**
     * 设置写入自动完成
     * @access public
     * @param string $field 字段名或者数组规则
     * @param array|null $rule 完成规则
     * @return Model
     */
    public function autoInsert($field, $rule = null)
    {
        if (is_array($field)) {
            $this->insert = array_merge($this->insert, $field);
        } else {
            $this->insert[$field] = $rule;
        }
        return $this;
    }

    /**
     * 设置更新自动完成
     * @access public
     * @param string $field 字段名或者数组规则
     * @param array|null $rule 完成规则
     * @return Model
     */
    public function autoUpdate($field, $rule = null)
    {
        if (is_array($field)) {
            $this->update = array_merge($this->update, $field);
        } else {
            $this->update[$field] = $rule;
        }
        return $this;
    }

    /**
     * 数据自动验证
     * @access protected
     * @param array $data  数据
     * @return void
     */
    protected function dataValidate(&$data)
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
            if (!$validate->check($data)) {
                $this->error = $validate->getError();
                return false;
            }
            $this->validate = null;
        }
        return true;
    }

    /**
     * 是否为更新操作
     * @access public
     * @param mixed $data 数据
     * @return bool
     */
    public function isUpdate($data = [])
    {
        $data = $data ?: $this->data;
        // 判断主键
        $pk = $this->pk;
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
        return $model->data($data)->save();
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
            array_push($path, $model);
            $model = implode('\\', $path);
        }
        return $model;
    }

    // HAS ONE
    public function hasOne($model, $foreignKey = '', $localKey = '')
    {
        $model          = $this->parseModel($model);
        $localKey       = $localKey ?: $this->pk;
        $foreignKey     = $foreignKey ?: strtolower($this->name) . '_id';
        $this->relation = self::HAS_ONE;
        return $model::where($foreignKey, $this->data[$localKey]);
    }

    // BELONGS TO
    public function belongsTo($model, $localKey = '', $foreignKey = '')
    {
        $model          = $this->parseModel($model);
        $foreignKey     = $foreignKey ?: $this->pk;
        $localKey       = $localKey ?: strtolower(basename(str_replace('\\', '/', $model))) . '_id';
        $this->relation = self::BELONGS_TO;
        return $model::where($foreignKey, $this->data[$localKey]);
    }

    // HAS MANY
    public function hasMany($model, $foreignKey = '', $localKey = '')
    {
        $model          = $this->parseModel($model);
        $localKey       = $localKey ?: $this->pk;
        $foreignKey     = $foreignKey ?: strtolower($this->name) . '_id';
        $this->relation = self::HAS_MANY;
        return $model::where($foreignKey, $this->data[$localKey]);
    }

    // BELONGS TO MANY
    public function belongsToMany($model, $localKey = '', $foreignKey = '')
    {
        $model          = $this->parseModel($model);
        $foreignKey     = $foreignKey ?: $this->pk;
        $localKey       = $localKey ?: strtolower(basename(str_replace('\\', '/', $model))) . '_id';
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
     * @access private
     * @return object
     */
    private static function db()
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
