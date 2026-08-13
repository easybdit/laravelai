<?php

namespace EasyAI\LaravelAI\Chat\Exceptions;

/**
 * Thrown by ChatGuard when a request is refused by the security suite
 * (rate limit, access restriction, blocklist, word filter, prompt
 * injection, bad captcha, message-too-long, ...). The message is always
 * safe to show directly to the end user — never wraps a raw exception.
 */
class ChatBlockedException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
