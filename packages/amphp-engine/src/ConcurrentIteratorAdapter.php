<?php

declare(strict_types=1);

namespace IfCastle\Amphp;

use Amp\Pipeline\ConcurrentIterator;
use IfCastle\Async\CancellationInterface;
use IfCastle\Async\ConcurrentIteratorInterface;
use Traversable;

/**
 * @template T
 */
final readonly class ConcurrentIteratorAdapter implements ConcurrentIteratorInterface
{
    /**
     * @param ConcurrentIterator<T> $concurrentIterator
     */
    public function __construct(public ConcurrentIterator $concurrentIterator) {}

    #[\Override]
    public function continue(?CancellationInterface $cancellation = null): bool
    {
        return $this->concurrentIterator->continue(CoroutineScheduler::resolveCancellation($cancellation));
    }

    #[\Override]
    public function getValue(): mixed
    {
        return $this->concurrentIterator->getValue();
    }

    #[\Override]
    public function getPosition(): int
    {
        return $this->concurrentIterator->getPosition();
    }

    #[\Override]
    public function isComplete(): bool
    {
        return $this->concurrentIterator->isComplete();
    }

    #[\Override]
    public function dispose(): void
    {
        $this->concurrentIterator->dispose();
    }

    #[\Override]
    public function getIterator(): Traversable
    {
        return $this->concurrentIterator->getIterator();
    }
}
