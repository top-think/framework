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

use InvalidArgumentException;
use think\Cache;
use think\Config;
use think\Db;
use think\db\Query;
use think\Exception;
use think\Exception\ValidateException;
use think\Loader;
use think\model\Relation;
use think\paginator\Collection as PaginatorCollection;

/**
 * Class Model
 * @package think
 * @method static PaginatorCollection paginate(integer $listRows = 15, boolean $simple = false, array $config = []) 分页查询
 * @method static mixed value($field, $default = null) 得到某个字段的值
 * @method static array column($field, $key = '') 得到某个列的数组
 * @method static integer count($field = '*') COUNT查询
 * @method static integer sum($field = '*') SUM查询
 * @method static integer min($field = '*') MIN查询
 * @method static integer max($field = '*') MAX查询
 * @method static integer avg($field = '*') AVG查询
 * @method static setField($field, $value = '')
 * @method static Query where($field, $op = null, $condition = null) 指定AND查询条件
 * @method static static findOrFail($data = null) 查找单条记录 如果不存在则抛出异常
 *
 */
abstract class Model implements \JsonSerializable, \ArrayAccess
{
    // 数据库对象池
    protected static $links = [];
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
    // 回调事件
    private static $event = [];
    // 错误信息
    protected $error;
    // 字段验证规则
    protected $validate;
    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk;
    // 数据表字段信息 留空则自动获取
    protected $field = [];
    // 只读字段
    protected $readonly = [];
    // 显示属性
    protected $visible = [];
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
    // 是否需要自动写入时间戳 如果设置为字符串 则表示时间字段的类型
    protected $autoWriteTimestamp;
    // 创建时间字段
    protected $createTime = 'create_time';
    // 更新时间字段
    protected $updateTime = 'update_time';
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
    // 验证失败是否抛出异常
    protected $failException = false;
    // 全局查询范围
    protected $useGlobalScope = true;

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
            // 当前模型名
            $name       = str_replace('\\', '/', $this->class);
            $this->name = basename($name);
            if (Config::get('class_suffix')) {
                $suffix     = basename(dirname($name));
                $this->name = substr($this->name, 0, -strlen($suffix));
            }
        }

        if (is_null($this->autoWriteTimestamp)) {
            // 自动写入时间戳
            $this->autoWriteTimestamp = $this->db()->getConfig('auto_timestamp');
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
            // 设置当前模型 确保查询返回模型对象
            $query = Db::connect($this->connection)->model($model, $this->query);

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
     *  获取关联模型实例
     * @access protected
     * @param string|array $relation 关联查询
     * @return Relation|Query
     */
    protected function relation($relation = null)
    {
        if (!is_null($relation)) {
            // 执行关联查询
            return $this->db()->relation($relation);
        }

        // 获取关联对象实例
        if (is_null($this->relation)) {
            $this->relation = new Relation($this);
        }
        return $this->relation;
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
     * @param mixed $data 数据或者属性名
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
        } else {
            throw new InvalidArgumentException('property not exists:' . $this->class . '->' . $name);
        }
    }

    /**
     * 修改器 设置数据对象值
     * @access public
     * @param string    $name 属性名
     * @param mixed     $value 属性值
     * @param array     $data 数据
     * @return $this
     */
    public function setAttr($name, $value, $data = [])
    {
        if (is_null($value) && $this->autoWriteTimestamp && in_array($name, [$this->createTime, $this->updateTime])) {
            // 自动写入的时间戳字段
            $value = $this->autoWriteTimestamp($name);
        } else {
            // 检测修改器
            $method = 'set' . Loader::parseName($name, 1) . 'Attr';
            if (method_exists($this, $method)) {
                $value = $this->$method($value, array_merge($data, $this->data));
            } elseif (isset($this->type[$name])) {
                // 类型转换
                $value = $this->writeTransform($value, $this->type[$name]);
            }
        }

        // 标记字段更改
        if (!isset($this->data[$name]) || ($this->data[$name] != $value && !in_array($name, $this->change))) {
            $this->change[] = $name;
        }
        // 设置数据对象属性
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * 自动写入时间戳
     * @access public
     * @param string         $name 时间戳字段
     * @return mixed
     */
    protected function autoWriteTimestamp($name)
    {
        if (isset($this->type[$name])) {
            $type = $this->type[$name];
            if (strpos($type, ':')) {
                list($type, $param) = explode(':', $type, 2);
            }
            switch ($type) {
                case 'datetime':
                case 'date':
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value  = date($format, $_SERVER['REQUEST_TIME']);
                    break;
                case 'timestamp':
                case 'int':
                    $value = $_SERVER['REQUEST_TIME'];
                    break;
            }
        } elseif (is_string($this->autoWriteTimestamp) && in_array(strtolower($this->autoWriteTimestamp), ['datetime', 'date', 'timestamp'])) {
            $value = date($this->dateFormat, $_SERVER['REQUEST_TIME']);
        } else {
            $value = $_SERVER['REQUEST_TIME'];
        }
        return $value;
    }

    /**
     * 数据写入 类型转换
     * @access public
     * @param mixed         $value 值
     * @param string|array  $type 要转换的类型
     * @return mixed
     */
    protected function writeTransform($value, $type)
    {
        if (is_array($type)) {
            list($type, $param) = $type;
        } elseif (strpos($type, ':')) {
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
            case 'timestamp':
                if (!is_numeric($value)) {
                    $value = strtotime($value);
                }
                break;
            case 'datetime':
                $format = !empty($param) ? $param : $this->dateFormat;
                $value  = date($format, is_numeric($value) ? $value : strtotime($value));
                break;
            case 'object':
                if (is_object($value)) {
                    $value = json_encode($value, JSON_FORCE_OBJECT);
                }
                break;
            case 'array':
                $value = (array) $value;
            case 'json':
                $option = !empty($param) ? (int) $param : JSON_UNESCAPED_UNICODE;
                $value  = json_encode($value, $option);
                break;
            case 'serialize':
                $value = serialize($value);
                break;
        }
        return $value;
    }

    /**
     * 获取器 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getAttr($name)
    {
        try {
            $notFound = false;
            $value    = $this->getData($name);
        } catch (InvalidArgumentException $e) {
            $notFound = true;
            $value    = null;
        }

        // 检测属性获取器
        $method = 'get' . Loader::parseName($name, 1) . 'Attr';
        if (method_exists($this, $method)) {
            $value = $this->$method($value, $this->data);
        } elseif (isset($this->type[$name])) {
            // 类型转换
            $value = $this->readTransform($value, $this->type[$name]);
        } elseif ($notFound) {
            $method = Loader::parseName($name, 1);
            if (method_exists($this, $method) && !method_exists('\think\Model', $method)) {
                // 不存在该字段 获取关联数据
                $value = $this->relation()->getRelation($method);
                // 保存关联对象值
                $this->data[$name] = $value;
            } else {
                throw new InvalidArgumentException('property not exists:' . $this->class . '->' . $name);
            }
        }
        return $value;
    }

    /**
     * 数据读取 类型转换
     * @access public
     * @param mixed         $value 值
     * @param string|array  $type 要转换的类型
     * @return mixed
     */
    protected function readTransform($value, $type)
    {
        if (is_array($type)) {
            list($type, $param) = $type;
        } elseif (strpos($type, ':')) {
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
            case 'timestamp':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value  = date($format, $value);
                }
                break;
            case 'datetime':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value  = date($format, strtotime($value));
                }
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
            case 'array':
                $value = is_null($value) ? [] : json_decode($value, true);
                break;
            case 'object':
                $value = empty($value) ? new \stdClass() : json_decode($value);
                break;
            case 'serialize':
                $value = unserialize($value);
                break;
        }
        return $value;
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
     * 设置需要隐藏的输出属性
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
     * 设置需要输出的属性
     * @param array $visible
     * @return $this
     */
    public function visible($visible = [])
    {
        $this->visible = $visible;
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

        //过滤属性
        if (!empty($this->visible)) {
            $data = array_intersect_key($this->data, array_flip($this->visible));
        } elseif (!empty($this->hidden)) {
            $data = array_diff_key($this->data, array_flip($this->hidden));
        } else {
            $data = $this->data;
        }

        foreach ($data as $key => $val) {
            if ($val instanceof Model || $val instanceof Collection) {
                // 关联模型对象
                $item[$key] = $val->toArray();
            } elseif (is_array($val) && reset($val) instanceof Model) {
                // 关联模型数据集
                $arr = [];
                foreach ($val as $k => $value) {
                    $arr[$k] = $value->toArray();
                }
                $item[$key] = $arr;
            } else {
                // 模型属性
                $item[$key] = $this->getAttr($key);
            }
        }
        // 追加属性（必须定义获取器）
        if (!empty($this->append)) {
            foreach ($this->append as $name) {
                $item[$name] = $this->getAttr($name);
            }
        }
        return !empty($item) ? $item : [];
    }

    /**
     * 转换当前模型对象为JSON字符串
     * @access public
     * @param integer   $options json参数
     * @return string
     */
    public function toJson($options = JSON_UNESCAPED_UNICODE)
    {
        return json_encode($this->toArray(), $options);
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
            $table = $this->db()->getTable($name);
            return $this->db()->getPk($table);
        } elseif (empty($this->pk)) {
            $this->pk = $this->db()->getPk();
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
     * @param array     $data 数据
     * @param array     $where 更新条件
     * @param string    $sequence     自增序列名
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
            $this->setAttr($this->updateTime, null);
        }

        // 事件回调
        if (false === $this->trigger('before_write', $this)) {
            return false;
        }
        $pk = $this->getPk();
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

            if (!empty($this->readonly)) {
                // 只读字段不允许更新
                foreach ($this->readonly as $key => $field) {
                    if (isset($data[$field])) {
                        unset($data[$field]);
                    }
                }
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

            $result = $this->db()->where($where)->update($data);
            // 清空change
            $this->change = [];
            // 更新回调
            $this->trigger('after_update', $this);
        } else {
            // 自动写入
            $this->autoCompleteData($this->insert);

            // 自动写入创建时间
            if ($this->autoWriteTimestamp && $this->createTime) {
                $this->setAttr($this->createTime, null);
            }

            if (false === $this->trigger('before_insert', $this)) {
                return false;
            }

            $result = $this->db()->insert($this->data);

            // 获取自动增长主键
            if ($result && is_string($pk) && !isset($this->data[$pk])) {
                $insertId = $this->db()->getLastInsID($sequence);
                if ($insertId) {
                    $this->data[$pk] = $insertId;
                }
            }
            // 标记为更新
            $this->isUpdate = true;
            // 清空change
            $this->change = [];
            // 新增回调
            $this->trigger('after_insert', $this);
        }
        // 写入回调
        $this->trigger('after_write', $this);

        return $result;
    }

    /**
     * 保存多个数据到当前数据对象
     * @access public
     * @param array     $dataSet 数据
     * @param boolean   $replace 是否自动识别更新和写入
     * @return array|false
     */
    public function saveAll($dataSet, $replace = true)
    {
        if ($this->validate) {
            // 数据批量验证
            $validate = $this->validate;
            foreach ($dataSet as $data) {
                if (!$this->validate($validate)->validateData($data)) {
                    return false;
                }
            }
        }

        $result = [];
        $db     = $this->db();
        $db->startTrans();
        try {
            $pk = $this->getPk();
            if (is_string($pk) && $replace) {
                $auto = true;
            }
            foreach ($dataSet as $key => $data) {
                if (!empty($auto) && isset($data[$pk])) {
                    $result[$key] = self::update($data);
                } else {
                    $result[$key] = self::create($data);
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
     * @param bool|array $field 允许写入的字段 如果为true只允许写入数据表字段
     * @return $this
     */
    public function allowField($field)
    {
        if (true === $field) {
            $field = $this->db()->getTableInfo('', 'fields');
        }
        $this->field = $field;
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
            if (!in_array($field, $this->change)) {
                $this->setAttr($field, !is_null($value) ? $value : (isset($this->data[$field]) ? $this->data[$field] : $value));
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
     * 设置字段验证
     * @access public
     * @param array|string|bool $rule 验证规则 true表示自动读取验证器类
     * @param array             $msg 提示信息
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
     * 设置验证失败后是否抛出异常
     * @access public
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    public function validateFailException($fail = true)
    {
        $this->failException = $fail;
        return $this;
    }

    /**
     * 自动验证数据
     * @access protected
     * @param array $data 验证数据
     * @return bool
     */
    protected function validateData($data)
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
            if (!$validate->check($data)) {
                $this->error = $validate->getError();
                if ($this->failException) {
                    throw new ValidateException($this->error);
                } else {
                    return false;
                }
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
     * @param string        $event 事件名
     * @param callable      $callback 回调方法
     * @param bool          $override 是否覆盖
     * @return void
     */
    public static function event($event, $callback, $override = false)
    {
        $class = get_called_class();
        if ($override) {
            self::$event[$class][$event] = [];
        }
        self::$event[$class][$event][] = $callback;
    }

    /**
     * 触发事件
     * @access protected
     * @param string    $event 事件名
     * @param mixed     $params 传入参数（引用）
     * @return bool
     */
    protected function trigger($event, &$params)
    {
        if (isset(self::$event[$this->class][$event])) {
            foreach (self::$event[$this->class][$event] as $callback) {
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
     * @param array     $data 数据数组
     * @return $this
     */
    public static function create($data = [])
    {
        $model = new static();
        $model->isUpdate(false)->save($data, []);
        return $model;
    }

    /**
     * 更新数据
     * @access public
     * @param array     $data 数据数组
     * @param array     $where 更新条件
     * @return $this
     */
    public static function update($data = [], $where = [])
    {
        $model  = new static();
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
    public static function get($data = null, $with = [], $cache = false)
    {
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
        $query = static::parseQuery($data, $with, $cache);
        return $query->select($data);
    }

    /**
     * 分析查询表达式
     * @access public
     * @param mixed         $data 主键列表或者查询条件（闭包）
     * @param string        $with 关联预查询
     * @param bool          $cache 是否缓存
     * @return Query
     */
    protected static function parseQuery(&$data, $with, $cache)
    {
        $result = self::with($with)->cache($cache);
        if (is_array($data) && key($data) !== 0) {
            $result = $result->where($data);
            $data   = null;
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [ & $result]);
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
            call_user_func_array($data, [ & $query]);
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
     * 命名范围
     * @access public
     * @param string|array|Closure  $name 命名范围名称 逗号分隔
     * @param mixed                 ...$params 参数调用
     * @return Model
     */
    public static function scope($name)
    {
        if ($name instanceof Query) {
            return $name;
        }
        $model     = new static();
        $params    = func_get_args();
        $params[0] = $model->db();
        if ($name instanceof \Closure) {
            call_user_func_array($name, $params);
        } elseif (is_string($name)) {
            $name = explode(',', $name);
        }
        if (is_array($name)) {
            foreach ($name as $scope) {
                $method = 'scope' . trim($scope);
                if (method_exists($model, $method)) {
                    call_user_func_array([$model, $method], $params);
                }
            }
        }
        return $model;
    }

    /**
     * 设置是否使用全局查询范围
     * @param bool  $use 是否启用全局查询范围
     * @access public
     * @return Model
     */
    public static function useGlobalScope($use)
    {
        $model                 = new static();
        $model->useGlobalScope = $use;
        return $model;
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param string    $relation 关联方法名
     * @param string    $operator 比较操作符
     * @param integer   $count 个数
     * @param string    $id 关联表的统计字段
     * @return Model
     */
    public static function has($relation, $operator = '>=', $count = 1, $id = '*')
    {
        $model = new static();
        $info  = $model->$relation()->getRelationInfo();
        $table = $info['model']::getTable();
        switch ($info['type']) {
            case Relation::HAS_MANY:
                return $model->db()->alias('a')
                    ->join($table . ' b', 'a.' . $info['localKey'] . '=b.' . $info['foreignKey'], $info['joinType'])
                    ->group('b.' . $info['foreignKey'])
                    ->having('count(' . $id . ')' . $operator . $count);
            case Relation::HAS_MANY_THROUGH: // TODO
            default:
                return $model;
        }
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param string    $relation 关联方法名
     * @param mixed     $where 查询条件（数组或者闭包）
     * @return Model
     */
    public static function hasWhere($relation, $where = [])
    {
        $model = new static();
        $info  = $model->$relation()->getRelationInfo();
        switch ($info['type']) {
            case Relation::HAS_ONE:
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
                    ->join($table . ' b', 'a.' . $info['localKey'] . '=b.' . $info['foreignKey'], $info['joinType'])
                    ->where($where);
            case Relation::HAS_MANY_THROUGH: // TODO
            default:
                return $model;
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
     * @param array     $resultSet 数据集
     * @param string    $relation 关联名
     * @return array
     */
    public function eagerlyResultSet($resultSet, $relation)
    {
        return $this->relation()->eagerlyResultSet($resultSet, $relation);
    }

    /**
     * 预载入关联查询 返回模型对象
     * @access public
     * @param Model     $result 数据对象
     * @param string    $relation 关联名
     * @return Model
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
     * @param string $joinType JOIN类型
     * @return Relation
     */
    public function hasOne($model, $foreignKey = '', $localKey = '', $alias = [], $joinType = 'INNER')
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: Loader::parseName($this->name) . '_id';
        return $this->relation()->hasOne($model, $foreignKey, $localKey, $alias, $joinType);
    }

    /**
     * BELONGS TO 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $otherKey 关联主键
     * @param array  $alias 别名定义
     * @param string $joinType JOIN类型
     * @return Relation
     */
    public function belongsTo($model, $foreignKey = '', $otherKey = '', $alias = [], $joinType = 'INNER')
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $foreignKey = $foreignKey ?: Loader::parseName(basename(str_replace('\\', '/', $model))) . '_id';
        $otherKey   = $otherKey ?: (new $model)->getPk();
        return $this->relation()->belongsTo($model, $foreignKey, $otherKey, $alias, $joinType);
    }

    /**
     * HAS MANY 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @return Relation
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
     * @return Relation
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
     * @return Relation
     */
    public function belongsToMany($model, $table = '', $foreignKey = '', $localKey = '', $alias = [])
    {
        // 记录当前关联信息
        $model      = $this->parseModel($model);
        $name       = Loader::parseName(basename(str_replace('\\', '/', $model)));
        $table      = $table ?: $this->db()->getTable(Loader::parseName($this->name) . '_' . $name);
        $foreignKey = $foreignKey ?: $name . '_id';
        $localKey   = $localKey ?: Loader::parseName($this->name) . '_id';
        return $this->relation()->belongsToMany($model, $table, $foreignKey, $localKey, $alias);
    }

    public function __call($method, $args)
    {
        $query = $this->db();
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

    public static function __callStatic($method, $params)
    {
        $query = self::getDb();
        return call_user_func_array([$query, $method], $params);
    }

    protected static function getDb()
    {
        $model = get_called_class();
        if (!isset(self::$links[$model])) {
            self::$links[$model] = (new static())->db();
        }
        return self::$links[$model];
    }

    /**
     * 修改器 设置数据对象的值
     * @access public
     * @param string    $name 名称
     * @param mixed     $value 值
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

}
