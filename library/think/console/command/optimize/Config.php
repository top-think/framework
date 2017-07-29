<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2017 http://thinkphp.cn All rights reserved.
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
use think\Container;
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
        $content = '// This cache file is automatically generated at:' . date('Y-m-d H:i:s') . PHP_EOL;
        $path    = realpath(App::getAppPath() . $module) . DIRECTORY_SEPARATOR;
        if ($module) {
            $configPath = is_dir($path . 'config') ? $path . 'config' : App::getConfigPath() . $module;
        } else {
            $configPath = App::getConfigPath();
        }
        $ext    = App::getConfigExt();
        $config = Container::get('config');

        $files = scandir($configPath);
        foreach ($files as $file) {
            if ('.' . pathinfo($file, PATHINFO_EXTENSION) === $ext) {
                $filename = $configPath . DIRECTORY_SEPARATOR . $file;
                $config->load($filename, pathinfo($file, PATHINFO_FILENAME));
            }
        }

        // 加载行为扩展文件
        if (is_file($path . 'tags.php')) {
            $content .= PHP_EOL . '\think\facade\Hook::import(' . (var_export(include $path . 'tags.php' ?: [], true)) . ');' . PHP_EOL;
        }

        // 加载公共文件
        if (is_file($path . 'common.php')) {
            $common = substr(php_strip_whitespace($path . 'common.php'), 6);
            if ($common) {
                $content .= PHP_EOL . $common . PHP_EOL;
            }
        }

        if (is_file($path . 'provider.php')) {
            $content .= PHP_EOL . '\think\Container::getInstance()->bind(' . var_export(include $path . 'provider.php' ?: [], true) . ');' . PHP_EOL;
        }

        $content .= PHP_EOL . '\think\facade\Config::set(' . var_export($config->get(), true) . ');' . PHP_EOL;

        return $content;
    }
}
