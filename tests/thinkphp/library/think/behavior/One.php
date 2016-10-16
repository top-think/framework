<?php

namespace tests\thinkphp\library\think\behavior;

class One
{

    public static function run(&$data)
    {
        $data['id'] = 1;
        return true;
    }

    public static function test(&$data)
    {
        $data['name'] = 'test';
        return false;
    }

}
