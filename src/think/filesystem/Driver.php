<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare ( strict_types = 1 );

namespace think\filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use think\File;
use think\file\UploadedFile;
use think\helper\Arr;

/**
 * Class Driver
 * @package think\filesystem
 * @mixin Filesystem
 */
abstract class Driver
{

    /** @var Filesystem */
    protected $filesystem;

    protected $adapter;

    /**
     * The Flysystem PathPrefixer instance.
     *
     * @var PathPrefixer
     */
    protected $prefixer;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(array $config)
    {
        $this->config   = array_merge( $this->config,$config );
        $separator      = $config['directory_separator'] ?? DIRECTORY_SEPARATOR;
        $this->prefixer = new PathPrefixer( $config['root'] ?? '',$separator );

        if (isset( $config['prefix'] )) {
            $this->prefixer = new PathPrefixer( $this->prefixer->prefixPath( $config['prefix'] ),$separator );
        }


        $this->adapter    = $this->createAdapter();
        $this->filesystem = $this->createFilesystem( $this->adapter,$this->config );
    }


    abstract protected function createAdapter();

    /**
     * @param FilesystemAdapter $adapter
     * @param array $config
     * @return Filesystem
     */
    protected function createFilesystem(FilesystemAdapter $adapter,array $config)
    {
        return new Filesystem( $adapter,Arr::only( $config,[
            'directory_visibility',
            'disable_asserts',
            'temporary_url',
            'url',
            'visibility',
        ] ) );
    }


