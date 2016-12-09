<?php

namespace tests\thinkphp\library\think\behavior;

class StaticDemo
{

    public static function run(&$data)
    {
        $data['function'] = 'run';
        return true;
    }

    public static function my_pos3(&$data)
    {
        $data['function'] = 'my_pos3';
        return true;
    }

}
