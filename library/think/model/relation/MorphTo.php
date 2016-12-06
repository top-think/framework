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

use think\Loader;
use think\Model;
use think\model\Relation;

class MorphTo extends Relation
{
    // 多态字段
    protected $morphKey;
    protected $morphType;

    /**
     * 架构函数
     * @access public
     * @param Model  $parent 上级模型对象
     * @param string $morphType 多态字段名
     * @param string $morphKey 外键名
     * @param array  $alias 多态别名定义
     */
    public function __construct(Model $parent, $morphType, $morphKey, $alias = [])
    {
        $this->parent    = $parent;
        $this->morphType = $morphType;
        $this->morphKey  = $morphKey;
        $this->alias     = $alias;
    }

    /**
     * 延迟获取关联数据
     * @access public
     */
    public function getRelation()
    {
        $morphKey  = $this->morphKey;
        $morphType = $this->morphType;
        // 多态模型
        $model = $this->parseModel($this->parent->$morphType);
        // 主键数据
        $pk = $this->parent->$morphKey;
        return (new $model)->find($pk);
    }

    /**
     * 解析模型的完整命名空间
     * @access public
     * @param string $model 模型名（或者完整类名）
     * @return string
     */
    protected function parseModel($model)
    {
        if (isset($this->alias[$model])) {
            $model = $this->alias[$model];
        }
        if (false === strpos($model, '\\')) {
            $path = explode('\\', get_class($this->parent));
            array_pop($path);
            array_push($path, Loader::parseName($model, 1));
            $model = implode('\\', $path);
        }
        return $model;
    }

    /**
     * 预载入关联查询
     * @access public
     * @param array     $resultSet 数据集
     * @param string    $relation 当前关联名
     * @param string    $subRelation 子关联名
     * @param \Closure  $closure 闭包
     * @param string    $class 数据集对象名 为空表示数组
     * @return void
     */
    public function eagerlyResultSet(&$resultSet, $relation, $subRelation, $closure, $class)
    {
        $morphKey  = $this->morphKey;
        $morphType = $this->morphType;
        $range     = [];
        foreach ($resultSet as $result) {
            // 获取关联外键列表
            if (!empty($result->$morphKey)) {
                $range[$result->$morphType][] = $result->$morphKey;
            }
        }

        if (!empty($range)) {
            foreach ($range as $key => $val) {
                // 多态类型映射
                $model = $this->parseModel($key);
                $obj   = new $model;
                $pk    = $obj->getPk();
                $list  = $obj->all($val, $subRelation);
                $data  = [];
                foreach ($list as $k => $vo) {
                    $data[$vo->$pk] = $vo;
                }
                foreach ($resultSet as $result) {
                    if ($key == $result->$morphType) {
                        if (!isset($data[$result->$morphKey])) {
                            $data[$result->$morphKey] = [];
                        }
                        $result->setAttr($relation, $this->resultSetBuild($data[$result->$morphKey], $class));
                    }
                }
            }
        }
    }

    /**
     * 预载入关联查询
     * @access public
     * @param Model     $result 数据对象
     * @param string    $relation 当前关联名
     * @param string    $subRelation 子关联名
     * @param \Closure  $closure 闭包
     * @param string    $class 数据集对象名 为空表示数组
     * @return void
     */
    public function eagerlyResult(&$result, $relation, $subRelation, $closure, $class)
    {
        $morphKey  = $this->morphKey;
        $morphType = $this->morphType;
        // 多态类型映射
        $model = $this->parseModel($result->{$this->morphType});
        $this->eagerlyMorphToOne($model, $relation, $result, $subRelation);
    }

    /**
     * 多态MorphTo 关联模型预查询
     * @access public
     * @param object    $model 关联模型对象
     * @param array     $where 关联预查询条件
     * @param string    $relation 关联名
     * @param string    $subRelation 子关联
     * @return void
     */
    protected function eagerlyMorphToOne($model, $relation, &$result, $subRelation = '')
    {
        // 预载入关联查询 支持嵌套预载入
        $pk   = $this->parent->{$this->morphKey};
        $data = (new $model)->with($subRelation)->find($pk);
        if ($data) {
            $data->isUpdate(true);
        }
        $result->setAttr($relation, $data ?: null);
    }

    /**
     * 执行基础查询（进执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {
    }
}
