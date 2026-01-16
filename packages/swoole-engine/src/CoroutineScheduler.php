<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CancellationInterface;
use IfCastle\Async\CoroutineInterface;
use IfCastle\Async\CoroutineSchedulerInterface;
use IfCastle\Async\DeferredCancellationInterface;
use IfCastle\Async\QueueInterface;
use IfCastle\DI\DisposableInterface;
use IfCastle\Exceptions\LogicalException;
use IfCastle\Exceptions\UnexpectedValue;
use IfCastle\Swoole\Internal\Awaiter;
use Swoole\Coroutine;
use Swoole\Timer;

class CoroutineScheduler implements CoroutineSchedulerInterface, DisposableInterface
{
    /**
     * @var array<string, callable>
     */
    protected array $callbacks  = [];

    #[\Override]
    public function run(\Closure $function): CoroutineInterface
    {
        return new CoroutineAdapter(Coroutine::create($function));
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function await(iterable $futures, ?CancellationInterface $cancellation = null): array
    {
        return Awaiter::await(\iterator_count($futures), $futures, $cancellation)[1];
    }

    /**
     * @param iterable<FutureInterface> $futures
     * @throws LogicalException
     * @throws UnexpectedValue
     * @throws \Throwable
     */
    #[\Override]
    public function awaitFirst(iterable $futures, ?CancellationInterface $cancellation = null): mixed
    {
        $results                    = Awaiter::await(1, $futures, $cancellation)[1];

        return $results[\array_key_first($results) ?? null] ?? null;
    }

    #[\Override]
    public function awaitFirstSuccessful(iterable $futures, ?CancellationInterface $cancellation = null): mixed
    {
        $results                    = Awaiter::await(1, $futures, $cancellation, true)[1];

        return $results[\array_key_first($results) ?? null] ?? null;
    }

    /**
     * @param iterable<FutureInterface> $futures
     * @return array<mixed>
     * @throws UnexpectedValue
     * @throws LogicalException
     * @throws \Throwable
     */
    #[\Override]
    public function awaitAll(iterable $futures, ?CancellationInterface $cancellation = null): array
    {
        return Awaiter::await(\iterator_count($futures), $futures, $cancellation, true);
    }

    /**
     * @throws LogicalException
     * @throws UnexpectedValue
     * @throws \Throwable
     */
    #[\Override]
    public function awaitAnyN(int $count, iterable $futures, ?CancellationInterface $cancellation = null): array
    {
        return Awaiter::await($count, $futures, $cancellation, true);
    }

    #[\Override]
    public function createChannelPair(int $size = 0): array
    {
        $channel                    = new ChannelAdapter(new Coroutine\Channel($size));
        return [$channel, $channel];
    }

    #[\Override]
    public function createQueue(int $size = 0): QueueInterface
    {
        return new Queue($size);
    }

    #[\Override]
    public function createTimeoutCancellation(float $timeout, string $message = 'Operation timed out'): CancellationInterface
    {
        return new TimeoutCancellation($timeout, $message);
    }

    #[\Override]
    public function compositeCancellation(CancellationInterface ...$cancellations): CancellationInterface
    {
        return new CompositeCancellation(...$cancellations);
    }

    #[\Override]
    public function createDeferredCancellation(): DeferredCancellationInterface
    {
        return new DeferredCancellation();
    }

    #[\Override]
    public function defer(callable $callback): void
    {
        Timer::after(0, $callback);
    }

    #[\Override]
    public function delay(float|int $delay, callable $callback): int|string
    {
        return Timer::after((int) ($delay * 1000), $callback);
    }

    /**
     * @throws UnexpectedValue
     */
    #[\Override]
    public function interval(float|int $interval, callable $callback): int|string
    {
        // Check if an interval is 10 ms or less.
        if ($interval <= 0.1) {
            throw new UnexpectedValue('$interval', $interval, 'Interval must be greater than 10 ms.');
        }

        $timerId                    = Timer::tick((int) ($interval * 1000), fn() => $callback());
        $this->callbacks[$timerId]  = $callback;

        return $timerId;
    }

    #[\Override]
    public function cancelInterval(int|string $timerId): void
    {
        if (Timer::exists($timerId)) {
            Timer::clear($timerId);
        }

        if (\array_key_exists($timerId, $this->callbacks) === false) {
            return;
        }

        //
        // We do this because PHP not free memory correctly for Array structure.
        // So we build a new array from old.
        //
        $callbacks                  = [];

        foreach ($this->callbacks as $key => $callback) {
            if ($key !== $timerId) {
                $callbacks[$key]    = $callback;
            }
        }

        $this->callbacks            = $callbacks;
    }

    #[\Override]
    public function stopAllCoroutines(?\Throwable $exception = null): bool
    {
        // TODO: Implement stopAllCoroutines() method.
    }

    #[\Override]
    public function dispose(): void
    {
        $callbacks                  = $this->callbacks;
        $this->callbacks            = [];

        foreach ($callbacks as $timerId => $callback) {
            try {

                Timer::clear($timerId);

                if ($callback instanceof DisposableInterface) {
                    $callback->dispose();
                }
            } catch (\Throwable) {
                // Ignore
            }
        }
    }
}
