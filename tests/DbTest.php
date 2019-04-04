<?php

namespace think\tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testConnect()
    {

    }

    public function connectProvider()
    {

    }

}
