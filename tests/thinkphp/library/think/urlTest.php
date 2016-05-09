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
 * Url测试
 * @author    liu21st <liu21st@gmail.com>
 */

namespace tests\thinkphp\library\think;

use think\Config;
use think\Route;
use think\Url;

class urlTest extends \PHPUnit_Framework_TestCase
{

    public function testBuildModule()
    {

        Route::get('hello/:name', 'index/hello');
        Route::get('hello/:id', 'index/hello');
        Config::set('pathinfo_depr', '/');
        $this->assertEquals('/hello/thinkphp', Url::build('index/hello?name=thinkphp'));
        $this->assertEquals('/hello/thinkphp.html', Url::build('index/hello', 'name=thinkphp', 'html'));
        $this->assertEquals('/hello/10', Url::build('index/hello?id=10'));
        $this->assertEquals('/hello/10.html', Url::build('index/hello', 'id=10', 'html'));

        Route::get('hello-<name><id?>', 'index/say');
        $this->assertEquals('/hello-thinkphp', Url::build('index/say?name=thinkphp'));
        $this->assertEquals('/hello-thinkphp2016', Url::build('index/say?name=thinkphp&id=2016'));
        Route::get('str', 'index/str');
        $this->assertEquals('/hello/str.html', Url::build('index/str', '', 'html'));
    }

    public function testBuildController()
    {
        Route::get('blog/:id', '@index/blog/read');
        $this->assertEquals('/blog/10.html', Url::build('@index/blog/read', 'id=10', 'html'));

        Route::get('foo/bar', '@foo/bar/index');
        $this->assertEquals('/foo/bar', Url::build('@foo/bar/index'));

        Route::get('foo/bar/baz', '@foo/bar.BAZ/index');
        $this->assertEquals('/foo/bar/baz', Url::build('@foo/bar.BAZ/index'));
    }

    public function testBuildMethod()
    {
        Route::get('blog/:id', ['\app\index\controller\blog', 'read']);
        $this->assertEquals('/blog/10.html', Url::build('\app\index\controller\blog\read', 'id=10', 'html'));
    }

    public function testBuildRoute()
    {
        Route::get('blog/:id', 'index/blog');
        Config::set('url_html_suffix', 'shtml');
        $this->assertNotEquals('/blog/10.html', Url::build('/blog/10'));
        $this->assertEquals('/blog/10.shtml', Url::build('/blog/10'));
    }

    public function testBuildAnchor()
    {
        Route::get('blog/:id', 'index/blog');
        Config::set('url_html_suffix', 'shtml');
        $this->assertEquals('/blog/10.shtml#detail', Url::build('/blog/10#detail'));
    }
}
