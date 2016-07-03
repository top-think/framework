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

use think\Config;
use think\Request;
use think\Route;

class routeTest extends \PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
        Config::set('app_multi_module', true);
    }

    public function testRegister()
    {
        $request = Request::instance();
        Route::get('hello/:name', 'index/hello');
        Route::get(['hello/:name' => 'index/hello']);
        Route::post('hello/:name', 'index/post');
        Route::put('hello/:name', 'index/put');
        Route::delete('hello/:name', 'index/delete');
        Route::any('user/:id', 'index/user');
        $result = Route::check($request, 'hello/thinkphp');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
        $this->assertEquals(['hello/:name' => ['route' => 'index/hello', 'option' => [], 'pattern' => []]], Route::getRules('GET'));
        Route::rule('type/:name', 'index/type', 'PUT|POST');
    }

    public function testResource()
    {
        $request = Request::instance();
        Route::resource('res', 'index/blog');
        Route::resource(['res' => ['index/blog']]);
        $result = Route::check($request, 'res');
        $this->assertEquals(['index', 'blog', 'index'], $result['module']);
        $result = Route::check($request, 'res/create');
        $this->assertEquals(['index', 'blog', 'create'], $result['module']);
        $result = Route::check($request, 'res/8');
        $this->assertEquals(['index', 'blog', 'read'], $result['module']);
        $result = Route::check($request, 'res/8/edit');
        $this->assertEquals(['index', 'blog', 'edit'], $result['module']);

        Route::resource('blog.comment', 'index/comment');
        $result = Route::check($request, 'blog/8/comment/10');
        $this->assertEquals(['index', 'comment', 'read'], $result['module']);
        $result = Route::check($request, 'blog/8/comment/10/edit');
        $this->assertEquals(['index', 'comment', 'edit'], $result['module']);
    }

    public function testRest()
    {
        $request = Request::instance();
        Route::rest('read', ['GET', '/:id', 'look']);
        Route::rest('create', ['GET', '/create', 'add']);
        Route::rest(['read' => ['GET', '/:id', 'look'], 'create' => ['GET', '/create', 'add']]);
        Route::resource('res', 'index/blog');
        $result = Route::check($request, 'res/create');
        $this->assertEquals(['index', 'blog', 'add'], $result['module']);
        $result = Route::check($request, 'res/8');
        $this->assertEquals(['index', 'blog', 'look'], $result['module']);

    }

    public function testRouteMap()
    {
        $request = Request::instance();
        Route::map('hello', 'index/hello');
        $this->assertEquals('index/hello', Route::map('hello'));
        $result = Route::check($request, 'hello');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
    }

    public function testMixVar()
    {
        $request = Request::instance();
        Route::get('hello-<name>', 'index/hello', [], ['name' => '\w+']);
        $result = Route::check($request, 'hello-thinkphp');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
        Route::get('hello-<name><id?>', 'index/hello', [], ['name' => '\w+', 'id' => '\d+']);
        $result = Route::check($request, 'hello-thinkphp2016');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
        Route::get('hello-<name>/[:id]', 'index/hello', [], ['name' => '\w+', 'id' => '\d+']);
        $result = Route::check($request, 'hello-thinkphp/2016');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
    }

    public function testParseUrl()
    {
        $result = Route::parseUrl('hello');
        $this->assertEquals(['hello', null, null], $result['module']);
        $result = Route::parseUrl('index/hello');
        $this->assertEquals(['index', 'hello', null], $result['module']);
        $result = Route::parseUrl('index/hello?name=thinkphp');
        $this->assertEquals(['index', 'hello', null], $result['module']);
        $result = Route::parseUrl('index/user/hello');
        $this->assertEquals(['index', 'user', 'hello'], $result['module']);
        $result = Route::parseUrl('index/user/hello/name/thinkphp');
        $this->assertEquals(['index', 'user', 'hello'], $result['module']);
        $result = Route::parseUrl('index-index-hello', '-');
        $this->assertEquals(['index', 'index', 'hello'], $result['module']);
    }

    public function testCheckRoute()
    {
        Route::get('hello/:name', 'index/hello');
        Route::get('blog/:id', 'blog/read', [], ['id' => '\d+']);
        $request = Request::instance();
        $this->assertEquals(false, Route::check($request, 'test/thinkphp'));
        $this->assertEquals(false, Route::check($request, 'blog/thinkphp'));
        $result = Route::check($request, 'blog/5');
        $this->assertEquals([null, 'blog', 'read'], $result['module']);
        $result = Route::check($request, 'hello/thinkphp/abc/test');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
    }

    public function testCheckRouteGroup()
    {
        $request = Request::instance();
        Route::pattern(['id' => '\d+', 'name' => '\w{6,25}']);
        Route::group('group', [':id' => 'index/hello', ':name' => 'index/say']);
        $this->assertEquals(false, Route::check($request, 'empty/think'));
        $result = Route::check($request, 'group/think');
        $this->assertEquals([null, 'index', 'say'], $result['module']);
        $result = Route::check($request, 'group/10');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
        $result = Route::check($request, 'group/thinkphp');
        $this->assertEquals([null, 'index', 'say'], $result['module']);
    }

    public function testRouteToModule()
    {
        $request = Request::instance();
        Route::get('hello/:name', 'index/hello');
        Route::get('blog/:id', 'blog/read', [], ['id' => '\d+']);
        $this->assertEquals(false, Route::check($request, 'test/thinkphp'));
        $this->assertEquals(false, Route::check($request, 'blog/thinkphp'));
        $result = Route::check($request, 'hello/thinkphp');
        $this->assertEquals([null, 'index', 'hello'], $result['module']);
        $result = Route::check($request, 'blog/5');
        $this->assertEquals([null, 'blog', 'read'], $result['module']);
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
        Route::get('info/:name', '\app\index\model\Info@getInfo', [], ['name' => '\w+']);
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
        Route::bind('index/blog');
        $result = Route::parseUrl('read/10');
        $this->assertEquals(['index', 'blog', 'read'], $result['module']);

        Route::get('index/blog/:id', 'index/blog/read');
        $result = Route::check($request, '10');
        $this->assertEquals(['index', 'blog', 'read'], $result['module']);

        Route::bind('\app\index\controller', 'namespace');
        $this->assertEquals(['type' => 'method', 'method' => ['\app\index\controller\blog', 'read'], 'params' => []], Route::check($request, 'blog/read'));

        Route::bind('\app\index\controller\blog', 'class');
        $this->assertEquals(['type' => 'method', 'method' => ['\app\index\controller\blog', 'read'], 'params' => []], Route::check($request, 'read'));
    }

    public function testDomain()
    {
        $request = Request::create('http://subdomain.thinkphp.cn');
        Route::domain('subdomain.thinkphp.cn', 'sub?abc=test&status=1');
        Route::checkDomain($request);
        $this->assertEquals('sub?abc=test&status=1', Route::domain('subdomain.thinkphp.cn'));
        $this->assertEquals('sub', Route::getbind('module'));
        $this->assertEquals('test', $_GET['abc']);
        $this->assertEquals(1, $_GET['status']);

        Route::domain('subdomain.thinkphp.cn', function () {return ['type' => 'module', 'module' => 'sub2'];});
        Route::checkDomain($request);
        $this->assertEquals('sub2', Route::getbind('module'));

        Route::domain('subdomain.thinkphp.cn', '\app\index\controller');
        Route::checkDomain($request);
        $this->assertEquals('\app\index\controller', Route::getbind('namespace'));

        Route::domain('subdomain.thinkphp.cn', '@\app\index\controller\blog');
        Route::checkDomain($request);
        $this->assertEquals('\app\index\controller\blog', Route::getbind('class'));

        Route::domain('subdomain.thinkphp.cn', '[sub3]');
        Route::checkDomain($request);
        $this->assertEquals('sub3', Route::getbind('group'));
    }
}
