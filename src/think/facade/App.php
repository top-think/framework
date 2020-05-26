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
 * @package think\facade
 * @mixin \think\App
 * @method \think\Service|null register(\think\Service|string $service, bool $force = false) 注册服务
 * @method mixed bootService(\think\Service $service) 执行服务
 * @method \think\Service|null getService(string|\think\Service $service) 获取服务
 * @method \think\App debug(bool $debug = true) 开启应用调试模式
 * @method bool isDebug(): bool 是否为调试模式
 * @method \think\App setNamespace(string $namespace) 设置应用命名空间
 * @method string getNamespace(): string 获取应用类库命名空间
 * @method string version(): string 获取框架版本
 * @method string getRootPath(): string 获取应用根目录
 * @method string getBasePath(): string 获取应用基础目录
 * @method string getAppPath(): string 获取当前应用目录
 * @method mixed setAppPath(string $path) 设置应用目录
 * @method string getRuntimePath(): string 获取应用运行时目录
 * @method void setRuntimePath(string $path): void 设置runtime目录
 * @method string getThinkPath(): string 获取核心框架目录
 * @method string getConfigPath(): string 获取应用配置目录
 * @method string getConfigExt(): string 获取配置后缀
 * @method float getBeginTime(): float 获取应用开启时间
 * @method integer getBeginMem(): int 获取应用初始内存占用
 * @method \think\App initialize() 初始化应用
 * @method bool initialized() 是否初始化过
 * @method void loadLangPack(string $langset) 加载语言包
 * @method void boot(): void 引导应用
 * @method void loadEvent(array $event): void 注册应用事件
 * @method string parseClass(string $layer, string $name): string 解析应用类的类名
 * @method bool runningInConsole() 是否运行在命令行下
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
