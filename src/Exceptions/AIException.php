<?php

namespace EasyAI\LaravelAI\Exceptions;

use RuntimeException;

class AIException extends RuntimeException
{
    protected string $provider;
    protected array $context;

    public function __construct(string $message, string $provider = '', array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->provider = $provider;
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
