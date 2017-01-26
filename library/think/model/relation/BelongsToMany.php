<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\model\relation;

use think\db\Query;
use think\Exception;
use think\Loader;
use think\Model;
use think\model\Pivot;
use think\model\Relation;

class BelongsToMany extends Relation
{
    // 中间表模型
    protected $middle;

    /**
     * 架构函数
     * @access public
     * @param Model  $parent     上级模型对象
     * @param string $model      模型名
     * @param string $table      中间表名
     * @param string $foreignKey 关联模型外键
     * @param string $localKey   当前模型关联键
     */
    public function __construct(Model $parent, $model, $table, $foreignKey, $localKey)
    {
        $this->parent     = $parent;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->middle     = $table;
        $this->query      = (new $model)->db();
    }

    /**
     * 延迟获取关联数据
     * @param string   $subRelation 子关联名
     * @param \Closure $closure     闭包查询条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getRelation($subRelation = '', $closure = null)
    {
        $foreignKey = $this->foreignKey;
        $localKey   = $this->localKey;
        $middle     = $this->middle;
        if ($closure) {
            call_user_func_array($closure, [& $this->query]);
        }
        // 关联查询
        $pk                              = $this->parent->getPk();
        $condition['pivot.' . $localKey] = $this->parent->$pk;
        $result                          = $this->belongsToManyQuery($middle, $foreignKey, $localKey, $condition)->relation($subRelation)->select();
        foreach ($result as $set) {
            $pivot = [];
            foreach ($set->getData() as $key => $val) {
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
        return $result;
    }

    /**
     * 预载入关联查询（数据集）
     * @access public
     * @param array    $resultSet   数据集
     * @param string   $relation    当前关联名
     * @param string   $subRelation 子关联名
     * @param \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResultSet(&$resultSet, $relation, $subRelation, $closure)
    {
        $localKey   = $this->localKey;
        $foreignKey = $this->foreignKey;

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
            $data = $this->eagerlyManyToMany([
                'pivot.' . $localKey => [
                    'in',
                    $range,
                ],
            ], $relation, $subRelation);
            // 关联属性名
            $attr = Loader::parseName($relation);
            // 关联数据封装
            foreach ($resultSet as $result) {
                if (!isset($data[$result->$pk])) {
                    $data[$result->$pk] = [];
                }

                $result->setAttr($attr, $this->resultSetBuild($data[$result->$pk]));
            }
        }
    }

    /**
     * 预载入关联查询（单个数据）
     * @access public
     * @param Model    $result      数据对象
     * @param string   $relation    当前关联名
     * @param string   $subRelation 子关联名
     * @param \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResult(&$result, $relation, $subRelation, $closure)
    {
        $pk = $result->getPk();
        if (isset($result->$pk)) {
            $pk = $result->$pk;
            // 查询管理数据
            $data = $this->eagerlyManyToMany(['pivot.' . $this->localKey => $pk], $relation, $subRelation);

            // 关联数据封装
            if (!isset($data[$pk])) {
                $data[$pk] = [];
            }
            $result->setAttr(Loader::parseName($relation), $this->resultSetBuild($data[$pk]));
        }
    }

    /**
     * 关联统计
     * @access public
     * @param Model    $result  数据对象
     * @param \Closure $closure 闭包
     * @return integer
     */
    public function relationCount($result, $closure)
    {
        $pk    = $result->getPk();
        $count = 0;
        if (isset($result->$pk)) {
            $pk    = $result->$pk;
            $count = $this->belongsToManyQuery($this->middle, $this->foreignKey, $this->localKey, ['pivot.' . $this->localKey => $pk])->count();
        }
        return $count;
    }

    /**
     * 获取关联统计子查询
     * @access public
     * @param \Closure $closure 闭包
     * @return string
     */
    public function getRelationCountQuery($closure)
    {
        return $this->belongsToManyQuery($this->middle, $this->foreignKey, $this->localKey, [
            'pivot.' . $this->localKey => [
                'exp',
                '=' . $this->parent->getTable() . '.' . $this->parent->getPk()
            ]
        ])->fetchSql()->count();
    }

