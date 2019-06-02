<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\facade;

use think\Facade;

/**
 * @see \think\Validate
 * @mixin \think\Validate
 * @method \think\Validate rule(mixed $name, mixed $rule = '') static 添加字段验证规则
 * @method void extend(string $type, callable $callback = null, string $message='') static 注册扩展验证（类型）规则
 * @method void setTypeMsg(mixed $type, string $msg = null) static 设置验证规则的默认提示信息
 * @method \think\Validate message(mixed $name, string $message = '') static 设置提示信息
 * @method \think\Validate scene(string $name) static 设置验证场景
 * @method bool hasScene(string $name) static 判断是否存在某个验证场景
 * @method \think\Validate batch(bool $batch = true) static 设置批量验证
 * @method \think\Validate only(array $fields) static 指定需要验证的字段列表
 * @method \think\Validate remove(mixed $field, mixed $rule = true) static 移除某个字段的验证规则
 * @method \think\Validate append(mixed $field, mixed $rule = null) static 追加某个字段的验证规则
 * @method bool confirm(mixed $value, mixed $rule, array $data = [], string $field = '') static 验证是否和某个字段的值一致
 * @method bool different(mixed $value, mixed $rule, array $data = []) static 验证是否和某个字段的值是否不同
 * @method bool egt(mixed $value, mixed $rule, array $data = []) static 验证是否大于等于某个值
 * @method bool gt(mixed $value, mixed $rule, array $data = []) static 验证是否大于某个值
 * @method bool elt(mixed $value, mixed $rule, array $data = []) static 验证是否小于等于某个值
 * @method bool lt(mixed $value, mixed $rule, array $data = []) static 验证是否小于某个值
 * @method bool eq(mixed $value, mixed $rule) static 验证是否等于某个值
 * @method bool must(mixed $value, mixed $rule) static 必须验证
 * @method bool is(mixed $value, mixed $rule, array $data = []) static 验证字段值是否为有效格式
 * @method bool ip(mixed $value, mixed $rule) static 验证是否有效IP
 * @method bool requireIf(mixed $value, mixed $rule) static 验证某个字段等于某个值的时候必须
 * @method bool requireCallback(mixed $value, mixed $rule,array $data) static 通过回调方法验证某个字段是否必须
 * @method bool requireWith(mixed $value, mixed $rule, array $data) static 验证某个字段有值的情况下必须
 * @method bool filter(mixed $value, mixed $rule) static 使用filter_var方式验证
 * @method bool in(mixed $value, mixed $rule) static 验证是否在范围内
 * @method bool notIn(mixed $value, mixed $rule) static 验证是否不在范围内
 * @method bool between(mixed $value, mixed $rule) static between验证数据
 * @method bool notBetween(mixed $value, mixed $rule) static 使用notbetween验证数据
 * @method bool length(mixed $value, mixed $rule) static 验证数据长度
 * @method bool max(mixed $value, mixed $rule) static 验证数据最大长度
 * @method bool min(mixed $value, mixed $rule) static 验证数据最小长度
 * @method bool after(mixed $value, mixed $rule) static 验证日期
 * @method bool before(mixed $value, mixed $rule) static 验证日期
 * @method bool expire(mixed $value, mixed $rule) static 验证有效期
 * @method bool allowIp(mixed $value, mixed $rule) static 验证IP许可
 * @method bool denyIp(mixed $value, mixed $rule) static 验证IP禁用
 * @method bool regex(mixed $value, mixed $rule) static 使用正则验证数据
 * @method bool token(mixed $value, mixed $rule) static 验证表单令牌
 * @method bool dateFormat(mixed $value, mixed $rule) static 验证时间和日期是否符合指定格式
 * @method bool unique(mixed $value, mixed $rule, array $data = [], string $field = '') static 验证是否唯一
 * @method bool check(array $data, mixed $rules = []) static 数据自动验证
 * @method bool checkRule(mixed $data, mixed $rules = []) static 数据手动验证
 * @method bool isNumber(mixed $data) static 验证是否为纯数字（不包含负数和小数点）
 * @method bool isAlpha(mixed $data) static 验证是否为纯字母
 * @method bool isAlphaNum(mixed $data) static 验证是否为字母和数字
 * @method bool isAlphaDash(mixed $data) static 验证是否为字母和数字，以及下划线_和破折号-
 * @method bool isChs(mixed $data) static 验证是否为中文
 * @method bool isChsAlpha(mixed $data) static 验证是否为中文和字母
 * @method bool isChsAlphaNum(mixed $data) static 验证是否为字母和数字
 * @method bool isChsDash(mixed $data) static 验证是否为中文，以及下划线_和破折号-
 * @method bool isCntrl(mixed $data) static 验证是否为控制字符（换行、缩进、空格）
 * @method bool isGraph(mixed $data) static 验证是否为可打印字符（空格除外）
 * @method bool isPrint(mixed $data) static 验证是否为可打印字符（包括空格）
 * @method bool isLower(mixed $data) static 验证是否为小写字符
 * @method bool isUpper(mixed $data) static 验证是否为大写字符
 * @method bool isSpace(mixed $data) static 验证是否为空白字符（包括缩进，垂直制表符，换行符，回车和换页字符）
 * @method bool isInteger(mixed $data) static 验证是否为整数
 * @method bool isFloat(mixed $data) static 验证是否为浮点数
 * @method bool isBool(mixed $data) static 验证是否为布尔值
 * @method bool isEmail(mixed $data) static 验证是否为邮箱地址
 * @method bool isArray(mixed $data) static 验证是否为数组
 * @method bool isAccepted(mixed $data) static 验证是否为yes, on, 或是 1
 * @method bool isDate(mixed $data) static 验证是否为日期格式
 * @method bool isXdigit(mixed $data) static 验证是否为十六进制字符串
 * @method bool isActiveUrl(mixed $data) static 验证是否为有效的域名或者IP
 * @method bool isUrl(mixed $data) static 验证是否为有效的URL地址
 * @method bool isMobile(mixed $data) static 验证是否为有效的手机
 * @method bool isIp(mixed $data) static 验证是否为有效的IP
 * @method bool isIdCard(mixed $data) static 验证是否为有效的身份证号码
 * @method bool isMacAddr(mixed $data) static 验证是否为有效的MAC地址
 * @method bool isZip(mixed $data) static 验证是否为有效的邮编
 * @method mixed getError() static 获取错误信息
 */
class Validate extends Facade
{
    /**
     * 始终创建新的对象实例
     * @var bool
     */
    protected static $alwaysNewInstance = true;

    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'validate';
    }
}
