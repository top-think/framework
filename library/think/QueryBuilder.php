<?php

namespace think;

use think\db\Query;
use think\facade\Request;

use think\model\Relation;
use think\model\relation\BelongsTo;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 查询构造器
 * Class TpQuerySet
 * @package app\common\lib
 */
class QueryBuilder
{
    /**
     * limit字段
     * @var string
     */
    public $limit;

    /**
     * 是否分页
     * @var bool
     */
    public $isPage = false;

    /**
     * 是否使用limit
     * @var bool
     */
    public $isLimit = false;

    /**
     * 默认的页数
     * @var int
     */
    protected $pageSize = 15;

    /**
     * with的选项
     * @var array
     */
    protected $with = [];

    /**
     * withJoin选项
     * @var array
     */
    protected $withJoin = [];

    /**
     * where条件
     * @var array
     */
    protected $where = [];

    /**
     * 查询的选项
     * @var string
     */
    protected $field = "*";

    /**
     * 排序的字段
     * @var
     */
    protected $order = [];

    /**
     * 分组字段
     * @var
     */
    protected $group;

    /**
     * 追加的属性
     * @var array
     */
    protected $append = [];

    /**
     * 关联属性
     * @var array
     */
    protected $withAttr = [];

    /**
     * 隐藏字段
     * @var
     */
    protected $hidden = [];

    /**
     * 显示字段
     * @var
     */
    protected $visible;

    /**
     * 客户端上传查询参数
     * @var
     */
    protected $queryParam = [];

    /**
     * 对应的模型的实例
     * @var Model
     */
    protected $model;

    /**
     * 去除重复的行,解决一对多或者多对多关联查询可能会出现的重复。
     * @var
     */
    protected $distinctRow = false;

    /**
     * 关联统计的数量
     * @var array
     */
    protected $withCount = [];


    /**
     * 是否使用默认的排序
     * @var bool
     */
    protected $useDefaultOrder = true;


    /**
     * 存放一对多，或者多对多，一对一的联查条件，
     * @var array
     */
    protected $manyJoins = [];


    /**
     * having语句
     * @var string
     */
    protected $having = "";


    /**
     * 快速构造方法
     * @param array $config
     * @return static
     */
    public static function create(array $config = [])
    {
        return new static($config);
    }


    /**
     * 创建一个不包含排序和默认字段的实例，给复杂的分组统计、绘图业务用。
     * @return QueryBuilder
     */
    public static function cleanInstance()
    {
        $instance = new  QueryBuilder();
        $instance->setOrder("");
        $instance->setUseDefaultOrder(false);
        $instance->setFiled("");
        return $instance;
    }

    /**
     * 初始化时传递一些参数，设置配置
     *
     * TpQuerySet constructor
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value) {
            if (property_exists(self::class, $key)) {
                $this->$key = $value;
            }
        }
    }


    /**
     * 构造field查询字段
     * @param string $fields
     * @param string $prefix
     * @param string $extraFields
     * @param string $except
     * @return string
     */
    public static function buildFields($fields = "", string $prefix = "", $extraFields = "", string $except = "deleted_at,deleted_by")
    {
        if (is_string($fields)) {
            $fields = explode(",", $fields);
        }
        $extraFields = !empty($extraFields) ? array_unique(explode(",", $extraFields)) : [];
        $fields = array_unique($fields);
        $finalFields = [];
        $exceptFields = array_unique(explode(",", $except));
        foreach ($fields as &$field) {
            if (in_array($field, $exceptFields)) {
                continue;
            }
            if (!preg_match('/^([a-zA-Z]|_)+$/', $field) && $field != '*') {
                $finalFields[] = $field;
                continue;
            }
            $field = !empty($prefix) ? $prefix . "." . $field : $field;
            if (in_array($field, $extraFields)) {
                $extraFields = array_diff($extraFields, [$field]);
            }
            $finalFields[] = $field;
        }
        foreach ($extraFields as $extraField) {
            $finalFields[] = $extraField;
        }
        return implode(",", $finalFields);
    }

    /**
     * @return array
     */
    public function getAppend()
    {
        $returnAppend = [];
        foreach ($this->append as $item) {
            $returnAppend[] = Loader::parseName($item, 0, false);
        }
        return $returnAppend;
    }

    public function setAppend($append)
    {
        $this->append = is_array($append) ? $append : explode(",", $append);
        return $this;

    }

