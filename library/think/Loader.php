<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\App;
use think\exception\ClassNotFoundException;
use think\Request;

class Loader
{
    protected static $instance = [];
    // 类名映射
    protected static $map = [];
    // 加载列表
    protected static $load = [];
    // 命名空间
    protected static $namespace = [];
    // 命名空间别名
    protected static $namespaceAlias = [];
    // PSR-4
    private static $prefixLengthsPsr4 = [];
    private static $prefixDirsPsr4    = [];
    // PSR-0
    private static $prefixesPsr0 = [];
    // Composer自动加载
    private static $composerLoader = false;

    // 自动加载
    public static function autoload($class)
    {
        // 检测命名空间别名
        if (!empty(self::$namespaceAlias)) {
            $namespace = dirname($class);
            if (isset(self::$namespaceAlias[$namespace])) {
                $original = self::$namespaceAlias[$namespace] . '\\' . basename($class);
                if (class_exists($original)) {
                    return class_alias($original, $class, false);
                }
            }
        }

        if (!empty(self::$map[$class])) {
            // 类库映射
            include self::$map[$class];
        } elseif (self::$composerLoader && $file = self::findFileInComposer($class)) {
            // Composer自动加载
            include $file;
        } else {
            // 命名空间自动加载
            if (!strpos($class, '\\')) {
                return false;
            }
            $item = explode('\\', $class);
            // 解析命名空间
            if (count($item) > 2 && isset(self::$namespace[$item[0] . '\\' . $item[1]])) {
                // 子命名空间定义（仅支持二级）
                list($ns1, $ns2, $class) = explode('\\', $class, 3);
                $path                    = self::$namespace[$ns1 . '\\' . $ns2];
            } elseif (isset(self::$namespace[$item[0]])) {
                // 根命名空间定义
                list($name, $class) = explode('\\', $class, 2);
                $path               = self::$namespace[$name];
            } elseif (is_dir(EXTEND_PATH . $item[0])) {
                // 扩展类库命名空间
                list($name, $class) = explode('\\', $class, 2);
                $path               = EXTEND_PATH . $name . DS;
            } else {
                return false;
            }
            $filename = $path . str_replace('\\', DS, $class) . EXT;
            if (is_file($filename)) {
                // 开启调试模式Win环境严格区分大小写
                if (IS_WIN && false === strpos(realpath($filename), $class . EXT)) {
                    return false;
                }
                include $filename;
            } else {
                return false;
            }
        }
        return true;
    }

    // 注册classmap
    public static function addMap($class, $map = '')
    {
        if (is_array($class)) {
            self::$map = array_merge(self::$map, $class);
        } else {
            self::$map[$class] = $map;
        }
    }

    // 注册命名空间
    public static function addNamespace($namespace, $path = '')
    {
        if (is_array($namespace)) {
            self::$namespace = array_merge(self::$namespace, $namespace);
        } else {
            self::$namespace[$namespace] = $path;
        }
    }

    // 注册命名空间别名
    public static function addNamespaceAlias($namespace, $original = '')
    {
        if (is_array($namespace)) {
            self::$namespaceAlias = array_merge(self::$namespace, $namespace);
        } else {
            self::$namespaceAlias[$namespace] = $original;
        }
    }

    // 注册自动加载机制
    public static function register($autoload = '')
    {
        // 注册系统自动加载
        spl_autoload_register($autoload ?: 'think\\Loader::autoload');

        if (is_dir(VENDOR_PATH . 'composer')) {
            // 注册Composer自动加载
            self::registerComposerLoader();
            self::$composerLoader = true;
        } elseif (is_file(VENDOR_PATH . 'think_autoload.php')) {
            // 读取Composer自动加载文件
            $autoload = include VENDOR_PATH . 'think_autoload.php';
            if (is_array($autoload)) {
                self::addMap($autoload);
            }
        } elseif (is_file(RUNTIME_PATH . 'autoload_composer.php')) {
            $autoload = include RUNTIME_PATH . 'autoload_composer.php';
            if (is_array($autoload)) {
                self::addNamespace($autoload);
            }
        } elseif (AUTO_SCAN_PACKAGE && is_dir(VENDOR_PATH)) {
            self::scanComposerPackage(VENDOR_PATH);
        }
    }

    // 扫描composer package
    private static function scanComposerPackage($path)
    {
        // 自动扫描下载Composer安装类库
        $dirs = scandir($path, 1);
        foreach ($dirs as $dir) {
            if ('.' != $dir && '..' != $dir && is_dir($path . $dir) && is_file($path . $dir . DS . 'composer.json')) {
                // 解析Composer 包
                self::parseComposerPackage($path . $dir . DS);
            }
        }

        $content = "<?php " . PHP_EOL;
        if (!empty(self::$load)) {
            foreach (self::$load as $file) {
                $content .= 'include ' . $file . ';' . PHP_EOL;
            }
        }

        if (!empty(self::$namespace)) {
            $content .= "return " . var_export(self::$namespace, true) . ';' . PHP_EOL;
        }
        // 生成缓存
        file_put_contents(RUNTIME_PATH . 'autoload_composer.php', $content);

    }

