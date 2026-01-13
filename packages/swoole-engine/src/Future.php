<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CancellationInterface;
use IfCastle\Async\FutureInterface;
use IfCastle\Swoole\Internal\FutureState;
use Swoole\Coroutine\Channel;

final readonly class Future implements FutureInterface
{
    public function __construct(public FutureState $state) {}

    #[\Override]
    public function isComplete(): bool
    {
        return $this->state->isComplete();
    }

    #[\Override]
    public function ignore(): void
    {
        $this->state->ignore();
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function await(?CancellationInterface $cancellation = null): mixed
    {
        $channel                    = new Channel(1);
        $state                      = \WeakReference::create($this->state);
        $handler                    = null;

        $handler                    = static function () use ($channel, &$handler, $state, $cancellation) {
            $channel->push(true);
            $state                  = $state->get();
            $state?->unsubscribe($handler);
            $cancellation?->unsubscribe((string) \spl_object_id($handler));
        };

        $this->state->subscribe($handler);
        $cancellation?->subscribe($handler);

        $channel->pop();

        $cancellation?->throwIfRequested();

        if ($this->state->getThrowable() !== null) {
            throw $this->state->getThrowable();
        }
        return $this->state->getResult();

    }

    #[\Override]
    public function map(callable $mapper): FutureInterface
    {
        $futureState                = new FutureState();
        $state                      = $this->state;

        $state->subscribe(static function () use ($futureState, $state, $mapper) {
            if ($state->getThrowable() !== null) {
                $futureState->complete($state->getThrowable());
            } else {
                $futureState->complete($mapper($state->getResult()));
            }
        });

        return new Future($futureState);
    }

    #[\Override]
    public function catch(callable $onRejected): static
    {
        $state = $this->state;

        $state->subscribe(static function () use ($onRejected, $state) {
            if ($state->getThrowable() !== null) {
                $onRejected($state->getThrowable());
            }
        });

        return $this;
    }

    #[\Override]
    public function finally(callable $onFinally): static
    {
        $this->state->subscribe($onFinally);
        return $this;
    }
}
