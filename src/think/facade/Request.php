<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\facade;

use think\Facade;

/**
 * @see \think\Request
 * @mixin \think\Request
 * @method void hook(mixed $method, mixed $callback = null) static Hook 方法注入
 * @method \think\Request create(string $uri, string $method = 'GET', array $params = [], array $cookie = [], array $files = [], array $server = [], string $content = null) static 创建一个URL请求
 * @method mixed domain(string $domain = null) static 设置或获取当前包含协议的域名
 * @method mixed url(mixed $url = null) static 设置或获取当前完整URL
 * @method mixed baseUrl(string $url = null) static 设置或获取当前URL
 * @method mixed baseFile(string $file = null) static 设置或获取当前执行的文件
 * @method mixed root(string $url = null) static 设置或获取URL访问根地址
 * @method string rootUrl() static 获取URL访问根目录
 * @method string pathinfo() static 获取当前请求URL的pathinfo信息（含URL后缀）
 * @method string path() static 获取当前请求URL的pathinfo信息(不含URL后缀)
 * @method string ext() static 当前URL的访问后缀
 * @method float time(bool $float = false) static 获取当前请求的时间
 * @method mixed type() static 当前请求的资源类型
 * @method void mimeType(mixed $type, string $val = '') static 设置资源类型
 * @method string method(bool $method = false) static 当前的请求类型
 * @method bool isGet() static 是否为GET请求
 * @method bool isPost() static 是否为POST请求
 * @method bool isPut() static 是否为PUT请求
 * @method bool isDelete() static 是否为DELTE请求
 * @method bool isHead() static 是否为HEAD请求
 * @method bool isPatch() static 是否为PATCH请求
 * @method bool isOptions() static 是否为OPTIONS请求
 * @method bool isCli() static 是否为cli
 * @method bool isCgi() static 是否为cgi
 * @method mixed param(mixed $name = '', mixed $default = null, mixed $filter = '') static 获取当前请求的参数
 * @method mixed route(mixed $name = '', mixed $default = null, mixed $filter = '') static 设置获取路由参数
 * @method mixed get(mixed $name = '', mixed $default = null, mixed $filter = '') static 设置获取GET参数
 * @method mixed post(mixed $name = '', mixed $default = null, mixed $filter = '') static 设置获取POST参数
 * @method mixed put(mixed $name = '', mixed $default = null, mixed $filter = '') static 设置获取PUT参数
 * @method mixed delete(mixed $name = '', mixed $default = null, mixed $filter = '') static 设置获取DELETE参数
 * @method mixed patch(mixed $name = '', mixed $default = null, mixed $filter = '') static 设置获取PATCH参数
 * @method mixed request(mixed $name = '', mixed $default = null, mixed $filter = '') static 获取request变量
 * @method mixed session(mixed $name = '', mixed $default = null, mixed $filter = '') static 获取session数据
 * @method mixed cookie(mixed $name = '', mixed $default = null, mixed $filter = '') static 获取cookie参数
 * @method mixed server(mixed $name = '', mixed $default = null, mixed $filter = '') static 获取server参数
 * @method mixed env(mixed $name = '', mixed $default = null, mixed $filter = '') static 获取环境变量
 * @method mixed file(mixed $name = '') static 获取上传的文件信息
 * @method mixed header(mixed $name = '', mixed $default = null) static 设置或者获取当前的Header
 * @method mixed input(array $data,mixed $name = '', mixed $default = null, mixed $filter = '') static 获取变量 支持过滤和默认值
 * @method mixed filter(mixed $filter = null) static 设置或获取当前的过滤规则
 * @method mixed has(string $name, string $type = 'param', bool $checkEmpty = false) static 是否存在某个请求参数
 * @method mixed only(mixed $name, string $type = 'param') static 获取指定的参数
 * @method mixed except(mixed $name, string $type = 'param') static 排除指定参数获取
 * @method bool isSsl() static 当前是否ssl
 * @method bool isAjax(bool $ajax = false) static 当前是否Ajax请求
 * @method bool isPjax(bool $pjax = false) static 当前是否Pjax请求
 * @method mixed ip() static 获取客户端IP地址
 * @method bool isMobile() static 检测是否使用手机访问
 * @method string scheme() static 当前URL地址中的scheme参数
 * @method string query() static 当前请求URL地址中的query参数
 * @method string host() static 当前请求的host
 * @method string port() static 当前请求URL地址中的port参数
 * @method string protocol() static 当前请求 SERVER_PROTOCOL
 * @method string remotePort() static 当前请求 REMOTE_PORT
 * @method string contentType() static 当前请求 HTTP_CONTENT_TYPE
 * @method array dispatch(array $dispatch = null) static 设置或者获取当前请求的调度信息
 * @method mixed app() static 获取当前的应用名
 * @method mixed controller(bool $convert = false) static 获取当前的控制器名
 * @method mixed action(bool $convert = false) static 获取当前的操作名
 * @method mixed setApp(string $app = null) static 设置当前的应用名
 * @method mixed setController(string $controller) static 设置当前的控制器名
 * @method mixed setAction(string $action) static 设置当前的操作名
 * @method string getContent() static 设置或者获取当前请求的content
 * @method string getInput() static 获取当前请求的php://input
 * @method string buildToken(string $name = '__token__', mixed $type = 'md5') static 生成请求令牌
 * @method string checkToken(string $name = '__token__', array $data) static 检查请求令牌
 */
class Request extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'request';
    }
}
