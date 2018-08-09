<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\model;

use think\Collection as BaseCollection;
use think\Model;

class Collection extends BaseCollection
{
    /**
     * 返回数组中指定的一列
     * @access public
     * @param  string        $column_key
     * @param  string|null   $index_key
     * @return array
     */
    public function column($column_key, $index_key = null)
    {
        return array_column($this->toArray(), $column_key, $index_key);
    }

    /**
     * 延迟预载入关联查询
     * @access public
     * @param  mixed $relation 关联
     * @return $this
     */
    public function load($relation)
    {
        $item = current($this->items);
        $item->eagerlyResultSet($this->items, $relation);

        return $this;
    }

    /**
     * 设置需要隐藏的输出属性
     * @access public
     * @param  array $hidden   属性列表
     * @param  bool  $override 是否覆盖
     * @return $this
     */
    public function hidden($hidden = [], $override = false)
    {
        $this->each(function ($model) use ($hidden, $override) {
            /** @var Model $model */
            $model->hidden($hidden, $override);
        });

        return $this;
    }

    /**
     * 设置需要输出的属性
     * @access public
     * @param  array $visible
     * @param  bool  $override 是否覆盖
     * @return $this
     */
    public function visible($visible = [], $override = false)
    {
        $this->each(function ($model) use ($visible, $override) {
            /** @var Model $model */
            $model->visible($visible, $override);
        });

        return $this;
    }

    /**
     * 设置需要追加的输出属性
     * @access public
     * @param  array $append   属性列表
     * @param  bool  $override 是否覆盖
     * @return $this
     */
    public function append($append = [], $override = false)
    {
        $this->each(function ($model) use ($append, $override) {
            /** @var Model $model */
            $model && $model->append($append, $override);
        });

        return $this;
    }

    /**
     * 设置数据字段获取器
     * @access public
     * @param  string|array $name       字段名
     * @param  callable     $callback   闭包获取器
     * @return $this
     */
    public function withAttr($name, $callback = null)
    {
        $this->each(function ($model) use ($name, $callback) {
            /** @var Model $model */
            $model && $model->withAttribute($name, $callback);
        });

        return $this;
    }

    /**
     * 按主键整理数据
     *
     * @access public
     * @param  mixed $items
     * @return array
     */
    public function dictionary($items = null)
    {
        $items = is_null($items) ? $this->items : $items;

        if ($items) {
            $indexKey = $items[0]->getPk();
        }

        if (isset($indexKey) && is_string($indexKey)) {
            return array_column($items, null, $indexKey);
        }

        return $items;
    }

    /**
     * 比较数组，返回差集
     *
     * @access public
     * @param  mixed $items
     * @return static
     */
    public function diff($items)
    {
        $diff       = [];
        $dictionary = $this->dictionary($items);

        foreach ($this->items as $item) {
            if (!isset($dictionary[$item->getkey()])) {
                $diff[] = $item;
            }
        }

        return new static($diff);
    }

    /**
     * 比较数组，返回交集
     *
     * @access public
     * @param  mixed $items
     * @return static
     */
    public function intersect($items)
    {
        $intersect  = [];
        $dictionary = $this->dictionary($items);

        foreach ($this->items as $item) {
            if (isset($dictionary[$item->getkey()])) {
                $intersect[] = $item;
            }
        }

        return new static($intersect);
    }
}
