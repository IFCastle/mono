<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CancellationInterface;
use IfCastle\Async\ConcurrentIteratorInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Traversable;

final class ConcurrentChannelIterator implements ConcurrentIteratorInterface
{
    private mixed $value;

    private int $position = 0;

    public function __construct(private readonly Channel $channel) {}

    #[\Override]
    public function continue(?CancellationInterface $cancellation = null): bool
    {
        $cancellation?->throwIfRequested();

        if ($cancellation === null || $cancellation instanceof TimeoutCancellation) {
            $this->value            = $this->channel->pop($cancellation?->timeout ?? -1);
            $this->position++;
            return $this->value !== null;
        }

        $waitObject                 = new Channel(1);

        $cancellation->subscribe(static fn() => $waitObject->push(false));

        Coroutine::create(function () use ($waitObject) {

            try {
                $this->value            = $this->channel->pop();
                $this->position++;
            } finally {
                $waitObject->push(true);
            }
        });

        $waitObject->pop();

        $cancellation->throwIfRequested();

        return false;
    }

    #[\Override]
    public function getValue(): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function getPosition(): int
    {
        return $this->position;
    }

    #[\Override]
    public function isComplete(): bool
    {
        return $this->channel->isEmpty();
    }

    #[\Override]
    public function dispose(): void
    {
        $this->channel->close();
    }

    #[\Override]
    public function getIterator(): Traversable
    {
        return new \ArrayIterator([]);
    }
}
