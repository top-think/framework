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

use think\Exception;
use think\Log;

class Php
{
    // 模板引擎参数
    protected $config = [
        // 模板起始路径
        'view_path'   => '',
        // 模板文件后缀
        'view_suffix' => 'php',
        // 模板文件名分隔符
        'view_depr'   => DS,
    ];

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 检测是否存在模板文件
     * @access public
     * @param string $template 模板文件或者模板规则
     * @return bool
     */
    public function exists($template)
    {
        if (!is_file($template)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }
        return is_file($template);
    }

    /**
     * 渲染模板文件
     * @access public
     * @param string $template 模板文件
     * @param array $data 模板变量
     * @return void
     */
    public function fetch($template, $data = [])
    {
        if (!is_file($template)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }
        // 模板不存在 抛出异常
        if (!is_file($template)) {
            throw new Exception('template file not exists:' . $template, 10700);
        }
        // 记录视图信息
        APP_DEBUG && Log::record('[ VIEW ] ' . $template . ' [ ' . var_export(array_keys($data), true) . ' ]', 'info');
        extract($data, EXTR_OVERWRITE);
        include $template;
    }

    /**
     * 渲染模板内容
     * @access public
     * @param string $content 模板内容
     * @param array $data 模板变量
     * @return void
     */
    public function display($content, $data = [])
    {
        extract($data, EXTR_OVERWRITE);
        eval('?>' . $content);
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template)
    {
        if (empty($this->config['view_path']) && defined('VIEW_PATH')) {
            $this->config['view_path'] = VIEW_PATH;
        }

        $depr     = $this->config['view_depr'];
        $template = str_replace(['/', ':'], $depr, $template);
        if (strpos($template, '@')) {
            list($module, $template) = explode('@', $template);
            $path                    = APP_PATH . (APP_MULTI_MODULE ? $module . DS : '') . VIEW_LAYER . DS;
        } else {
            $path = $this->config['view_path'];
        }

        // 分析模板文件规则
        if (defined('CONTROLLER_NAME')) {
            if ('' == $template) {
                // 如果模板文件名为空 按照默认规则定位
                $template = str_replace('.', DS, CONTROLLER_NAME) . $depr . ACTION_NAME;
            } elseif (false === strpos($template, $depr)) {
                $template = str_replace('.', DS, CONTROLLER_NAME) . $depr . $template;
            }
        }
        return $path . $template . '.' . ltrim($this->config['view_suffix'], '.');
    }

}
