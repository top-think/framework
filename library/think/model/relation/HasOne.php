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

use think\Model;
use think\model\relation\OneToOne;

class HasOne extends OneToOne
{
    /**
     * 架构函数
     * @access public
     * @param Model $parent 上级模型对象
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @param string $joinType JOIN类型
     */
    public function __construct(Model $parent, $model, $foreignKey, $localKey, $alias = [], $joinType = 'INNER')
    {
        $this->parent     = $parent;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->alias      = $alias;
        $this->joinType   = $joinType;
        $this->query      = (new $model)->db();
    }

    /**
     * 延迟获取关联数据
     * @access public
     */
    public function getRelation()
    {
        // 执行关联定义方法
        $localKey = $this->localKey;

        // 判断关联类型执行查询
        return $this->query->where($this->foreignKey, $this->parent->$localKey)->find();
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param Model    $model 模型对象
     * @param mixed    $where 查询条件（数组或者闭包）
     * @return Query
     */
    public function hasWhere($model, $where = [])
    {
        $table = $this->query->getTable();
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
            ->join($table . ' b', 'a.' . $this->localKey . '=b.' . $this->foreignKey, $this->joinType)
            ->where($where);
    }

    /**
     * 预载入关联查询（数据集）
     * @access public
     * @param array     $resultSet 数据集
     * @param string    $relation 当前关联名
     * @param string    $subRelation 子关联名
     * @param \Closure  $closure 闭包
     * @param string    $class 数据集对象名 为空表示数组
     * @return void
     */
    protected function eagerlySet(&$resultSet, $relation, $subRelation, $closure, $class)
    {
        $localKey   = $this->localKey;
        $foreignKey = $this->foreignKey;

        $range = [];
        foreach ($resultSet as $result) {
            // 获取关联外键列表
            if (isset($result->$localKey)) {
                $range[] = $result->$localKey;
            }
        }

        if (!empty($range)) {
            $this->where[$foreignKey] = ['in', $range];
            $data                     = $this->eagerlyWhere($this, [
                $foreignKey => [
                    'in',
                    $range,
                ],
            ], $foreignKey, $relation, $subRelation, $closure);

            // 关联数据封装
            foreach ($resultSet as $result) {
                if (!isset($data[$result->$localKey])) {
                    $data[$result->$localKey] = [];
                }
                $relationModel = $this->resultSetBuild($data[$result->$localKey], $class);
                if (!empty($this->bindAttr)) {
                    // 绑定关联属性
                    $this->bindAttr($relationModel, $result, $this->bindAttr);
                }
                // 设置关联属性
                $result->setAttr($relation, $relationModel);
            }
        }
    }

    /**
     * 预载入关联查询（数据）
     * @access public
     * @param Model     $result 数据对象
     * @param string    $relation 当前关联名
     * @param string    $subRelation 子关联名
     * @param \Closure  $closure 闭包
     * @param string    $class 数据集对象名 为空表示数组
     * @return void
     */
    protected function eagerlyOne(&$result, $relation, $subRelation, $closure, $class)
    {
        $localKey   = $this->localKey;
        $foreignKey = $this->foreignKey;
        $data       = $this->eagerlyWhere($this, [$foreignKey => $result->$localKey], $foreignKey, $relation, $subRelation, $closure);
        // 关联数据封装
        if (!isset($data[$result->$localKey])) {
            $data[$result->$localKey] = [];
        }
        $relationModel = $this->resultSetBuild($data[$result->$localKey], $class);
        if (!empty($this->bindAttr)) {
            // 绑定关联属性
            $this->bindAttr($relationModel, $result, $this->bindAttr);
        }
        $result->setAttr($relation, $relationModel);
    }

}
