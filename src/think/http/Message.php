<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\http;

use Psr\Http\Message\MessageInterface;

abstract class Message implements MessageInterface
{
    /**
     * Protocol version
     *
     * @var string
     */
    protected $protocolVersion = '1.1';

    /**
     * body
     *
     * @var StreamInterface
     */
    protected $body;

    /**
     * 当前HEADER参数
     * @var array
     */
    protected $header = [];

    /**
     * 获取字符串形式的 HTTP 协议版本信息。
     *
     * 字符串 **必须** 包含 HTTP 版本数字（如：「1.1」, 「1.0」）。
     *
     * @return string HTTP 协议版本
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * 返回指定 HTTP 版本号的消息实例。
     *
     * 传参的版本号只 **必须** 包含 HTTP 版本数字，如："1.1", "1.0"。
     *
     * 此方法在实现的时候，**必须** 保留原有的不可修改的 HTTP 消息对象，然后返回
     * 一个新的带有传参进去的 HTTP 版本的实例
     *
     * @param string $version HTTP 版本信息
     * @return self
     */
    public function withProtocolVersion($version)
    {
        $clone = clone $this;

        $clone->protocolVersion = $version;

        return $clone;
    }

    /**
     * 获取所有的报头信息
     *
     * 返回的二维数组中，第一维数组的「键」代表单条报头信息的名字，「值」是
     * 以数组形式返回的，见以下实例：
     *
     *     // 把「值」的数据当成字串打印出来
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ': ' . implode(', ', $values);
     *     }
     *
     *     // 迭代的循环二维数组
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * 虽然报头信息是没有大小写之分，但是使用 `getHeaders()` 会返回保留了原本
     * 大小写形式的内容。
     *
     * @return string[][] 返回一个两维数组，第一维数组的「键」 **必须** 为单条报头信息的
     *     名称，对应的是由字串组成的数组，请注意，对应的「值」 **必须** 是数组形式的。
     */
    public function getHeaders(): array
    {
        return $this->header;
    }

    /**
     * 检查是否报头信息中包含有此名称的值，不区分大小写
     *
     * @param string $name 不区分大小写的报头信息名称
     * @return bool 找到返回 true，未找到返回 false
     */
    public function hasHeader($name): bool
    {
        return isset($this->header[strtolower($name)]);
    }

    /**
     * 根据给定的名称，获取一条报头信息，不区分大小写，以数组形式返回
     *
     * 此方法以数组形式返回对应名称的报头信息。
     *
     * 如果没有对应的报头信息，**必须** 返回一个空数组。
     *
     * @param string $name 不区分大小写的报头字段名称。
     * @return string[] 返回报头信息中，对应名称的，由字符串组成的数组值，如果没有对应
     *  的内容，**必须** 返回空数组。
     */
    public function getHeader($name): array
    {
        return $this->header[strtolower($name)] ?? [];
    }

    /**
     * 根据给定的名称，获取一条报头信息，不区分大小写，以逗号分隔的形式返回
     *
     * 此方法返回所有对应的报头信息，并将其使用逗号分隔的方法拼接起来。
     *
     * 注意：不是所有的报头信息都可使用逗号分隔的方法来拼接，对于那些报头信息，请使用
     * `getHeader()` 方法来获取。
     *
     * 如果没有对应的报头信息，此方法 **必须** 返回一个空字符串。
     *
     * @param string $name 不区分大小写的报头字段名称。
     * @return string 返回报头信息中，对应名称的，由逗号分隔组成的字串，如果没有对应
     *  的内容，**必须** 返回空字符串。
     */
    public function getHeaderLine($name): string
    {
        return implode(',', $this->getHeader($name));
    }

    /**
     * 返回替换指定报头信息「键/值」对的消息实例。
     *
     * 虽然报头信息是不区分大小写的，但是此方法必须保留其传参时的大小写状态，并能够在
     * 调用 `getHeaders()` 的时候被取出。
     *
     * 此方法在实现的时候，**必须** 保留原有的不可修改的 HTTP 消息对象，然后返回
     * 一个更新后带有传参进去报头信息的实例
     *
     * @param string $name 不区分大小写的报头字段名称。
     * @param string|string[] $value 报头信息或报头信息数组。
     * @return self
     * @throws \InvalidArgumentException 无效的报头字段或报头信息时抛出
     */
    public function withHeader($name, $value)
    {
        $clone = clone $this;

        if (is_array($value)) {
            $clone->header[strtolower($name)] = $value;
        } else {
            $clone->header[strtolower($name)][] = $value;

        }
        return $clone;
    }

    /**
     * 返回一个报头信息增量的 HTTP 消息实例。
     *
     * 原有的报头信息会被保留，新的值会作为增量加上，如果报头信息不存在的话，字段会被加上。
     *
     * 此方法在实现的时候，**必须** 保留原有的不可修改的 HTTP 消息对象，然后返回
     * 一个新的修改过的 HTTP 消息实例。
     *
     * @param string $name 不区分大小写的报头字段名称。
     * @param string|string[] $value 报头信息或报头信息数组。
     * @return self
     * @throws \InvalidArgumentException 报头字段名称非法时会被抛出。
     * @throws \InvalidArgumentException 报头头信息的值非法的时候会被抛出。
     */
    public function withAddedHeader($name, $value)
    {
        $clone = clone $this;

        $clone->header[strtolower($name)][] = $value;

        return $clone;
    }

    /**
     * 返回被移除掉指定报头信息的 HTTP 消息实例。
     *
     * 报头信息字段在解析的时候，**必须** 保证是不区分大小写的。
     *
     * 此方法在实现的时候，**必须** 保留原有的不可修改的 HTTP 消息对象，然后返回
     * 一个新的修改过的 HTTP 消息实例。
     *
     * @param string $name 不区分大小写的头部字段名称。
     * @return self
     */
    public function withoutHeader($name)
    {
        $clone = clone $this;

        unset($clone->header[strtolower($name)]);

        return $clone;
    }

    /**
     * 获取 HTTP 消息的内容。
     *
     * @return StreamInterface 以数据流的形式返回。
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * 返回指定内容的 HTTP 消息实例。
     *
     * 内容 **必须** 是 `StreamInterface` 接口的实例。
     *
     * 此方法在实现的时候，**必须** 保留原有的不可修改的 HTTP 消息对象，然后返回
     * 一个新的修改过的 HTTP 消息实例。
     *
     * @param StreamInterface $body 数据流形式的内容。
     * @return self
     * @throws \InvalidArgumentException 当消息内容不正确的时候抛出。
     */
    public function withBody(StreamInterface $body)
    {
        $clone       = clone $this;
        $clone->body = $body;

        return $clone;
    }
}
