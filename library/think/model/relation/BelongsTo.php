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

class BelongsTo extends OneToOne
{
    /**
     * 架构函数
     * @access public
     * @param Model  $parent 上级模型对象
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
        $foreignKey = $this->foreignKey;
        $localKey   = $this->localKey;
        return $this->query->where($localKey, $this->parent->$foreignKey)->find();
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
            if (isset($result->$foreignKey)) {
                $range[] = $result->$foreignKey;
            }
        }

        if (!empty($range)) {
            $this->where[$localKey] = ['in', $range];
            $data                   = $this->eagerlyWhere($this, [
                $localKey => [
                    'in',
                    $range,
                ],
            ], $localKey, $relation, $subRelation, $closure);

            // 关联数据封装
            foreach ($resultSet as $result) {
                if (!isset($data[$result->$foreignKey])) {
                    $data[$result->$foreignKey] = [];
                }
                $relationModel = $this->resultSetBuild($data[$result->$foreignKey], $class);
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
        $data       = $this->eagerlyWhere($this, [$localKey => $result->$foreignKey], $localKey, $relation, $subRelation, $closure);
        // 关联数据封装
        if (!isset($data[$result->$foreignKey])) {
            $data[$result->$foreignKey] = [];
        }
        $relationModel = $this->resultSetBuild($data[$result->$foreignKey], $class);
        if (!empty($this->bindAttr)) {
            // 绑定关联属性
            $this->bindAttr($relationModel, $result, $this->bindAttr);
        }
        // 设置关联属性
        $result->setAttr($relation, $relationModel);
    }

}