    /**
     * 获取文件完整路径
     * @param string $path
     * @return string
     */
    public function path(string $path): string
    {
        return $this->prefixer->prefixPath( $path );
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists($path)
    {
        return $this->adapter->fileExists( $path ) || $this->adapter->directoryExists( $path );
    }

    /**
     * Determine if a file or directory is missing.
     *
     * @param string $path
     * @return bool
     */
    public function missing($path)
    {
        return !$this->exists( $path );
    }


    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     */
    public function setVisibility($path,$visibility)
    {
        try {
            $this->filesystem->setVisibility( $path,$visibility );
        } catch ( UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }


    /**
     * Delete the file at a given path.
     *
     * @param string|array $paths
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array( $paths ) ? $paths : func_get_args();

        $success = true;

        foreach ( $paths as $path ) {
            try {
                $this->filesystem->delete( $path );
            } catch ( UnableToDeleteFile $e ) {
                throw_if( $this->throwsExceptions(),$e );

                $success = false;
            }
        }

        return $success;
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy($from,$to)
    {
        try {
            $this->filesystem->copy( $from,$to );
        } catch ( UnableToCopyFile $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move($from,$to)
    {
        try {
            $this->filesystem->move( $from,$to );
        } catch ( UnableToMoveFile $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int
     * @throws FilesystemException
     */
    public function size($path)
    {
        return $this->filesystem->fileSize( $path );
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     * @return string|false
     */
    public function mimeType($path)
    {
        try {
            return $this->filesystem->mimeType( $path );
        } catch ( UnableToRetrieveMetadata $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }

        return false;
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     * @return int
     */
    public function lastModified($path): int
    {
        return $this->filesystem->lastModified( $path );
    }


    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        try {
            return $this->filesystem->readStream( $path );
        } catch ( UnableToReadFile $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path,$resource,array $options = [])
    {
        try {
            $this->filesystem->writeStream( $path,$resource,$options );
        } catch ( UnableToWriteFile|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    protected function getLocalUrl($path)
    {
        if (isset( $this->config['url'] )) {
            return $this->concatPathToUrl( $this->config['url'],$path );
        }

        return $path;
    }

    protected function concatPathToUrl($url,$path)
    {
        return rtrim( $url,'/' ).'/'.ltrim( $path,'/' );
    }

    public function url(string $path): string
    {
        $adapter = $this->adapter;

        if (method_exists( $adapter,'getUrl' )) {
            return $adapter->getUrl( $path );
        } elseif (method_exists( $this->filesystem,'getUrl' )) {
            return $this->filesystem->getUrl( $path );
        } elseif ($adapter instanceof LocalFilesystemAdapter) {
            return $this->getLocalUrl( $path );
        } else {
            throw new RuntimeException( 'This driver does not support retrieving URLs.' );
        }
    }

    /**
     * Get the Flysystem adapter.
     *
     * @return \League\Flysystem\FilesystemAdapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * 保存文件
     * @param string $path 路径
     * @param File|string $file 文件
     * @param null|string|\Closure $rule 文件名规则
     * @param array $options 参数
     * @return bool|string
     */
    public function putFile(string $path,$file,$rule = null,array $options = [])
    {
        $file = is_string( $file ) ? new File( $file ) : $file;
        return $this->putFileAs( $path,$file,$file->hashName( $rule ),$options );
    }

    /**
     * 指定文件名保存文件
     * @param string $path 路径
     * @param File $file 文件
     * @param string $name 文件名
     * @param array $options 参数
     * @return bool|string
     */
    public function putFileAs(string $path,File $file,string $name,array $options = [])
    {
        $stream = fopen( $file->getRealPath(),'r' );
        $path   = trim( $path.'/'.$name,'/' );

        $result = $this->put( $path,$stream,$options );

        if (is_resource( $stream )) {
            fclose( $stream );
        }

        return $result ? $path : false;
    }

    public function put($path,$contents,$options = [])
    {
        $options = is_string( $options )
            ? ['visibility' => $options]
            : (array)$options;

        // If the given contents is actually a file or uploaded file instance than we will
        // automatically store the file using a stream. This provides a convenient path
        // for the developer to store streams without managing them manually in code.
        if ($contents instanceof File ||
            $contents instanceof UploadedFile) {
            return $this->putFile( $path,$contents,$options );
        }

        try {
            if ($contents instanceof StreamInterface) {
                $this->writeStream( $path,$contents->detach(),$options );

                return true;
            }

            is_resource( $contents )
                ? $this->writeStream( $path,$contents,$options )
                : $this->write( $path,$contents,$options );
        } catch ( UnableToWriteFile|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function files($directory = null,$recursive = false)
    {
        return $this->filesystem->listContents( $directory ?? '',$recursive )
            ->filter( function (StorageAttributes $attributes) {
                return $attributes->isFile();
            } )
            ->sortByPath()
            ->map( function (StorageAttributes $attributes) {
                return $attributes->path();
            } )
            ->toArray();
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param string|null $directory
     * @return array
     */
    public function allFiles($directory = null)
    {
        return $this->files( $directory,true );
    }

    /**
     * Get all of the directories within a given directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function directories($directory = null,$recursive = false)
    {
        return $this->filesystem->listContents( $directory ?? '',$recursive )
            ->filter( function (StorageAttributes $attributes) {
                return $attributes->isDir();
            } )
            ->map( function (StorageAttributes $attributes) {
                return $attributes->path();
            } )
            ->toArray();
    }

    /**
     * Get all the directories within a given directory (recursive).
     *
     * @param string|null $directory
     * @return array
     */
    public function allDirectories($directory = null)
    {
        return $this->directories( $directory,true );
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory($path)
    {
        try {
            $this->filesystem->createDirectory( $path );
        } catch ( UnableToCreateDirectory|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     * @return bool
     */
    public function deleteDirectory($directory)
    {
        try {
            $this->filesystem->deleteDirectory( $directory );
        } catch ( UnableToDeleteDirectory $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Determine if Flysystem exceptions should be thrown.
     *
     * @return bool
     */
    protected function throwsExceptions(): bool
    {
        return (bool)( $this->config['throw'] ?? false );
    }

    public function __call($method,$parameters)
    {
        return $this->filesystem->$method( ...$parameters );
    }
}
