<?php

declare(strict_types=1);

namespace IfCastle\Amphp;

use Amp\Future;
use IfCastle\Async\CancellationInterface;
use IfCastle\Async\FutureInterface;

/**
 * @template T
 */
final readonly class FutureAdapter implements FutureInterface
{
    /**
     * @param Future<T> $future
     */
    public function __construct(public Future $future) {}


    #[\Override]
    public function isComplete(): bool
    {
        return $this->future->isComplete();
    }

    #[\Override]
    public function ignore(): void
    {
        $this->future->ignore();
    }

    #[\Override]
    public function await(?CancellationInterface $cancellation = null): mixed
    {
        return $this->future->await();
    }

    #[\Override]
    public function map(callable $mapper): FutureInterface
    {
        return new FutureAdapter($this->future->map($mapper));
    }

    #[\Override]
    public function catch(callable $onRejected): static
    {
        $this->future->catch($onRejected)->ignore();
        return $this;
    }

    #[\Override]
    public function finally(callable $onFinally): static
    {
        $this->future->finally($onFinally)->ignore();
        return $this;
    }
}
