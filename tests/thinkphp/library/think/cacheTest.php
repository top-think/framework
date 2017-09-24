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

namespace tests\thinkphp\library\think;

use tests\thinkphp\library\think\config\ConfigInitTrait;
use think\Cache;
use think\Config;

class cacheTest extends \PHPUnit_Framework_TestCase
{
    use ConfigInitTrait {
        ConfigInitTrait::tearDown as ConfigTearDown;
    }

    public function tearDown()
    {
        $this->ConfigTearDown();

        call_user_func(\Closure::bind(function () {
            Cache::$handler    = null;
            Cache::$instance   = [];
            Cache::$readTimes  = 0;
            Cache::$writeTimes = 0;
        }, null, '\think\Cache'));
    }

    /**
     * @dataProvider provideTestConnect
     */
    public function testConnect($options, $expected)
    {
        $connection = Cache::connect($options);
        $this->assertInstanceOf($expected, $connection);
        $this->assertSame($connection, Cache::connect($options));

        $instance = $this->getPropertyVal('instance');
        $this->assertArrayHasKey(md5(serialize($options)), $instance);

        $newConnection = Cache::connect($options, true);
        $newInstance = $this->getPropertyVal('instance');
        $this->assertInstanceOf($expected, $connection);
        $this->assertNotSame($connection, $newConnection);
        $this->assertEquals($instance, $newInstance);
    }

    /**
     * @dataProvider provideTestInit
     */
    public function testInit($options, $expected)
    {
        $connection = Cache::init($options);
        $this->assertInstanceOf($expected, $connection);

        $connectionNew = Cache::init(['type' => 'foo']);
        $this->assertSame($connection, $connectionNew);
    }

    public function testStore()
    {
        Config::set('cache.redis', ['type' => 'redis']);

        $connectionDefault = Cache::store();
        $this->assertInstanceOf('\think\cache\driver\File', $connectionDefault);

        Config::set('cache.type', false);
        $connectionNotRedis = Cache::store('redis');
        $this->assertSame($connectionDefault, $connectionNotRedis);

        Config::set('cache.type', 'complex');
        $connectionRedis = Cache::store('redis');
        $this->assertNotSame($connectionNotRedis, $connectionRedis);
        $this->assertInstanceOf('\think\cache\driver\Redis', $connectionRedis);

        // 即便成功切换到其他存储类型，也不影响原先的操作句柄
        $this->assertSame($connectionDefault, Cache::store());
    }

    public function testHas()
    {
        $key = $this->buildTestKey('Has');

        $this->assertFalse(Cache::has($key));

        Cache::set($key, 5);
        $this->assertTrue(Cache::has($key));
    }

    public function testGet()
    {
        $key = $this->buildTestKey('Get');

        $this->assertFalse(Cache::get($key));

        $this->assertEquals('default', Cache::get($key, 'default'));

        Cache::set($key, 5);
        $this->assertSame(5, Cache::get($key));
    }

    public function testSet()
    {
        $key = $this->buildTestKey('Set');

        $this->assertTrue(Cache::set(null, null));
        $this->assertTrue(Cache::set($key, 'ThinkPHP3.2'));
        $this->assertTrue(Cache::set($key, 'ThinkPHP5.0', null));
        $this->assertTrue(Cache::set($key, 'ThinkPHP5.0', -1));
        $this->assertTrue(Cache::set($key, 'ThinkPHP5.0', 0));
        $this->assertTrue(Cache::set($key, 'ThinkPHP5.0', 7200));
    }

    public function testInc()
    {
        $key = $this->buildTestKey('Inc');

        Cache::inc($key);
        $this->assertEquals(1, Cache::get($key));

        Cache::inc($key, '2');
        $this->assertEquals(3, Cache::get($key));

        Cache::inc($key, -1);
        $this->assertEquals(2, Cache::get($key));

        Cache::inc($key, null);
        $this->assertEquals(2, Cache::get($key));

        Cache::inc($key, true);
        $this->assertEquals(3, Cache::get($key));

        Cache::inc($key, false);
        $this->assertEquals(3, Cache::get($key));

        Cache::inc($key, 0.789);
        $this->assertEquals(3.789, Cache::get($key));
    }
    public function testDec()
    {
        $key = $this->buildTestKey('Dec');

        Cache::dec($key);
        $this->assertEquals(-1, Cache::get($key));

        Cache::dec($key, '2');
        $this->assertEquals(-3, Cache::get($key));

        Cache::dec($key, -1);
        $this->assertEquals(-2, Cache::get($key));

        Cache::dec($key, null);
        $this->assertEquals(-2, Cache::get($key));

        Cache::dec($key, true);
        $this->assertEquals(-3, Cache::get($key));

        Cache::dec($key, false);
        $this->assertEquals(-3, Cache::get($key));

        Cache::dec($key, 0.359);
        $this->assertEquals(-3.359, Cache::get($key));
    }

