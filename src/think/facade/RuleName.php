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
 * @see \think\route\RuleName
 * @mixin \think\route\RuleName
 * @method void setName(string $name, $value, bool $first = false) static 注册路由标识
 * @method void setRule(string $rule, RuleItem $route) static 注册路由规则
 * @method array getRule(string $rule, string $domain = null) static 根据路由规则获取路由对象（列表）
 * @method void clear() static 清空路由规则
 * @method array getRuleList(string $domain = null) static 获取全部路由列表
 * @method void import(array $item) static 导入路由标识
 * @method array getName(string $name = null, string $domain = null, string $method = '*') static 根据路由标识获取路由信息（用于URL生成）
 */
class RuleName extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'rule_name';
    }
}
