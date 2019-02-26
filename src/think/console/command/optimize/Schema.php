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

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\App;
use think\facade\Db;

class Schema extends Command
{
    protected function configure()
    {
        $this->setName('optimize:schema')
            ->addArgument('app', Argument::OPTIONAL, 'app name .')
            ->addOption('db', null, Option::VALUE_REQUIRED, 'db name .')
            ->addOption('table', null, Option::VALUE_REQUIRED, 'table name .')
            ->setDescription('Build database schema cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->getArgument('app')) {
            $runtimePath = App::getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . $input->getArgument('app') . DIRECTORY_SEPARATOR;
            $appPath     = App::getBasePath() . $input->getArgument('app') . DIRECTORY_SEPARATOR;
            $namespace   = App::getNamespace() . '\\' . $input->getArgument('app');
        } else {
            $runtimePath = App::getRuntimePath();
            $appPath     = App::getBasePath();
            $namespace   = App::getNamespace();
        }

        $schemaPath = $runtimePath . 'schema' . DIRECTORY_SEPARATOR;
        if (!is_dir($schemaPath)) {
            mkdir($schemaPath, 0755, true);
        }

        if ($input->hasOption('table')) {
            $table = $input->getOption('table');
            if (false === strpos($table, '.')) {
                $dbName = Db::getConfig('database');
            }

            $tables[] = $table;
        } elseif ($input->hasOption('db')) {
            $dbName = $input->getOption('db');
            $tables = Db::getConnection()->getTables($dbName);
        } else {

            $path = $appPath . 'model';
            $list = is_dir($path) ? scandir($path) : [];

            foreach ($list as $file) {
                if (0 === strpos($file, '.')) {
                    continue;
                }
                $class = '\\' . $namespace . '\\model\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->buildModelSchema($schemaPath, $class);
            }

            $output->writeln('<info>Succeed!</info>');
            return;
        }

        $db = isset($dbName) ? $dbName . '.' : '';
        $this->buildDataBaseSchema($schemaPath, $tables, $db);

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildModelSchema(string $path, string $class): void
    {
        $reflect = new \ReflectionClass($class);
        if (!$reflect->isAbstract() && $reflect->isSubclassOf('\think\Model')) {
            $table   = $class::getTable();
            $dbName  = $class::getConfig('database');
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = $class::getConnection()->getFields($table);
            $content .= var_export($info, true) . ';';

            file_put_contents($path . $dbName . '.' . $table . '.php', $content);
        }
    }

    protected function buildDataBaseSchema(string $path, array $tables, string $db): void
    {
        if ('' == $db) {
            $dbName = Db::getConfig('database') . '.';
        } else {
            $dbName = $db;
        }

        foreach ($tables as $table) {
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = Db::getConnection()->getFields($db . $table);
            $content .= var_export($info, true) . ';';
            file_put_contents($path . $dbName . $table . '.php', $content);
        }
    }
}
