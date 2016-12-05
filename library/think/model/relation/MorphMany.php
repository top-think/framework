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

namespace think\model\relation;

use think\Db;
use think\db\Query;
use think\Model;
use think\model\Relation;

class MorphMany extends Relation
{
    // 多态字段
    protected $morphKey;
    protected $morphType;
    // 多态类型
    protected $type;

    /**
     * 架构函数
     * @access public
     * @param Model  $parent 上级模型对象
     * @param string $model 模型名
     * @param string $morphKey 关联外键
     * @param string $morphType 多态字段名
     * @param string $type 多态类型
     */
    public function __construct(Model $parent, $model, $morphKey, $morphType, $type)
    {
        $this->parent    = $parent;
        $this->model     = $model;
        $this->type      = $type;
        $this->morphKey  = $morphKey;
        $this->morphType = $morphType;
        $this->query     = (new $model)->db();
    }

    // 动态获取关联数据
    public function getRelation()
    {
        return $this->select();
    }

    /**
     * 预载入关联查询
     * @access public
     * @param Query     $query 查询对象
     * @param string    $relation 关联名
     * @param bool      $first 是否需要使用基础表
     * @return void
     */
    public function eagerly(Query $query, $relation, $subRelation, $closure, $first)
    {

    }

    /**
     * 预载入关联查询 返回数据集
     * @access public
     * @param array     $resultSet 数据集
     * @param string    $relation 关联名
     * @param string    $class 数据集对象名 为空表示数组
     * @return array
     */
    public function eagerlyResultSet(&$resultSet, $relation, $subRelation, $closure, $class)
    {
        $morphType = $this->morphType;
        $morphKey  = $this->morphKey;
        $type      = $this->type;
        $range     = [];
        foreach ($resultSet as $result) {
            $pk = $result->getPk();
            // 获取关联外键列表
            if (isset($result->$pk)) {
                $range[] = $result->$pk;
            }
        }

        if (!empty($range)) {
            $this->where[$morphKey]  = ['in', $range];
            $this->where[$morphType] = $type;
            $data                    = $this->eagerlyMorphToMany([
                $morphKey  => ['in', $range],
                $morphType => $type,
            ], $relation, $subRelation, $closure);

            // 关联数据封装
            foreach ($resultSet as $result) {
                if (!isset($data[$result->$pk])) {
                    $data[$result->$pk] = [];
                }
                $result->setAttr($relation, $this->resultSetBuild($data[$result->$pk], $class));
            }
        }
    }

    /**
     * 预载入关联查询 返回模型对象
     * @access public
     * @param Model     $result 数据对象
     * @param string    $relation 关联名
     * @param string    $class 数据集对象名 为空表示数组
     * @return Model
     */
    public function eagerlyResult(&$result, $relation, $subRelation, $closure, $class)
    {
        $morphType = $this->morphType;
        $morphKey  = $this->morphKey;
        $type      = $this->type;
        $pk        = $result->getPk();
        if (isset($result->$pk)) {
            $data = $this->eagerlyMorphToMany([$morphKey => $result->$pk, $morphType => $type], $relation, $subRelation, $closure);
            $result->setAttr($relation, $this->resultSetBuild($data[$result->$pk], $class));
        }
    }

    /**
     * 多态一对多 关联模型预查询
     * @access public
     * @param object    $model 关联模型对象
     * @param array     $where 关联预查询条件
     * @param string    $relation 关联名
     * @param string    $subRelation 子关联
     * @return array
     */
    protected function eagerlyMorphToMany($where, $relation, $subRelation = '', $closure = false)
    {
        // 预载入关联查询 支持嵌套预载入
        if ($closure) {
            call_user_func_array($closure, [ & $this]);
        }
        $list     = $this->query->where($where)->with($subRelation)->select();
        $morphKey = $this->morphKey;
        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $data[$set->$morphKey][] = $set;
        }
        return $data;
    }

    public function __call($method, $args)
    {
        static $baseQuery = false;
        if ($this->query) {
            if (empty($baseQuery)) {
                $baseQuery             = true;
                $pk                    = $this->parent->getPk();
                $map[$this->morphKey]  = $this->parent->$pk;
                $map[$this->morphType] = $this->type;
                $this->query->where($map);
            }

            $result = call_user_func_array([$this->query, $method], $args);
            if ($result instanceof \think\db\Query) {
                $this->option = $result->getOptions();
                return $this;
            } else {
                $this->option = [];
                $baseQuery    = false;
                return $result;
            }
        } else {
            throw new Exception('method not exists:' . __CLASS__ . '->' . $method);
        }
    }
}
