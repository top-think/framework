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
    use model\concern\Scope;
    use model\concern\Conversion;
    use model\concern\DataValidate;

    // 是否为更新数据
    private $isUpdate = false;
    // 更新条件
    private $updateWhere;

    // 数据库配置
    protected $connection = [];
    // 数据库查询对象类名
    protected $queryClass;
    // 数据库查询对象
    protected $query;
    // 当前模型名称
    protected $name;
    // 数据表名称
    protected $table;

    // 写入自动完成列表
    protected $auto = [];
    // 新增自动完成列表
    protected $insert = [];
    // 更新自动完成列表
    protected $update = [];

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

        if (empty($this->name)) {
            // 当前模型名
            $name       = str_replace('\\', '/', get_called_class());
            $this->name = basename($name);
            if (Facade::make('config')->get('class_suffix')) {
                $suffix     = basename(dirname($name));
                $this->name = substr($this->name, 0, -strlen($suffix));
            }
        }

        if (is_null($this->autoWriteTimestamp)) {
            // 自动写入时间戳
            $this->autoWriteTimestamp = $this->getQuery()->getConfig('auto_timestamp');
        }

        if (is_null($this->dateFormat)) {
            // 设置时间戳格式
            $this->dateFormat = $this->getQuery()->getConfig('datetime_format');
        }

        if (is_null($this->resultSetType)) {
            $this->resultSetType = $this->getQuery()->getConfig('resultset_type');
        }

        // 执行初始化操作
        $this->initialize();
    }

    /**
     * 创建模型的查询对象
     * @access protected
     * @return Query
     */
    protected function buildQuery()
    {
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
        $class = $this->queryClass ?: Facade::make('config')->get('database.query');
        $query = new $class();
        $query->connect($connection)->model(get_called_class());

        // 设置当前数据表和模型名
        if (!empty($this->table)) {
            $query->setTable($this->table);
        } else {
            $query->name($this->name);
        }

        if (!empty($this->pk)) {
            $query->pk($this->pk);
        }

        return $query;
    }

    /**
     * 获取当前模型的查询对象
     * @access public
     * @param bool      $buildNewQuery  创建新的查询对象
     * @return Query
     */
    public function getQuery($buildNewQuery = false)
    {
        if (!$buildNewQuery && $this->query) {
            return $this->query;
        } else {
            // 创建模型查询对象
            $query = $this->buildQuery();

            if (!$buildNewQuery) {
                $this->query = $query;
            }
        }

        return $query;
    }

    /**
     * 获取当前模型的数据库查询对象
     * @access public
     * @param bool $useBaseQuery 是否调用全局查询范围
     * @param bool $buildNewQuery 创建新的查询对象
     * @return Query
     */
    public function db($useBaseQuery = true, $buildNewQuery = true)
    {
        $query = $this->getQuery($buildNewQuery);

        // 全局作用域
        if ($useBaseQuery && method_exists($this, 'base')) {
            call_user_func_array([$this, 'base'], [ & $query]);
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

        // 关联写入检查
        if ($this->together) {
            $this->checkAutoRelationWrite();
        }

        // 检测字段
        if (!empty($this->field)) {
            if (true === $this->field) {
                $this->field = $this->getQuery()->getTableInfo('', 'fields');
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

            // 获取有更新的数据
            $data = $this->getChangedData();

            if (empty($data)) {
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

            // 保留主键数据
            foreach ($this->data as $key => $val) {
                if ($this->isPk($key)) {
                    $data[$key] = $val;
                }
            }

            if (is_string($pk) && isset($data[$pk])) {
                if (!isset($where[$pk])) {
                    unset($where);
                    $where[$pk] = $data[$pk];
                }
                unset($data[$pk]);
            }

            if ($this->relationWrite) {
                foreach ($this->relationWrite as $name => $val) {
                    if (is_array($val)) {
                        foreach ($val as $key) {
                            if (isset($data[$key])) {
                                unset($data[$key]);
                            }
                        }
                    }
                }
            }

            // 模型更新
            $result = $this->db()->where($where)->update($data);

            // 关联更新
            if (isset($this->relationWrite)) {
                $this->autoRelationUpdate();
            }

            // 更新回调
            $this->trigger('after_update');
        } else {
            // 自动写入
            $this->autoCompleteData($this->insert);

            // 时间戳自动写入
            $this->checkTimeStampWrite();

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
            if (isset($this->relationWrite)) {
                $this->autoRelationInsert();
            }

            // 标记为更新
            $this->isUpdate = true;

            // 新增回调
            $this->trigger('after_insert');
        }
        // 写入回调
        $this->trigger('after_write');

        // 重新记录原始数据
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
            $this->autoRelationDelete();
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
                $default = null;
            } elseif (isset($this->origin[$field]) && $this->data[$field] === $this->origin[$field]) {
                $default = $this->data[$field];
            }

            $this->setAttr($field, !is_null($value) ? $value : $default);
        }
    }
}
