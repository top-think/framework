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
        'think\cache\driver\Apc'                => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Apc' . EXT,
        'think\cache\driver\File'               => CORE_PATH . 'cache' . DS . 'driver' . DS . 'File' . EXT,
        'think\cache\driver\Lite'               => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Lite' . EXT,
        'think\cache\driver\Memcache'           => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Memcache' . EXT,
        'think\cache\driver\Memcached'          => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Memcached' . EXT,
        'think\cache\driver\Redis'              => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Redis' . EXT,
        'think\cache\driver\Secache'            => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Secache' . EXT,
        'think\cache\driver\Sqlite'             => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Sqlite' . EXT,
        'think\cache\driver\Wincache'           => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Wincache' . EXT,
        'think\cache\driver\Xcache'             => CORE_PATH . 'cache' . DS . 'driver' . DS . 'Xcache' . EXT,
        'think\Config'                          => CORE_PATH . 'Config' . EXT,
        'think\config\Ini'                      => CORE_PATH . 'config' . DS . 'driver' . DS . 'Ini' . EXT,
        'think\config\Json'                     => CORE_PATH . 'config' . DS . 'driver' . DS . 'Json' . EXT,
        'think\config\Xml'                      => CORE_PATH . 'config' . DS . 'driver' . DS . 'Xml' . EXT,
        'think\Console'                         => CORE_PATH . 'Console' . EXT,
        'think\Collection'                      => CORE_PATH . 'Collection' . EXT,
        'think\Controller'                      => CORE_PATH . 'Controller' . EXT,
        'think\controller\Hprose'               => CORE_PATH . 'controller' . DS . 'Hprose' . EXT,
        'think\controller\Jsonrpc'              => CORE_PATH . 'controller' . DS . 'Jsonrpc' . EXT,
        'think\controller\Rest'                 => CORE_PATH . 'controller' . DS . 'Rest' . EXT,
        'think\controller\Rpc'                  => CORE_PATH . 'controller' . DS . 'Rpc' . EXT,
        'think\controller\Yar'                  => CORE_PATH . 'controller' . DS . 'Yar' . EXT,
        'think\Cookie'                          => CORE_PATH . 'Cookie' . EXT,
        'think\Db'                              => CORE_PATH . 'Db' . EXT,
        'think\db\Connection'                   => CORE_PATH . 'db' . DS . 'Connection' . EXT,
        'think\db\connector\Mysql'              => CORE_PATH . 'db' . DS . 'connector' . DS . 'Mysql' . EXT,
        'think\db\connector\Oracle'             => CORE_PATH . 'db' . DS . 'connector' . DS . 'Oracle' . EXT,
        'think\db\connector\Pgsql'              => CORE_PATH . 'db' . DS . 'connector' . DS . 'Pgsql' . EXT,
        'think\db\connector\Sqlite'             => CORE_PATH . 'db' . DS . 'connector' . DS . 'Sqlite' . EXT,
        'think\db\connector\Sqlsrv'             => CORE_PATH . 'db' . DS . 'connector' . DS . 'Sqlsrv' . EXT,
        'think\db\Builder'                      => CORE_PATH . 'db' . DS . 'Builder' . EXT,
        'think\db\builder\Mysql'                => CORE_PATH . 'db' . DS . 'builder' . DS . 'Mysql' . EXT,
        'think\db\builder\Oracle'               => CORE_PATH . 'db' . DS . 'builder' . DS . 'Oracle' . EXT,
        'think\db\builder\Pgsql'                => CORE_PATH . 'db' . DS . 'builder' . DS . 'Pgsql' . EXT,
        'think\db\builder\Sqlite'               => CORE_PATH . 'db' . DS . 'builder' . DS . 'Sqlite' . EXT,
        'think\db\builder\Sqlsrv'               => CORE_PATH . 'db' . DS . 'builder' . DS . 'Sqlsrv' . EXT,
        'think\db\Query'                        => CORE_PATH . 'db' . DS . 'Query' . EXT,
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
        'think\log\driver\File'                 => CORE_PATH . 'log' . DS . 'driver' . DS . 'File' . EXT,
        'think\log\driver\Socket'               => CORE_PATH . 'log' . DS . 'driver' . DS . 'Socket' . EXT,
        'think\log\driver\Trace'                => CORE_PATH . 'log' . DS . 'driver' . DS . 'Trace' . EXT,
        'think\Model'                           => CORE_PATH . 'Model' . EXT,
        'think\model\Relation'                  => CORE_PATH . 'model' . DS . 'Relation' . EXT,
        'think\model\Merge'                     => CORE_PATH . 'model' . DS . 'Merge' . EXT,
        'think\model\Pivot'                     => CORE_PATH . 'model' . DS . 'Pivot' . EXT,
        'think\Request'                         => CORE_PATH . 'Request' . EXT,
        'think\Response'                        => CORE_PATH . 'Response' . EXT,
        'think\response\Json'                   => CORE_PATH . 'response' . DS . 'Json' . EXT,
        'think\response\Jsonp'                  => CORE_PATH . 'response' . DS . 'Jsonp' . EXT,
        'think\response\Redirect'               => CORE_PATH . 'response' . DS . 'Redirect' . EXT,
        'think\response\View'                   => CORE_PATH . 'response' . DS . 'View' . EXT,
        'think\response\Xml'                    => CORE_PATH . 'response' . DS . 'Xml' . EXT,
        'think\Process'                         => CORE_PATH . 'Process' . EXT,
        'think\paginator\Collection'            => CORE_PATH . 'paginator' . DS . 'Collection' . EXT,
        'think\paginator\driver\Bootstrap'      => CORE_PATH . 'paginator' . DS . 'driver' . DS . 'Bootstrap' . EXT,
        'think\Route'                           => CORE_PATH . 'Route' . EXT,
        'think\Session'                         => CORE_PATH . 'Session' . EXT,
        'think\session\driver\Memcache'         => CORE_PATH . 'session' . DS . 'driver' . DS . 'Memcache' . EXT,
        'think\session\driver\Memcached'        => CORE_PATH . 'session' . DS . 'driver' . DS . 'Memcached' . EXT,
        'think\session\driver\Redis'            => CORE_PATH . 'session' . DS . 'driver' . DS . 'Redis' . EXT,
        'think\Template'                        => CORE_PATH . 'Template' . EXT,
        'think\template\Taglib'                 => CORE_PATH . 'template' . DS . 'Taglib' . EXT,
        'think\template\taglib\Cx'              => CORE_PATH . 'template' . DS . 'taglib' . DS . 'Cx' . EXT,
        'think\template\driver\File'            => CORE_PATH . 'template' . DS . 'driver' . DS . 'File' . EXT,
        'think\Url'                             => CORE_PATH . 'Url' . EXT,
        'think\Validate'                        => CORE_PATH . 'Validate' . EXT,
        'think\View'                            => CORE_PATH . 'View' . EXT,
        'think\view\driver\Think'               => CORE_PATH . 'view' . DS . 'driver' . DS . 'Think' . EXT,
        'think\view\driver\Php'                 => CORE_PATH . 'view' . DS . 'driver' . DS . 'Php' . EXT,
        'traits\controller\Jump'                => TRAIT_PATH . 'controller' . DS . 'Jump' . EXT,
    ],
];
