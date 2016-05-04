<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\view\driver;

interface ViewInterface
{
    /**
     * 渲染模板文件
     * @access public
     * @param string $template 模板文件
     * @param array $data 模板变量
     * @return void
     */
    public function fetch($template, $data = []);

    /**
     * 渲染模板内容
     * @access public
     * @param string $content 模板内容
     * @param array $data 模板变量
     * @return void
     */
    public function display($content, $data = []);

}
