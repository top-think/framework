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

use think\App;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;

class Schema extends Command
{
    /** @var  Output */
    protected $output;

    protected function configure()
    {
        $this->setName('optimize:schema')
            ->addOption('db', null, Option::VALUE_REQUIRED, 'db name .')
            ->addOption('table', null, Option::VALUE_REQUIRED, 'table name .')
            ->addOption('module', null, Option::VALUE_REQUIRED, 'module name .')
            ->setDescription('Build database schema cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        if (!is_dir(RUNTIME_PATH . 'schema')) {
            @mkdir(RUNTIME_PATH . 'schema', 0755, true);
        }
        if ($input->hasOption('module')) {
            $module = $input->getOption('module');
            // 读取模型
            $list = scandir(APP_PATH . $module . DS . 'model');
            $app  = App::$namespace;
            foreach ($list as $file) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                $class = '\\' . $app . '\\' . $module . '\\model\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->buildModelSchema($class);
            }
            $output->writeln('<info>Succeed!</info>');
            return;
        } else if ($input->hasOption('table')) {
            $table = $input->getOption('table');
            if (!strpos($table, '.')) {
                $dbName = Db::getConfig('database');
            }
            $tables[] = $table;
        } elseif ($input->hasOption('db')) {
            $dbName = $input->getOption('db');
            $tables = Db::getTables($dbName);
        } elseif (!\think\Config::get('app_multi_module')) {
            $app  = App::$namespace;
            $list = scandir(APP_PATH . 'model');
            foreach ($list as $file) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                $class = '\\' . $app . '\\model\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->buildModelSchema($class);
            }
            $output->writeln('<info>Succeed!</info>');
            return;
        } else {
            $tables = Db::getTables();
        }

        $db = isset($dbName) ? $dbName . '.' : '';
        $this->buildDataBaseSchema($tables, $db);

        $output->writeln('<info>Succeed!</info>');
    }

    protected function buildModelSchema($class)
    {
        $reflect = new \ReflectionClass($class);
        if (!$reflect->isAbstract() && $reflect->isSubclassOf('\think\Model')) {
            $table   = $class::getTable();
            $dbName  = $class::getConfig('database');
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = $class::getConnection()->getFields($table);
            $content .= var_export($info, true) . ';';
            file_put_contents(RUNTIME_PATH . 'schema' . DS . $dbName . '.' . $table . EXT, $content);
        }
    }

    protected function buildDataBaseSchema($tables, $db)
    {
        if ('' == $db) {
            $dbName = Db::getConfig('database') . '.';
        } else {
            $dbName = $db;
        }
        foreach ($tables as $table) {
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = Db::getFields($db . $table);
            $content .= var_export($info, true) . ';';
            file_put_contents(RUNTIME_PATH . 'schema' . DS . $dbName . $table . EXT, $content);
        }
    }
}
