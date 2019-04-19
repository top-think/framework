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

use Psr\Cache\CacheException as Psr6CacheExceptionInterface;
use Psr\SimpleCache\CacheException as SimpleCacheExceptionInterface;
use think\Exception;

/**
 * 缓存异常
 */
class CacheException extends Exception implements Psr6CacheExceptionInterface, SimpleCacheExceptionInterface
{
}
