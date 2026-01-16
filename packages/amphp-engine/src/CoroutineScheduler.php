<?php

declare(strict_types=1);

namespace IfCastle\Amphp;

use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\TimeoutCancellation;
use IfCastle\Amphp\Internal\Coroutine;
use IfCastle\Amphp\Internal\Scheduler;
use IfCastle\Async\CancellationInterface;
use IfCastle\Async\CoroutineInterface;
use IfCastle\Async\CoroutineSchedulerInterface;
use IfCastle\Async\DeferredCancellationInterface;
use IfCastle\Async\QueueInterface;
use IfCastle\Exceptions\UnexpectedValueType;
use Revolt\EventLoop;

use function Amp\Future\await;
use function Amp\Future\awaitAll;
use function Amp\Future\awaitAny;
use function Amp\Future\awaitAnyN;
use function Amp\Future\awaitFirst;
use function Amp\Sync\createChannelPair;

final class CoroutineScheduler implements CoroutineSchedulerInterface
{
    public static function resolveCancellation(CancellationInterface|null $cancellation): ?\Amp\Cancellation
    {
        if ($cancellation instanceof CancellationExternalAdapter) {
            return $cancellation->cancellation;
        }

        if ($cancellation !== null) {
            throw new UnexpectedValueType('$cancellation', $cancellation, CancellationExternalAdapter::class);
        }

        return null;
    }

    #[\Override]
    public function run(\Closure $function): CoroutineInterface
    {
        return new CoroutineAdapter(Scheduler::default()->run(new Coroutine($function)));
    }

    #[\Override]
    public function await(iterable $futures, ?CancellationInterface $cancellation = null): array
    {
        return await($futures, self::resolveCancellation($cancellation));
    }

    #[\Override]
    /**
     * @param iterable<int|string, FutureInterface<mixed>> $futures
     */
    public function awaitFirst(iterable $futures, ?CancellationInterface $cancellation = null): mixed
    {
        return awaitFirst($futures, self::resolveCancellation($cancellation));
    }

    #[\Override]
    /**
     * @param iterable<int|string, FutureInterface<mixed>> $futures
     */
    public function awaitFirstSuccessful(iterable $futures, ?CancellationInterface $cancellation = null
    ): mixed {
        return awaitAny($futures, self::resolveCancellation($cancellation));
    }

    #[\Override]
    /**
     * @param iterable<FutureInterface<mixed>> $futures
     * @return array<mixed>
     */
    public function awaitAll(iterable $futures, ?CancellationInterface $cancellation = null): array
    {
        return awaitAll($futures, self::resolveCancellation($cancellation));
    }

    #[\Override]
    public function awaitAnyN(int $count, iterable $futures, ?CancellationInterface $cancellation = null): array
    {
        return awaitAnyN($count, $futures, self::resolveCancellation($cancellation));
    }

    #[\Override]
    public function createChannelPair(int $size = 0): array
    {
        [$left, $right] = createChannelPair($size);
        return [new ChannelAdapter($left), new ChannelAdapter($right)];
    }

    #[\Override]
    /**
     * @return QueueInterface<mixed>
     */
    public function createQueue(int $size = 0): QueueInterface
    {
        return new QueueAdapter(new \Amp\Pipeline\Queue($size));
    }

    #[\Override]
    public function createTimeoutCancellation(float $timeout, string $message = 'Operation timed out'): CancellationInterface
    {
        return new CancellationExternalAdapter(new TimeoutCancellation($timeout, $message));
    }

    #[\Override]
    public function compositeCancellation(CancellationInterface... $cancellations): CancellationInterface
    {
        return new CancellationExternalAdapter(new CompositeCancellation(...\array_map(self::resolveCancellation(...), $cancellations)));
    }

    #[\Override]
    public function createDeferredCancellation(): DeferredCancellationInterface
    {
        return new DeferredCancellationAdapter(new DeferredCancellation());
    }

    #[\Override]
    public function stopAllCoroutines(?\Throwable $exception = null): bool
    {
        Scheduler::default()->stopAll($exception);
        return true;
    }

    #[\Override]
    public function defer(callable $callback): void
    {
        EventLoop::defer($callback);
    }

    #[\Override]
    public function delay(float|int $delay, callable $callback): string
    {
        return EventLoop::delay($delay, $callback);
    }

    #[\Override]
    public function interval(float|int $interval, callable $callback): string
    {
        return EventLoop::repeat($interval, $callback);
    }

    #[\Override]
    public function cancelInterval(int|string $timerId): void
    {
        EventLoop::cancel($timerId);
    }
}
