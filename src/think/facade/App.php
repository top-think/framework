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
declare (strict_types = 1);

namespace think\facade;

use think\Facade;

/**
 * @see \think\App
 * @mixin \think\App
 * @method \think\App multi(bool $multi) static 设置多应用
 * @method \think\App path(string $path) static 设置应用路径
 * @method \think\App debug(bool $debug) static 设置应用调试模式
 * @method \think\App name(string $name) static 设置应用名称
 * @method \think\App controllerLayer(string $layer) static 设置应用控制器层
 * @method \think\App emptyController(string $empty) static 设置应用的空控制器名称
 * @method \think\App setNamespace(string $namespace) static 设置应用的命名空间
 * @method \think\App setRootNamespace(string $namespace) static 设置应用的根命名空间
 * @method \think\App controllerSuffix(bool $suffix) static 设置应用是否启用控制器类库后缀
 * @method \think\App autoMulti(array $map) static 自动多应用
 * @method bool isAutoMulti() static 判断是否为自动多应用
 * @method bool isMulti() static 判断是否为多应用
 * @method void initialize() static 初始化应用
 * @method void init() static 初始化应用
 * @method \think\Response run() static 执行应用
 * @method object controller(string $name) static 实例化控制器
 * @method string parseClass(string $layer, string $name) static 解析应用类的类名
 * @method string version() static 获取框架版本
 * @method bool isDebug() static 是否为调试模式
 * @method string getRootPath() static 获取应用根目录
 * @method string getBasePath() static 获取应用基础目录
 * @method string getAppPath() static 获取应用类库目录
 * @method string getRuntimePath() static 获取应用运行时目录
 * @method string getThinkPath() static 获取核心框架目录
 * @method string getRoutePath() static 获取路由目录
 * @method string getConfigPath() static 获取应用配置目录
 * @method string getConfigExt() static 获取配置后缀
 * @method string setNamespace(string $namespace) static 设置应用类库命名空间
 * @method string getNamespace() static 获取应用类库命名空间
 * @method string getRootNamespace() static 获取应用类库根命名空间
 * @method string hasControllerSuffix() static 是否启用类库后缀
 * @method float getBeginTime() static 获取应用开启时间
 * @method integer getBeginMem() static 获取应用初始内存占用
 * @method string serialize(mixed $data) static 序列化数据
 * @method mixed unserialize(string $data) static 解序列化
 * @method string classBaseName(mixed $class) static 获取类名(不包含命名空间)
 * @method mixed factory(string $name, string $namespace = '', ...$args) static 工厂方法
 * @method string parseName(string $name = null, int $type = 0, bool $ucfirst = true) static 字符串命名风格转换
 */
class App extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'app';
    }
}
