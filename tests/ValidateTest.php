<?php

namespace think\tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Lang;
use think\Validate;

class ValidateTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    protected function setUp(): void
    {
        $this->app  = m::mock(App::class)->makePartial();
        $this->lang = new Lang($this->app);
    }

    public function testDeclined()
    {
        $validate = new Validate();

        $this->assertTrue($validate->isDeclined('no'));
        $this->assertTrue($validate->isDeclined('off'));
        $this->assertTrue($validate->isDeclined('false'));
        $this->assertTrue($validate->isDeclined(false));
        $this->assertTrue($validate->isDeclined('0'));
        $this->assertTrue($validate->isDeclined(0));

        $this->assertFalse($validate->isDeclined('00'));
        $this->assertFalse($validate->isDeclined('yes'));
        $this->assertFalse($validate->isDeclined('on'));
        $this->assertFalse($validate->isDeclined('true'));
        $this->assertFalse($validate->isDeclined(true));
        $this->assertFalse($validate->isDeclined('1'));
        $this->assertFalse($validate->isDeclined(1));
        $this->assertFalse($validate->isDeclined('\u0030'));
    }

    public function testAccepted()
    {
        $validate = new Validate();

        $this->assertTrue($validate->isAccepted('yes'));
        $this->assertTrue($validate->isAccepted('on'));
        $this->assertTrue($validate->isAccepted('true'));
        $this->assertTrue($validate->isAccepted(true));
        $this->assertTrue($validate->isAccepted('1'));
        $this->assertTrue($validate->isAccepted(1));

        $this->assertFalse($validate->isAccepted('no'));
        $this->assertFalse($validate->isAccepted('off'));
        $this->assertFalse($validate->isAccepted('false'));
        $this->assertFalse($validate->isAccepted(false));
        $this->assertFalse($validate->isAccepted('0'));
        $this->assertFalse($validate->isAccepted(0));
    }

    public function testAcceptedIf()
    {
        $validate = new Validate();
        $validate->setLang($this->lang);

        $rule = [
            'tag'      => 'require',
            'password' => 'require|acceptedIf:tag,1',
        ];

        $data = [
            'tag' => '1',
        ];

        $result = $validate->rule($rule)->check($data);

        $this->assertFalse($result);
        $this->assertEquals('password must be yes,on,true or 1', $validate->getError());
    }

    public function testDeclinedIf()
    {
        $validate = new Validate();
        $validate->setLang($this->lang);

        $rule = [
            'tag'      => 'require',
            'password' => 'require|declinedIf:tag,1',
        ];

        $data = [
            'tag' => '1',
        ];

        $result = $validate->rule($rule)->check($data);

        $this->assertFalse($result);
        $this->assertEquals('password must be no,off,false or 0', $validate->getError());
    }

    public function testMultipleOf()
    {
        $validate = new Validate();

        $this->assertTrue($validate->multipleOf('6', '3'));
        $this->assertTrue($validate->multipleOf('3', '3'));
        $this->assertTrue($validate->multipleOf(3, '3'));
        $this->assertTrue($validate->multipleOf('3', 3));

        $this->assertFalse($validate->multipleOf('0', '1'));
        $this->assertFalse($validate->multipleOf(0, '1'));
        $this->assertFalse($validate->multipleOf('1', '0'));
        $this->assertFalse($validate->multipleOf('3', '4'));
        $this->assertFalse($validate->multipleOf('4', '3'));
        $this->assertFalse($validate->multipleOf(4, '3'));
        $this->assertFalse($validate->multipleOf('4', 3));

    }
}
