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
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class Model extends Command
{
    protected function configure()
    {
        $this->setName('optimize:model')
            ->addArgument('app', Argument::OPTIONAL, 'app name .')
            ->setDescription('Build app model ide helper.');
    }

    protected function execute(Input $input, Output $output)
    {
        $app = $input->getArgument('app');

        if (empty($app) && !is_dir($this->app->getBasePath() . 'controller')) {
            $output->writeln('<error>Miss app name!</error>');
            return false;
        }

        $path = $this->app->getBasePath() . ($app ? $app . DIRECTORY_SEPARATOR : '') . 'model';

        $models    = glob($path . DIRECTORY_SEPARATOR . '*.php');
        $namespace = $this->app->getNameSpace() . ($app ? '\\' . $app : '');

        foreach ($models as $model) {
            $this->buildModel($namespace, $model);
        }

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildModel($namespace, $model)
    {
        $class   = $namespace . '\\model\\' . basename($model, '.php');
        $reflect = new ReflectionClass($class);
        $comment = $reflect->getDocComment();

        if (false !== strpos($comment, '@mixin think\Model') && false === strpos($comment, '@property ')) {
            $fieldType = $class::getFieldsType();
            $item[]    = '@mixin think\Model';
            foreach ($fieldType as $field => $type) {
                $type   = $this->parseFieldType($type);
                $item[] = ' * @property ' . $type . ' $' . $field;
            }

            $content = str_replace('@mixin think\Model', implode(PHP_EOL, $item), file_get_contents($model));

            file_put_contents($model, $content);
            $this->output->writeln('<info>class "' . $class . '" complete!</info>');
        }
    }

    /**
     * 获取字段绑定类型
     * @access public
     * @param  string $type 字段类型
     * @return string
     */
    public function parseFieldType(string $type): string
    {
        if (0 === strpos($type, 'set') || 0 === strpos($type, 'enum')) {
            $result = 'string';
        } elseif (preg_match('/(double|float|decimal|real|numeric)/is', $type)) {
            $result = 'float';
        } elseif (preg_match('/(int|serial|bit)/is', $type)) {
            $result = 'int';
        } elseif (preg_match('/bool/is', $type)) {
            $result = 'bool';
        } else {
            $result = 'string';
        }

        return $result;
    }
}
