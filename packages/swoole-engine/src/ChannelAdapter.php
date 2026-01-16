<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\ChannelInterface;
use Swoole\Coroutine\Channel;

final class ChannelAdapter implements ChannelInterface
{
    private bool $closed = false;

    /**
     * @var array<callable>
     */
    private array $onClose = [];

    public function __construct(private readonly Channel $channel) {}

    #[\Override] public function send(mixed $data): void
    {
        $this->channel->push($data);
    }

    #[\Override]
    public function receive(): mixed
    {
        return $this->channel->pop();
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->channel->isEmpty();
    }

    #[\Override]
    public function isFull(): bool
    {
        return $this->channel->isFull();
    }

    #[\Override]
    public function close(): void
    {
        $this->closed               = true;
        $this->channel->close();

        $handlers                   = $this->onClose;
        $this->onClose              = [];

        foreach ($handlers as $onClose) {
            $onClose();
        }
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->closed;
    }

    #[\Override]
    public function onClose(\Closure $onClose): void
    {
        $this->onClose[] = $onClose;
    }
}
