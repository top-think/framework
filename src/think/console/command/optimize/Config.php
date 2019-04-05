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

class Config extends Command
{
    protected function configure()
    {
        $this->setName('optimize:config')
            ->addArgument('app', Argument::OPTIONAL, 'app name .')
            ->setDescription('Build config and common file cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        $app = $input->getArgument('app');

        if (empty($app) && !is_dir($this->app->getBasePath() . 'controller')) {
            $output->writeln('<error>Miss app name!</error>');
            return false;
        }

        $runtimePath = $this->app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . ($app ? $app . DIRECTORY_SEPARATOR : '');

        $content = '<?php ' . PHP_EOL . $this->buildCacheContent($input->getArgument('app') ?: '');

        if (!is_dir($runtimePath)) {
            mkdir($runtimePath, 0755, true);
        }

        file_put_contents($runtimePath . 'init.php', $content);

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildCacheContent(string $app): string
    {
        $header  = '// This cache file is automatically generated at:' . date('Y-m-d H:i:s') . PHP_EOL . 'declare (strict_types = 1);' . PHP_EOL;
        $content = '';

        if ($app) {
            $path = $this->app->getBasePath() . $app . DIRECTORY_SEPARATOR;
        } else {
            $path = $this->app->getAppPath();
        }

        $configPath = $this->app->getConfigPath();
        $configExt  = $this->app->getConfigExt();

        // 加载公共文件
        if ($app && is_file($this->app->getBasePath() . 'common.php')) {
            $common = substr(php_strip_whitespace($this->app->getBasePath() . 'common.php'), 6);
            if ($common) {
                $content .= PHP_EOL . $common . PHP_EOL;
            }
        }

        if (is_file($path . 'common.php')) {
            $common = substr(php_strip_whitespace($path . 'common.php'), 6);
            if ($common) {
                $content .= PHP_EOL . $common . PHP_EOL;
            }
        }

        $content .= PHP_EOL . substr(php_strip_whitespace($this->app->getThinkPath() . 'helper.php'), 6) . PHP_EOL;

        // 加载应用配置文件
        $files = [];

        if (is_dir($configPath)) {
            $files = glob($configPath . '*' . $configExt);
        }

        if ($app) {
            if (is_dir($path . 'config')) {
                $files = array_merge($files, glob($path . 'config' . DIRECTORY_SEPARATOR . '*' . $configExt));
            } elseif (is_dir($configPath . $app)) {
                $files = array_merge($files, glob($configPath . $app . DIRECTORY_SEPARATOR . '*' . $configExt));
            }
        }

        foreach ($files as $file) {
            $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }

        $content .= PHP_EOL . '\think\facade\Config::set(\think\App::unserialize(\'' . addslashes(\think\App::serialize($this->app->config->get())) . '\'));' . PHP_EOL;

        // 加载事件定义文件
        if (is_file($path . 'event.php')) {
            $event = include $path . 'event.php';

            if (is_array($event)) {
                if (isset($event['bind'])) {
                    $content .= PHP_EOL . '\think\facade\Event::bind(' . (var_export($event['bind'], true)) . ');' . PHP_EOL;
                }

                if (isset($event['listen'])) {
                    $content .= PHP_EOL . '\think\facade\Event::listenEvents(' . (var_export($event['listen'], true)) . ');' . PHP_EOL;
                }

                if (isset($event['subscribe'])) {
                    foreach ($event['subscribe'] as $subscribe) {
                        $content .= PHP_EOL . '\think\facade\Event::observe(\'' . $subscribe . '\');' . PHP_EOL;
                    }
                }
            }
        }

        if (is_file($path . 'middleware.php')) {
            $middleware = include $path . 'middleware.php';
            if (is_array($middleware)) {
                $content .= PHP_EOL . '\think\Container::pull("middleware")->import(' . var_export($middleware, true) . ');' . PHP_EOL;
            }
        }

        if (is_file($path . 'provider.php')) {
            $provider = include $path . 'provider.php';
            if (is_array($provider)) {
                $content .= PHP_EOL . '\think\Container::getInstance()->bind(' . var_export($provider, true) . ');' . PHP_EOL;
            }
        }

        return $header . preg_replace('/declare\s?\(\s?strict_types\s?=\s?1\s?\)\s?\;/i', '', $content);
    }
}