    public function setWithAttr($withAttr)
    {
        $this->withAttr = $withAttr;
        return $this;
    }

    /**
     * @return array
     */
    public function getWithAttr()
    {
        return $this->withAttr;
    }

    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getHidden()
    {
        return $this->hidden;
    }

    public function setVisible($visible)
    {
        $this->visible = $visible;
        return $this;
    }

    public function getVisible()
    {
        return $this->visible;
    }


    public function setOrder($order)
    {
        if (is_string($order)) {
            $orders = explode(",", $order);
        } else {
            $orders = (array)$order;
        }
        foreach ($orders as $item) {
            //兼容case语句
            if (preg_match('/^([\s\S]*?)(desc|DESC)/', $item, $matches)) {
                $this->order[trim($matches[1])] = 'desc';
            } else if (preg_match('/^([\s\S]*?)(asc|ASC)/', $item, $matches)) {
                $this->order[trim($matches[1])] = 'asc';
            }
        }
        return $this;
    }

    public function getOrder($sortField = 'sortField', $sortOrder = 'sortOrder')
    {
        if (empty($this->order)) {
            $sort = Request::param($sortField);
            $order = Request::param($sortOrder);
            if (!empty($sort) && !empty($order)) {
                return $sort . " " . $order;
            }
        } else {
            $items = [];
            foreach ($this->order as $field => $sort) {
                $items[] = $field . " " . $sort;
            }
            return implode(",", $items);
        }
        if (!$this->order) {
            return "";
        }
        return $this->order;
    }


    public function setQueryParam(array $qParam)
    {
        $this->queryParam = array_merge($this->queryParam, $qParam);
        return $this;
    }

