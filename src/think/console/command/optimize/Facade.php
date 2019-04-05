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
namespace think\console\command\optimize;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class Facade extends Command
{
    protected function configure()
    {
        $this->setName('optimize:facade')
            ->addArgument('app', Argument::OPTIONAL, 'Build app facade .')
            ->setDescription('Build facade ide helper.');
    }

    protected function execute(Input $input, Output $output)
    {
        $app = $input->getArgument('app');

        if (empty($app) && !is_dir($this->app->getBasePath() . 'controller')) {
            $output->writeln('<error>Miss app name!</error>');
            return false;
        }

        $path = $this->app->getBasePath() . ($app ? $app . DIRECTORY_SEPARATOR : '') . 'facade';

        $facades   = glob($path . DIRECTORY_SEPARATOR . '*.php');
        $namespace = 'app' . ($app ? '\\' . $app : '');

        foreach ($facades as $facade) {
            $this->buildFacade($namespace, $facade);
        }

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildFacade(string $namespace, string $facade): void
    {
        $class   = $namespace . '\\facade\\' . basename($facade, '.php');
        $reflect = new ReflectionClass($class);
        $comment = $reflect->getDocComment();

        if (false !== strpos($comment, '@mixin ') && false === strpos($comment, '@method ')) {

            $facadeClass = trim(substr(explode(PHP_EOL, strstr($comment, '@mixin'), 2)[0], 6));

            $reflect = new ReflectionClass($facadeClass);
            $methods = $reflect->getMethods(ReflectionMethod::IS_PUBLIC);
            $item[]  = '@mixin ' . $facadeClass;
            foreach ($methods as $method) {
                $parse = $this->parseMethod($method);
                if ($parse) {
                    $item[] = $parse;
                }
            }

            $content = str_replace('@mixin ' . $facadeClass, implode(PHP_EOL, $item), file_get_contents($facade));

            file_put_contents($facade, $content);
            $this->output->writeln('<info>class "' . $class . '" complete!</info>');
        }
    }

    protected function parseMethod(ReflectionMethod $method): string
    {
        $methodName = $method->getName();

        if (0 === strpos($methodName, '__') || 0 === strpos($methodName, 'offset')) {
            return '';
        }

        $methodComment = $method->getDocComment();

        $comments   = explode(PHP_EOL, $methodComment);
        $describe   = $methodComment ? trim($comments[1], ' *') : '';
        $returnType = $method->getReturnType() ?: 'mixed';

        if (strpos($returnType, '\\')) {
            $returnType = '\\' . $returnType;
        }

        $params = $method->getParameters();
        $item   = [];

        foreach ($params as $param) {
            $item[] = $this->parseParam($param);
        }

        return ' * @method ' . $returnType . ' ' . $methodName . '(' . ($item ? implode(', ', $item) : '') . ') static ' . $describe;
    }

    protected function parseParam(ReflectionParameter $param): string
    {
        $name = '$' . $param->getName();
        $type = $param->getType() ?: 'mixed';

        if (strpos($type, '\\')) {
            $type = '\\' . $type;
        }

        if ($param->isPassedByReference()) {
            $name = '&' . $name;
        }

        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            $name .= ' = ' . var_export($default, true);
        }

        return $type . ' ' . $name;
    }
}
