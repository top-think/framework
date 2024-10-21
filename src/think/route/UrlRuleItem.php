<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\route;

use think\Request;
use think\Response;
use think\route\dispatch\Callback;

/**
 * 路由规则类
 */
class UrlRuleItem extends RuleItem
{
    /**
     * 检测路由（含路由匹配）
     * @access public
     * @param  Request      $request  请求对象
     * @param  string       $url      访问地址
     * @param  bool         $completeMatch   路由是否完全匹配
     * @return Dispatch|false
     */
    public function check(Request $request, string $url, bool $completeMatch = false)
    {
        if ($request->method() == 'OPTIONS') {
            // 自动响应options请求
            return new Callback($request, $this->parent, function () {
                return Response::create('', 'html', 204)->header(['Allow' => 'GET, POST, PUT, DELETE']);
            });
        }

        return $this->checkRule($request, $url, null, $completeMatch);
    }

}
