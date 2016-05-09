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

namespace tests\thinkphp\library\think\cache\driver;

/**
 * Redisd缓存驱动测试
 * @author 尘缘 <130775@qq.com>
 */
class redisdTest extends cacheTestCase
{
    private $_cacheInstance = null;

    protected function setUp()
    {
        if (!extension_loaded("redis")) {
            $this->markTestSkipped("Redis没有安装，已跳过测试！");
        }
        \think\Cache::connect(array('type' => 'redis', 'expire' => 2));
    }

    protected function getCacheInstance()
    {
        if (null === $this->_cacheInstance) {
            $this->_cacheInstance = new \think\cache\driver\Redisd();
        }
        return $this->_cacheInstance;
    }

    public function testGet()
    {
        $cache = $this->prepare();
        $this->assertEquals('string_test', $cache->get('string_test'));
        $this->assertEquals(11, $cache->get('number_test'));
        $result =  $cache->get('array_test');
        $this->assertEquals('array_test', $result['array_test']);
    }

    public function testStoreSpecialValues()
    {
        $redis = $this->getCacheInstance();
        $redis->master(true);

        $redis->handler()->setnx('key', 'value');
        $value = $redis->handler()->get('key');
        $this->assertEquals('value', $value);
        
        $redis->master(true)->set('key', 'val');
        $value = $redis->master(false)->get('key');
        $this->assertEquals('val', $value);
        
        $redis->handler(true)->hset('hash', 'key', 'value');
        $value = $redis->handler(false)->hget('hash', 'key');
        $this->assertEquals('value', $value);
    }

    public function testExpire()
    {
    }
    
    public function testQueue()
    {
    }
}
