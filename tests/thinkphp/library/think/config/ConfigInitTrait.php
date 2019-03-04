<?php

/**
 * 测试用例在使用Config时(如reset),可能会影响其他测试用例
 * 此Trait在每次执行测试用例后会对Config进行还原
 */
namespace tests\thinkphp\library\think\config;

use think\Config;

trait ConfigInitTrait
{
    /**
     * @var \Closure
     */
    protected static $internalConfigFoo;

    /**
     * @var \Closure
     */
    protected static $internalRangeFoo;

    /**
     * @var mixed
     */
    protected static $originConfig;

    /**
     * @var string
     */
    protected static $originRange;

    public static function setUpBeforeClass()
    {
        self::$internalConfigFoo = \Closure::bind(function ($value = null) {
            return !is_null($value) ? Config::$config = $value : Config::$config;
        }, null, '\\Think\\Config');

        self::$internalRangeFoo  = \Closure::bind(function ($value = null) {
            return !is_null($value) ? Config::$range = $value : Config::$range;
        }, null, '\\Think\\Config');

        self::$originConfig = call_user_func(self::$internalConfigFoo);
        self::$originRange  = call_user_func(self::$internalRangeFoo);
    }

    public function tearDown()
    {
        call_user_func(self::$internalConfigFoo, self::$originConfig);
        call_user_func(self::$internalRangeFoo, self::$originRange);
    }
}
