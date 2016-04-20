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

    /**
     * 架构函数
     * @access public
     * @param \think\Model $model 上级模型对象
     */
    public function __construct($model)
    {
        $this->parent = $model;
        $this->db     = $model->db();
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
    public function getRelation($name)
    {
        // 执行关联定义方法
        $relation   = $this->parent->$name();
        $foreignKey = $this->foreignKey;
        $localKey   = $this->localKey;
        // 判断关联类型执行查询
        switch ($this->type) {
            case self::HAS_ONE:
            case self::BELONGS_TO:
                $result = $relation->where($foreignKey, $this->parent->$localKey)->find();
                if (false === $result) {
                    $class               = $this->model;
                    $result              = new $class;
                    $result->$foreignKey = $this->parent->$localKey;
                }
                break;
            case self::HAS_MANY:
                $result = $relation->where($foreignKey, $this->parent->$localKey)->select();
                break;
            case self::BELONGS_TO_MANY:
                // 关联查询
                $pk                                = $this->parent->getPk();
                $condition['pivot.' . $foreignKey] = $this->parent->$pk;
                $result                            = $this->belongsToManyQuery($this->model, $this->middle, $localKey, $foreignKey, $condition)->select();
                foreach ($result as $set) {
                    $pivot = [];
                    foreach ($set->toArray() as $key => $val) {
                        if (strpos($key, '__')) {
                            list($name, $attr) = explode('__', $key, 2);
                            if ('pivot' == $name) {
                                $pivot[$attr] = $val;
                                unset($set->$key);
                            }
                        }
                    }
                    $set->pivot = new Pivot($pivot, $this->middle);
                }
                break;
            default:
                // 直接返回
                $result = $model;
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
        $relations = is_string($relation) ? explode(',', $relation) : $relation;

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
                        $data = $this->eagerlyOneToMany($this->model, [$foreignKey => ['in', $range]], $relation, $subRelation);

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
                        // 查询关联数据
                        $data = $this->eagerlyManyToMany($this->model, ['pivot.' . $foreignKey => ['in', $range]], $relation, $subRelation);

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
        $relations = is_string($relation) ? explode(',', $relation) : $relation;

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
                    $this->match($this->model, $relation, $result);
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
                    $pk = $result->getPk();
                    if (isset($result->$pk)) {
                        $pk = $result->$pk;
                        // 查询管理数据
                        $data = $this->eagerlyManyToMany($this->model, ['pivot.' . $foreignKey => $pk], $relation, $subRelation);

                        // 关联数据封装
                        if (!isset($data[$pk])) {
                            $data[$pk] = [];
                        }
                        $result->__set($relation, $data[$pk]);
                    }
                    break;

            }
        }
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
                list($name, $attr) = explode('__', $key, 2);
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
        $list = $model->where($where)->with($subRelation)->select();

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
        $localKey   = $this->localKey;
        // 预载入关联查询 支持嵌套预载入
        $list = $this->belongsToManyQuery($model, $this->middle, $localKey, $foreignKey, $where)->with($subRelation)->select();

        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $pivot = [];
            foreach ($set->toArray() as $key => $val) {
                if (strpos($key, '__')) {
                    list($name, $attr) = explode('__', $key, 2);
                    if ('pivot' == $name) {
                        $pivot[$attr] = $val;
                        unset($set->$key);
                    }
                }
            }
            $set->pivot                = new Pivot($pivot, $this->middle);
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

        // 返回关联的模型对象
        return $this;
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

        // 返回关联的模型对象
        return $this;
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

        // 返回关联的模型对象
        return $this;
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

        // 返回关联的模型对象
        return $this;
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
        return $model::field($tableName . '.*')
            ->field(true, false, $table, 'pivot', 'pivot__')
            ->join($table . ' pivot', 'pivot.' . $localKey . '=' . $tableName . '.' . $relationFk)
            ->where($condition);
    }

    /**
     * 保存当前关联数据对象
     * @access public
     * @param array $data 数据
     * @return integer
     */
    public function save($data, $pivot = [])
    {
        // 判断关联类型执行查询
        switch ($this->type) {
            case self::HAS_ONE:
            case self::BELONGS_TO:
            case self::HAS_MANY:
                $data[$this->foreignKey] = $this->parent->{$this->localKey};
                break;
            case self::BELONGS_TO_MANY:
                break;
        }
        $model = new $this->model;
        return $model->save($data);
    }

    /**
     * 保存当前关联数据对象
     * @access public
     * @param array $data 数据
     * @return integer
     */
    public function saveAll($dataSet, $pivot = [])
    {
        foreach ($dataSet as $data) {
            // 判断关联类型执行查询
            switch ($this->type) {
                case self::HAS_MANY:
                    $data[$this->foreignKey] = $this->parent->{$this->localKey};
                    break;
                case self::BELONGS_TO_MANY:
                    break;
            }
        }
        $model = new $this->model;
        return $model->saveAll($dataSet);
    }

    public function __call($method, $args)
    {
        $model = new $this->model;
        return call_user_func_array([$model->db(), $method], $args);
    }

}
