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

class Relation
{
    const HAS_ONE         = 1;
    const HAS_MANY        = 2;
    const BELONGS_TO      = 3;
    const BELONGS_TO_MANY = 4;

    // 父模型
    protected $parent;
    // 当前的模型类
    protected $model;
    // 中间表模型
    protected $middle;
    // 当前关联类型
    protected $type;
    // 关联外键
    protected $foreignKey;
    // 关联键
    protected $localKey;
    // 是否预载入
    protected $eagerly = false;

    /**
     * 架构函数
     * @access public
     * @param \think\Model $model 上级模型对象
     */
    public function __construct($model)
    {
        $this->parent = $model;
    }

    /**
     * 获取当前关联信息
     * @access public
     * @param string $name 关联信息
     * @return array|string|integer
     */
    public function getRelationInfo($name = '')
    {
        $info = [
            'type'       => $this->type,
            'model'      => $this->model,
            'middle'     => $this->middle,
            'foreignKey' => $this->foreignKey,
            'localKey'   => $this->localKey,
        ];
        return $name ? $info[$name] : $info;
    }

    // 获取关联数据
    public function getRelation($relation)
    {
        // 执行关联定义方法
        $db = $this->parent->$relation();
        // 判断关联类型执行查询
        switch ($this->type) {
            case self::HAS_ONE:
            case self::BELONGS_TO:
                $result = $db->find();
                break;
            case self::HAS_MANY:
            case self::BELONGS_TO_MANY:
                $result = $db->select();
                break;
            default:
                // 直接返回
                $result = $db;
        }
        return $result;
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
        $this->eagerly = true;
        $relations     = is_string($relation) ? explode(',', $relation) : $relation;

        foreach ($relations as $relation) {
            $subRelation = '';
            if (strpos($relation, '.')) {
                list($relation, $subRelation) = explode('.', $relation);
            }
            // 执行关联方法
            $model = $this->parent->$relation();
            // 获取关联信息
            $localKey   = $this->localKey;
            $foreignKey = $this->foreignKey;
            switch ($this->type) {
                case self::HAS_ONE:
                case self::BELONGS_TO:
                    foreach ($resultSet as $result) {
                        // 模型关联组装
                        $this->match($model, $relation, $result);
                    }
                    break;
                case self::HAS_MANY:
                    $range = [];
                    foreach ($resultSet as $result) {
                        // 获取关联外键列表
                        if (isset($result->$localKey)) {
                            $range[] = $result->$localKey;
                        }
                    }

                    if (!empty($range)) {
                        $data = $this->eagerlyOneToMany($model, [$foreignKey => ['in', $range]], $relation, $subRelation);

                        // 关联数据封装
                        foreach ($resultSet as $result) {
                            if (isset($data[$result->$localKey])) {
                                $result->__set($relation, $data[$result->$localKey]);
                            } else {
                                $result->__set($relation, []);
                            }
                        }
                    }
                    break;
                case self::BELONGS_TO_MANY:
                    $pk    = $resultSet[0]->getPk();
                    $range = [];
                    foreach ($resultSet as $result) {
                        // 获取关联外键列表
                        if (isset($result->$pk)) {
                            $range[] = $result->$pk;
                        }
                    }

                    if (!empty($range)) {
                        $condition[$this->middle . '.' . $foreignKey] = ['in', $range];
                        $data                                         = $this->eagerlyManyToMany($model, $condition, $relation, $subRelation);

                        // 关联数据封装
                        foreach ($resultSet as $result) {
                            if (isset($data[$result->$pk])) {
                                $result->__set($relation, $data[$result->$pk]);
                            } else {
                                $result->__set($relation, []);
                            }
                        }
                    }
                    break;
            }
            $this->relation = [];
        }
        $this->eagerly = false;
        return $resultSet;
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
        $this->eagerly = true;
        $relations     = is_string($relation) ? explode(',', $relation) : $relation;

        foreach ($relations as $relation) {
            $subRelation = '';
            if (strpos($relation, '.')) {
                list($relation, $subRelation) = explode('.', $relation);
            }
            // 执行关联方法
            $model      = $this->parent->$relation();
            $localKey   = $this->localKey;
            $foreignKey = $this->foreignKey;
            switch ($this->type) {
                case self::HAS_ONE:
                case self::BELONGS_TO:
                    // 模型关联组装
                    $this->match($model, $relation, $result);
                    break;
                case self::HAS_MANY:
                    if (isset($result->$localKey)) {
                        $data = $this->eagerlyOneToMany($model, [$foreignKey => $result->$localKey], $relation, $subRelation);
                        // 关联数据封装
                        if (!isset($data[$result->$localKey])) {
                            $data[$result->$localKey] = [];
                        }
                        $result->__set($relation, $data[$result->$localKey]);
                    }
                    break;
                case self::BELONGS_TO_MANY:
                    $pk                                           = $result->getPk();
                    $condition[$this->middle . '.' . $foreignKey] = $this->parent->$pk;
                    if (isset($result->$pk)) {
                        $data = $this->eagerlyManyToMany($model, $condition, $relation, $subRelation);
                        // 关联数据封装
                        if (!isset($data[$result->$pk])) {
                            $data[$result->$pk] = [];
                        }
                        $result->__set($relation, $data[$result->$pk]);
                    }
                    break;

            }
        }
        $this->eagerly = false;
        return $result;
    }

