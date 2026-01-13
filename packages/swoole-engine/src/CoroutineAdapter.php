<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CoroutineInterface;
use Swoole\Coroutine;

final readonly class CoroutineAdapter implements CoroutineInterface
{
    public function __construct(private int $cid) {}

    #[\Override]
    public function getCoroutineId(): int
    {
        return $this->cid;
    }

    #[\Override]
    public function isRunning(): bool
    {
        return Coroutine::exists($this->cid);
    }

    #[\Override]
    public function isCancelled(): bool
    {
        return false === Coroutine::exists($this->cid);
    }

    #[\Override]
    public function isFinished(): bool
    {
        return false === Coroutine::exists($this->cid);
    }

    #[\Override]
    public function stop(?\Throwable $throwable = null): bool
    {
        return Coroutine::cancel($this->cid);
    }
}
