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

namespace think;

use think\db\Query;

/**
 * Class Model
 * @package think
 * @mixin Query
 */
abstract class Model implements \JsonSerializable, \ArrayAccess
{
    use model\concern\Attribute;
    use model\concern\RelationShip;
    use model\concern\ModelEvent;
    use model\concern\TimeStamp;
    use model\concern\Conversion;

    /**
     * 是否更新数据
     * @var bool
     */
    private $isUpdate = false;

    /**
     * 是否Replace
     * @var bool
     */
    private $replace = false;

    /**
     * 是否强制更新所有数据
     * @var bool
     */
    private $force = false;

    /**
     * 更新条件
     * @var array
     */
    private $updateWhere;

    /**
     * 数据库配置信息
     * @var array|string
     */
    protected $connection = [];

    /**
     * 数据库查询对象类名
     * @var string
     */
    protected $query;

    /**
     * 模型名称
     * @var string
     */
    protected $name;

    /**
     * 数据表名称
     * @var string
     */
    protected $table;

    /**
     * 写入自动完成定义
     * @var array
     */
    protected $auto = [];

    /**
     * 新增自动完成定义
     * @var array
     */
    protected $insert = [];

    /**
     * 更新自动完成定义
     * @var array
     */
    protected $update = [];

    /**
     * 初始化过的模型.
     * @var array
     */
    protected static $initialized = [];

    /**
     * 是否从主库读取（主从分布式有效）
     * @var array
     */
    protected static $readMaster;

    /**
     * 查询对象实例
     * @var Query
     */
    protected $queryInstance;

    /**
     * 错误信息
     * @var mixed
     */
    protected $error;

    /**
     * 软删除字段默认值
     * @var mixed
     */
    protected $defaultSoftDelete;

    /**
     * 架构函数
     * @access public
     * @param  array|object $data 数据
     */
    public function __construct($data = [])
    {
        if (is_object($data)) {
            $this->data = get_object_vars($data);
        } else {
            $this->data = $data;
        }

        if ($this->disuse) {
            // 废弃字段
            foreach ((array) $this->disuse as $key) {
                if (array_key_exists($key, $this->data)) {
                    unset($this->data[$key]);
                }
            }
        }

        // 记录原始数据
        $this->origin = $this->data;

        $config = Db::getConfig();

        if (empty($this->name)) {
            // 当前模型名
            $name       = str_replace('\\', '/', static::class);
            $this->name = basename($name);
            if (Container::get('config')->get('class_suffix')) {
                $suffix     = basename(dirname($name));
                $this->name = substr($this->name, 0, -strlen($suffix));
            }
        }

        if (is_null($this->autoWriteTimestamp)) {
            // 自动写入时间戳
            $this->autoWriteTimestamp = $config['auto_timestamp'];
        }

        if (is_null($this->dateFormat)) {
            // 设置时间戳格式
            $this->dateFormat = $config['datetime_format'];
        }

        if (is_null($this->resultSetType)) {
            $this->resultSetType = $config['resultset_type'];
        }

        if (is_null($this->query)) {
            // 设置查询对象
            $this->query = $config['query'];
        }

        if (!empty($this->connection) && is_array($this->connection)) {
            // 设置模型的数据库连接
            $this->connection = array_merge($config, $this->connection);
        }

        if ($this->observerClass) {
            // 注册模型观察者
            static::observe($this->observerClass);
        }

        // 执行初始化操作
        $this->initialize();
    }

    /**
     * 获取当前模型名称
     * @access public
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 是否从主库读取数据（主从分布有效）
     * @access public
     * @param  bool     $all 是否所有模型有效
     * @return $this
     */
    public function readMaster($all = false)
    {
        $model = $all ? '*' : static::class;

        static::$readMaster[$model] = true;

        return $this;
    }

    /**
     * 创建新的模型实例
     * @access public
     * @param  array|object $data 数据
     * @param  bool         $isUpdate 是否为更新
     * @param  mixed        $where 更新条件
     * @return Model
     */
    public function newInstance($data = [], $isUpdate = false, $where = null)
    {
        return (new static($data))->isUpdate($isUpdate, $where);
    }

