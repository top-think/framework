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

if (interface_exists('Psr\Cache\InvalidArgumentException')) {
    interface Psr6CacheInvalidArgumentInterface extends \Psr\Cache\InvalidArgumentException
    {}
} else {
    interface Psr6CacheInvalidArgumentInterface
    {}
}

if (interface_exists('Psr\SimpleCache\InvalidArgumentException')) {
    interface SimpleCacheInvalidArgumentInterface extends \Psr\SimpleCache\InvalidArgumentException
    {}
} else {
    interface SimpleCacheInvalidArgumentInterface
    {}
}

class InvalidArgumentException extends \InvalidArgumentException implements Psr6CacheInvalidArgumentInterface, SimpleCacheInvalidArgumentInterface
{
}
