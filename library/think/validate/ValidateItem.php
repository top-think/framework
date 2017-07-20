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

namespace think\validate;

use think\Validate;

/**
 * Class ValidateItem
 * @package think\validate
 * @method ValidateItem confirm(mixed $rule, string $msg = '') static 验证是否和某个字段的值一致
 * @method ValidateItem different(mixed $rule, string $msg = '') static 验证是否和某个字段的值是否不同
 */
class ValidateItem
{
    protected $title;

    // 当前验证规则
    protected $rule = [];

    // 验证提示信息
    protected $message = [];

    /**
     * 添加验证因子
     * @access public
     * @param string    $name  验证名称
     * @param mixed     $rule  验证规则
     * @param string    $msg   提示信息
     * @return $this
     */
    public function addItem($name, $rule = '', $msg = '')
    {
        if (is_array($rule)) {
            $this->rule[] = [$name => $rule];
        } else {
            $this->rule[] = $name . ':' . $rule;
        }

        $this->messge[] = $msg;

        return $this;
    }

    /**
     * 获取验证规则
     * @access public
     * @return array
     */
    public function getRule()
    {
        return $this->rule;
    }

    /**
     * 获取名称
     * @access public
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * 获取验证提示
     * @access public
     * @return array
     */
    public function getMsg()
    {
        return $this->message;
    }

    /**
     * 设置验证名称
     * @access public
     * @return $this
     */
    public function title($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * 验证字段值是否为有效格式
     * @access public
     * @param string    $rule  验证规则
     * @param string    $msg   提示信息
     * @return bool
     */
    public static function is($rule, $msg = '')
    {
        $validateItem = new static;

        return $validateItem->addItem($rule, '', $msg);
    }

    public static function __callStatic($method, $args)
    {
        $validateItem = new static();

        array_unshift($args, $method);

        return call_user_func_array([$validateItem, 'addItem'], $args);
    }
}
