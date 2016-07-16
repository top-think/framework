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

use think\Db;
use think\Exception;
use think\Loader;
use think\Model;
use think\model\Pivot;

class Relation
{
    const HAS_ONE          = 1;
    const HAS_MANY         = 2;
    const HAS_MANY_THROUGH = 5;
    const BELONGS_TO       = 3;
    const BELONGS_TO_MANY  = 4;

    // 父模型对象
    protected $parent;
    /** @var  Model 当前关联的模型类 */
    protected $model;
    // 中间表模型
    protected $middle;
    // 当前关联类型
    protected $type;
    // 关联表外键
    protected $foreignKey;
    // 中间关联表外键
    protected $throughKey;
    // 关联表主键
    protected $localKey;
    // 数据表别名
    protected $alias;
    // 当前关联的JOIN类型
    protected $joinType;
    // 关联模型查询对象
    protected $query;

    /**
     * 架构函数
     * @access public
     * @param Model $model 上级模型对象
     */
    public function __construct(Model $model)
    {
        $this->parent = $model;
    }

    /**
     * 获取当前关联信息
     * @access public
     * @param string $name 关联信息
     * @return array|string|integer
     */
    public function getRelationInfo($name = '')
    {
        $info = [
            'type'       => $this->type,
            'model'      => $this->model,
            'middle'     => $this->middle,
            'foreignKey' => $this->foreignKey,
            'localKey'   => $this->localKey,
            'alias'      => $this->alias,
            'joinType'   => $this->joinType,
        ];
        return $name ? $info[$name] : $info;
    }