    /**
     * 一对一 关联模型预查询拼装
     * @access public
     * @param string $model 模型名称
     * @param string $relation 关联名
     * @param Model $result 模型对象实例
     * @return void
     */
    protected function match($model, $relation, &$result)
    {
        $modelName = strtolower(basename(str_replace('\\', '/', $model)));
        // 重新组装模型数据
        foreach ($result->toArray() as $key => $val) {
            if (strpos($key, '__')) {
                list($name, $attr) = explode('__', $key);
                if ($name == $modelName) {
                    $list[$name][$attr] = $val;
                    unset($result->$key);
                }
            }
        }

        if (!isset($list[$modelName])) {
            // 设置关联模型属性
            $list[$modelName] = [];
        }
        $result->__set($relation, new $model($list[$modelName]));
    }

    /**
     * 一对多 关联模型预查询
     * @access public
     * @param string $model 模型名称
     * @param array $where 关联预查询条件
     * @param string $relation 关联名
     * @param string $subRelation 子关联
     * @return void
     */
    protected function eagerlyOneToMany($model, $where, $relation, $subRelation = '')
    {
        $foreignKey = $this->foreignKey;
        // 预载入关联查询 支持嵌套预载入
        $list = $model::where($where)->with($subRelation)->select();

        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $data[$set->$foreignKey][] = $set;
        }
        return $data;
    }

    /**
     * 多对多 关联模型预查询
     * @access public
     * @param string $model 模型名称
     * @param array $where 关联预查询条件
     * @param string $relation 关联名
     * @param string $subRelation 子关联
     * @return void
     */
    protected function eagerlyManyToMany($model, $where, $relation, $subRelation = '')
    {
        $foreignKey = $this->foreignKey;
        // 预载入关联查询 支持嵌套预载入
        $list = $this->belongsToManyQuery($model, $this->middle, $this->localKey, $this->foreignKey, $where)->with($subRelation)->select();

        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $data[$set->$foreignKey][] = $set;
        }
        return $data;
    }

    /**
     * HAS ONE 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @return \think\db\Query|string
     */
    public function hasOne($model, $foreignKey, $localKey)
    {
        $this->type       = self::HAS_ONE;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;

        if (!$this->eagerly && isset($this->parent->$localKey)) {
            // 关联查询封装
            return $model::where($foreignKey, $this->parent->$localKey);
        } else {
            // 预载入封装
            return $model;
        }
    }

    /**
     * BELONGS TO 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $localKey 关联主键
     * @param string $foreignKey 关联外键
     * @return \think\db\Query|string
     */
    public function belongsTo($model, $localKey, $foreignKey)
    {
        // 记录当前关联信息
        $this->type       = self::BELONGS_TO;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;

        if (!$this->eagerly && isset($this->parent->$localKey)) {
            // 关联查询封装
            return $model::where($foreignKey, $this->parent->$localKey);
        } else {
            // 预载入封装
            return $model;
        }
    }

    /**
     * HAS MANY 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @return \think\db\Query|string
     */
    public function hasMany($model, $foreignKey, $localKey)
    {
        // 记录当前关联信息
        $this->type       = self::HAS_MANY;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;

        if (!$this->eagerly && isset($this->parent->$localKey)) {
            // 关联查询封装
            return $model::where($foreignKey, $this->parent->$localKey);
        } else {
            // 预载入封装
            return $model;
        }
    }

    /**
     * BELONGS TO MANY 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $table 中间表名
     * @param string $localKey 当前模型关联键
     * @param string $foreignKey 关联模型关联键
     * @return \think\db\Query|string
     */
    public function belongsToMany($model, $table, $localKey, $foreignKey)
    {
        // 记录当前关联信息
        $this->type       = self::BELONGS_TO_MANY;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->middle     = $table;
        $pk               = $this->parent->getPk();
        if (!$this->eagerly && isset($this->parent->$pk)) {
            // 关联查询
            $condition[$table . '.' . $foreignKey] = $this->parent->$pk;
            return $this->belongsToManyQuery($model, $table, $localKey, $foreignKey, $condition);
        } else {
            // 预载入封装
            return $model;
        }
    }

    /**
     * BELONGS TO MANY 关联查询
     * @access public
     * @param string $model 模型名
     * @param string $table 中间表名
     * @param string $localKey 当前模型关联键
     * @param string $foreignKey 关联模型关联键
     * @param array $condition 关联查询条件
     * @return \think\db\Query|string
     */
    protected function belongsToManyQuery($model, $table, $localKey, $foreignKey, $condition = [])
    {
        // 关联查询封装
        $tableName  = $model::getTableName();
        $relationFk = (new $model)->getPk();
        return $model::join($table, $table . '.' . $localKey . '=' . $tableName . '.' . $relationFk)
            ->where($condition);
    }

}
