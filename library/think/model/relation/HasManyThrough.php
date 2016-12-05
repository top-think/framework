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

class HasManyThrough extends Relation
{
    // 中间关联表外键
    protected $throughKey;

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
        $this->middle     = $through;
        $this->foreignKey = $foreignKey;
        $this->throughKey = $throughKey;
        $this->localKey   = $localKey;
        $this->alias      = $alias;
        $this->query      = (new $model)->db();
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

    }

    public function __call($method, $args)
    {
        static $baseQuery = false;
        if ($this->query) {
            if (empty($baseQuery)) {
                $baseQuery    = true;
                $through      = $this->middle;
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
