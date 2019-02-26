<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\exception;

use Exception;
use think\console\Output;
use think\Container;
use think\Response;
use Throwable;

class Handle
{
    protected $render;
    protected $ignoreReport = [
        '\\think\\exception\\HttpException',
    ];

    public function setRender(\Closure $render)
    {
        $this->render = $render;
    }

    /**
     * Report or log an exception.
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        if (!$this->isIgnoreReport($exception)) {
            // 收集异常数据
            if (Container::pull('app')->isDebug()) {
                $data = [
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'message' => $this->getMessage($exception),
                    'code'    => $this->getCode($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";
            } else {
                $data = [
                    'code'    => $this->getCode($exception),
                    'message' => $this->getMessage($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}";
            }

            if (Container::pull('config')->get('log.record_trace')) {
                $log .= "\r\n" . $exception->getTraceAsString();
            }

            Container::pull('log')->record($log, 'error');
        }
    }

    protected function isIgnoreReport(Throwable $exception)
    {
        foreach ($this->ignoreReport as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param  Throwable $e
     * @return Response
     */
    public function render(Throwable $e)
    {
        if ($this->render && $this->render instanceof \Closure) {
            $result = call_user_func_array($this->render, [$e]);

            if ($result) {
                return $result;
            }
        }

        if ($e instanceof HttpException) {
            return $this->renderHttpException($e);
        } else {
            return $this->convertExceptionToResponse($e);
        }
    }

    /**
     * @access public
     * @param  Output    $output
     * @param  Throwable $e
     */
    public function renderForConsole(Output $output, Throwable $e)
    {
        if (Container::pull('app')->isDebug()) {
            $output->setVerbosity(Output::VERBOSITY_DEBUG);
        }

        $output->renderException($e);
    }

    /**
     * @access protected
     * @param  HttpException $e
     * @return Response
     */
    protected function renderHttpException(HttpException $e)
    {
        $status   = $e->getStatusCode();
        $template = Container::pull('config')->get('app.http_exception_template');

        if (!Container::pull('app')->isDebug() && !empty($template[$status])) {
            return Response::create($template[$status], 'view', $status)->assign(['e' => $e]);
        } else {
            return $this->convertExceptionToResponse($e);
        }
    }

    /**
     * @access protected
     * @param  Throwable $exception
     * @return Response
     */
    protected function convertExceptionToResponse(Throwable $exception)
    {
        // 收集异常数据
        if (Container::pull('app')->isDebug()) {
            // 调试模式，获取详细的错误信息
            $data = [
                'name'    => get_class($exception),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'message' => $this->getMessage($exception),
                'trace'   => $exception->getTrace(),
                'code'    => $this->getCode($exception),
                'source'  => $this->getSourceCode($exception),
                'datas'   => $this->getExtendData($exception),
                'tables'  => [
                    'GET Data'              => $_GET,
                    'POST Data'             => $_POST,
                    'Files'                 => $_FILES,
                    'Cookies'               => $_COOKIE,
                    'Session'               => $_SESSION ?? [],
                    'Server/Request Data'   => $_SERVER,
                    'Environment Variables' => $_ENV,
                    'ThinkPHP Constants'    => $this->getConst(),
                ],
            ];
        } else {
            // 部署模式仅显示 Code 和 Message
            $data = [
                'code'    => $this->getCode($exception),
                'message' => $this->getMessage($exception),
            ];

            if (!Container::pull('config')->get('app.show_error_msg')) {
                // 不显示详细错误信息
                $data['message'] = Container::pull('config')->get('app.error_message');
            }
        }

        $type = Container::pull('config')->get('app.exception_response_type') ?: 'html';

        if ('html' == $type) {
            //保留一层
            while (ob_get_level() > 1) {
                ob_end_clean();
            }

            $data['echo'] = ob_get_clean();

            ob_start();
            extract($data);
            include Container::pull('config')->get('app.exception_tmpl') ?: __DIR__ . '/../../tpl/think_exception.tpl';

            // 获取并清空缓存
            $data = ob_get_clean();
        }

        $response = Response::create($data, $type);

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
            $response->header($exception->getHeaders());
        }

        return $response->code($statusCode ?? 500);
    }

    /**
     * 获取错误编码
     * ErrorException则使用错误级别作为错误编码
     * @access protected
     * @param  Throwable $exception
     * @return integer                错误编码
     */
    protected function getCode(Throwable $exception)
    {
        $code = $exception->getCode();

        if (!$code && $exception instanceof ErrorException) {
            $code = $exception->getSeverity();
        }

        return $code;
    }

    /**
     * 获取错误信息
     * ErrorException则使用错误级别作为错误编码
     * @access protected
     * @param  Throwable $exception
     * @return string                错误信息
     */
    protected function getMessage(Throwable $exception)
    {
        $message = $exception->getMessage();

        if (PHP_SAPI == 'cli') {
            return $message;
        }

        $lang = Container::pull('lang');

        if (strpos($message, ':')) {
            $name    = strstr($message, ':', true);
            $message = $lang->has($name) ? $lang->get($name) . strstr($message, ':') : $message;
        } elseif (strpos($message, ',')) {
            $name    = strstr($message, ',', true);
            $message = $lang->has($name) ? $lang->get($name) . ':' . substr(strstr($message, ','), 1) : $message;
        } elseif ($lang->has($message)) {
            $message = $lang->get($message);
        }

        return $message;
    }

    /**
     * 获取出错文件内容
     * 获取错误的前9行和后9行
     * @access protected
     * @param  Throwable $exception
     * @return array                 错误文件内容
     */
    protected function getSourceCode(Throwable $exception)
    {
        // 读取前9行和后9行
        $line  = $exception->getLine();
        $first = ($line - 9 > 0) ? $line - 9 : 1;

        try {
            $contents = file($exception->getFile()) ?: [];
            $source   = [
                'first'  => $first,
                'source' => array_slice($contents, $first - 1, 19),
            ];
        } catch (Exception $e) {
            $source = [];
        }

        return $source;
    }

    /**
     * 获取异常扩展信息
     * 用于非调试模式html返回类型显示
     * @access protected
     * @param  Throwable $exception
     * @return array                 异常类定义的扩展数据
     */
    protected function getExtendData(Throwable $exception)
    {
        $data = [];

        if ($exception instanceof \think\Exception) {
            $data = $exception->getData();
        }

        return $data;
    }

    /**
     * 获取常量列表
     * @access private
     * @return array 常量列表
     */
    private static function getConst()
    {
        $const = get_defined_constants(true);

        return $const['user'] ?? [];
    }
}
