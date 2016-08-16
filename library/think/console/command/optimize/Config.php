<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
namespace think\console\command\optimize;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class Config extends Command
{
    /** @var  Output */
    protected $output;

    protected function configure()
    {
        $this->setName('optimize:config')
            ->addOption('module', null, Option::VALUE_REQUIRED, 'Build module config cache .')
            ->setDescription('Build config and common file cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->hasOption('module')) {
            $module = $input->getOption('module') . DS;
        } else {
            $module = '';
        }

        $content = '<?php ' . PHP_EOL . $this->buildCacheContent($module);

        if (!is_dir(RUNTIME_PATH . $module)) {
            @mkdir(RUNTIME_PATH . $module, 0755, true);
        }

        file_put_contents(RUNTIME_PATH . $module . 'init' . EXT, $content);

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildCacheContent($module)
    {
        $content = '';
        $path    = realpath(APP_PATH . $module) . DS;
        // 加载模块配置
        $config = \think\Config::load(CONF_PATH . $module . 'config' . CONF_EXT);

        // 加载应用状态配置
        if ($module && $config['app_status']) {
            $config = \think\Config::load(CONF_PATH . $module . $config['app_status'] . CONF_EXT);
        }

        // 读取扩展配置文件
        if ($module && $config['extra_config_list']) {
            foreach ($config['extra_config_list'] as $name => $file) {
                $filename = CONF_PATH . $module . $file . CONF_EXT;
                \think\Config::load($filename, is_string($name) ? $name : pathinfo($filename, PATHINFO_FILENAME));
            }
        }

        // 加载别名文件
        if (is_file(CONF_PATH . $module . 'alias' . EXT)) {
            $content .= '\think\Loader::addClassMap(' . (var_export(include CONF_PATH . $module . 'alias' . EXT, true)) . ');' . PHP_EOL;
        }

        // 加载行为扩展文件
        if (is_file(CONF_PATH . $module . 'tags' . EXT)) {
            $content .= '\think\Hook::import(' . (var_export(include CONF_PATH . $module . 'tags' . EXT, true)) . ');' . PHP_EOL;
        }

        // 加载公共文件
        if (is_file($path . 'common' . EXT)) {
            $content .= substr(file_get_contents($path . 'common' . EXT), 5) . PHP_EOL;
        }

        $content .= '\think\Config::set(' . var_export(\think\Config::get(), true) . ');';
        return $content;
    }
}
