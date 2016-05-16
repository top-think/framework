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

namespace think\response;

use think\Config;
use think\Response;
use think\View;

class Html extends Response
{
    // 输出参数
    protected $options     = [];
    protected $vars        = [];
    protected $replace     = [];
    protected $contentType = 'text/html';
    protected $render      = false;

    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        // 返回JSON数据格式到客户端 包含状态信息
        if ($this->render) {
            // 渲染模板输出
            return View::instance(Config::get('template'), Config::get('view_replace_str'))
                ->fetch($data, $this->vars, $this->replace, [], false, false);
        } else {
            return $data;
        }
    }

    /**
     * 是否需要进行视图渲染
     * @access protected
     * @param bool $render 是否渲染
     * @return $this
     */
    public function render($render = true)
    {
        $this->render = $render;
        return $this;
    }

    /**
     * 视图变量赋值
     * @access protected
     * @param array $vars 模板变量
     * @return $this
     */
    public function vars($vars = [])
    {
        $this->vars = $vars;
        return $this;
    }

    /**
     * 模板变量赋值
     * @access public
     * @param mixed $name  变量名
     * @param mixed $value 变量值
     * @return $this
     */
    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->vars = array_merge($this->vars, $name);
            return $this;
        } else {
            $this->vars[$name] = $value;
        }
        return $this;
    }

    /**
     * 视图内容替换
     * @access public
     * @param string|array $content 被替换内容（支持批量替换）
     * @param string  $replace    替换内容
     * @return $this
     */
    public function replace($content, $replace = '')
    {
        if (is_array($content)) {
            $this->replace = array_merge($this->replace, $content);
        } else {
            $this->replace[$content] = $replace;
        }
        return $this;
    }

}
