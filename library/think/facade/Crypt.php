<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\facade;

use think\Facade;

/**
 * @see \think\Crypt
 * @mixin \think\Crypt
 * @method \think\crypt\Driver init(string $type = 'think') static 初始化加密
 * @method mixed encrypt(string $data, string $key, int $expire = 0) static 加密字符串
 * @method mixed decrypt(string $data, string $key) static 解密字符串
 */
class Crypt extends Facade
{
}
