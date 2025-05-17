<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\event;

/**
 * LogRecord事件类
 *
 * @template-implements \ArrayAccess<'type'|'message'|'datetime'|'context', int|string|\DateTimeImmutable|array>
 */
class LogRecord implements \ArrayAccess
{
    public function __construct(public string $type, public string $message, public \DateTimeImmutable $datetime, public array $context) {}

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->{$offset});
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->{$offset};
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Unsupported operation: setting ' . $offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Unsupported operation');
    }
}
