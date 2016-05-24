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
 * Route测试
 * @author    liu21st <liu21st@gmail.com>
 */

namespace tests\thinkphp\library\think;

use think\Request;
use think\Route;

class routeTest extends \PHPUnit_Framework_TestCase
{

    public function testRegister()
    {
        $request = Request::instance();
        Route::get('hello/:name', 'index/hello');
        Route::get(['hello/:name' => 'index/hello']);
        Route::post('hello/:name', 'index/post');
        Route::put('hello/:name', 'index/put');
        Route::delete('hello/:name', 'index/delete');
        Route::any('user/:id', 'index/user');
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'hello']], Route::check($request, 'hello/thinkphp'));
        $this->assertEquals(['hello/:name' => ['route' => 'index/hello', 'option' => [], 'pattern' => []]], Route::getRules('GET'));
        Route::rule('type/:name', 'index/type', 'PUT|POST');
    }

    public function testResource()
    {
        $request = Request::instance();
        Route::resource('res', 'index/blog');
        Route::resource(['res' => ['index/blog']]);

        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'index']], Route::check($request, 'res'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'create']], Route::check($request, 'res/create'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'read']], Route::check($request, 'res/8'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'edit']], Route::check($request, 'res/8/edit'));

        Route::resource('blog.comment', 'index/comment');
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'comment', 'read']], Route::check($request, 'blog/8/comment/10'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'comment', 'edit']], Route::check($request, 'blog/8/comment/10/edit'));
    }

    public function testRest()
    {
        $request = Request::instance();
        Route::rest('read', ['GET', '/:id', 'look']);
        Route::rest('create', ['GET', '/create', 'add']);
        Route::rest(['read' => ['GET', '/:id', 'look'], 'create' => ['GET', '/create', 'add']]);
        Route::resource('res', 'index/blog');

        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'add']], Route::check($request, 'res/create'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'look']], Route::check($request, 'res/8'));

    }

    public function testRouteMap()
    {
        $request = Request::instance();
        Route::map('hello', 'index/hello');
        $this->assertEquals('index/hello', Route::map('hello'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'hello', null]], Route::check($request, 'hello'));
    }

    public function testMixVar()
    {
        $request = Request::instance();
        Route::get('hello-<name>', 'index/hello', [], ['name' => '\w+']);
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'hello']], Route::check($request, 'hello-thinkphp'));
        Route::get('hello-<name><id?>', 'index/hello', [], ['name' => '\w+', 'id' => '\d+']);
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'hello']], Route::check($request, 'hello-thinkphp2016'));
        Route::get('hello-<name>/[:id]', 'index/hello', [], ['name' => '\w+', 'id' => '\d+']);
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'hello']], Route::check($request, 'hello-thinkphp/2016'));
    }

    public function testParseUrl()
    {
        $this->assertEquals(['type' => 'module', 'module' => ['hello', null, null]], Route::parseUrl('hello'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'hello', null]], Route::parseUrl('index/hello'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'hello', null]], Route::parseUrl('index/hello?name=thinkphp'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'user', 'hello']], Route::parseUrl('index/user/hello'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'user', 'hello']], Route::parseUrl('index/user/hello/name/thinkphp'));
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'index', 'hello']], Route::parseUrl('index-index-hello', '-'));
    }

    public function testCheckRoute()
    {
        Route::get('hello/:name', 'index/hello');
        Route::get('blog/:id', 'blog/read', [], ['id' => '\d+']);
        $request = Request::instance();
        $this->assertEquals(false, Route::check($request, 'test/thinkphp'));
        $this->assertEquals(false, Route::check($request, 'blog/thinkphp'));
        $this->assertEquals(['type' => 'module', 'module' => [null, 'blog', 'read']], Route::check($request, 'blog/5'));
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'hello']], Route::check($request, 'hello/thinkphp/abc/test'));
    }

    public function testCheckRouteGroup()
    {
        $request = Request::instance();
        Route::pattern(['id' => '\d+', 'name' => '\w{6,25}']);
        Route::group('group', [':id' => 'index/hello', ':name' => 'index/say']);
        $this->assertEquals(false, Route::check($request, 'empty/think'));
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'say']], Route::check($request, 'group/think'));
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'hello']], Route::check($request, 'group/10'));
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'say']], Route::check($request, 'group/thinkphp'));
    }

    public function testRouteToModule()
    {
        $request = Request::instance();
        Route::get('hello/:name', 'index/hello');
        Route::get('blog/:id', 'blog/read', [], ['id' => '\d+']);
        $this->assertEquals(false, Route::check($request, 'test/thinkphp'));
        $this->assertEquals(false, Route::check($request, 'blog/thinkphp'));
        $this->assertEquals(['type' => 'module', 'module' => [null, 'index', 'hello']], Route::check($request, 'hello/thinkphp'));
        $this->assertEquals(['type' => 'module', 'module' => [null, 'blog', 'read']], Route::check($request, 'blog/5'));
    }

    public function testRouteToController()
    {
        $request = Request::instance();
        Route::get('say/:name', '@app\index\controller\index\hello');
        $this->assertEquals(['type' => 'controller', 'controller' => 'app\index\controller\index\hello', 'params' => ['name' => 'thinkphp']], Route::check($request, 'say/thinkphp'));
    }

    public function testRouteToMethod()
    {
        $request = Request::instance();
        Route::get('user/:name', '\app\index\service\User::get', [], ['name' => '\w+']);
        Route::get('info/:name', ['\app\index\model\Info', 'getInfo'], [], ['name' => '\w+']);
        $this->assertEquals(['type' => 'method', 'method' => '\app\index\service\User::get', 'params' => ['name' => 'thinkphp']], Route::check($request, 'user/thinkphp'));
        $this->assertEquals(['type' => 'method', 'method' => ['\app\index\model\Info', 'getInfo'], 'params' => ['name' => 'thinkphp']], Route::check($request, 'info/thinkphp'));
    }

    public function testRouteToRedirect()
    {
        $request = Request::instance();
        Route::get('art/:id', '/article/read/id/:id', [], ['id' => '\d+']);
        $this->assertEquals(['type' => 'redirect', 'url' => '/article/read/id/8', 'status' => 301], Route::check($request, 'art/8'));
    }

    public function testBind()
    {
        $request = Request::instance();
        Route::bind('module', 'index/blog');
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'read']], Route::parseUrl('read/10'));

        Route::get('index/blog/:id', 'index/blog/read');
        $this->assertEquals(['type' => 'module', 'module' => ['index', 'blog', 'read']], Route::check($request, '10'));

        Route::bind('namespace', '\app\index\controller');
        $this->assertEquals(['type' => 'method', 'method' => ['\app\index\controller\blog', 'read'], 'params' => []], Route::check($request, 'blog/read'));

        Route::bind('class', '\app\index\controller\blog');
        $this->assertEquals(['type' => 'method', 'method' => ['\app\index\controller\blog', 'read'], 'params' => []], Route::check($request, 'read'));
    }

    public function testDomain()
    {
        $_SERVER['HTTP_HOST']   = 'subdomain.thinkphp.cn';
        $_SERVER['REQUEST_URI'] = '';
        Route::domain('subdomain.thinkphp.cn', 'sub?abc=test&status=1');
        Route::checkDomain();
        $this->assertEquals('sub?abc=test&status=1', Route::domain('subdomain.thinkphp.cn'));
        $this->assertEquals('sub', Route::bind('module'));
        $this->assertEquals('test', $_GET['abc']);
        $this->assertEquals(1, $_GET['status']);

        Route::domain('subdomain.thinkphp.cn', function () {return ['type' => 'module', 'module' => 'sub2'];});
        Route::checkDomain();
        $this->assertEquals('sub2', Route::bind('module'));

        Route::domain('subdomain.thinkphp.cn', '\app\index\controller');
        Route::checkDomain();
        $this->assertEquals('\app\index\controller', Route::bind('namespace'));

        Route::domain('subdomain.thinkphp.cn', '@\app\index\controller\blog');
        Route::checkDomain();
        $this->assertEquals('\app\index\controller\blog', Route::bind('class'));

        Route::domain('subdomain.thinkphp.cn', '[sub3]');
        Route::checkDomain();
        $this->assertEquals('sub3', Route::bind('group'));
    }
}
