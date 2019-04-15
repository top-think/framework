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
        // 读取默认语言
        $this->lang->setLangset($this->app->config->get('app.default_lang', 'zh-cn'));

        if ($this->app->config->get('app.lang_switch_on', false)) {
            // 开启多语言机制 检测当前语言
            $this->lang->detect($request);
        }

        $langset = $this->lang->getLangSet();

        if ($this->app->config->get('app.lang_use_cookie')) {
            $this->app->cookie->set($this->lang->getLangCookieVar(), $langset);
        }

        // 加载系统语言包
        $this->lang->load([
            $this->app->getThinkPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . '.php',
            $this->app->getAppPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . '.php',
        ]);

        return $next($request);
    }
}
