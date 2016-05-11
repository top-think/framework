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

        Route::get('blog/:name', 'index/blog');
        Route::get('blog/:id', 'index/blog');
        Config::set('pathinfo_depr', '/');
        $this->assertEquals('/blog/thinkphp', Url::build('index/blog?name=thinkphp'));
        $this->assertEquals('/blog/thinkphp.html', Url::build('index/blog', 'name=thinkphp', 'html'));
        $this->assertEquals('/blog/10', Url::build('index/blog?id=10'));
        $this->assertEquals('/blog/10.html', Url::build('index/blog', 'id=10', 'html'));

        Route::get('item-<name><id?>', 'blog/item', [], ['name' => '\w+', 'id' => '\d+']);
        $this->assertEquals('/item-thinkphp', Url::build('blog/item?name=thinkphp'));
        $this->assertEquals('/item-thinkphp2016', Url::build('blog/item?name=thinkphp&id=2016'));
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
