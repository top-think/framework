<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\model;

use think\Collection as BaseCollection;
use think\Model;

class Collection extends BaseCollection
{
    /**
     * 延迟预载入关联查询
     * @access public
     * @param  array $relation 关联
     * @return $this
     */
    public function load(array $relation)
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
    public function hidden(array $hidden, bool $override = false)
    {
        $this->each(function (Model $model) use ($hidden, $override) {
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
    public function visible(array $visible, bool $override = false)
    {
        $this->each(function (Model $model) use ($visible, $override) {
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
    public function append(array $append, bool $override = false)
    {
        $this->each(function (Model $model) use ($append, $override) {
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
     * 按指定键整理数据
     *
     * @access public
     * @param  mixed    $items      数据
     * @param  string   $indexKey   键名
     * @return array
     */
    public function dictionary($items = null, string &$indexKey = null)
    {
        if ($items instanceof self || $items instanceof Paginator) {
            $items = $items->all();
        }

        $items = is_null($items) ? $this->items : $items;

        if ($items && empty($indexKey)) {
            $indexKey = $items[0]->getPk();
        }

        if (isset($indexKey) && is_string($indexKey)) {
            return array_column($items, null, $indexKey);
        }

        return $items;
    }

    /**
     * 比较数据集，返回差集
     *
     * @access public
     * @param  mixed    $items      数据
     * @param  string   $indexKey   指定比较的键名
     * @return static
     */
    public function diff($items, string $indexKey = null)
    {
        if ($this->isEmpty()) {
            return new static($items);
        }

        $diff       = [];
        $dictionary = $this->dictionary($items, $indexKey);

        if (is_string($indexKey)) {
            foreach ($this->items as $item) {
                if (!isset($dictionary[$item[$indexKey]])) {
                    $diff[] = $item;
                }
            }
        }

        return new static($diff);
    }

    /**
     * 比较数据集，返回交集
     *
     * @access public
     * @param  mixed    $items      数据
     * @param  string   $indexKey   指定比较的键名
     * @return static
     */
    public function intersect($items, string $indexKey = null)
    {
        if ($this->isEmpty()) {
            return new static([]);
        }

        $intersect  = [];
        $dictionary = $this->dictionary($items, $indexKey);

        if (is_string($indexKey)) {
            foreach ($this->items as $item) {
                if (isset($dictionary[$item[$indexKey]])) {
                    $intersect[] = $item;
                }
            }
        }

        return new static($intersect);
    }
}
