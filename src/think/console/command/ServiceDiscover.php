<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\console\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\App;

class ServiceDiscover extends Command
{
    public function configure()
    {
        $this->setName('service:discover')
            ->setDescription('Discover Services for ThinkPHP');
    }

    public function run(Input $input, Output $output): int
    {
        if (is_file($path = App::getRootPath() . 'vendor/composer/installed.json')) {
            $packages = json_decode(@file_get_contents($path), true);

            $services = [];
            foreach ($packages as $package) {
                if (!empty($package['extra']['think']['services'])) {
                    $services += (array) $package['extra']['think']['services'];
                }
            }

            $header = '// This cache file is automatically generated at:' . date('Y-m-d H:i:s') . PHP_EOL . 'declare (strict_types = 1);' . PHP_EOL;

            $content = '<?php ' . PHP_EOL . $header . "return " . var_export($services, true);

            if (!is_dir($runtimePath = App::getRuntimePath())) {
                mkdir($runtimePath, 0755, true);
            }

            file_put_contents($runtimePath . 'services.php', $content);

            $output->writeln('<info>Succeed!</info>');
        }

    }
}
