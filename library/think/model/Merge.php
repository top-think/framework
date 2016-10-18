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

    protected $relationModel = []; // HAS ONE 关联的模型列表
    protected $fk            = ''; //  外键名 默认为主表名_id
    protected $mapFields     = []; //  需要处理的模型映射字段，避免混淆 array( id => 'user.id'  )

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
     * @param mixed     $data 主键值或者查询条件（闭包）
     * @param string    $with 关联预查询
     * @param bool      $cache 是否缓存
     * @return \think\Model
     */
    public static function get($data = null, $with = [], $cache = false)
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
        $class  = new static();
        $master = $class->name;
        $fields = self::getModelField($query, $master, '', $class->mapFields);
        $query->alias($master)->field($fields);

        foreach ($class->relationModel as $key => $model) {
            $name  = is_int($key) ? $model : $key;
            $table = is_int($key) ? $query->getTable($name) : $model;
            $query->join($table . ' ' . $name, $name . '.' . $class->fk . '=' . $master . '.' . $class->getPk());
            $fields = self::getModelField($query, $name, $table, $class->mapFields);
            $query->field($fields);
        }
        return $query;
    }

    /**
     * 获取关联模型的字段 并解决混淆
     * @access protected
     * @param \think\db\Query   $query 查询对象
     * @param string            $name 模型名称
     * @param string            $table 关联表名称
     * @param array             $map 字段映射
     * @return array
     */
    protected static function getModelField($query, $name, $table = '', $map = [])
    {
        // 获取模型的字段信息
        $fields = $query->getTableInfo($table, 'fields');
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
     * @param mixed     $data 主键列表或者查询条件（闭包）
     * @param string    $with 关联预查询
     * @return array|false|string
     */
    public static function all($data = null, $with = [], $cache = false)
    {
        $query = self::parseQuery($data, $with, $cache);
        $query = self::attachQuery($query);
        return $query->select($data);
    }

    /**
     * 处理写入的模型数据
     * @access public
     * @param string    $model 模型名称
     * @param array     $data 数据
     * @param bool      $insert 是否新增
     * @return void
     */
    protected function parseData($model, $data, $insert = false)
    {
        $item = [];
        foreach ($data as $key => $val) {
            if ($insert || in_array($key, $this->change) || $this->isPk($key)) {
                if ($this->fk != $key && array_key_exists($key, $this->mapFields)) {
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
     * @param mixed     $data 数据
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
                $this->setAttr($key, $value);
            }
            if (!empty($where)) {
                $this->isUpdate = true;
            }
        }

        // 数据自动完成
        $this->autoCompleteData($this->auto);

        // 自动写入更新时间
        if ($this->autoWriteTimestamp && $this->updateTime) {
            $this->setAttr($this->updateTime, null);
        }

        $db = $this->db();
        $db->startTrans();
        try {
            if ($this->isUpdate) {
                // 自动写入
                $this->autoCompleteData($this->update);

                if (false === $this->trigger('before_update', $this)) {
                    return false;
                }

                if (empty($where) && !empty($this->updateWhere)) {
                    $where = $this->updateWhere;
                }

                if (!empty($where)) {
                    $pk = $this->getPk();

                    if (isset($this->mapFields[$pk])) {
                        $pk = $this->mapFields[$pk];
                    }
                    if (isset($where[$pk])) {
                        unset($where[$pk]);
                    }
                }

                // 处理模型数据
                $data = $this->parseData($this->name, $this->data);
                // 写入主表数据
                $result = $db->strict(false)->where($where)->update($data);

                // 写入附表数据
                foreach ($this->relationModel as $key => $model) {
                    $name  = is_int($key) ? $model : $key;
                    $table = is_int($key) ? $db->getTable($model) : $model;
                    // 处理关联模型数据
                    $data  = $this->parseData($name, $this->data);
                    $query = clone $db;
                    if ($query->table($table)->strict(false)->where($this->fk, $this->data[$this->getPk()])->update($data)) {
                        $result = 1;
                    }
                }
                // 清空change
                $this->change = [];
                // 新增回调
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

                // 处理模型数据
                $data = $this->parseData($this->name, $this->data, true);
                // 写入主表数据
                $result = $db->name($this->name)->strict(false)->insert($data);
                if ($result) {
                    $insertId = $db->getLastInsID($sequence);
                    // 写入外键数据
                    $pk = $this->getPk();
                    if ($insertId) {
                        if (is_string($pk)) {
                            $this->data[$pk] = $insertId;
                            if ($this->fk == $pk) {
                                $this->change[] = $pk;
                            }
                        }
                        $this->data[$this->fk] = $insertId;
                    }

                    // 写入附表数据
                    $source = $this->data;
                    if ($insertId && is_string($pk) && isset($source[$pk]) && $this->fk != $pk) {
                        unset($source[$pk]);
                    }
                    foreach ($this->relationModel as $key => $model) {
                        $name  = is_int($key) ? $model : $key;
                        $table = is_int($key) ? $db->getTable($model) : $model;
                        // 处理关联模型数据
                        $data  = $this->parseData($name, $source, true);
                        $query = clone $db;
                        $query->table($table)->strict(false)->insert($data);
                    }
                }
                // 标记为更新
                $this->isUpdate = true;
                // 清空change
                $this->change = [];
                // 新增回调
                $this->trigger('after_insert', $this);
            }
            $db->commit();
            return $result;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
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

        $db = $this->db();
        $db->startTrans();
        try {
            $result = $db->delete($this->data);
            if ($result) {
                // 获取主键数据
                $pk = $this->data[$this->getPk()];

                // 删除关联数据
                foreach ($this->relationModel as $key => $model) {
                    $table = is_int($key) ? $db->getTable($model) : $model;
                    $query = clone $db;
                    $query->table($table)->where($this->fk, $pk)->delete();
                }
            }
            $this->trigger('after_delete', $this);
            $db->commit();
            return $result;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

}
