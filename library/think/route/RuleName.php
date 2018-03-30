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

namespace think\route;

class RuleName
{
    protected $item = [];

    /**
     * 注册路由标识
     * @access public
     * @param  string   $name      路由标识
     * @param  array    $value     路由规则
     * @param  bool     $first     是否置顶
     * @return void
     */
    public function set($name, $value, $first = false)
    {
        if ($first && isset($this->item[$name])) {
            array_unshift($this->item[$name], $value);
        } else {
            $this->item[$name][] = $value;
        }
    }

    /**
     * 导入路由标识
     * @access public
     * @param  array   $name      路由标识
     * @return void
     */
    public function import($item)
    {
        $this->item = $item;
    }

    /**
     * 根据路由标识获取路由信息（用于URL生成）
     * @access public
     * @param  string   $name      路由标识
     * @return array|null
     */
    public function get($name = null)
    {
        if (is_null($name)) {
            return $this->item;
        }

        $name = strtolower($name);

        return isset($this->item[$name]) ? $this->item[$name] : null;
    }

}
