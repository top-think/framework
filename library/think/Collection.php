<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think;


use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected $items = [];

    public function __construct($items = [])
    {
        $this->items = $items instanceof self ? $items->all() : (array)$items;
    }

    public static function make($items = [])
    {
        return new static($items);
    }

    /**
     * 是否为空
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    public function toArray()
    {
        return array_map(function ($value) {
            return ($value instanceof Model || $value instanceof self) ? $value->toArray() : $value;
        }, $this->items);
    }

    public function all()
    {
        return $this->items;
    }

    /**
     * 截取数组
     *
     * @param  int $offset
     * @param  int $length
     * @param  bool $preserveKeys
     * @return static
     */
    public function slice($offset, $length = null, $preserveKeys = false)
    {
        return new static(array_slice($this->items, $offset, $length, $preserveKeys));
    }

    // ArrayAccess
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->items);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    //Countable
    public function count()
    {
        return count($this->items);
    }

    //IteratorAggregate
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    //JsonSerializable
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * 转换当前数据集为JSON字符串
     * @access public
     * @param integer $options json参数
     * @return string
     */
    public function toJson($options = JSON_UNESCAPED_UNICODE)
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString()
    {
        return $this->toJson();
    }
}