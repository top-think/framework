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

trait Serialize
{

    // 显示属性
    protected $visible = [];
    // 隐藏属性
    protected $hidden = [];
    // 追加属性
    protected $append = [];

    /**
     * 设置需要追加的输出属性
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

        return !empty($item) ? $item : [];
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

}
