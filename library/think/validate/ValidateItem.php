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
 * @method ValidateItem egt(mixed $rule, string $msg = '') static 验证是否大于等于某个值
 * @method ValidateItem gt(mixed $rule, string $msg = '') static 验证是否大于某个值
 * @method ValidateItem elt(mixed $rule, string $msg = '') static 验证是否小于等于某个值
 * @method ValidateItem lt(mixed $rule, string $msg = '') static 验证是否小于某个值
 * @method ValidateItem eg(mixed $rule, string $msg = '') static 验证是否等于某个值
 * @method ValidateItem in(mixed $rule, string $msg = '') static 验证是否在范围内
 * @method ValidateItem notin(mixed $rule, string $msg = '') static 验证是否不在某个范围
 * @method ValidateItem between(mixed $rule, string $msg = '') static 验证是否在某个区间
 * @method ValidateItem notBetween(mixed $rule, string $msg = '') static 验证是否不在某个区间
 * @method ValidateItem length(mixed $rule, string $msg = '') static 验证数据长度
 * @method ValidateItem max(mixed $rule, string $msg = '') static 验证数据最大长度
 * @method ValidateItem min(mixed $rule, string $msg = '') static 验证数据最小长度
 * @method ValidateItem after(mixed $rule, string $msg = '') static 验证日期
 * @method ValidateItem before(mixed $rule, string $msg = '') static 验证日期
 * @method ValidateItem expire(mixed $rule, string $msg = '') static 验证有效期
 * @method ValidateItem allowIp(mixed $rule, string $msg = '') static 验证IP许可
 * @method ValidateItem denyIp(mixed $rule, string $msg = '') static 验证IP禁用
 * @method ValidateItem regex(mixed $rule, string $msg = '') static 使用正则验证数据
 * @method ValidateItem token(mixed $rule='__token__', string $msg = '') static 验证表单令牌
 * @method ValidateItem is(mixed $rule, string $msg = '') static 验证字段值是否为有效格式
 * @method ValidateItem activeUrl(mixed $rule, string $msg = '') static 验证是否为合格的域名或者IP
 * @method ValidateItem ip(mixed $rule, string $msg = '') static 验证是否有效IP
 * @method ValidateItem fileExt(mixed $rule, string $msg = '') static 验证文件后缀
 * @method ValidateItem fileMime(mixed $rule, string $msg = '') static 验证文件类型
 * @method ValidateItem fileSize(mixed $rule, string $msg = '') static 验证文件大小
 * @method ValidateItem image(mixed $rule, string $msg = '') static 验证图像文件
 * @method ValidateItem method(mixed $rule, string $msg = '') static 验证请求类型
 * @method ValidateItem dateFormat(mixed $rule, string $msg = '') static 验证时间和日期是否符合指定格式
 * @method ValidateItem unique(mixed $rule, string $msg = '') static 验证是否唯一
 * @method ValidateItem behavior(mixed $rule, string $msg = '') static 使用行为类验证
 * @method ValidateItem filter(mixed $rule, string $msg = '') static 使用filter_var方式验证
 * @method ValidateItem requireIf(mixed $rule, string $msg = '') static 验证某个字段等于某个值的时候必须
 * @method ValidateItem requireCallback(mixed $rule, string $msg = '') static 通过回调方法验证某个字段是否必须
 * @method ValidateItem requireWith(mixed $rule, string $msg = '') static 验证某个字段有值的情况下必须
 */
class ValidateItem
{
    // 验证字段的名称
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

        $this->message[] = $msg;

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
     * 获取验证字段名称
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
     * 设置验证字段名称
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

    public function __call($method, $args)
    {
        array_unshift($args, $method);

        return call_user_func_array([$this, 'addItem'], $args);
    }

    public static function __callStatic($method, $args)
    {
        $validateItem = new static();

        array_unshift($args, $method);

        return call_user_func_array([$validateItem, 'addItem'], $args);
    }
}
