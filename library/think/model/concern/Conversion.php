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

namespace think\model\concern;

use think\Collection;
use think\Loader;
use think\Model;
use think\Exception;

/**
 * 模型数据转换处理
 */
trait Conversion
{
    // 显示属性
    protected $visible = [];
    // 隐藏属性
    protected $hidden = [];
    // 附加属性
    protected $append = [];
    // 查询数据集对象
    protected $resultSetType;

    /**
     * 设置需要附加的输出属性
     * @access public
     * @param array $append   属性列表
     * @param bool  $override 是否覆盖
     * @return $this
     */
    public function append($append = [], $override = false)
    {
        $this->append = $override ? $append : array_merge($this->append, $append);

        return $this;
    }

    /**
     * 设置附加关联对象的属性
     * @access public
     * @param string       $attr    关联属性
     * @param string|array $append  追加属性名
     * @return $this
     * @throws Exception
     */
    public function appendRelationAttr($attr, $append)
    {
        if (is_string($append)) {
            $append = explode(',', $append);
        }

        $relation = Loader::parseName($attr, 1, false);
        $model    = $this->getRelation($relation);

        if ($model instanceof Model) {
            foreach ($append as $key => $attr) {
                $key = is_numeric($key) ? $attr : $key;
                if ($this->__isset($key)) {
                    throw new Exception('bind attr has exists:' . $key);
                } else {
                    $this->setAttr($key, $model->$attr);
                }
            }
        }

        return $this;
    }

    /**
     * 设置需要隐藏的输出属性
     * @access public
     * @param array $hidden   属性列表
     * @param bool  $override 是否覆盖
     * @return $this
     */
    public function hidden($hidden = [], $override = false)
    {
        $this->hidden = $override ? $hidden : array_merge($this->hidden, $hidden);

        return $this;
    }

    /**
     * 设置需要输出的属性
     * @access public
     * @param array $visible
     * @param bool  $override 是否覆盖
     * @return $this
     */
    public function visible($visible = [], $override = false)
    {
        $this->visible = $override ? $visible : array_merge($this->visible, $visible);

        return $this;
    }

    /**
     * 转换当前模型对象为数组
     * @access public
     * @return array
     */
    public function toArray()
    {
        $item    = [];
        $visible = [];
        $hidden  = [];

        // 合并关联数据
        $data = array_merge($this->data, $this->relation);

        // 过滤属性
        if (!empty($this->visible)) {
            $array = $this->parseAttr($this->visible, $visible);
            $data  = array_intersect_key($data, array_flip($array));
        } elseif (!empty($this->hidden)) {
            $array = $this->parseAttr($this->hidden, $hidden, false);
            $data  = array_diff_key($data, array_flip($array));
        }

        foreach ($data as $key => $val) {
            if ($val instanceof Model || $val instanceof ModelCollection) {
                // 关联模型对象
                if (isset($visible[$key])) {
                    $val->visible($visible[$key]);
                } elseif (isset($hidden[$key])) {
                    $val->hidden($hidden[$key]);
                }
                // 关联模型对象
                $item[$key] = $val->toArray();
            } else {
                // 模型属性
                $item[$key] = $this->getAttr($key);
            }
        }

        // 追加属性（必须定义获取器）
        if (!empty($this->append)) {
            foreach ($this->append as $key => $name) {
                if (is_array($name)) {
                    // 追加关联对象属性
                    $relation   = $this->getAttr($key);
                    $item[$key] = $relation->append($name)->toArray();
                } elseif (strpos($name, '.')) {
                    list($key, $attr) = explode('.', $name);
                    // 追加关联对象属性
                    $relation   = $this->getAttr($key);
                    $item[$key] = $relation->append([$attr])->toArray();
                } else {
                    $item[$name] = $this->getAttr($name);
                }
            }
        }

        return $item;
    }

    /**
     * 转换当前模型对象为JSON字符串
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

    // JsonSerializable
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * 转换数据集为数据集对象
     * @access public
     * @param array|Collection $collection 数据集
     * @return Collection
     */
    public function toCollection($collection)
    {
        if ($this->resultSetType && false !== strpos($this->resultSetType, '\\')) {
            $class      = $this->resultSetType;
            $collection = new $class($collection);
        } else {
            $collection = new \think\model\Collection($collection);
        }

        return $collection;
    }

    /**
     * 解析隐藏及显示属性
     * @access protected
     * @param array $attrs  属性
     * @param array $result 结果集
     * @param bool  $visible
     * @return array
     */
    protected function parseAttr($attrs, &$result, $visible = true)
    {
        $array = [];

        foreach ($attrs as $key => $val) {
            if (is_array($val)) {
                if ($visible) {
                    $array[] = $key;
                }

                $result[$key] = $val;
            } elseif (strpos($val, '.')) {
                list($key, $name) = explode('.', $val);

                if ($visible) {
                    $array[] = $key;
                }

                $result[$key][] = $name;
            } else {
                $array[] = $val;
            }
        }

        return $array;
    }
}
