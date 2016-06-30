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
 * Test session driver Memcache
 * @author    步入微尘 <lilwil@163.com>
 */

/**
 */
namespace tests\thinkphp\library\think\session\driver;

use think\Session;

class memcacheTest extends \PHPUnit_Framework_TestCase
{

    public function testInit()
    {
        Session::init(['type' => 'memcache',]);
    }

    /**
     *@depends testInit
     **/
    public function testRead()
    {
        $test_read = 'session';
        Session::set('test_session', $test_read);
        $this->assertTrue(Session::has('test_session'));
        $this->assertEquals($test_read, Session::get('test_session'));
    }
    /**       
     *@depends testInit      
     **/
    public function testWrite()
    {
        $test_read = 'session';
        $this->assertFalse(Session::has('test_write_session'));
        Session::set('test_write_session', $test_read);
        $this->assertTrue(Session::has('test_write_session'));
        $this->assertEquals($test_read, Session::get('test_write_session'));
    }
    
}
