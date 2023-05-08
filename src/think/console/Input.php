<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\console;

use think\console\input\Definition;

class Input
{

    /**
     * @var Definition
     */
    protected $definition;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $arguments = [];

    protected $interactive = true;

    private $tokens;
    private $parsed;

    public function __construct($argv = null)
    {
        if (null === $argv) {
            $argv = $_SERVER['argv'];
            // 去除命令名
            array_shift($argv);
        }

        $this->tokens = $argv;

        $this->definition = new Definition();
    }

    protected function setTokens(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * 绑定实例
     * @param Definition $definition A InputDefinition instance
     */
    public function bind(Definition $definition): void
    {
        $this->arguments  = [];
        $this->options    = [];
        $this->definition = $definition;

        $this->parse();
    }

    /**
     * 解析参数
     */
    protected function parse(): void
    {
        $parseOptions = true;
        $this->parsed = $this->tokens;
        while (null !== $token = array_shift($this->parsed)) {
            if ($parseOptions && '' == $token) {
                $this->parseArgument($token);
            } elseif ($parseOptions && '--' == $token) {
                $parseOptions = false;
            } elseif ($parseOptions && 0 === strpos($token, '--')) {
                $this->parseLongOption($token);
            } elseif ($parseOptions && '-' === $token[0] && '-' !== $token) {
                $this->parseShortOption($token);
            } else {
                $this->parseArgument($token);
            }
        }
    }

    /**
     * 解析短选项
     * @param string $token 当前的指令.
     */
    private function parseShortOption(string $token): void
    {
        $name = substr($token, 1);

        if (strlen($name) > 1) {
            if ($this->definition->hasShortcut($name[0])
                && $this->definition->getOptionForShortcut($name[0])->acceptValue()
            ) {
                $this->addShortOption($name[0], substr($name, 1));
            } else {
                $this->parseShortOptionSet($name);
            }
        } else {
            $this->addShortOption($name, null);
        }
    }

    /**
     * 解析短选项
     * @param string $name 当前指令
     * @throws \RuntimeException
     */
    private function parseShortOptionSet(string $name): void
    {
        $len = strlen($name);
        for ($i = 0; $i < $len; ++$i) {
            if (!$this->definition->hasShortcut($name[$i])) {
                throw new \RuntimeException(sprintf('The "-%s" option does not exist.', $name[$i]));
            }

            $option = $this->definition->getOptionForShortcut($name[$i]);
            if ($option->acceptValue()) {
                $this->addLongOption($option->getName(), $i === $len - 1 ? null : substr($name, $i + 1));

                break;
            } else {
                $this->addLongOption($option->getName(), null);
            }
        }
    }

    /**
     * 解析完整选项
     * @param string $token 当前指令
     */
    private function parseLongOption(string $token): void
    {
        $name = substr($token, 2);

        if (false !== $pos = strpos($name, '=')) {
            $this->addLongOption(substr($name, 0, $pos), substr($name, $pos + 1));
        } else {
            $this->addLongOption($name, null);
        }
    }

    /**
     * 解析参数
     * @param string $token 当前指令
     * @throws \RuntimeException
     */
    private function parseArgument(string $token): void
    {
        $c = count($this->arguments);

        if ($this->definition->hasArgument($c)) {
            $arg = $this->definition->getArgument($c);

            $this->arguments[$arg->getName()] = $arg->isArray() ? [$token] : $token;

        } elseif ($this->definition->hasArgument($c - 1) && $this->definition->getArgument($c - 1)->isArray()) {
            $arg = $this->definition->getArgument($c - 1);

            $this->arguments[$arg->getName()][] = $token;
        } else {
            throw new \RuntimeException('Too many arguments.');
        }
    }

    /**
     * 添加一个短选项的值
     * @param string $shortcut 短名称
     * @param mixed  $value    值
     * @throws \RuntimeException
     */
    private function addShortOption(string $shortcut, $value): void
    {
        if (!$this->definition->hasShortcut($shortcut)) {
            throw new \RuntimeException(sprintf('The "-%s" option does not exist.', $shortcut));
        }

        $this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
    }

    /**
     * 添加一个完整选项的值
     * @param string $name  选项名
     * @param mixed  $value 值
     * @throws \RuntimeException
     */
    private function addLongOption(string $name, $value): void
    {
        if (!$this->definition->hasOption($name)) {
            throw new \RuntimeException(sprintf('The "--%s" option does not exist.', $name));
        }

        $option = $this->definition->getOption($name);

        if (false === $value) {
            $value = null;
        }

        if (null !== $value && !$option->acceptValue()) {
            throw new \RuntimeException(sprintf('The "--%s" option does not accept a value.', $name));
        }

        if (null === $value && $option->acceptValue() && count($this->parsed)) {
            $next = array_shift($this->parsed);
            if (isset($next[0]) && '-' !== $next[0]) {
                $value = $next;
            } elseif (empty($next)) {
                $value = '';
            } else {
                array_unshift($this->parsed, $next);
            }
        }

        if (null === $value) {
            if ($option->isValueRequired()) {
                throw new \RuntimeException(sprintf('The "--%s" option requires a value.', $name));
            }

            if (!$option->isArray()) {
                $value = $option->isValueOptional() ? $option->getDefault() : true;
            }
        }

        if ($option->isArray()) {
            $this->options[$name][] = $value;
        } else {
            $this->options[$name] = $value;
        }
    }

    /**
     * 获取第一个参数
     * @return string|null
     */
    public function getFirstArgument(): ?string
    {
        foreach ($this->tokens as $token) {
            if ($token && '-' === $token[0]) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * 检查原始参数是否包含某个值
     * @param string|array $values 需要检查的值
     * @return bool
     */
    public function hasParameterOption($values): bool
    {
        $values = (array) $values;

        foreach ($this->tokens as $token) {
            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value . '=')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 获取原始选项的值
     * @param string|array $values  需要检查的值
     * @param mixed        $default 默认值
     * @return mixed The option value
     */
    public function getParameterOption($values, $default = false)
    {
        $values = (array) $values;
        $tokens = $this->tokens;

        while (0 < count($tokens)) {
            $token = array_shift($tokens);

            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value . '=')) {
                    if (false !== $pos = strpos($token, '=')) {
                        return substr($token, $pos + 1);
                    }

                    return array_shift($tokens);
                }
            }
        }

        return $default;
    }

    /**
     * 验证输入
     * @throws \RuntimeException
     */
    public function validate()
    {
        if (count($this->arguments) < $this->definition->getArgumentRequiredCount()) {
            throw new \RuntimeException('Not enough arguments.');
        }
    }

    /**
     * 检查输入是否是交互的
     * @return bool
     */
    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    /**
     * 设置输入的交互
     * @param bool
     */
    public function setInteractive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }

