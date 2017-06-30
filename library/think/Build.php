<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

class Build
{
    protected $app;
    protected $basePath;

    public function __construct(App $app)
    {
        $this->app      = $app;
        $this->basePath = $this->app->getAppPath();
    }

    /**
     * 根据传入的build资料创建目录和文件
     * @access protected
     * @param  array  $build build列表
     * @param  string $namespace 应用类库命名空间
     * @param  bool   $suffix 类库后缀
     * @return void
     */
    public function run(array $build = [], $namespace = 'app', $suffix = false)
    {
        // 锁定
        $lockfile = $this->basePath . 'build.lock';

        if (is_writable($lockfile)) {
            return;
        } elseif (!touch($lockfile)) {
            throw new Exception('应用目录[' . $this->basePath . ']不可写，目录无法自动生成！<BR>请手动生成项目目录~', 10006);
        }

        foreach ($build as $module => $list) {
            if ('__dir__' == $module) {
                // 创建目录列表
                $this->buildDir($list);
            } elseif ('__file__' == $module) {
                // 创建文件列表
                $this->buildFile($list);
            } else {
                // 创建模块
                $this->module($module, $list, $namespace, $suffix);
            }
        }

        // 解除锁定
        unlink($lockfile);
    }

    /**
     * 创建目录
     * @access protected
     * @param  array $list 目录列表
     * @return void
     */
    protected function buildDir($list)
    {
        foreach ($list as $dir) {
            $this->checkDirBuild($this->basePath . $dir);
        }
    }

    /**
     * 创建文件
     * @access protected
     * @param  array $list 文件列表
     * @return void
     */
    protected function buildFile($list)
    {
        foreach ($list as $file) {
            if (!is_dir($this->basePath . dirname($file))) {
                // 创建目录
                mkdir($this->basePath . dirname($file), 0755, true);
            }

            if (!is_file($this->basePath . $file)) {
                file_put_contents($this->basePath . $file, 'php' == pathinfo($file, PATHINFO_EXTENSION) ? "<?php\n" : '');
            }
        }
    }

    /**
     * 创建模块
     * @access public
     * @param  string $module 模块名
     * @param  array  $list build列表
     * @param  string $namespace 应用类库命名空间
     * @param  bool   $suffix 类库后缀
     * @return void
     */
    public function module($module = '', $list = [], $namespace = 'app', $suffix = false)
    {
        $module = $module ? $module : '';

        if (!is_dir($this->basePath . $module)) {
            // 创建模块目录
            mkdir($this->basePath . $module);
        }

        if (basename($this->app->getRuntimePath()) != $module) {
            // 创建配置文件和公共文件
            $this->buildCommon($module);
            // 创建模块的默认页面
            $this->buildHello($module, $namespace, $suffix);
        }

        if (empty($list)) {
            // 创建默认的模块目录和文件
            $list = [
                '__file__' => ['common.php'],
                '__dir__'  => ['controller', 'model', 'view', 'config'],
            ];
        }

        // 创建子目录和文件
        foreach ($list as $path => $file) {
            $modulePath = $this->basePath . $module . '/';
            if ('__dir__' == $path) {
                // 生成子目录
                foreach ($file as $dir) {
                    $this->checkDirBuild($modulePath . $dir);
                }
            } elseif ('__file__' == $path) {
                // 生成（空白）文件
                foreach ($file as $name) {
                    if (!is_file($modulePath . $name)) {
                        file_put_contents($modulePath . $name, 'php' == pathinfo($name, PATHINFO_EXTENSION) ? "<?php\n" : '');
                    }
                }
            } else {
                // 生成相关MVC文件
                foreach ($file as $val) {
                    $val      = trim($val);
                    $filename = $modulePath . $path . DIRECTORY_SEPARATOR . $val . ($suffix ? ucfirst($path) : '') . '.php';
                    $space    = $namespace . '\\' . ($module ? $module . '\\' : '') . $path;
                    $class    = $val . ($suffix ? ucfirst($path) : '');
                    switch ($path) {
                        case 'controller': // 控制器
                            $content = "<?php\nnamespace {$space};\n\nclass {$class}\n{\n\n}";
                            break;
                        case 'model': // 模型
                            $content = "<?php\nnamespace {$space};\n\nuse think\Model;\n\nclass {$class} extends Model\n{\n\n}";
                            break;
                        case 'view': // 视图
                            $filename = $modulePath . $path . DIRECTORY_SEPARATOR . $val . '.html';
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
     * 创建模块的欢迎页面
     * @access public
     * @param  string $module 模块名
     * @param  string $namespace 应用类库命名空间
     * @param  bool   $suffix 类库后缀
     * @return void
     */
    protected function buildHello($module, $namespace, $suffix = false)
    {
        $filename = $this->basePath . ($module ? $module . DIRECTORY_SEPARATOR : '') . 'controller' . DIRECTORY_SEPARATOR . 'Index' . ($suffix ? 'Controller' : '') . '.php';
        if (!is_file($filename)) {
            $content = file_get_contents($this->app->getThinkPath() . 'tpl' . DIRECTORY_SEPARATOR . 'default_index.tpl');
            $content = str_replace(['{$app}', '{$module}', '{layer}', '{$suffix}'], [$namespace, $module ? $module . '\\' : '', 'controller', $suffix ? 'Controller' : ''], $content);
            $this->checkDirBuild(dirname($filename));

            file_put_contents($filename, $content);
        }
    }

    /**
     * 创建模块的公共文件
     * @access public
     * @param  string $module 模块名
     * @return void
     */
    protected function buildCommon($module)
    {
        $filename = $this->app->getConfigPath() . ($module ? $module . DIRECTORY_SEPARATOR : '') . 'config.php';
        $this->checkDirBuild(dirname($filename));

        if (!is_file($filename)) {
            file_put_contents($filename, "<?php\n//配置文件\nreturn [\n\n];");
        }

        $filename = $this->basePath . ($module ? $module . DIRECTORY_SEPARATOR : '') . 'common.php';

        if (!is_file($filename)) {
            file_put_contents($filename, "<?php\n");
        }
    }

    protected function checkDirBuild($dirname)
    {
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
    }
}