    // 获取关联数据
    public function getRelation($name)
    {
        // 执行关联定义方法
        $relation   = $this->parent->$name();
        $foreignKey = $this->foreignKey;
        $localKey   = $this->localKey;

        // 判断关联类型执行查询
        switch ($this->type) {
            case self::HAS_ONE:
                $result = $relation->where($foreignKey, $this->parent->$localKey)->find();
                break;
            case self::BELONGS_TO:
                $result = $relation->where($localKey, $this->parent->$foreignKey)->find();
                break;
            case self::HAS_MANY:
                $result = $relation->select();
                break;
            case self::HAS_MANY_THROUGH:
                $result = $relation->select();
                break;
            case self::BELONGS_TO_MANY:
                // 关联查询
                $pk                              = $this->parent->getPk();
                $condition['pivot.' . $localKey] = $this->parent->$pk;
                $result                          = $this->belongsToManyQuery($relation, $this->middle, $foreignKey, $localKey, $condition)->select();
                foreach ($result as $set) {
                    $pivot = [];
                    foreach ($set->toArray() as $key => $val) {
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
                break;
            default:
                // 直接返回
                $result = $relation;
        }
        return $result;
    }

    /**
     * 预载入关联查询 返回数据集
     * @access public
     * @param array     $resultSet 数据集
     * @param string    $relation 关联名
     * @param string    $class 数据集对象名 为空表示数组
     * @return array
     */
    public function eagerlyResultSet($resultSet, $relation, $class = '')
    {
        /** @var array $relations */
        $relations = is_string($relation) ? explode(',', $relation) : $relation;

        foreach ($relations as $key => $relation) {
            $subRelation = '';
            $closure     = false;
            if ($relation instanceof \Closure) {
                $closure  = $relation;
                $relation = $key;
            }
            if (strpos($relation, '.')) {
                list($relation, $subRelation) = explode('.', $relation);
            }
            // 执行关联方法
            $model = $this->parent->$relation();
            // 获取关联信息
            $localKey   = $this->localKey;
            $foreignKey = $this->foreignKey;
            switch ($this->type) {
                case self::HAS_ONE:
                case self::BELONGS_TO:
                    foreach ($resultSet as $result) {
                        // 模型关联组装
                        $this->match($this->model, $relation, $result);
                    }
                    break;
                case self::HAS_MANY:
                    $range = [];
                    foreach ($resultSet as $result) {
                        // 获取关联外键列表
                        if (isset($result->$localKey)) {
                            $range[] = $result->$localKey;
                        }
                    }

                    if (!empty($range)) {
                        $data = $this->eagerlyOneToMany($model, [
                            $foreignKey => [
                                'in',
                                $range,
                            ],
                        ], $relation, $subRelation, $closure);

                        // 关联数据封装
                        foreach ($resultSet as $result) {
                            if (!isset($data[$result->$localKey])) {
                                $data[$result->$localKey] = [];
                            }
                            $result->setAttr($relation, $this->resultSetBuild($data[$result->$localKey], $class));
                        }
                    }
                    break;
                case self::BELONGS_TO_MANY:
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
                        $data = $this->eagerlyManyToMany($model, [
                            'pivot.' . $localKey => [
                                'in',
                                $range,
                            ],
                        ], $relation, $subRelation);

                        // 关联数据封装
                        foreach ($resultSet as $result) {
                            if (!isset($data[$result->$pk])) {
                                $data[$result->$pk] = [];
                            }

                            $result->setAttr($relation, $this->resultSetBuild($data[$result->$pk], $class));
                        }
                    }
                    break;
            }
        }
        return $resultSet;
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
     * 预载入关联查询 返回模型对象
     * @access public
     * @param Model     $result 数据对象
     * @param string    $relation 关联名
     * @param string    $class 数据集对象名 为空表示数组
     * @return Model
     */
    public function eagerlyResult($result, $relation, $class = '')
    {
        $relations = is_string($relation) ? explode(',', $relation) : $relation;

        foreach ($relations as $key => $relation) {
            $subRelation = '';
            $closure     = false;
            if ($relation instanceof \Closure) {
                $closure  = $relation;
                $relation = $key;
            }
            if (strpos($relation, '.')) {
                list($relation, $subRelation) = explode('.', $relation);
            }
            // 执行关联方法
            $model      = $this->parent->$relation();
            $localKey   = $this->localKey;
            $foreignKey = $this->foreignKey;
            switch ($this->type) {
                case self::HAS_ONE:
                case self::BELONGS_TO:
                    // 模型关联组装
                    $this->match($this->model, $relation, $result);
                    break;
                case self::HAS_MANY:
                    if (isset($result->$localKey)) {
                        $data = $this->eagerlyOneToMany($model, [$foreignKey => $result->$localKey], $relation, $subRelation, $closure);
                        // 关联数据封装
                        if (!isset($data[$result->$localKey])) {
                            $data[$result->$localKey] = [];
                        }
                        $result->setAttr($relation, $this->resultSetBuild($data[$result->$localKey], $class));
                    }
                    break;
                case self::BELONGS_TO_MANY:
                    $pk = $result->getPk();
                    if (isset($result->$pk)) {
                        $pk = $result->$pk;
                        // 查询管理数据
                        $data = $this->eagerlyManyToMany($model, ['pivot.' . $localKey => $pk], $relation, $subRelation);

                        // 关联数据封装
                        if (!isset($data[$pk])) {
                            $data[$pk] = [];
                        }
                        $result->setAttr($relation, $this->resultSetBuild($data[$pk], $class));
                    }
                    break;

            }
        }
        return $result;
    }

    /**
     * 一对一 关联模型预查询拼装
     * @access public
     * @param string    $model 模型名称
     * @param string    $relation 关联名
     * @param Model     $result 模型对象实例
     * @return void
     */
    protected function match($model, $relation, &$result)
    {
        // 重新组装模型数据
        foreach ($result->toArray() as $key => $val) {
            if (strpos($key, '__')) {
                list($name, $attr) = explode('__', $key, 2);
                if ($name == $relation) {
                    $list[$name][$attr] = $val;
                    unset($result->$key);
                }
            }
        }

        if (!isset($list[$relation])) {
            // 设置关联模型属性
            $list[$relation] = [];
        }
        $result->setAttr($relation, (new $model($list[$relation]))->isUpdate(true));
    }

    /**
     * 一对多 关联模型预查询
     * @access public
     * @param object    $model 关联模型对象
     * @param array     $where 关联预查询条件
     * @param string    $relation 关联名
     * @param string    $subRelation 子关联
     * @param bool      $closure
     * @return array
     */
    protected function eagerlyOneToMany($model, $where, $relation, $subRelation = '', $closure = false)
    {
        $foreignKey = $this->foreignKey;
        // 预载入关联查询 支持嵌套预载入
        $list = $model->where($where)->where($closure)->with($subRelation)->select();

        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $data[$set->$foreignKey][] = $set;
        }
        return $data;
    }

    /**
     * 多对多 关联模型预查询
     * @access public
     * @param object    $model 关联模型对象
     * @param array     $where 关联预查询条件
     * @param string    $relation 关联名
     * @param string    $subRelation 子关联
     * @return array
     */
    protected function eagerlyManyToMany($model, $where, $relation, $subRelation = '')
    {
        $foreignKey = $this->foreignKey;
        $localKey   = $this->localKey;
        // 预载入关联查询 支持嵌套预载入
        $list = $this->belongsToManyQuery($model, $this->middle, $foreignKey, $localKey, $where)->with($subRelation)->select();

        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $pivot = [];
            foreach ($set->toArray() as $key => $val) {
                if (strpos($key, '__')) {
                    list($name, $attr) = explode('__', $key, 2);
                    if ('pivot' == $name) {
                        $pivot[$attr] = $val;
                        unset($set->$key);
                    }
                }
            }
            $set->pivot                = new Pivot($pivot, $this->middle);
            $data[$pivot[$localKey]][] = $set;
        }
        return $data;
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

    /**
     * HAS ONE 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @param string $joinType JOIN类型
     * @return $this
     */
    public function hasOne($model, $foreignKey, $localKey, $alias = [], $joinType = 'INNER')
    {
        $this->type       = self::HAS_ONE;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->alias      = $alias;
        $this->joinType   = $joinType;
        $this->query      = (new $model)->db();
        // 返回关联的模型对象
        return $this;
    }

    /**
     * BELONGS TO 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $otherKey 关联主键
     * @param array  $alias 别名定义
     * @param string $joinType JOIN类型
     * @return $this
     */
    public function belongsTo($model, $foreignKey, $otherKey, $alias = [], $joinType = 'INNER')
    {
        // 记录当前关联信息
        $this->type       = self::BELONGS_TO;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $otherKey;
        $this->alias      = $alias;
        $this->joinType   = $joinType;
        $this->query      = (new $model)->db();
        // 返回关联的模型对象
        return $this;
    }

    /**
     * HAS MANY 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @return $this
     */
    public function hasMany($model, $foreignKey, $localKey, $alias)
    {
        // 记录当前关联信息
        $this->type       = self::HAS_MANY;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->alias      = $alias;
        $this->query      = (new $model)->db();
        // 返回关联的模型对象
        return $this;
    }

    /**
     * HAS MANY 远程关联定义
     * @access public
     * @param string $model 模型名
     * @param string $through 中间模型名
     * @param string $firstkey 关联外键
     * @param string $secondKey 关联外键
     * @param string $localKey 关联主键
     * @param array  $alias 别名定义
     * @return $this
     */
    public function hasManyThrough($model, $through, $foreignKey, $throughKey, $localKey, $alias)
    {
        // 记录当前关联信息
        $this->type       = self::HAS_MANY_THROUGH;
        $this->model      = $model;
        $this->middle     = $through;
        $this->foreignKey = $foreignKey;
        $this->throughKey = $throughKey;
        $this->localKey   = $localKey;
        $this->alias      = $alias;
        $this->query      = (new $model)->db();
        // 返回关联的模型对象
        return $this;
    }

    /**
     * BELONGS TO MANY 关联定义
     * @access public
     * @param string $model 模型名
     * @param string $table 中间表名
     * @param string $foreignKey 关联模型外键
     * @param string $localKey 当前模型关联键
     * @param array  $alias 别名定义
     * @return $this
     */
    public function belongsToMany($model, $table, $foreignKey, $localKey, $alias)
    {
        // 记录当前关联信息
        $this->type       = self::BELONGS_TO_MANY;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->middle     = $table;
        $this->alias      = $alias;
        $this->query      = (new $model)->db();
        // 返回关联的模型对象
        return $this;
    }

    /**
     * BELONGS TO MANY 关联查询
     * @access public
     * @param object    $model 关联模型对象
     * @param string    $table 中间表名
     * @param string    $foreignKey 关联模型关联键
     * @param string    $localKey 当前模型关联键
     * @param array     $condition 关联查询条件
     * @return \think\db\Query|string
     */
    protected function belongsToManyQuery($model, $table, $foreignKey, $localKey, $condition = [])
    {
        // 关联查询封装
        $tableName  = $model->getTable();
        $relationFk = $model->getPk();
        return $model::field($tableName . '.*')
            ->field(true, false, $table, 'pivot', 'pivot__')
            ->join($table . ' pivot', 'pivot.' . $foreignKey . '=' . $tableName . '.' . $relationFk)
            ->where($condition);
    }

    /**
     * 保存（新增）当前关联数据对象
     * @access public
     * @param mixed     $data 数据 可以使用数组 关联模型对象 和 关联对象的主键
     * @param array     $pivot 中间表额外数据
     * @return integer
     */
    public function save($data, array $pivot = [])
    {
        // 判断关联类型
        switch ($this->type) {
            case self::HAS_ONE:
            case self::BELONGS_TO:
            case self::HAS_MANY:
                if ($data instanceof Model) {
                    $data = $data->toArray();
                }
                // 保存关联表数据
                $data[$this->foreignKey] = $this->parent->{$this->localKey};
                $model                   = new $this->model;
                return $model->save($data);
            case self::BELONGS_TO_MANY:
                // 保存关联表/中间表数据
                return $this->attach($data, $pivot);
        }
    }

    /**
     * 批量保存当前关联数据对象
     * @access public
     * @param array     $dataSet 数据集
     * @param array     $pivot 中间表额外数据
     * @return integer
     */
    public function saveAll(array $dataSet, array $pivot = [])
    {
        $result = false;
        foreach ($dataSet as $key => $data) {
            // 判断关联类型
            switch ($this->type) {
                case self::HAS_MANY:
                    $data[$this->foreignKey] = $this->parent->{$this->localKey};
                    $result                  = $this->save($data);
                    break;
                case self::BELONGS_TO_MANY:
                    // TODO
                    $result = $this->attach($data, !empty($pivot) ? $pivot[$key] : []);
                    break;
            }
        }
        return $result;
    }

    /**
     * 附加关联的一个中间表数据
     * @access public
     * @param mixed     $data 数据 可以使用数组、关联模型对象 或者 关联对象的主键
     * @param array     $pivot 中间表额外数据
     * @return integer
     */
    public function attach($data, $pivot = [])
    {
        if (is_array($data)) {
            // 保存关联表数据
            $model = new $this->model;
            $id    = $model->save($data);
        } elseif (is_numeric($data)) {
            // 根据关联表主键直接写入中间表
            $id = $data;
        } elseif ($data instanceof Model) {
            // 根据关联表主键直接写入中间表
            $relationFk = $data->getPk();
            $id         = $data->$relationFk;
        }

        if ($id) {
            // 保存中间表数据
            $pk                       = $this->parent->getPk();
            $pivot[$this->localKey]   = $this->parent->$pk;
            $pivot[$this->foreignKey] = $id;
            $query                    = clone $this->parent->db();
            return $query->table($this->middle)->insert($pivot);
        } else {
            throw new Exception('miss relation data');
        }
    }

    /**
     * 解除关联的一个中间表数据
     * @access public
     * @param integer|array     $data 数据 可以使用关联对象的主键
     * @param bool              $relationDel 是否同时删除关联表数据
     * @return integer
     */
    public function detach($data, $relationDel = false)
    {
        if (is_array($data)) {
            $id = $data;
        } elseif (is_numeric($data)) {
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
        $query = clone $this->parent->db();
        $query->table($this->middle)->where($pivot)->delete();

        // 删除关联表数据
        if (isset($id) && $relationDel) {
            $model = $this->model;
            $model::destroy($id);
        }
    }

    public function __call($method, $args)
    {
        if ($this->query) {
            switch ($this->type) {
                case self::HAS_MANY:
                    if (isset($this->parent->{$this->localKey})) {
                        // 关联查询带入关联条件
                        $this->query->where($this->foreignKey, $this->parent->{$this->localKey});
                    }
                    break;
                case self::HAS_MANY_THROUGH:
                    $through      = $this->middle;
                    $model        = $this->model;
                    $alias        = Loader::parseName(basename(str_replace('\\', '/', $model)));
                    $throughTable = $through::getTable();
                    $pk           = (new $this->model)->getPk();
                    $throughKey   = $this->throughKey;
                    $modelTable   = $this->parent->getTable();
                    $result       = $this->query->field($alias . '.*')->alias($alias)
                        ->join($throughTable, $throughTable . '.' . $pk . '=' . $alias . '.' . $throughKey)
                        ->join($modelTable, $modelTable . '.' . $this->localKey . '=' . $throughTable . '.' . $this->foreignKey)
                        ->where($throughTable . '.' . $this->foreignKey, $this->parent->{$this->localKey});
                    break;
            }
            $result = call_user_func_array([$this->query, $method], $args);
            if ($result instanceof \think\db\Query) {
                return $this;
            } else {
                return $result;
            }
        } else {
            throw new Exception('method not exists:' . __CLASS__ . '->' . $method);
        }
    }

}
