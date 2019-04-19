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

namespace think\middleware;

use Closure;
use think\App;
use think\Lang;
use think\Request;

/**
 * 多语言加载
 */
class LoadLangPack
{
    /** @var Lang */
    protected $lang;

    /** @var App */
    protected $app;

    public function __construct(Lang $lang, App $app)
    {
        $this->lang = $lang;
        $this->app  = $app;
    }

    /**
     * 路由初始化（路由规则注册）
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return void
     */
    public function handle($request, Closure $next)
    {
        // 当前语言
        $langset = $this->lang->getLangSet();

        // 加载系统语言包
        $this->lang->load([
            $this->app->getThinkPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . '.php',
            $this->app->getAppPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . '.php',
        ]);

        // 加载扩展（自定义）语言包
        $list = $this->app->config->get('lang.extend_list', []);

        if (isset($list[$langset])) {
            $this->lang->load($list[$langset]);
        }

        $this->lang->saveToCookie($this->app->cookie);

        return $next($request);
    }
}
