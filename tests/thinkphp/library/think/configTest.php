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

/**
 * 配置测试
 * @author    Haotong Lin <lofanmi@gmail.com>
 */

namespace tests\thinkphp\library\think;

use tests\thinkphp\library\think\config\ConfigInitTrait;
use think\Config;

class configTest extends \PHPUnit_Framework_TestCase
{
    use ConfigInitTrait;

    public function testRange()
    {
        // test default range
        $this->assertEquals('_sys_', call_user_func(self::$internalRangeFoo));

        $this->assertTrue(is_array(call_user_func(self::$internalConfigFoo)));
        // test range initialization
        Config::range('_test_');
        $this->assertEquals('_test_', call_user_func(self::$internalRangeFoo));
        $this->assertEquals([], call_user_func(self::$internalConfigFoo)['_test_']);
    }

    // public function testParse()
    // {
    //  see \think\config\driver\...Test.php
    // }

    public function testLoad()
    {
        $file   = APP_PATH . 'config' . EXT;
        $config = array_change_key_case(include $file);
        $name   = '_name_';
        $range  = '_test_';

        $this->assertEquals($config, Config::load($file, $name, $range));
        $this->assertNotEquals(null, Config::load($file, $name, $range));
    }

    public function testHas()
    {
        $range = '_test_';
        $this->assertFalse(Config::has('abcd', $range));

        call_user_func(self::$internalConfigFoo, [
            $range => ['abcd' => 'value'],
        ]);
        $this->assertTrue(Config::has('abcd', $range));

        // else ...
        $this->assertFalse(Config::has('abcd.efg', $range));

        call_user_func(self::$internalConfigFoo, [
            $range => ['abcd' => ['efg' => 'value']],
        ]);
        $this->assertTrue(Config::has('abcd.efg', $range));
    }

    public function testGet()
    {
        $range = '_test_';
        call_user_func(self::$internalConfigFoo, [
            $range => []
        ]);
        $this->assertEquals([], Config::get(null, $range));
        $this->assertEquals(null, Config::get(null, 'does_not_exist'));
        $value = 'value';
        // test getting configuration
        call_user_func(self::$internalConfigFoo, [
            $range => ['abcd' => 'efg']
        ]);
        $this->assertEquals('efg', Config::get('abcd', $range));
        $this->assertEquals(null, Config::get('does_not_exist', $range));
        $this->assertEquals(null, Config::get('abcd', 'does_not_exist'));
        // test getting configuration with dot syntax
        call_user_func(self::$internalConfigFoo, [
            $range => ['one' => ['two' => $value]]
        ]);
        $this->assertEquals($value, Config::get('one.two', $range));
        $this->assertEquals(null, Config::get('one.does_not_exist', $range));
        $this->assertEquals(null, Config::get('one.two', 'does_not_exist'));
    }

    public function testSet()
    {
        $range = '_test_';

        // without dot syntax
        $name  = 'name';
        $value = 'value';
        Config::set($name, $value, $range);
        $config = call_user_func(self::$internalConfigFoo);
        $this->assertEquals($value, $config[$range][$name]);
        // with dot syntax
        $name  = 'one.two';
        $value = 'dot value';
        Config::set($name, $value, $range);
        $config = call_user_func(self::$internalConfigFoo);
        $this->assertEquals($value, $config[$range]['one']['two']);
        // if (is_array($name)):
        // see testLoad()
        // ...
        // test getting all configurations...?
        // return self::$config[$range]; ??
        $value = ['all' => 'configuration'];
        call_user_func(self::$internalConfigFoo, [$range => $value]);
        $this->assertEquals($value, Config::set(null, null, $range));
        $this->assertNotEquals(null, Config::set(null, null, $range));
    }

    public function testReset()
    {
        $range = '_test_';
        call_user_func(self::$internalConfigFoo, [$range => ['abcd' => 'efg']]);

        // clear all configurations
        Config::reset(true);
        $config = call_user_func(self::$internalConfigFoo);
        $this->assertEquals([], $config);
        // clear the configuration in range of parameter.
        call_user_func(self::$internalConfigFoo, [
            $range => [
                'abcd' => 'efg',
                'hijk' => 'lmn',
            ],
            'a'    => 'b',
        ]);
        Config::reset($range);
        $config = call_user_func(self::$internalConfigFoo);
        $this->assertEquals([
            $range => [],
            'a'    => 'b',
        ], $config);
    }
}