    /**
     * 多对多 关联模型预查询
     * @access public
     * @param array  $where       关联预查询条件
     * @param string $relation    关联名
     * @param string $subRelation 子关联
     * @return array
     */
    protected function eagerlyManyToMany($where, $relation, $subRelation = '')
    {
        // 预载入关联查询 支持嵌套预载入
        $list = $this->belongsToManyQuery($this->middle, $this->foreignKey, $this->localKey, $where)->with($subRelation)->select();

        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $pivot = [];
            foreach ($set->getData() as $key => $val) {
                if (strpos($key, '__')) {
                    list($name, $attr) = explode('__', $key, 2);
                    if ('pivot' == $name) {
                        $pivot[$attr] = $val;
                        unset($set->$key);
                    }
                }
            }
            $set->pivot                      = new Pivot($pivot, $this->middle);
            $data[$pivot[$this->localKey]][] = $set;
        }
        return $data;
    }

    /**
     * BELONGS TO MANY 关联查询
     * @access public
     * @param string $table      中间表名
     * @param string $foreignKey 关联模型关联键
     * @param string $localKey   当前模型关联键
     * @param array  $condition  关联查询条件
     * @return Query
     */
    protected function belongsToManyQuery($table, $foreignKey, $localKey, $condition = [])
    {
        // 关联查询封装
        $tableName  = $this->query->getTable();
        $relationFk = $this->query->getPk();
        return $this->query->field($tableName . '.*')
            ->field(true, false, $table, 'pivot', 'pivot__')
            ->join($table . ' pivot', 'pivot.' . $foreignKey . '=' . $tableName . '.' . $relationFk)
            ->where($condition);
    }

    /**
     * 保存（新增）当前关联数据对象
     * @access public
     * @param mixed $data  数据 可以使用数组 关联模型对象 和 关联对象的主键
     * @param array $pivot 中间表额外数据
     * @return integer
     */
    public function save($data, array $pivot = [])
    {
        // 保存关联表/中间表数据
        return $this->attach($data, $pivot);
    }

    /**
     * 批量保存当前关联数据对象
     * @access public
     * @param array $dataSet   数据集
     * @param array $pivot     中间表额外数据
     * @param bool  $samePivot 额外数据是否相同
     * @return integer
     */
    public function saveAll(array $dataSet, array $pivot = [], $samePivot = false)
    {
        $result = false;
        foreach ($dataSet as $key => $data) {
            if (!$samePivot) {
                $pivotData = isset($pivot[$key]) ? $pivot[$key] : [];
            } else {
                $pivotData = $pivot;
            }
            $result = $this->attach($data, $pivotData);
        }
        return $result;
    }

    /**
     * 附加关联的一个中间表数据
     * @access public
     * @param mixed $data  数据 可以使用数组、关联模型对象 或者 关联对象的主键
     * @param array $pivot 中间表额外数据
     * @return int
     * @throws Exception
     */
    public function attach($data, $pivot = [])
    {
        if (is_array($data)) {
            if (key($data) === 0) {
                $id = $data;
            } else {
                // 保存关联表数据
                $model = new $this->model;
                $model->save($data);
                $id = $model->getLastInsID();
            }
        } elseif (is_numeric($data) || is_string($data)) {
            // 根据关联表主键直接写入中间表
            $id = $data;
        } elseif ($data instanceof Model) {
            // 根据关联表主键直接写入中间表
            $relationFk = $data->getPk();
            $id         = $data->$relationFk;
        }

        if ($id) {
            // 保存中间表数据
            $pk                     = $this->parent->getPk();
            $pivot[$this->localKey] = $this->parent->$pk;
            $ids                    = (array) $id;
            foreach ($ids as $id) {
                $pivot[$this->foreignKey] = $id;
                $result                   = $this->query->table($this->middle)->insert($pivot, true);
            }
            return $result;
        } else {
            throw new Exception('miss relation data');
        }
    }

    /**
     * 解除关联的一个中间表数据
     * @access public
     * @param integer|array $data        数据 可以使用关联对象的主键
     * @param bool          $relationDel 是否同时删除关联表数据
     * @return integer
     */
    public function detach($data = null, $relationDel = false)
    {
        if (is_array($data)) {
            $id = $data;
        } elseif (is_numeric($data) || is_string($data)) {
            // 根据关联表主键直接写入中间表
            $id = $data;
        } elseif ($data instanceof Model) {
            // 根据关联表主键直接写入中间表
            $relationFk = $data->getPk();
            $id         = $data->$relationFk;
        }
        // 删除中间表数据
        $pk                     = $this->parent->getPk();
        $pivot[$this->localKey] = $this->parent->$pk;
        if (isset($id)) {
            $pivot[$this->foreignKey] = is_array($id) ? ['in', $id] : $id;
        }
        $this->query->table($this->middle)->where($pivot)->delete();

        // 删除关联表数据
        if (isset($id) && $relationDel) {
            $model = $this->model;
            $model::destroy($id);
        }
    }

    /**
     * 执行基础查询（进执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery)) {
            $pk = $this->parent->getPk();
            $this->query->join($this->middle . ' pivot', 'pivot.' . $this->foreignKey . '=' . $this->query->getTable() . '.' . $this->query->getPk())->where('pivot.' . $this->localKey, $this->parent->$pk);
            $this->baseQuery = true;
        }
    }

}
