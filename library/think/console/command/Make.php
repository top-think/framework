<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 刘志淳 <chun@engineer.com>
// +----------------------------------------------------------------------

namespace think\console\command;

class Make extends Command
{
    // 创建目录
    protected static function buildDir($dir)
    {
        if (!is_dir(APP_PATH . $dir)) {
            mkdir(APP_PATH . strtolower($dir), 0777, true);
        }
    }

    // 创建文件
    protected static function buildFile($file, $content)
    {
        if (is_file(APP_PATH . $file)) {
            exception('file already exists');
        }
        file_put_contents(APP_PATH . $file, $content);
    }

    protected static function formatNameSpace($namespace)
    {
        $namespace = explode('\\', $namespace);

        foreach ($namespace as $key => $value) {
            if ($key == count($namespace) - 1) {
                $newNameSpace[1] = $value;
            } else {
                $newNameSpace[0][$key] = $value;
            }
        }

        return $newNameSpace;
    }
}