    /**
     * 创建模型的查询对象
     * @access protected
     * @return Query
     */
    protected function buildQuery()
    {
        // 设置当前模型 确保查询返回模型对象
        $query = Db::connect($this->connection, false, $this->query);
        $query->model($this)
            ->json($this->json)
            ->setJsonFieldType($this->jsonType);

        if (isset(static::$readMaster['*']) || isset(static::$readMaster[static::class])) {
            $query->master(true);
        }

        // 设置当前数据表和模型名
        if (!empty($this->table)) {
            $query->table($this->table);
        } else {
            $query->name($this->name);
        }

        if (!empty($this->pk)) {
            $query->pk($this->pk);
        }

        return $query;
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @param  Query $query 查询对象实例
     * @return $this
     */
    public function setQuery($query)
    {
        $this->queryInstance = $query;
        return $this;
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @param  bool $useBaseQuery 是否调用全局查询范围
     * @return Query
     */
    public function db($useBaseQuery = true)
    {
        if ($this->queryInstance) {
            return $this->queryInstance;
        }

        $query = $this->buildQuery();

        if ($useBaseQuery) {
            // 软删除
            if (method_exists($this, 'withNoTrashed')) {
                $this->withNoTrashed($query);
            }

            // 全局作用域
            if (method_exists($this, 'base')) {
                call_user_func_array([$this, 'base'], [ & $query]);
            }
        }

        // 返回当前模型的数据库查询对象
        return $query;
    }

    /**
     *  初始化模型
     * @access protected
     * @return void
     */
    protected function initialize()
    {
        if (!isset(static::$initialized[static::class])) {
            static::$initialized[static::class] = true;
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
     * 数据自动完成
     * @access protected
     * @param  array $auto 要自动更新的字段列表
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
                $default = null;
            } else {
                $default = $this->data[$field];
            }

            $this->setAttr($field, !is_null($value) ? $value : $default);
        }
    }

    /**
     * 更新是否强制写入数据 而不做比较
     * @access public
     * @param  bool $force
     * @return $this
     */
    public function force($force = true)
    {
        $this->force = $force;
        return $this;
    }

    /**
     * 新增数据是否使用Replace
     * @access public
     * @param  bool $replace
     * @return $this
     */
    public function replace($replace = true)
    {
        $this->replace = $replace;
        return $this;
    }

    /**
     * 保存当前数据对象
     * @access public
     * @param  array  $data     数据
     * @param  array  $where    更新条件
     * @param  string $sequence 自增序列名
     * @return integer|false
     */
    public function save($data = [], $where = [], $sequence = null)
    {
        if (is_string($data)) {
            $sequence = $data;
            $data     = [];
        }

        if (!$this->checkBeforeSave($data, $where)) {
            return false;
        }

        $result = $this->isUpdate ? $this->updateData($where) : $this->insertData($sequence);

        if (false === $result) {
            return false;
        }

        // 写入回调
        $this->trigger('after_write');

        // 重新记录原始数据
        $this->origin = $this->data;

        return $result;
    }

    /**
     * 写入之前检查数据
     * @access protected
     * @param  array   $data  数据
     * @param  array   $where 保存条件
     * @return bool
     */
    protected function checkBeforeSave($data, $where)
    {
        if (!empty($data)) {

            // 数据对象赋值
            foreach ($data as $key => $value) {
                $this->setAttr($key, $value, $data);
            }

            if (!empty($where)) {
                $this->isUpdate    = true;
                $this->updateWhere = $where;
            }
        }

        // 数据自动完成
        $this->autoCompleteData($this->auto);

        // 事件回调
        if (false === $this->trigger('before_write')) {
            return false;
        }

        return true;
    }

    /**
     * 检查数据是否允许写入
     * @access protected
     * @param  array   $append 自动完成的字段列表
     * @return array
     */
    protected function checkAllowFields(array $append = [])
    {
        // 检测字段
        if (empty($this->field) || true === $this->field) {
            $query = $this->db(false);
            $table = $this->table ?: $query->getTable();

            $this->field = $query->getConnection()->getTableFields($table);

            $field = $this->field;
        } else {
            $field = array_merge($this->field, $append);

            if ($this->autoWriteTimestamp) {
                array_push($field, $this->createTime, $this->updateTime);
            }
        }

        if ($this->disuse) {
            // 废弃字段
            $field = array_diff($field, (array) $this->disuse);
        }
        return $field;
    }

    /**
     * 保存写入数据
     * @access protected
     * @param  array   $where 保存条件
     * @return int|false
     */
    protected function updateData($where)
    {
        // 自动更新
        $this->autoCompleteData($this->update);

        // 事件回调
        if (false === $this->trigger('before_update')) {
            return false;
        }

        // 获取有更新的数据
        $data = $this->getChangedData();

        if (empty($data)) {
            // 关联更新
            if (!empty($this->relationWrite)) {
                $this->autoRelationUpdate();
            }

            return 0;
        } elseif ($this->autoWriteTimestamp && $this->updateTime && !isset($data[$this->updateTime])) {
            // 自动写入更新时间
            $data[$this->updateTime] = $this->autoWriteTimestamp($this->updateTime);

            $this->data[$this->updateTime] = $data[$this->updateTime];
        }

        if (empty($where) && !empty($this->updateWhere)) {
            $where = $this->updateWhere;
        }

        // 检查允许字段
        $allowFields = $this->checkAllowFields(array_merge($this->auto, $this->update));

        // 保留主键数据
        foreach ($this->data as $key => $val) {
            if ($this->isPk($key)) {
                $data[$key] = $val;
            }
        }

        $pk    = $this->getPk();
        $array = [];

        foreach ((array) $pk as $key) {
            if (isset($data[$key])) {
                $array[] = [$key, '=', $data[$key]];
                unset($data[$key]);
            }
        }

        if (!empty($array)) {
            $where = $array;
        }

        foreach ((array) $this->relationWrite as $name => $val) {
            if (is_array($val)) {
                foreach ($val as $key) {
                    if (isset($data[$key])) {
                        unset($data[$key]);
                    }
                }
            }
        }

        // 模型更新
        $result = $this->db(false)->where($where)->strict(false)->field($allowFields)->update($data);

        // 关联更新
        if (!empty($this->relationWrite)) {
            $this->autoRelationUpdate();
        }

        // 更新回调
        $this->trigger('after_update');

        return $result;
    }

    /**
     * 新增写入数据
     * @access protected
     * @param  string   $sequence 自增名
     * @return int|false
     */
    protected function insertData($sequence)
    {
        // 自动写入
        $this->autoCompleteData($this->insert);

        // 时间戳自动写入
        $this->checkTimeStampWrite();

        if (false === $this->trigger('before_insert')) {
            return false;
        }

        // 检查允许字段
        $allowFields = $this->checkAllowFields(array_merge($this->auto, $this->insert));

        $result = $this->db(false)->strict(false)->field($allowFields)->insert($this->data, $this->replace, false, $sequence);

        // 获取自动增长主键
        if ($result && $insertId = $this->db(false)->getLastInsID($sequence)) {
            $pk = $this->getPk();

            foreach ((array) $pk as $key) {
                if (!isset($this->data[$key]) || '' == $this->data[$key]) {
                    $this->data[$key] = $insertId;
                }
            }
        }

        // 关联写入
        if (!empty($this->relationWrite)) {
            $this->autoRelationInsert();
        }

        // 标记为更新
        $this->isUpdate = true;

        // 新增回调
        $this->trigger('after_insert');

        return $result;
    }

    /**
     * 字段值(延迟)增长
     * @access public
     * @param  string  $field    字段名
     * @param  integer $step     增长值
     * @param  integer $lazyTime 延时时间(s)
     * @return integer|true
     * @throws Exception
     */
    public function setInc($field, $step = 1, $lazyTime = 0)
    {
        // 读取更新条件
        $where = $this->getWhere();

        // 事件回调
        if (false === $this->trigger('before_update')) {
            return false;
        }

        $result = $this->db(false)->where($where)->setInc($field, $step, $lazyTime);

        if (true !== $result) {
            $this->data[$field] += $step;
        }

        // 更新回调
        $this->trigger('after_update');

        return $result;
    }

    /**
     * 字段值(延迟)减少
     * @access public
     * @param  string  $field    字段名
     * @param  integer $step     减少值
     * @param  integer $lazyTime 延时时间(s)
     * @return integer|true
     * @throws Exception
     */
    public function setDec($field, $step = 1, $lazyTime = 0)
    {
        // 读取更新条件
        $where = $this->getWhere();

        // 事件回调
        if (false === $this->trigger('before_update')) {
            return false;
        }

        $result = $this->db(false)->where($where)->setDec($field, $step, $lazyTime);

        if (true !== $result) {
            $this->data[$field] -= $step;
        }

        // 更新回调
        $this->trigger('after_update');

        return $result;
    }

    /**
     * 获取当前的更新条件
     * @access protected
     * @return mixed
     */
    protected function getWhere()
    {
        // 删除条件
        $pk = $this->getPk();

        if (is_string($pk) && isset($this->data[$pk])) {
            $where[] = [$pk, '=', $this->data[$pk]];
        } elseif (!empty($this->updateWhere)) {
            $where = $this->updateWhere;
        } else {
            $where = null;
        }

        return $where;
    }

    /**
     * 保存多个数据到当前数据对象
     * @access public
     * @param  array   $dataSet 数据
     * @param  boolean $replace 是否自动识别更新和写入
     * @return Collection
     * @throws \Exception
     */
    public function saveAll($dataSet, $replace = true)
    {
        $result = [];

        $db = $this->db(false);
        $db->startTrans();

        try {
            $pk = $this->getPk();

            if (is_string($pk) && $replace) {
                $auto = true;
            }

            foreach ($dataSet as $key => $data) {
                if ($this->isUpdate || (!empty($auto) && isset($data[$pk]))) {
                    $result[$key] = self::update($data, [], $this->field);
                } else {
                    $result[$key] = self::create($data, $this->field);
                }
            }

            $db->commit();

            return $this->toCollection($result);
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * 是否为更新数据
     * @access public
     * @param  mixed  $update
     * @param  mixed  $where
     * @return $this
     */
    public function isUpdate($update = true, $where = null)
    {
        if (is_bool($update)) {
            $this->isUpdate = $update;

            if (!empty($where)) {
                $this->updateWhere = $where;
            }
        } else {
            $this->isUpdate    = true;
            $this->updateWhere = $update;
        }

        return $this;
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

        // 读取更新条件
        $where = $this->getWhere();

        // 删除当前模型数据
        $result = $this->db(false)->where($where)->delete();

        // 关联删除
        if (!empty($this->relationWrite)) {
            $this->autoRelationDelete();
        }

        $this->trigger('after_delete');

        // 清空数据
        $this->data   = [];
        $this->origin = [];

        return $result;
    }

    /**
     * 设置自动完成的字段（ 规则通过修改器定义）
     * @access public
     * @param  array $fields 需要自动完成的字段
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
     * @param  array      $data  数据数组
     * @param  array|true $field 允许字段
     * @param  bool       $replace 使用Replace
     * @return static
     */
    public static function create($data = [], $field = null, $replace = false)
    {
        $model = new static();

        if (!empty($field)) {
            $model->allowField($field);
        }

        $model->isUpdate(false)->replace($replace)->save($data, []);

        return $model;
    }

    /**
     * 更新数据
     * @access public
     * @param  array      $data  数据数组
     * @param  array      $where 更新条件
     * @param  array|true $field 允许字段
     * @return static
     */
    public static function update($data = [], $where = [], $field = null)
    {
        $model = new static();

        if (!empty($field)) {
            $model->allowField($field);
        }

        $model->isUpdate(true)->save($data, $where);

        return $model;
    }

    /**
     * 查找单条记录
     * @access public
     * @param  mixed     $data  主键值或者查询条件（闭包）
     * @param  mixed     $with  关联预查询
     * @param  bool      $cache 是否缓存
     * @param  bool      $failException 是否抛出异常
     * @return static|null
     * @throws exception\DbException
     */
    public static function get($data, $with = [], $cache = false, $failException = false)
    {
        if (is_null($data)) {
            return;
        }

        if (true === $with || is_int($with)) {
            $cache = $with;
            $with  = [];
        }

        $query = static::parseQuery($data, $with, $cache);

        return $query->failException($failException)->find($data);
    }

    /**
     * 查找单条记录 如果不存在直接抛出异常
     * @access public
     * @param  mixed     $data  主键值或者查询条件（闭包）
     * @param  mixed     $with  关联预查询
     * @param  bool      $cache 是否缓存
     * @return static|null
     * @throws exception\DbException
     */
    public static function getOrFail($data, $with = [], $cache = false)
    {
        return self::get($data, $with, $cache, true);
    }

    /**
     * 查找所有记录
     * @access public
     * @param  mixed        $data  主键列表或者查询条件（闭包）
     * @param  array|string $with  关联预查询
     * @param  bool         $cache 是否缓存
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
     * @param  mixed  $data  主键列表或者查询条件（闭包）
     * @param  string $with  关联预查询
     * @param  bool   $cache 是否缓存
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
     * @param  mixed $data 主键列表 支持闭包查询条件
     * @return integer 成功删除的记录数
     */
    public static function destroy($data)
    {
        if (empty($data) && 0 !== $data) {
            return 0;
        }

        $model = new static();

        $query = $model->db();

        if (is_array($data) && key($data) !== 0) {
            $query->where($data);
            $data = null;
        } elseif ($data instanceof \Closure) {
            $data($query);
            $data = null;
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
     * 获取错误信息
     * @access public
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
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
            'data'     => $this->data,
            'relation' => $this->relation,
        ];
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
        $this->setAttr($name, $value);
    }

    /**
     * 获取器 获取数据对象的值
     * @access public
     * @param  string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttr($name);
    }

    /**
     * 检测数据对象的值
     * @access public
     * @param  string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        if (array_key_exists($name, $this->data) || array_key_exists($name, $this->relation)) {
            return true;
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
        unset($this->data[$name], $this->relation[$name]);
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
     * 设置是否使用全局查询范围
     * @access public
     * @param  bool $use 是否启用全局查询范围
     * @return Query
     */
    public static function useGlobalScope($use)
    {
        $model = new static();

        return $model->db($use);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->db(), $method], $args);
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();

        return call_user_func_array([$model->db(), $method], $args);
    }
}
