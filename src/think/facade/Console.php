<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\facade;

use think\Facade;
use think\console\Command;
use think\console\Input;
use think\console\input\Definition as InputDefinition;
use think\console\Output;
use think\console\output\driver\Buffer;

/**
 * Class Console
 * @package think\facade
 * @mixin \think\Console
 * @method Output|Buffer call(string $command, array $parameters = [], string $driver = 'buffer')
 * @method int run() 执行当前的指令
 * @method int doRun(Input $input, Output $output) 执行指令
 * @method void setDefinition(InputDefinition $definition): void 设置输入参数定义
 * @method InputDefinition The InputDefinition instance getDefinition(): InputDefinition 获取输入参数定义
 * @method string A help message. getHelp(): string Gets the help message.
 * @method void setCatchExceptions(bool $boolean): void 是否捕获异常
 * @method void setAutoExit(bool $boolean): void 是否自动退出
 * @method string getLongVersion(): string 获取完整的版本号
 * @method void addCommands(array $commands): void 添加指令集
 * @method Command|void addCommand(string|Command $command, string $name = '') 添加一个指令
 * @method Command getCommand(string $name): Command 获取指令
 * @method bool hasCommand(string $name): bool 某个指令是否存在
 * @method array getNamespaces(): array 获取所有的命名空间
 * @method string findNamespace(string $namespace): string 查找注册命名空间中的名称或缩写。
 * @method Command find(string $name): Command 查找指令
 * @method Command[] all(string $namespace = null): array 获取所有的指令
 * @method string extractNamespace(string $name, int $limit = 0): string 返回命名空间部分
 */
class Console extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'console';
    }
}
