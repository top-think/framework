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
            ->setDescription('Build database schema cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->hasOption('table')) {
            $tables[] = $input->getOption('table');
        } else {
            if ($input->hasOption('db')) {
                $dbName = $input->getOption('db');
                $tables = Db::getTables($dbName);
            } else {
                $tables = Db::getTables();
            }
        }

        if (!is_dir(RUNTIME_PATH . 'schema')) {
            @mkdir(RUNTIME_PATH . 'schema', 0755, true);
        }

        $db = isset($dbName) ? $dbName . '.' : '';
        foreach ($tables as $table) {
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = Db::getFields($db . $table);
            $content .= var_export($info, true) . ';';
            file_put_contents(RUNTIME_PATH . 'schema' . DS . $db . $table . EXT, $content);
        }

        $output->writeln('<info>Succeed!</info>');
    }

}
