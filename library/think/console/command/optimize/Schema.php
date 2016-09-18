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
            ->addOption('table', null, Option::VALUE_REQUIRED, 'Build table schema cache .')
            ->setDescription('Build database schema cache.');
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->hasOption('table')) {
            $tables[] = $input->getOption('table');
        } else {
            $tables = Db::getTables();
        }

        if (!is_dir(RUNTIME_PATH . 'schema')) {
            @mkdir(RUNTIME_PATH . 'schema', 0755, true);
        }

        foreach ($tables as $table) {
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = Db::getFields($table);
            $content .= var_export($info, true) . ';';
            file_put_contents(RUNTIME_PATH . 'schema' . DS . $table . EXT, $content);
        }

        $output->writeln('<info>Succeed!</info>');
    }

}
