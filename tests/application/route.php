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
// $Id$

return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        'str'               => 'index/str',
        'hello-<name><id?>' => 'index/say',
        ':id'               => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name'             => ['index/hello', ['method' => 'post']],
    ],
];
