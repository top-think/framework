<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
namespace think\console\command\optimize;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Facade;
use think\facade\App;

class Config extends Command
{
    /** @var  Output */
    protected $output;

    protected function configure()
    {
        $this->setName('optimize:config')
            ->addArgument('module', Argument::OPTIONAL, 'Build module config cache .')
            ->setDescription('Build config and common file cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->hasArgument('module')) {
            $module = $input->getArgument('module') . DIRECTORY_SEPARATOR;
        } else {
            $module = '';
        }

        $content     = '<?php ' . PHP_EOL . $this->buildCacheContent($module);
        $runtimePath = App::getRuntimePath();
        if (!is_dir($runtimePath . $module)) {
            @mkdir($runtimePath . $module, 0755, true);
        }

        file_put_contents($runtimePath . $module . 'init.php', $content);

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildCacheContent($module)
    {
        $content    = '';
        $path       = realpath(App::getAppPath() . $module) . DIRECTORY_SEPARATOR;
        $configPath = App::getConfigPath();
        $ext        = App::getConfigExt();
        $con        = Facade::make('config');

        if ($module) {
            // 加载模块配置
            $config = $con->load($configPath . $module . 'config' . $ext);

            // 读取数据库配置文件
            $filename = $configPath . $module . 'database' . $ext;
            $con->load($filename, 'database');

            // 加载应用状态配置
            if (!empty($config['app_status'])) {
                $config = $con->load($configPath . $module . $config['app_status'] . $ext);
            }

            // 读取扩展配置文件
            if (is_dir($configPath . $module . 'extra')) {
                $dir   = $configPath . $module . 'extra';
                $files = scandir($dir);
                foreach ($files as $file) {
                    if (strpos($file, $ext)) {
                        $filename = $dir . DIRECTORY_SEPARATOR . $file;
                        $con->load($filename, pathinfo($file, PATHINFO_FILENAME));
                    }
                }
            }
        }

        // 加载行为扩展文件
        if (is_file($configPath . $module . 'tags.php')) {
            $content .= '\think\Hook::import(' . (var_export(include $configPath . $module . 'tags.php', true)) . ');' . PHP_EOL;
        }

        // 加载公共文件
        if (is_file($path . 'common.php')) {
            $content .= substr(php_strip_whitespace($path . 'common.php'), 5) . PHP_EOL;
        }

        $content .= '\think\facade\Config::set(' . var_export($con->get(), true) . ');';

        return $content;
    }
}
