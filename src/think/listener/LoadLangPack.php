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

namespace think\listener;

use think\App;

class LoadLangPack
{
    /**
     * 路由初始化（路由规则注册）
     * @access public
     * @return void
     */
    public function handle($event, App $app): void
    {
        // 读取默认语言
        $app->lang->range($app->config->get('app.default_lang', 'zh-cn'));

        if ($app->config->get('app.lang_switch_on', false)) {
            // 开启多语言机制 检测当前语言
            $app->lang->detect();
        }

        $app->request->setLangset($app->lang->range());

        // 加载系统语言包
        $app->lang->load([
            $app->getThinkPath() . 'lang' . DIRECTORY_SEPARATOR . $app->request->langset() . '.php',
            $app->getAppPath() . 'lang' . DIRECTORY_SEPARATOR . $app->request->langset() . '.php',
        ]);
    }
}