    /**
     * 获取所有的参数
     * @return array
     */
    public function getArguments(): array
    {
        return array_merge($this->definition->getArgumentDefaults(), $this->arguments);
    }

    /**
     * 根据名称获取参数
     * @param string $name 参数名
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getArgument(string $name)
    {
        if (!$this->definition->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        return $this->arguments[$name] ?? $this->definition->getArgument($name)
            ->getDefault();
    }

    /**
     * 设置参数的值
     * @param string $name  参数名
     * @param string $value 值
     * @throws \InvalidArgumentException
     */
    public function setArgument(string $name, $value)
    {
        if (!$this->definition->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        $this->arguments[$name] = $value;
    }

    /**
     * 检查是否存在某个参数
     * @param string|int $name 参数名或位置
     * @return bool
     */
    public function hasArgument($name): bool
    {
        return $this->definition->hasArgument($name);
    }

    /**
     * 获取所有的选项
     * @return array
     */
    public function getOptions(): array
    {
        return array_merge($this->definition->getOptionDefaults(), $this->options);
    }

    /**
     * 获取选项值
     * @param string $name 选项名称
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getOption(string $name)
    {
        if (!$this->definition->hasOption($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" option does not exist.', $name));
        }

        return $this->options[$name] ?? $this->definition->getOption($name)->getDefault();
    }

    /**
     * 设置选项值
     * @param string      $name  选项名
     * @param string|bool $value 值
     * @throws \InvalidArgumentException
     */
    public function setOption(string $name, $value): void
    {
        if (!$this->definition->hasOption($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" option does not exist.', $name));
        }

        $this->options[$name] = $value;
    }

    /**
     * 是否有某个选项
     * @param string $name 选项名
     * @return bool
     */
    public function hasOption(string $name): bool
    {
        return $this->definition->hasOption($name) && isset($this->options[$name]);
    }

    /**
     * 转义指令
     * @param string $token
     * @return string
     */
    public function escapeToken(string $token): string
    {
        return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
    }

    /**
     * 返回传递给命令的参数的字符串
     * @return string
     */
    public function __toString()
    {
        $tokens = array_map(function ($token) {
            if (preg_match('{^(-[^=]+=)(.+)}', $token, $match)) {
                return $match[1] . $this->escapeToken($match[2]);
            }

            if ($token && '-' !== $token[0]) {
                return $this->escapeToken($token);
            }

            return $token;
        }, $this->tokens);

        return implode(' ', $tokens);
    }
}