    // 解析Composer Package
    private static function parseComposerPackage($package)
    {
        $content = file_get_contents($package . 'composer.json');
        $result  = json_decode($content, true);

        if (!empty($result['autoload'])) {
            $autoload = $result['autoload'];
            if (isset($autoload['psr-0'])) {
                foreach ($autoload['psr-0'] as $ns => $path) {
                    self::$namespace[rtrim($ns, '\\')] = realpath($package . $path . DS . str_replace('\\', DS, $ns)) . DS;
                }
            }

            if (isset($autoload['psr-4'])) {
                foreach ($autoload['psr-4'] as $ns => $path) {
                    self::$namespace[rtrim($ns, '\\')] = realpath($package . $path) . DS;
                }
            }

            if (isset($autoload['files'])) {
                foreach ($autoload['files'] as $file) {
                    self::$load[] = realpath($package . $file);
                    require $package . $file;
                }
            }
        }
    }

    // 注册composer自动加载
    private static function registerComposerLoader()
    {
        if (is_file(VENDOR_PATH . 'composer/autoload_namespaces.php')) {
            $map = require VENDOR_PATH . 'composer/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                self::$prefixesPsr0[$namespace[0]][$namespace] = (array) $path;
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_psr4.php')) {
            $map = require VENDOR_PATH . 'composer/autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                $length = strlen($namespace);
                if ('\\' !== $namespace[$length - 1]) {
                    throw new \InvalidArgumentException("PSR-4 error: A non-empty PSR-4 prefix must end with a namespace separator.");
                }
                self::$prefixLengthsPsr4[$namespace[0]][$namespace] = $length;
                self::$prefixDirsPsr4[$namespace]                   = (array) $path;
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_classmap.php')) {
            $classMap = require VENDOR_PATH . 'composer/autoload_classmap.php';
            if ($classMap) {
                self::addMap($classMap);
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_files.php')) {
            $includeFiles = require VENDOR_PATH . 'composer/autoload_files.php';
            foreach ($includeFiles as $fileIdentifier => $file) {
                self::composerRequire($fileIdentifier, $file);
            }
        }
    }

    private static function composerRequire($fileIdentifier, $file)
    {
        if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
            require $file;
            $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
        }
    }

