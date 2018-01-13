<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Slince <taosikai@yeah.net>
// +---------------------------------------

namespace think\console\command;

use think\console\AppAwareCommand;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class RunServer extends AppAwareCommand
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('run')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL,
                'The port to serve the application on', '127.0.0.1')
            ->addOption('port', 'p', Option::VALUE_OPTIONAL,
                'The port to serve the application on', 8000)
            ->setDescription('PHP Built-in Server for ThinkPHP');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Input $input, Output $output)
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $command = sprintf(
            'php -S %s:%d -t %s %s',
            $host,
            $port,
            escapeshellarg($this->getWebroot()),
            escapeshellarg($this->getWebroot().'/index.php')
        );
        $output->writeln(sprintf('built-in server is running in http://%s:%s/', $host, $port));
        $output->writeln(sprintf('You can exit with <info>`CTRL-C`</info>'));
        passthru($command);
    }

    /**
     * 获取网站根目录.
     *
     * @return string
     */
    protected function getWebroot()
    {
        return $this->getApp()->getRootPath().'/public';
    }
}