<?php

declare(strict_types=1);

namespace IfCastle\Amphp;

use Amp\Sync\Channel;
use IfCastle\Async\ChannelInterface;

/**
 * @template T
 */
final readonly class ChannelAdapter implements ChannelInterface
{
    /**
     * @param Channel<T> $channel
     */
    public function __construct(public Channel $channel) {}

    #[\Override]
    public function send(mixed $data): void
    {
        $this->channel->send($data);
    }

    #[\Override]
    public function receive(): mixed
    {
        return $this->channel->receive();
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return false;
    }

    #[\Override]
    public function isFull(): bool
    {
        return false;
    }

    #[\Override]
    public function close(): void
    {
        if (false === $this->channel->isClosed()) {
            $this->channel->close();
        }
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    #[\Override]
    public function onClose(\Closure $onClose): void
    {
        $this->channel->onClose($onClose);
    }
}
