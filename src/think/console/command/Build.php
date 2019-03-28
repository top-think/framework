<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\console\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class Build extends Command
{

    /**
     * 应用基础目录
     * @var string
     */
    protected $basePath;

    /**
     * 应用目录
     * @var string
     */
    protected $appPath;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('build')
            ->setDefinition([
                new Option('config', null, Option::VALUE_OPTIONAL, "build.php path"),
                new Option('app', null, Option::VALUE_OPTIONAL, "app name"),
            ])
            ->setDescription('Build Application Dirs');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->basePath = $this->app->getBasePath();
        $this->appPath  = $this->app->getAppPath();

        if ($input->hasOption('app')) {
            $this->buildApp($input->getOption('app'));
            $output->writeln("Successed");
            return;
        }

        if ($input->hasOption('config')) {
            $build = include $input->getOption('config');
        } else {
            $build = include $this->appPath . 'build.php';
        }

        if (empty($build)) {
            $output->writeln("Build Config Is Empty");
            return;
        }

        $this->build($build);
        $output->writeln("Successed");
    }

    /**
     * 根据配置文件创建应用和文件
     * @access protected
     * @param  array  $config 配置列表
     * @return void
     */
    protected function build(array $config): void
    {
        // 创建子目录和文件
        foreach ($config as $app => $list) {
            $this->buildApp(is_numeric($app) ? '' : $app, $list);
        }
    }

    /**
     * 创建应用
     * @access protected
     * @param  string $name 应用名
     * @param  array  $list 文件列表
     * @param  string $rootNamespace 应用类库命名空间
     * @return void
     */
    protected function buildApp(string $app, array $list = [], string $rootNamespace = 'app'): void
    {
        if (!is_dir($this->basePath . $app)) {
            // 创建应用目录
            mkdir($this->basePath . $app);
        }

        $appPath   = $this->basePath . ($app ? $app . DIRECTORY_SEPARATOR : '');
        $namespace = $rootNamespace . ($app ? '\\' . $app : '');

        // 创建配置文件和公共文件
        $this->buildCommon($app);
        // 创建模块的默认页面
        $this->buildHello($app, $namespace);

        foreach ($list as $path => $file) {
            if ('__dir__' == $path) {
                // 生成子目录
                foreach ($file as $dir) {
                    $this->checkDirBuild($appPath . $dir);
                }
            } elseif ('__file__' == $path) {
                // 生成（空白）文件
                foreach ($file as $name) {
                    if (!is_file($appPath . $name)) {
                        file_put_contents($appPath . $name, 'php' == pathinfo($name, PATHINFO_EXTENSION) ? "<?php\n" : '');
                    }
                }
            } else {
                // 生成相关MVC文件
                foreach ($file as $val) {
                    $val      = trim($val);
                    $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . '.php';
                    $space    = $namespace . '\\' . $path;
                    $class    = $val;
                    switch ($path) {
                        case 'controller': // 控制器
                            if ($this->app->config->get('route.controller_suffix')) {
                                $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . 'Controller.php';
                                $class    = $val . 'Controller';
                            }
                            $content = "<?php\nnamespace {$space};\n\nuse think\Controller;\n\nclass {$class} extends Controller\n{\n\n}";
                            break;
                        case 'model': // 模型
                            $content = "<?php\nnamespace {$space};\n\nuse think\Model;\n\nclass {$class} extends Model\n{\n\n}";
                            break;
                        case 'view': // 视图
                            $filename = $appPath . $path . DIRECTORY_SEPARATOR . $val . '.html';
                            $this->checkDirBuild(dirname($filename));
                            $content = '';
                            break;
                        default:
                            // 其他文件
                            $content = "<?php\nnamespace {$space};\n\nclass {$class}\n{\n\n}";
                    }

                    if (!is_file($filename)) {
                        file_put_contents($filename, $content);
                    }
                }
            }
        }
    }

    /**
     * 创建应用的欢迎页面
     * @access protected
     * @param  string $appName 应用名
     * @param  string $namespace 应用类库命名空间
     * @return void
     */
    protected function buildHello(string $appName, string $namespace): void
    {
        $suffix   = $this->app->config->get('route.controller_suffix') ? 'Controller' : '';
        $filename = $this->basePath . ($appName ? $appName . DIRECTORY_SEPARATOR : '') . 'controller' . DIRECTORY_SEPARATOR . 'Index' . $suffix . '.php';
        if (!is_file($filename)) {
            $content = file_get_contents($this->app->getThinkPath() . 'tpl' . DIRECTORY_SEPARATOR . 'default_index.tpl');
            $content = str_replace(['{$app}', '{layer}', '{$suffix}'], [$namespace, 'controller', $suffix], $content);
            $this->checkDirBuild(dirname($filename));

            file_put_contents($filename, $content);
        }
    }

    /**
     * 创建应用的公共文件
     * @access protected
     * @param  string $appName 应用名称
     * @return void
     */
    protected function buildCommon(string $appName): void
    {
        $filename = $this->basePath . ($appName ? $appName . DIRECTORY_SEPARATOR : '') . 'common.php';

        if (!is_file($filename)) {
            file_put_contents($filename, "<?php\n");
        }
    }

    /**
     * 创建目录
     * @access protected
     * @param  string $dirname 目录名称
     * @return void
     */
    protected function checkDirBuild(string $dirname): void
    {
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
    }
}
