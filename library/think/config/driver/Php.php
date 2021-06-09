<?php


namespace think\config\driver;


class Php
{
    protected $config;

    public function __construct($config)
    {
        $this->config = is_file($config) ? include $config : $config;
    }

    public function parse()
    {
        return $this->config;
    }
}