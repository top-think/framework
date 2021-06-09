<?php


namespace think\config\driver;


class Yaml
{
    protected $config;

    public function __construct($config)
    {
        if (!function_exists('yaml_parse_file')) {
            throw new \Exception('请先开启yaml扩展');
        }
        $this->config = is_file($config) ? yaml_parse_file($config) : $config;
    }

    public function parse()
    {
        return $this->config;
    }
}