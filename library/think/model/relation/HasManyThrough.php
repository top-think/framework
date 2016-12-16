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
use think\Loader;
use think\Model;
use think\model\Relation;

class HasManyThrough extends Relation
{
    // 中间关联表外键
    protected $throughKey;
    // 中间表模型
    protected $through;

    /**
     * 架构函数
     * @access public
     * @param Model  $parent 上级模型对象
     * @param string $model 模型名
     * @param string $through 中间模型名
     * @param string $firstkey 关联外键
     * @param string $secondKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     */
    public function __construct(Model $parent, $model, $through, $foreignKey, $throughKey, $localKey, $alias = [])
    {
        $this->parent     = $parent;
        $this->model      = $model;
        $this->through    = $through;
        $this->foreignKey = $foreignKey;
        $this->throughKey = $throughKey;
        $this->localKey   = $localKey;
        $this->alias      = $alias;
        $this->query      = (new $model)->db();
    }

    /**
     * 延迟获取关联数据
     * @access public
     */
    public function getRelation()
    {
        return $this->select();
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
    }

    /**
     * 预载入关联查询 返回模型对象
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
    }

    /**
     * 执行基础查询（进执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery)) {
            $through      = $this->through;
            $model        = $this->model;
            $alias        = Loader::parseName(basename(str_replace('\\', '/', $model)));
            $throughTable = $through::getTable();
            $pk           = (new $this->model)->getPk();
            $throughKey   = $this->throughKey;
            $modelTable   = $this->parent->getTable();
            $this->query->field($alias . '.*')->alias($alias)
                ->join($throughTable, $throughTable . '.' . $pk . '=' . $alias . '.' . $throughKey)
                ->join($modelTable, $modelTable . '.' . $this->localKey . '=' . $throughTable . '.' . $this->foreignKey)
                ->where($throughTable . '.' . $this->foreignKey, $this->parent->{$this->localKey});
            $this->baseQuery = true;
        }
    }

}
