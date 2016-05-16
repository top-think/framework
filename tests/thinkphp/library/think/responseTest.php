<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/**
 * Response测试
 * @author    大漠 <zhylninc@gmail.com>
 */

namespace tests\thinkphp\library\think;

use think\Config;
use think\Response;

class responseTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     * @var \think\Response
     */
    protected $object;

    protected $default_return_type;

    protected $default_ajax_return;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        // 1.
        // restore_error_handler();
        // Warning: Cannot modify header information - headers already sent by (output started at PHPUnit\Util\Printer.php:173)
        // more see in https://www.analysisandsolutions.com/blog/html/writing-phpunit-tests-for-wordpress-plugins-wp-redirect-and-continuing-after-php-errors.htm

        // 2.
        // the Symfony used the HeaderMock.php

        // 3.
        // not run the eclipse will held, and travis-ci.org Searching for coverage reports
        // **> Python coverage not found
        // **> No coverage report found.
        // add the
        // /**
        // * @runInSeparateProcess
        // */
        if (!$this->default_return_type) {
            $this->default_return_type = Config::get('default_return_type');
        }
        if (!$this->default_ajax_return) {
            $this->default_ajax_return = Config::get('default_ajax_return');
        }
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        Config::set('default_ajax_return', $this->default_ajax_return);
        Config::set('default_return_type', $this->default_return_type);
        Response::create(Config::get('default_return_type')); // 会影响其他测试
    }

    /**
     * @covers think\Response::send
     * @todo Implement testSend().
     */
    public function testSend()
    {
        $dataArr        = array();
        $dataArr["key"] = "value";
        //$dataArr->key   = "val";

        $response = Response::create();
        $result   = $response->type('json')->send($dataArr);
        $this->assertEquals('{"key":"value"}', $result);
        $_GET['callback'] = 'callback';
        $result           = $response->type('jsonp', ['var_jsonp_handler' => 'callback'])->send($dataArr);
        $this->assertEquals('callback({"key":"value"});', $result);

        $response->transform(function () {

            return "callbackreturndata";
        });

        $result = $response->send($dataArr);
        $this->assertEquals("callbackreturndata", $result);
        $_GET[Config::get('var_jsonp_handler')] = "";
    }

    /**
     * @covers think\Response::transform
     * @todo Implement testtransform().
     */
    public function testtransform()
    {
        $response = Response::create();
        $response->transform(function () {

            return "callbackreturndata";
        });
        $dataArr = [];
        $result  = $response->send($dataArr);
        $this->assertEquals("callbackreturndata", $result);

        $response->transform(null);
    }

    /**
     * @covers think\Response::type
     * @todo Implement testType().
     */
    public function testType()
    {
        $type = "json";
        Response::create($type);
    }

    /**
     * @covers think\Response::data
     * @todo Implement testData().
     */
    public function testData()
    {
        $data     = "data";
        $response = Response::create();
        $response->data($data);
        $response->data(null);
    }

    /**
     * @#runInSeparateProcess
     * @covers think\Response::redirect
     * @todo Implement testRedirect().
     */
    public function testRedirect()
    {
        // $url = "http://www.testredirect.com";
        // $params = array();
        // $params[] = 301;

        // // FIXME 静态方法mock Url::build
        // // echo "\r\n" . json_encode(xdebug_get_headers()) . "\r\n";
        // Response::redirect($url, $params);

        // $this->assertContains('Location: ' . $url, xdebug_get_headers());
    }

    /**
     * @#runInSeparateProcess
     * @covers think\Response::header
     * @todo Implement testHeader().
     */
    public function testHeader()
    {
        // $name = "Location";
        // $url = "http://www.testheader.com/";
        // Response::header($name, $url);
        // $this->assertContains($name . ': ' . $url, xdebug_get_headers());
    }

}
