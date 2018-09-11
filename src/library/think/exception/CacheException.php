<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\exception;

use think\Exception;

if (interface_exists('Psr\Cache\CacheException')) {
    interface Psr6CacheExceptionInterface extends \Psr\Cache\CacheException
    {}
} else {
    interface Psr6CacheExceptionInterface
    {}
}

if (interface_exists('Psr\SimpleCache\CacheException')) {
    interface SimpleCacheExceptionInterface extends \Psr\SimpleCache\CacheException
    {}
} else {
    interface SimpleCacheExceptionInterface
    {}
}

class CacheException extends Exception implements Psr6CacheExceptionInterface, SimpleCacheExceptionInterface
{
}
