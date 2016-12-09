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
}
