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
 * Hook类测试
 * @author    liu21st <liu21st@gmail.com>
 */

namespace tests\thinkphp\library\think;

use \think\Hook;

class hookTest extends \PHPUnit_Framework_TestCase
{

    public function testRun()
    {
        Hook::add('my_pos', '\tests\thinkphp\library\think\behavior\One');
        Hook::add('my_pos', ['\tests\thinkphp\library\think\behavior\Two']);
        Hook::add('my_pos', '\tests\thinkphp\library\think\behavior\Three', true);
        $data['id']   = 0;
        $data['name'] = 'thinkphp';
        Hook::listen('my_pos', $data);
        $this->assertEquals(2, $data['id']);
        $this->assertEquals('thinkphp', $data['name']);
        $this->assertEquals([
            '\tests\thinkphp\library\think\behavior\Three',
            '\tests\thinkphp\library\think\behavior\One',
            '\tests\thinkphp\library\think\behavior\Two'], Hook::get('my_pos'));
    }

    public function testStatic()
    {

        Hook::add('my_pos2', '\tests\thinkphp\library\think\behavior\StaticDemo');
        $data2 = [];
        Hook::setStatic('my_pos2');
        $this->assertEquals(true, Hook::isStatic('my_pos2'));
        Hook::listen('my_pos2', $data2);
        $this->assertEquals('run', $data2['function']);

        //第二组测试
        Hook::add('my_pos3', '\tests\thinkphp\library\think\behavior\StaticDemo');
        $data3 = [];
        Hook::setStatic('my_pos3');
        $this->assertEquals(true, Hook::isStatic('my_pos3'));
        Hook::listen('my_pos3', $data3);
        $this->assertEquals('my_pos3', $data3['function']);
    }

    public function testImport()
    {
        Hook::import(['my_pos' => [
                '\tests\thinkphp\library\think\behavior\One',
                '\tests\thinkphp\library\think\behavior\Three'],
        ]);
        Hook::import(['my_pos' => ['\tests\thinkphp\library\think\behavior\Two']], false);
        Hook::import(['my_pos' => ['\tests\thinkphp\library\think\behavior\Three', '_overlay' => true]]);
        $data['id']   = 0;
        $data['name'] = 'thinkphp';
        Hook::listen('my_pos', $data);
        $this->assertEquals(3, $data['id']);
    }

    public function testExec()
    {
        $data['id']   = 0;
        $data['name'] = 'thinkphp';
        $this->assertEquals(true, Hook::exec('\tests\thinkphp\library\think\behavior\One'));
        $this->assertEquals(false, Hook::exec('\tests\thinkphp\library\think\behavior\One', 'test', $data));
        $this->assertEquals('test', $data['name']);
        $this->assertEquals('Closure', Hook::exec(function (&$data) {
                    $data['name'] = 'Closure';
                    return 'Closure';
                }));
    }

}
