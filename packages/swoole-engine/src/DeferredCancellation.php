<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CancellationInterface;
use IfCastle\Async\DeferredCancellationInterface;

final class DeferredCancellation implements DeferredCancellationInterface
{
    /**
     * @var array<callable>
     */
    private array $callbacks = [];

    private bool $isCancelled = false;

    #[\Override]
    public function getCancellation(): CancellationInterface
    {
        return new CancellationByTrigger(fn(callable $trigger) => $this->callbacks[] = $trigger);
    }

    #[\Override]
    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }

    #[\Override]
    public function cancel(?\Throwable $previous = null): void
    {
        if ($this->isCancelled) {
            return;
        }

        $callbacks                  = $this->callbacks;
        $this->callbacks            = [];

        $this->isCancelled          = true;

        foreach ($callbacks as $callback) {
            $callback($previous);
        }
    }
}
