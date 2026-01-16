<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\ConcurrentIteratorInterface;
use IfCastle\Async\FutureInterface;
use IfCastle\Async\QueueInterface;
use IfCastle\Swoole\Internal\FutureState;
use Swoole\Coroutine\Channel;

/**
 * @implements QueueInterface<mixed>
 */
final class Queue implements QueueInterface
{
    private readonly Channel $channel;

    private bool $closed            = false;

    /**
     * @var array<callable>
     */
    private array $onClose          = [];

    public function __construct(int $size = 0)
    {
        if ($size === 0) {
            $size                   = 1;
        }

        $this->channel              = new Channel($size);
    }


    #[\Override]
    /**
     * @param mixed $value
     */
    public function pushAsync(mixed $value): void
    {
        $this->channel->push($value);
    }

    #[\Override]
    /**
     * @param mixed $value
     * @return FutureInterface<null>
     */
    public function pushWithPromise(mixed $value): FutureInterface
    {
        $this->channel->push($value);
        return new Future(FutureState::completed());
    }

    #[\Override]
    /**
     * @param mixed $value
     */
    public function push(mixed $value): void
    {
        $this->channel->push($value);
    }

    #[\Override]
    /**
     * @return ConcurrentIteratorInterface<mixed>
     */
    public function getIterator(): ConcurrentIteratorInterface
    {
        return new ConcurrentChannelIterator($this->channel);
    }

    #[\Override]
    public function isComplete(): bool
    {
        return $this->closed;
    }

    #[\Override]
    public function isDisposed(): bool
    {
        return $this->closed;
    }

    #[\Override]
    public function complete(): void
    {
        $this->closed               = true;

        $handlers                   = $this->onClose;
        $this->onClose              = [];

        foreach ($handlers as $onClose) {
            $onClose();
        }
    }

    #[\Override]
    public function error(\Throwable $reason): void
    {
        $this->closed               = true;

        $handlers                   = $this->onClose;
        $this->onClose              = [];

        foreach ($handlers as $onClose) {
            $onClose();
        }
    }
}
