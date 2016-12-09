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

use think\db\Query;
use think\Exception;
use think\Model;

abstract class Relation
{
    // 父模型对象
    protected $parent;
    /** @var  Model 当前关联的模型类 */
    protected $model;
    // 关联表外键
    protected $foreignKey;
    // 关联表主键
    protected $localKey;
    // 数据表别名
    protected $alias;
    // 当前关联的JOIN类型
    protected $joinType;
    // 关联模型查询对象
    protected $query;
    // 关联查询条件
    protected $where;
    // 关联查询参数
    protected $option;
    // 基础查询
    protected $baseQuery;

    /**
     * 获取关联的所属模型
     * @access public
     * @return Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * 获取当前的关联模型类
     * @access public
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * 获取关联的查询对象
     * @access public
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * 封装关联数据集
     * @access public
     * @param array     $resultSet 数据集
     * @param string    $class 数据集类名
     * @return mixed
     */
    protected function resultSetBuild($resultSet, $class = '')
    {
        return $class ? new $class($resultSet) : $resultSet;
    }

    /**
     * 设置当前关联定义的数据表别名
     * @access public
     * @param array  $alias 别名定义
     * @return $this
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    public function __call($method, $args)
    {
        if ($this->query) {
            // 执行基础查询
            $this->baseQuery();

            $result = call_user_func_array([$this->query, $method], $args);
            if ($result instanceof Query) {
                $this->option = $result->getOptions();
                return $this;
            } else {
                $this->option    = [];
                $this->baseQuery = false;
                return $result;
            }
        } else {
            throw new Exception('method not exists:' . __CLASS__ . '->' . $method);
        }
    }
}