    public function testRm()
    {
        $key = $this->buildTestKey('Rm');

        $this->assertFalse(Cache::rm($key));

        Cache::set($key, 'ThinkPHP');
        $this->assertTrue(Cache::rm($key));
    }

    public function testClear()
    {
        $key1 = $this->buildTestKey('Clear1');
        $key2 = $this->buildTestKey('Clear2');

        Cache::set($key1, 'ThinkPHP3.2');
        Cache::set($key2, 'ThinkPHP5.0');

        $this->assertEquals('ThinkPHP3.2', Cache::get($key1));
        $this->assertEquals('ThinkPHP5.0', Cache::get($key2));
        Cache::clear();
        $this->assertFalse(Cache::get($key1));
        $this->assertFalse(Cache::get($key2));
    }

    public function testPull()
    {
        $key = $this->buildTestKey('Pull');

        $this->assertNull(Cache::pull($key));

        Cache::set($key, 'ThinkPHP');
        $this->assertEquals('ThinkPHP', Cache::pull($key));
        $this->assertFalse(Cache::get($key));
    }

    public function testRemember()
    {
        $key1 = $this->buildTestKey('Remember1');
        $key2 = $this->buildTestKey('Remember2');

        $this->assertEquals('ThinkPHP3.2', Cache::remember($key1, 'ThinkPHP3.2'));
        $this->assertEquals('ThinkPHP3.2', Cache::remember($key1, 'ThinkPHP5.0'));

        $this->assertEquals('ThinkPHP5.0', Cache::remember($key2, function () {
            return 'ThinkPHP5.0';
        }));
        $this->assertEquals('ThinkPHP5.0', Cache::remember($key2, function () {
            return 'ThinkPHP3.2';
        }));
    }

    public function testTag()
    {
        $key = $this->buildTestKey('Tag');

        $cacheTagWithoutName = Cache::tag(null);
        $this->assertInstanceOf('think\cache\Driver', $cacheTagWithoutName);

        $cacheTagWithKey = Cache::tag($key, [1, 2, 3]);
        $this->assertSame($cacheTagWithoutName, $cacheTagWithKey);
    }

    protected function getPropertyVal($name)
    {
        static $reflectionClass;
        if (empty($reflectionClass)) {
            $reflectionClass = new \ReflectionClass('\think\Cache');
        }

        $property = $reflectionClass->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue();
    }

    public function provideTestConnect()
    {
        $provideData = [];

        $options   = ['type' => null];
        $expected  = '\think\cache\driver\File';
        $provideData[] = [$options, $expected];

        $options   = ['type' => 'File'];
        $expected  = '\think\cache\driver\File';
        $provideData[] = [$options, $expected];

        $options   = ['type' => 'Lite'];
        $expected  = '\think\cache\driver\Lite';
        $provideData[] = [$options, $expected];

        $options   = ['type' => 'Memcached'];
        $expected  = '\think\cache\driver\Memcached';
        $provideData[] = [$options, $expected];

        $options   = ['type' => 'Redis'];
        $expected  = '\think\cache\driver\Redis';
        $provideData[] = [$options, $expected];

        // TODO
        // $options   = ['type' => 'Memcache'];
        // $expected  = '\think\cache\driver\Memcache';
        // $provideData[] = [$options, $expected];
        //
        // $options   = ['type' => 'Wincache'];
        // $expected  = '\think\cache\driver\Wincache';
        // $provideData[] = [$options, $expected];

        // $options   = ['type' => 'Sqlite'];
        // $expected  = '\think\cache\driver\Sqlite';
        // $provideData[] = [$options, $expected];
        //
        // $options   = ['type' => 'Xcache'];
        // $expected  = '\think\cache\driver\Xcache';
        // $provideData[] = [$options, $expected];

        return $provideData;
    }

    public function provideTestInit()
    {
        $provideData = [];

        $options   = [];
        $expected  = '\think\cache\driver\File';
        $provideData[] = [$options, $expected];

        $options   = ['type' => 'File'];
        $expected  = '\think\cache\driver\File';
        $provideData[] = [$options, $expected];

        $options   = ['type' => 'Lite'];
        $expected  = '\think\cache\driver\Lite';
        $provideData[] = [$options, $expected];

        return $provideData;
    }

    protected function buildTestKey($tag)
    {
        return sprintf('ThinkPHP_Test_%s_%d_%d', $tag, time(), rand(0, 10000));
    }
}
