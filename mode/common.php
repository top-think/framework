<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/**
 * ThinkPHP 普通模式定义
 */
return [
    // 命名空间
    'namespace' => [
        'think'       => LIB_PATH . 'think' . DS,
        'behavior'    => LIB_PATH . 'behavior' . DS,
        'traits'      => LIB_PATH . 'traits' . DS,
        APP_NAMESPACE => APP_PATH,
    ],

    // 配置文件
    'config'    => THINK_PATH . 'convention' . EXT,

    // 别名定义
    'alias'     => [
        'think\App'                             => CORE_PATH . 'App' . EXT,
        'think\Build'                           => CORE_PATH . 'Build' . EXT,
        'think\Cache'                           => CORE_PATH . 'Cache' . EXT,
        'think\Config'                          => CORE_PATH . 'Config' . EXT,
        'think\Console'                         => CORE_PATH . 'Console' . EXT,
        'think\Controller'                      => CORE_PATH . 'Controller' . EXT,
        'think\Cookie'                          => CORE_PATH . 'Cookie' . EXT,
        'think\Db'                              => CORE_PATH . 'Db' . EXT,
        'think\Debug'                           => CORE_PATH . 'Debug' . EXT,
        'think\Error'                           => CORE_PATH . 'Error' . EXT,
        'think\Exception'                       => CORE_PATH . 'Exception' . EXT,
        'think\exception\DbException'           => CORE_PATH . 'exception' . DS . 'DbException' . EXT,
        'think\exception\Handle'                => CORE_PATH . 'exception' . DS . 'Handle' . EXT,
        'think\exception\HttpException'         => CORE_PATH . 'exception' . DS . 'HttpException' . EXT,
        'think\exception\HttpResponseException' => CORE_PATH . 'exception' . DS . 'HttpResponseException' . EXT,
        'think\exception\PDOException'          => CORE_PATH . 'exception' . DS . 'PDOException' . EXT,
        'think\exception\ErrorException'        => CORE_PATH . 'exception' . DS . 'ErrorException' . EXT,
        'think\exception\DbBindParamException'  => CORE_PATH . 'exception' . DS . 'DbBindParamException' . EXT,
        'think\exception\ThrowableError'        => CORE_PATH . 'exception' . DS . 'ThrowableError' . EXT,
        'think\File'                            => CORE_PATH . 'File' . EXT,
        'think\Hook'                            => CORE_PATH . 'Hook' . EXT,
        'think\Input'                           => CORE_PATH . 'Input' . EXT,
        'think\Lang'                            => CORE_PATH . 'Lang' . EXT,
        'think\Log'                             => CORE_PATH . 'Log' . EXT,
        'think\Model'                           => CORE_PATH . 'Model' . EXT,
        'think\model\Relation'                  => CORE_PATH . 'model' . DS . 'Relation' . EXT,
        'think\model\Merge'                     => CORE_PATH . 'model' . DS . 'Merge' . EXT,
        'think\model\Pivot'                     => CORE_PATH . 'model' . DS . 'Pivot' . EXT,
        'think\Response'                        => CORE_PATH . 'Response' . EXT,
        'think\Process'                         => CORE_PATH . 'Process' . EXT,
        'think\Route'                           => CORE_PATH . 'Route' . EXT,
        'think\Session'                         => CORE_PATH . 'Session' . EXT,
        'think\Template'                        => CORE_PATH . 'Template' . EXT,
        'think\Url'                             => CORE_PATH . 'Url' . EXT,
        'think\Validate'                        => CORE_PATH . 'Validate' . EXT,
        'think\View'                            => CORE_PATH . 'View' . EXT,
        'think\db\Connection'                   => CORE_PATH . 'db' . DS . 'Connection' . EXT,
        'think\db\connector\Mysql'              => CORE_PATH . 'db' . DS . 'connector' . DS . 'Mysql' . EXT,
        'think\db\Builder'                      => CORE_PATH . 'db' . DS . 'Builder' . EXT,
        'think\db\Builder\Mysql'                => CORE_PATH . 'db' . DS . 'builder' . DS . 'Mysql' . EXT,
        'think\db\Query'                        => CORE_PATH . 'db' . DS . 'Query' . EXT,
        'think\view\driver\Think'               => CORE_PATH . 'view' . DS . 'driver' . DS . 'Think' . EXT,
        'think\view\driver\Php'                 => CORE_PATH . 'view' . DS . 'driver' . DS . 'Php' . EXT,
        'think\template\driver\File'            => CORE_PATH . 'template' . DS . 'driver' . DS . 'File' . EXT,
        'think\log\driver\File'                 => CORE_PATH . 'log' . DS . 'driver' . DS . 'File' . EXT,
        'think\cache\driver\File'               => CORE_PATH . 'cache' . DS . 'driver' . DS . 'File' . EXT,
    ],
];
