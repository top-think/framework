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

namespace think\model;

use think\Db;
use think\Model;

class Merge extends Model
{

    protected static $relationModel = []; // HAS ONE 关联的模型列表
    protected $fk                   = ''; //  外键名 默认为主表名_id
    protected $mapFields            = []; //  需要处理的模型映射字段，避免混淆 array( id => 'user.id'  )

    /**
     * 架构函数
     * @access public
     * @param array|object $data 数据
     */
    public function __construct($data = [])
    {
        parent::__construct($data);

        // 设置默认外键名 仅支持单一外键
        if (empty($this->fk)) {
            $this->fk = strtolower($this->name) . '_id';
        }
    }

    /**
     * 查找单条记录
     * @access public
     * @param mixed $data 主键值或者查询条件（闭包）
     * @param string $with 关联预查询
     * @param bool $cache 是否缓存
     * @return \think\Model
     */
    public static function get($data = '', $with = [], $cache = false)
    {
        $query = self::parseQuery($data, $with, $cache);
        $query = self::attachQuery($query);
        return $query->find($data);
    }

    /**
     * 附加查询表达式
     * @access protected
     * @param \think\db\Query $query 查询对象
     * @return \think\db\Query
     */
    protected static function attachQuery($query)
    {
        $master = basename(str_replace('\\', '/', get_called_class()));
        $class  = new static();
        $fields = self::getModelField($master, '', $class->mapFields);
        $query->alias($master)->field($fields);

        foreach (static::$relationModel as $key => $model) {
            $name  = is_int($key) ? $model : $key;
            $table = is_int($key) ? self::db()->name($name)->getTable() : $model;
            $query->join($table . ' ' . $name, $name . '.' . $class->fk . '=' . $master . '.' . $class->pk);
            $fields = self::getModelField($name, $table, $class->mapFields);
            $query->field($fields);
        }
        return $query;
    }

    /**
     * 获取关联模型的字段 并解决混淆
     * @access protected
     * @param string $name 模型名称
     * @param string $table 关联表名称
     * @param array $map 字段映射
     * @return array
     */
    protected static function getModelField($name, $table = '', $map = [])
    {
        // 获取模型的字段信息
        $fields = self::db()->getTableInfo($table, 'fields');
        $array  = [];
        foreach ($fields as $field) {
            if ($key = array_search($name . '.' . $field, $map)) {
                // 需要处理映射字段
                $array[] = $name . '.' . $field . ' AS ' . $key;
            } else {
                $array[] = $field;
            }
        }
        return $array;
    }

    /**
     * 查找所有记录
     * @access public
     * @param mixed $data 主键列表或者查询条件（闭包）
     * @param string $with 关联预查询
     * @return array|false|string
     */
    public static function all($data = [], $with = [], $cache = false)
    {
        $query = self::parseQuery($data, $with, $cache);
        $query = self::attachQuery($query);
        return $query->select($data);
    }

    /**
     * 处理写入的模型数据
     * @access public
     * @param string $model 模型名称
     * @param array $data 数据
     * @param bool $insert 是否新增
     * @return void
     */
    protected function parseData($model, $data, $insert = false)
    {
        $item = [];
        foreach ($data as $key => $val) {
            if ($insert || in_array($key, $this->change) || $this->isPk($key)) {
                if (array_key_exists($key, $this->mapFields)) {
                    list($name, $key) = explode('.', $this->mapFields[$key]);
                    if ($model == $name) {
                        $item[$key] = $val;
                    }
                } else {
                    $item[$key] = $val;
                }
            }
        }
        return $item;
    }

    /**
     * 保存模型数据 以及关联数据
     * @access public
     * @param mixed $data 数据
     * @param array $where 更新条件
     * @return mixed
     */
    public function save($data = [], $where = [], $getInsertId = true)
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
        // 数据自动完成
        $this->autoCompleteData($this->auto);
        // 处理模型数据
        $data = $this->parseData($this->name, $this->data);

        self::db()->startTrans();
        try {
            if ($this->isUpdate) {
                // 自动写入
                $this->autoCompleteData($this->update);

                if (false === $this->trigger('before_update', $this)) {
                    return false;
                }

                // 写入主表数据
                $result = self::db()->strict(false)->update($data);

                // 写入附表数据
                foreach (static::$relationModel as $key => $model) {
                    $name  = is_int($key) ? $model : $key;
                    $table = is_int($key) ? self::db()->name($model)->getTable() : $model;
                    // 处理关联模型数据
                    $data = $this->parseData($name, $this->data);
                    self::db()->table($table)->strict(false)->where($this->fk, $this->data[$this->pk])->update($data);
                }
                // 新增回调
                $this->trigger('after_update', $this);
            } else {
                // 自动写入
                $this->autoCompleteData($this->insert);

                if (false === $this->trigger('before_insert', $this)) {
                    return false;
                }

                // 写入主表数据
                $result = self::db()->name($this->name)->strict(false)->insert($this->data);
                if ($result) {
                    $insertId = self::db()->getLastInsID();
                    // 写入外键数据
                    $this->data[$this->fk] = $insertId;

                    // 写入附表数据
                    foreach (static::$relationModel as $key => $model) {
                        $name  = is_int($key) ? $model : $key;
                        $table = is_int($key) ? self::db()->name($model)->getTable() : $model;
                        // 处理关联模型数据
                        $data = $this->parseData($name, $this->data, true);
                        self::db()->table($table)->strict(false)->insert($data);
                    }
                }
                // 新增回调
                $this->trigger('after_insert', $this);
            }
            self::db()->commit();
            return $result;
        } catch (\PDOException $e) {
            self::db()->rollback();
            return false;
        }
    }

    /**
     * 删除当前的记录 并删除关联数据
     * @access public
     * @return integer
     */
    public function delete()
    {
        if (false === $this->trigger('before_delete', $this)) {
            return false;
        }
        self::db()->startTrans();
        try {
            $result = self::db()->delete($this->data);
            if ($result) {
                // 获取主键数据
                $pk = $this->data[$this->pk];

                // 删除关联数据
                foreach (static::$relationModel as $key => $model) {
                    $table = is_int($key) ? self::db()->name($model)->getTable() : $model;
                    self::db()->table($table)->where($this->fk, $pk)->delete();
                }
            }
            $this->trigger('after_delete', $this);
            self::db()->commit();
            return $result;
        } catch (\PDOException $e) {
            self::db()->rollback();
            return false;
        }
    }

}