    private static function findFileInComposer($class, $ext = '.php')
    {
        // PSR-4 lookup
        $logicalPathPsr4 = strtr($class, '\\', DS) . $ext;

        $first = $class[0];
        if (isset(self::$prefixLengthsPsr4[$first])) {
            foreach (self::$prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach (self::$prefixDirsPsr4[$prefix] as $dir) {
                        if (file_exists($file = $dir . DS . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }
        // PSR-0 lookup
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
            . strtr(substr($logicalPathPsr4, $pos + 1), '_', DS);
        } else {
            // PEAR-like class name
            $logicalPathPsr0 = strtr($class, '_', DS) . $ext;
        }

        if (isset(self::$prefixesPsr0[$first])) {
            foreach (self::$prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (file_exists($file = $dir . DS . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }
        // Remember that this class does not exist.
        return self::$map[$class] = false;
    }

    /**
     * 导入所需的类库 同java的Import 本函数有缓存功能
     * @param string $class 类库命名空间字符串
     * @param string $baseUrl 起始路径
     * @param string $ext 导入的文件扩展名
     * @return boolean
     */
    public static function import($class, $baseUrl = '', $ext = EXT)
    {
        static $_file = [];
        $class        = str_replace(['.', '#'], [DS, '.'], $class);
        if (isset($_file[$class . $baseUrl])) {
            return true;
        }

        if (empty($baseUrl)) {
            list($name, $class) = explode(DS, $class, 2);
            if (isset(self::$namespace[$name])) {
                // 注册的命名空间
                $baseUrl = self::$namespace[$name];
            } elseif ('@' == $name) {
                //加载当前模块应用类库
                $baseUrl = App::$modulePath;
            } elseif (is_dir(EXTEND_PATH . $name)) {
                $baseUrl = EXTEND_PATH;
            } else {
                // 加载其它模块的类库
                $baseUrl = APP_PATH . $name . DS;
            }
        } elseif (substr($baseUrl, -1) != DS) {
            $baseUrl .= DS;
        }
        // 如果类存在 则导入类库文件
        $filename = $baseUrl . $class . $ext;
        if (is_file($filename)) {
            // 开启调试模式Win环境严格区分大小写
            if (IS_WIN && false === strpos(realpath($filename), $class . $ext)) {
                return false;
            }
            include $filename;
            $_file[$class . $baseUrl] = true;
            return true;
        }
        return false;
    }

    /**
     * 实例化（分层）模型
     * @param string    $name Model名称
     * @param string    $layer 业务层名称
     * @param bool      $appendSuffix 是否添加类名后缀
     * @param string    $common 公共模块名
     * @return Object
     * @throws ClassNotFoundException
     */
    public static function model($name = '', $layer = 'model', $appendSuffix = false, $common = 'common')
    {
        if (isset(self::$instance[$name . $layer])) {
            return self::$instance[$name . $layer];
        }
        if (strpos($name, '/')) {
            list($module, $name) = explode('/', $name, 2);
        } else {
            $module = Request::instance()->module();
        }
        $class = self::parseClass($module, $layer, $name, $appendSuffix);
        if (class_exists($class)) {
            $model = new $class();
        } else {
            $class = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);
            if (class_exists($class)) {
                $model = new $class();
            } else {
                throw new ClassNotFoundException('class not exists:' . $class, $class);
            }
        }
        self::$instance[$name . $layer] = $model;
        return $model;
    }

    /**
     * 实例化（分层）控制器 格式：[模块名/]控制器名
     * @param string    $name 资源地址
     * @param string    $layer 控制层名称
     * @param bool      $appendSuffix 是否添加类名后缀
     * @param string    $empty 空控制器名称
     * @return Object|false
     * @throws ClassNotFoundException
     */
    public static function controller($name, $layer = 'controller', $appendSuffix = false, $empty = '')
    {
        if (strpos($name, '/')) {
            list($module, $name) = explode('/', $name);
        } else {
            $module = Request::instance()->module();
        }
        $class = self::parseClass($module, $layer, $name, $appendSuffix);
        if (class_exists($class)) {
            return new $class(Request::instance());
        } elseif ($empty && class_exists($emptyClass = self::parseClass($module, $layer, $empty, $appendSuffix))) {
            return new $emptyClass(Request::instance());
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }

    /**
     * 实例化验证类 格式：[模块名/]验证器名
     * @param string    $name 资源地址
     * @param string    $layer 验证层名称
     * @param bool      $appendSuffix 是否添加类名后缀
     * @param string    $common 公共模块名
     * @return Object|false
     * @throws ClassNotFoundException
     */
    public static function validate($name = '', $layer = 'validate', $appendSuffix = false, $common = 'common')
    {
        $name = $name ?: Config::get('default_validate');
        if (empty($name)) {
            return new Validate;
        }

        if (isset(self::$instance[$name . $layer])) {
            return self::$instance[$name . $layer];
        }
        if (strpos($name, '/')) {
            list($module, $name) = explode('/', $name);
        } else {
            $module = Request::instance()->module();
        }
        $class = self::parseClass($module, $layer, $name, $appendSuffix);
        if (class_exists($class)) {
            $validate = new $class;
        } else {
            $class = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);
            if (class_exists($class)) {
                $validate = new $class;
            } else {
                throw new ClassNotFoundException('class not exists:' . $class, $class);
            }
        }
        self::$instance[$name . $layer] = $validate;
        return $validate;
    }

    /**
     * 实例化数据库
     * @param mixed $config 数据库配置
     * @return object
     */
    public static function db($config = [])
    {
        return Db::connect($config);
    }

    /**
     * 远程调用模块的操作方法 参数格式 [模块/控制器/]操作
     * @param string        $url 调用地址
     * @param string|array  $vars 调用参数 支持字符串和数组
     * @param string        $layer 要调用的控制层名称
     * @param bool          $appendSuffix 是否添加类名后缀
     * @return mixed
     */
    public static function action($url, $vars = [], $layer = 'controller', $appendSuffix = false)
    {
        $info   = pathinfo($url);
        $action = $info['basename'];
        $module = '.' != $info['dirname'] ? $info['dirname'] : Request::instance()->controller();
        $class  = self::controller($module, $layer, $appendSuffix);
        if ($class) {
            if (is_scalar($vars)) {
                if (strpos($vars, '=')) {
                    parse_str($vars, $vars);
                } else {
                    $vars = [$vars];
                }
            }
            return App::invokeMethod([$class, $action . Config::get('action_suffix')], $vars);
        }
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string    $name 字符串
     * @param integer   $type 转换类型
     * @return string
     */
    public static function parseName($name, $type = 0)
    {
        if ($type) {
            return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {return strtoupper($match[1]);}, $name));
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    /**
     * 解析应用类的类名
     * @param string $module 模块名
     * @param string $layer 层名 controller model ...
     * @param string $name 类名
     * @return string
     */
    public static function parseClass($module, $layer, $name, $appendSuffix = false)
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = self::parseName(array_pop($array), 1) . (App::$suffix || $appendSuffix ? ucfirst($layer) : '');
        $path  = $array ? implode('\\', $array) . '\\' : '';
        return App::$namespace . '\\' . ($module ? $module . '\\' : '') . $layer . '\\' . $path . $class;
    }

    /**
     * 初始化类的实例
     * @return void
     */
    public static function clearInstance()
    {
        self::$instance = [];
    }
}
