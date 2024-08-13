<?php

namespace think\tests;

use PHPUnit\Framework\TestCase;
use think\Validate;

class ValidateTest extends TestCase
{
    public function testIn()
    {
        $validate = new Validate();

        $this->assertTrue($validate->in("0", ["0"]));
        $this->assertFalse($validate->in('0', ["000"]));
        $this->assertFalse($validate->in('0', ["0xoO0"]));
    }
}