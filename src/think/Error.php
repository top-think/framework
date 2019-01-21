<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://zjzit.cn>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think;

use think\console\Output as ConsoleOutput;
use think\exception\ErrorException;
use think\exception\Handle;
use Throwable;

class Error
{
    /**
     * 异常处理类
     * @var string
     */
    protected static $exceptionHandler;

    /**
     * 注册异常处理
     * @access public
     * @return void
     */
    public static function register()
    {
        error_reporting(E_ALL);
        set_error_handler([__CLASS__, 'appError']);
        set_exception_handler([__CLASS__, 'appException']);
        register_shutdown_function([__CLASS__, 'appShutdown']);
    }

    /**
     * Exception Handler
     * @access public
     * @param  \Throwable $e
     */
    public static function appException(Throwable $e): void
    {
        $handler = self::getExceptionHandler();

        $handler->report($e);

        if (PHP_SAPI == 'cli') {
            $handler->renderForConsole(new ConsoleOutput, $e);
        } else {
            $handler->render($e)->send();
        }
    }

    /**
     * Error Handler
     * @access public
     * @param  integer $errno   错误编号
     * @param  integer $errstr  详细错误信息
     * @param  string  $errfile 出错的文件
     * @param  integer $errline 出错行号
     * @throws ErrorException
     */
    public static function appError(int $errno, string $errstr, string $errfile = '', int $errline = 0): void
    {
        $exception = new ErrorException($errno, $errstr, $errfile, $errline);

        if (error_reporting() & $errno) {
            // 将错误信息托管至 think\exception\ErrorException
            throw $exception;
        }

        self::getExceptionHandler()->report($exception);
    }

    /**
     * Shutdown Handler
     * @access public
     */
    public static function appShutdown(): void
    {
        if (!is_null($error = error_get_last()) && self::isFatal($error['type'])) {
            // 将错误信息托管至think\ErrorException
            $exception = new ErrorException($error['type'], $error['message'], $error['file'], $error['line']);

            self::appException($exception);
        }

        // 写入日志
        Container::pull('think\Log')->save();
    }

    /**
     * 确定错误类型是否致命
     *
     * @access protected
     * @param  int $type
     * @return bool
     */
    protected static function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    /**
     * 设置异常处理类
     *
     * @access public
     * @param  mixed $handle
     * @return void
     */
    public static function setExceptionHandler($handle): void
    {
        self::$exceptionHandler = $handle;
    }

    /**
     * Get an instance of the exception handler.
     *
     * @access public
     * @return Handle
     */
    public static function getExceptionHandler()
    {
        static $handle;

        if (!$handle) {
            // 异常处理handle
            $class = self::$exceptionHandler;

            if ($class && is_string($class) && class_exists($class) && is_subclass_of($class, "\\think\\exception\\Handle")) {
                $handle = new $class;
            } else {
                $handle = new Handle;
                if ($class instanceof \Closure) {
                    $handle->setRender($class);
                }
            }
        }

        return $handle;
    }
}
