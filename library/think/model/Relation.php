<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\model;

use think\db\Query;
use think\Exception;
use think\Model;

/**
 * Class Relation
 * @package think\model
 *
 * @mixin Query
 */
abstract class Relation
{
    // 父模型对象
    protected $parent;
    /** @var  Model 当前关联的模型类 */
    protected $model;
    /** @var Query 关联模型查询对象 */
    protected $query;
    // 关联表外键
    protected $foreignKey;
    // 关联表主键
    protected $localKey;
    // 基础查询
    protected $baseQuery;
    // 是否为自关联
    protected $selfRelation;


    /**
     * 获取关联表外键
     * @access public
     * @return String
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * 获取关联表主键
     * @access public
     * @return String
     */
    public function getLocalKey()
    {
        return $this->localKey;
    }

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
     * 获取当前的关联模型类的实例
     * @access public
     * @return Model
     */
    public function getModel()
    {
        return $this->query->getModel();
    }

    /**
     * 获取当前的关联模型类的实例
     * @access public
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * 设置当前关联为自关联
     * @access public
     * @param  bool $self 是否自关联
     * @return $this
     */
    public function selfRelation($self = true)
    {
        $this->selfRelation = $self;
        return $this;
    }

    /**
     * 当前关联是否为自关联
     * @access public
     * @return bool
     */
    public function isSelfRelation()
    {
        return $this->selfRelation;
    }

    /**
     * 封装关联数据集
     * @access public
     * @param  array $resultSet 数据集
     * @return mixed
     */
    protected function resultSetBuild($resultSet)
    {
        return (new $this->model)->toCollection($resultSet);
    }

    protected function getQueryFields($model)
    {
        $fields = $this->query->getOptions('field');
        return $this->getRelationQueryFields($fields, $model);
    }

    protected function getRelationQueryFields($fields, $model)
    {
        if ($fields) {

            if (is_string($fields)) {
                $fields = explode(',', $fields);
            }

            foreach ($fields as &$field) {
                if (false === strpos($field, '.')) {
                    $field = $model . '.' . $field;
                }
            }
        } else {
            $fields = $model . '.*';
        }

        return $fields;
    }

    protected function getQueryWhere(&$where, $relation)
    {
        foreach ($where as $key => &$val) {
            if (is_string($key)) {
                $where[] = [false === strpos($key, '.') ? $relation . '.' . $key : $key, '=', $val];
                unset($where[$key]);
            } elseif (isset($val[0]) && false === strpos($val[0], '.')) {
                $val[0] = $relation . '.' . $val[0];
            }
        }
    }

    /**
     * 更新数据
     * @access public
     * @param  array $data 更新数据
     * @return integer|string
     */
    public function update(array $data = [])
    {
        return $this->query->update($data);
    }

    /**
     * 删除记录
     * @access public
     * @param  mixed $data 表达式 true 表示强制删除
     * @return int
     * @throws Exception
     * @throws PDOException
     */
    public function delete($data = null)
    {
        return $this->query->delete($data);
    }

    /**
     * 执行基础查询（仅执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {}

    public function __call($method, $args)
    {
        if ($this->query) {
            // 执行基础查询
            $this->baseQuery();

            $result = call_user_func_array([$this->query->getModel(), $method], $args);

            return $result === $this->query && !in_array(strtolower($method), ['fetchsql', 'fetchpdo']) ? $this : $result;
        } else {
            throw new Exception('method not exists:' . __CLASS__ . '->' . $method);
        }
    }
}