    public function getQueryParam()
    {
        return $this->queryParam;
    }

    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }

    public function getModel()
    {
        return $this->model;
    }

    /**
     * 获取where条件的字段或者进行自动连表
     * 当传入当前模型对应数据表存在的字段时，如status,会返回当前数据表名加字段名称。如`table_name.status`,防止连表字段重名
     * 当需要查询关联表的字段时，比如当前模型是article，article里面有authorInfo关联，传入关联的方法名加`-`加字段名，如`authorInfo-name`
     * 此时当前的article表会自动根据authorInfo定义的关联join author表，并将中间表的名字定义为authorInfo,同时返回authorInfo.name,用于构造查询条件，
     * 支持一对一，一对多，多对多关联自动拼接，同时会自动防止重复连表。如需指定join类型可以用这样的格式 "withData|join_type-field_name"
     *
     * @param $key
     * @return string
     */
    public function getQueryKeyByField($key)
    {
        return $this->processWithQueryParam($key, $this->getModel()->getTable());
    }

    /**
     * 该函数根据客户端传递进来的参数，自动将with操作转换为withJoin
     *
     * @param  string $key 客户端传递的参数字段，如果需要使用withJoin，格式 `withData`-field，withData是with属性的驼峰命名，field是查询的字段，中间用'-'连接
     * @param  string $mainTable 表名
     * @return string
     */
    protected function processWithQueryParam($key, $mainTable)
    {
        if (strpos($key, "-") !== false) {
            list($joinTable, $field) = explode("-", $key); // 带有-符号的表示要连表查询，根据-符号拆分表名和字段
            if (strpos($joinTable, "|") !== false) {
                $method = explode("|", $joinTable)[0];
            } else {
                $method = $joinTable;
            }
            if (method_exists($this->model, $method)) { //模型的关联方法存在
                $returnTable = $this->autoJoin($joinTable);
                if ($returnTable == $method) {
                    return $method . "." . $field;
                }
            }
        }
        return $mainTable . "." . $key;
    }

    /**
     * 过滤withjoin，改用手动连表
     */
    public function filterWithJoin()
    {
        if (!$this->withJoin) {
            return;
        }
        foreach ($this->withJoin as $key => $value) {
            if (is_callable($value)) {
                $this->autoJoin($key);
            } else {
                $this->autoJoin($value);
            }
        }
    }

    /**
     *  order里面的字段自动连表
     */
    public function makeOrderJoin()
    {
        foreach ($this->order as $field => $sort) {
            if (strpos($field, ".")) {
                list($joinTable, $field) = explode('.', $field, 2);
                if (strpos($joinTable, "_") === false) {
                    $this->autoJoin($joinTable);
                }
            }
        }
    }

    /**
     * 自动连表
     * @param $joinTable
     * @return string
     */
    protected function autoJoin($joinTable)
    {
        if (strpos($joinTable, "|")) {
            list($joinTable, $joinType) = explode("|", $joinTable, 2);
        } else {
            $joinType = "INNER";
        }
        $relation = $this->model->$joinTable();
        $mainTable = $this->model->getTable();
        if ($relation instanceof Relation) { //调用后返回的是relation对象
            $realJoinTable = $relation->getModel()->getTable();
            $foreignKey = $relation->getForeignKey();
            $localKey = $relation->getLocalKey();
            if ($relation instanceof HasOne) {
                $joinCond = "(" . $joinTable . "." . $foreignKey . "=" . $mainTable . "." . $localKey . ")";
                $this->appendManyJoins($realJoinTable . " " . $joinTable, $joinCond, $joinType);
            } elseif ($relation instanceof BelongsTo) {
                $joinCond = "(" . $joinTable . "." . $localKey . "=" . $mainTable . "." . $foreignKey . ")";
                $this->appendManyJoins($realJoinTable . " " . $joinTable, $joinCond, $joinType);
            } elseif ($relation instanceof HasMany) { //一对多
                $relationSql = $relation->buildSql();
                $matches = [];
                preg_match("#WHERE(.*?)\)#", $relationSql, $matches);
                $joinCond = "(" . $joinTable . "." . $foreignKey . "=" . $mainTable . "." . $localKey . ")";
                if ($matches) {
                    $joinCond = $joinCond . " AND " . "(" . $matches[1] . ")";
                }
                $this->appendManyJoins($realJoinTable . " " . $joinTable, $joinCond, $joinType);
            } elseif ($relation instanceof BelongsToMany) {
                $middleName = $relation->getMiddle();
                $middleAlias = $mainTable . "_" . $relation->getMiddle() . "_" . $joinTable;
                //防止中间表在一次查询里面重名
                $relationSql = $relation->buildSql();
                $matches = [];
                preg_match("#WHERE(.*?)\)#", $relationSql, $matches);
                $relationWhere = "";
                if ($matches) {
                    $relationWhere = str_replace('pivot', $middleAlias, $matches[1]);
                }
                $anotherModel = $relation->getModel();
                if (!$relationWhere) {
                    $joinMidCond = "(" . $mainTable . "." . $this->model->getPk() . "=" . $middleAlias . "." . $localKey . ")";
                } else {
                    $joinMidCond = "(" . $mainTable . "." . $this->model->getPk() . "=" . $middleAlias . "." . $localKey . " AND $relationWhere)";
                }
                $this->appendManyJoins($middleName . " " . $middleAlias, $joinMidCond, $joinType);
                $joinMidCond2 = $joinTable . "." . $anotherModel->getPk() . "=" . $middleAlias . "." . $foreignKey;
                $this->appendManyJoins($anotherModel->getTable() . " " . $joinTable, $joinMidCond2, $joinType);
            } else {
                return $mainTable;
            }
            return $joinTable;
        }
        return $joinTable;
    }

    /**
     * 获取关联的名字
     * @param string $relation
     * @return mixed
     */
    protected function getModelRelations($relation = '')
    {
        $relations = $this->model->with($this->getWith())
            ->find()->getRelation($relation);
        $this->model->removeOption();
        return $relations;
    }

    public function getManyJoins()
    {
        $joinArray = [];
        foreach ($this->manyJoins as $joinTable => $joinItem) {
            $joinWhere = $joinItem[0];
            $joinType = $joinItem[1];
            $joinArray[] = [$joinTable, "(" . (is_array($joinWhere) ? implode(" AND ", $joinWhere) : $joinWhere) . ")", $joinType];
        }
        return $joinArray;
    }

    /**
     * 整合连表的方法
     * @param string $joinTable 连的表的名字
     * @param mixed $condition 连表的条件
     * @param string $joinType 连表类型
     * @param bool $override 是否覆盖已经存在的
     */
    public function appendManyJoins($joinTable, $condition, $joinType = "INNER", $override = false)
    {
        if (isset($this->manyJoins[$joinTable]) && $override == false) {
            $this->manyJoins[$joinTable][] = [$condition, $joinType];
        } else {
            $this->manyJoins[$joinTable] = [$condition, $joinType];
        }
    }


    /**
     * 创建query
     *
     * @param TpQuerySet $querySet
     * @return Query;
     */
    public function queryWithSet()
    {
        $query = $this->model->db();
        $query = $query
            ->field($this->getFiled())
            ->append($this->getAppend())
            ->withAttr($this->getWithAttr());


        if ($this->getWithCount()) {
            $query = $query->withCount($this->getWithCount());
        }

        //自动根据order的条件连表
        $this->makeOrderJoin();
        $this->filterWithJoin();

        $manyJoins = $this->getManyJoins();
        if ($manyJoins) {
            foreach ($manyJoins as $join) {
                $query = $query->join($join[0], $join[1], $join[2]);
            }
        }
        //对于已经hidden的属性，就没有必要with查询，只需要拼接连表语句
        $hideAttr = $this->getHidden();
        $realWith = [];

        foreach ($this->getWith() as $withKey => $withItem) {
            if (in_array($withKey, $hideAttr, true) || in_array($withItem, $hideAttr, true)) {
                continue;
            }
            $realWith[$withKey] = $withItem;
        }

        $query = $query
            ->where($this->getWhere())
            ->with($realWith)
            ->group($this->getGroup())
            ->having($this->getHaving());

        $order = $this->getOrder();

        $pkID = $this->getModel()->getTable() . "." . $this->getModel()->getPk();

        if ($order) {
            if (strpos($order, "$pkID desc") === false && $this->getUseDefaultOrder()) {
                $order = $order . "," . ($pkID) . " desc";
            }
        } elseif ($this->getUseDefaultOrder()) {
            $order = $pkID . " desc";
        }
        if (strpos($order, "CASE") !== false) {
            $query = $query->orderRaw($order);
        } elseif ($order) {
            $query = $query->order($order);
        }

        if ($this->getHidden()) {
            $query->hidden($this->getHidden());
        }
        if ($this->getVisible()) {
            $query->visible($this->getVisible());
        }

        return $query;
    }

    /**
     * having条件设置
     * @param $having
     * @return $this
     */
    public function setHaving($having)
    {
        $this->having = $having;
        return $this;
    }

    public function getHaving()
    {
        return $this->having;
    }

    /**
     * 返回查询对象的实例
     * @param Model|null $model
     * @return Query
     */
    public function query(Model $model = null)
    {
        if (!$this->model && $model) {
            $this->model = $model;
        }
        return $this->queryWithSet();
    }

    /**
     * 是否使用默认的排序，默认按照当前模型主键倒序排列
     * @param $value
     * @return $this
     */
    public function setUseDefaultOrder($value)
    {
        $this->useDefaultOrder = $value;
        return $this;
    }

    public function getUseDefaultOrder()
    {
        return $this->useDefaultOrder;
    }


    /**
     * 显示的设置去重，对当前模型的主键id group by
     * @param bool $value
     * @return $this
     */
    public function setDistinctRow($value = true)
    {
        $mainTableKey = $this->getModel()->getTable() . "." . $this->getModel()->getPk();
        $this->setGroup($mainTableKey);
        return $this;
    }

    /**
     * 获取去重属性
     * @return bool
     */
    public function getDistinctRow()
    {
        return $this->distinctRow;
    }

    /**
     * 设置withCount
     * @param $withCount
     * @return $this
     */
    public function setWithCount(array $withCount)
    {
        $this->withCount = array_merge($this->withCount, $withCount);
        return $this;
    }

    /**
     * 获取withCount
     * @return array
     */
    public function getWithCount()
    {
        return implode(",", $this->withCount);
    }

    public function getWhere()
    {
        return $this->where;
    }

    public function setWhere($where)
    {
        $this->where = array_merge($this->where, $where);
        return $this;
    }


    public function setWith($with)
    {
        $this->with = array_merge($this->with, $with);
        return $this;
    }

    public function getWith()
    {
        return $this->with;
    }

    public function setGroup($group)
    {
        $this->group = $group;
        return $this;

    }

    public function getGroup()
    {
        return $this->group;
    }


    public function setFiled($field, $isAppend = false)
    {
        if ($isAppend == false) {
            $this->field = $field;
        } else {
            $this->field = $this->field . "," . $field;
        }
        return $this;
    }

    /**
     * 获取field的原始的值
     * @return string
     */
    public function getOriginField()
    {
        return $this->field;
    }

    /**
     * @param bool $hasTable
     * @return string
     */
    public function getFiled($hasTable = true)
    {
        $field = self::buildFields($this->field, $hasTable == true ? $this->model->getTable() : "");
        return $field;
    }

}

