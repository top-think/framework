<?php

namespace think\filesystem;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory as MemoryStore;
use League\Flysystem\Filesystem;
use think\App;
use think\File;

/**
 * Class Driver
 * @package think\filesystem
 * @mixin Filesystem
 */
abstract class Driver
{

    /** @var App */
    protected $app;

    /** @var Filesystem */
    protected $filesystem;

    protected $config = [];

    public function __construct(App $app, $config)
    {
        $this->app    = $app;
        $this->config = array_merge($this->config, $config);

        $adapter          = $this->createAdapter();
        $this->filesystem = $this->createFilesystem($adapter);
    }

    protected function createCacheStore($config)
    {
        if ($config === true) {
            return new MemoryStore;
        }

        return new CacheStore(
            $this->app->cache->store($config['store']),
            $config['prefix'] ?? 'flysystem',
            $config['expire'] ?? null
        );
    }

    abstract protected function createAdapter(): AdapterInterface;

    protected function createFilesystem(AdapterInterface $adapter)
    {
        if (!empty($this->config['cache'])) {
            $adapter = new CachedAdapter($adapter, $this->createCacheStore($this->config['cache']));
        }

        $config = array_intersect_key($this->config, array_flip(['visibility', 'disable_asserts', 'url']));

        return new Filesystem($adapter, count($config) > 0 ? $config : null);
    }

    /**
     * 保存文件
     * @param string               $path
     * @param File                 $file
     * @param null|string|\Closure $rule
     * @param array                $options
     * @return bool|string
     */
    public function putFile($path, $file, $rule = null, $options = [])
    {
        return $this->putFileAs($path, $file, $file->hashName($rule), $options);
    }

    /**
     * 指定文件名保存文件
     * @param string $path
     * @param File   $file
     * @param string $name
     * @param array  $options
     * @return bool|string
     */
    public function putFileAs($path, $file, $name, $options = [])
    {
        $stream = fopen($file->getRealPath(), 'r');

        $result = $this->putStream(
            $path = trim($path . '/' . $name, '/'), $stream, $options
        );

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $result ? $path : false;
    }

    public function __call($method, $parameters)
    {
        return $this->filesystem->$method(...$parameters);
    }
}
