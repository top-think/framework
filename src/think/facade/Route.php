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
declare (strict_types = 1);

namespace think\facade;

use think\Facade;
use think\route\Dispatch;
use think\route\Domain;
use think\route\Rule;
use think\route\RuleGroup;
use think\route\RuleItem;
use think\route\RuleName;
use think\route\Url as UrlBuild;

/**
 * @see \think\Route
 * @package think\facade
 * @mixin \think\Route
 * @method mixed config(string $name = null)
 * @method \think\Route lazy(bool $lazy = true) 设置路由域名及分组（包括资源路由）是否延迟解析
 * @method void setTestMode(bool $test): void 设置路由为测试模式
 * @method bool isTest(): bool 检查路由是否为测试模式
 * @method \think\Route mergeRuleRegex(bool $merge = true) 设置路由域名及分组（包括资源路由）是否合并解析
 * @method void setGroup(RuleGroup $group): void 设置当前分组
 * @method RuleGroup getGroup(string $name = null) 获取指定标识的路由分组 不指定则获取当前分组
 * @method \think\Route pattern(array $pattern) 注册变量规则
 * @method \think\Route option(array $option) 注册路由参数
 * @method Domain domain(string|array $name, mixed $rule = null): Domain 注册域名路由
 * @method array getDomains(): array 获取域名
 * @method RuleName getRuleName(): RuleName 获取RuleName对象
 * @method \think\Route bind(string $bind, string $domain = null) 设置路由绑定
 * @method array getBind(): array 读取路由绑定信息
 * @method string|null getDomainBind(string $domain = null) 读取路由绑定
 * @method RuleItem[] getName(string $name = null, string $domain = null, string $method = '*'): array 读取路由标识
 * @method void import(array $name): void 批量导入路由标识
 * @method void setName(string $name, RuleItem $ruleItem, bool $first = false): void 注册路由标识
 * @method void setRule(string $rule, RuleItem $ruleItem = null): void 保存路由规则
 * @method RuleItem[] getRule(string $rule): array 读取路由
 * @method array getRuleList(): array 读取路由列表
 * @method void clear(): void 清空路由规则
 * @method RuleItem rule(string $rule, mixed $route = null, string $method = '*'): RuleItem 注册路由规则
 * @method \think\Route setCrossDomainRule(Rule $rule, string $method = '*') 设置跨域有效路由规则
 * @method RuleGroup group(string|\Closure $name, mixed $route = null): RuleGroup 注册路由分组
 * @method RuleItem any(string $rule, mixed $route): RuleItem 注册路由
 * @method RuleItem get(string $rule, mixed $route): RuleItem 注册GET路由
 * @method RuleItem post(string $rule, mixed $route): RuleItem 注册POST路由
 * @method RuleItem put(string $rule, mixed $route): RuleItem 注册PUT路由
 * @method RuleItem delete(string $rule, mixed $route): RuleItem 注册DELETE路由
 * @method RuleItem patch(string $rule, mixed $route): RuleItem 注册PATCH路由
 * @method RuleItem options(string $rule, mixed $route): RuleItem 注册OPTIONS路由
 * @method Resource resource(string $rule, string $route): Resource 注册资源路由
 * @method RuleItem view(string $rule, string $template = '', array $vars = []): RuleItem 注册视图路由
 * @method RuleItem redirect(string $rule, string $route = '', int $status = 301): RuleItem 注册重定向路由
 * @method \think\Route rest(string|array $name, array|bool $resource = []) rest方法定义和修改
 * @method array|null getRest(string $name = null) 获取rest方法定义的参数
 * @method RuleItem miss(string|Closure $route, string $method = '*'): RuleItem 注册未匹配路由规则后的处理
 * @method Response dispatch(\think\Request $request, Closure|bool $withRoute = true) 路由调度
 * @method Dispatch|false check() 检测URL路由
 * @method Dispatch url(string $url): Dispatch 默认URL解析
 * @method UrlBuild buildUrl(string $url = '', array $vars = []): UrlBuild URL生成 支持路由反射
 * @method RuleGroup __call(string $method, array $args) 设置全局的路由分组参数
 */
class Route extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'route';
    }
}
