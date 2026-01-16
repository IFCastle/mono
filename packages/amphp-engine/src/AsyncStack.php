<?php

declare(strict_types=1);

namespace IfCastle\Amphp;

use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;
use IfCastle\DesignPatterns\Pool\StackInterface;

final class AsyncStack implements StackInterface
{
    /**
     * @var Queue<object>|null
     */
    private Queue|null $queue;

    /**
     * @var ConcurrentIterator<object>|null
     */
    private ConcurrentIterator|null $iterator;

    private int $size = 0;

    public function __construct(private readonly int $waitTimeout = 1, int $bufferSize = 1)
    {
        $this->queue                = new Queue($bufferSize);
        $this->iterator             = $this->queue->iterate();
    }

    #[\Override]
    public function pop(): object|null
    {
        try {

            if ($this->size < 1) {
                return null;
            }

            $this->iterator->continue(new TimeoutCancellation($this->waitTimeout));
            --$this->size;

            if ($this->size < 0) {
                $this->size         = 0;
            }

            return $this->iterator->getValue();
        } catch (TimeoutException) {
            return null;
        }
    }

    #[\Override]
    public function push(object $object): void
    {
        if ($this->queue === null) {
            return;
        }

        $this->queue->pushAsync($object)->ignore();
        ++$this->size;
    }

    #[\Override]
    public function getSize(): int
    {
        return $this->size;
    }

    #[\Override]
    public function clear(): void
    {
        $this->size                = 0;
        $this->iterator->dispose();

        if (false === $this->queue->isComplete()) {
            $this->queue->complete();
        }

        $this->queue                = null;
        $this->iterator             = null;
    }
}
