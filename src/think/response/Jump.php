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

namespace think\response;

use think\Response;
use think\View;

/**
 * Jump Response
 */
class Jump extends Response
{
    protected $contentType = 'text/html';

    protected $view;

    public function __construct(View $view, $data = '', int $code = 200)
    {
        parent::__construct($data, $code);
        $this->view = $view;
    }

    /**
     * 处理数据
     * @access protected
     * @param  mixed $data 要处理的数据
     * @return string
     * @throws \Exception
     */
    protected function output($data): string
    {
        return $this->view->assign($data)
            ->fetch($this->options['jump_template']);
    }
}
