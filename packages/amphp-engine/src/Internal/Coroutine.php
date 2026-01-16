<?php

declare(strict_types=1);

namespace IfCastle\Amphp\Internal;

use Amp\DeferredFuture;
use Amp\Future;
use IfCastle\Amphp\Internal\Exceptions\CoroutineNotStarted;
use Revolt\EventLoop\Suspension;

final class Coroutine
{
    private int $startAt = 0;

    private bool $isFinished = false;

    private bool $isCancelled = false;

    /**
     * @var Suspension<mixed>|null
     */
    private Suspension|null     $suspension          = null;

    /**
     * @var \WeakReference<Suspension<mixed>>|null
     */
    private \WeakReference|null $schedulerSuspension = null;

    /**
     * @param DeferredFuture<mixed>|null $future
     */
    public function __construct(
        private \Closure|null $closure,
        private readonly int $priority  = 0,
        private readonly int $timeLimit = 0,
        private readonly DeferredFuture|null $future  = null
    ) {}

    public function __destruct()
    {
        if (false === $this->future?->isComplete()) {
            $this->future->error(new CoroutineNotStarted($this));
        }
    }

    public function execute(): void
    {
        if (null === $this->closure) {
            throw new \Error('Coroutine is already executed');
        }

        $closure                    = $this->closure;
        $this->closure              = null;

        $this->startAt              = \time();

        try {
            $closure($this);
            $this->resolve();
        } catch (\Throwable $exception) {
            $this->fail($exception);
        } finally {
            $this->isFinished       = true;
        }
    }

    public function getClosure(): \Closure|null
    {
        return $this->closure;
    }

    private function resolve(): void
    {
        if (false === $this->future?->isComplete()) {
            $this->future->complete();
        }
    }

    private function fail(\Throwable $exception): void
    {
        if (false === $this->future?->isComplete()) {
            $this->future->error($exception);
        }
    }

    public function cancel(): void
    {
        if (false === $this->future?->isComplete()) {
            $this->isCancelled = true;
            $this->future->error(new CoroutineNotStarted($this));
        }
    }

    /**
     * @return Suspension<mixed>|null
     */
    public function getSuspension(): ?Suspension
    {
        return $this->suspension;
    }

    /**
     * @param Suspension<mixed> $suspension
     */
    public function defineSuspension(Suspension $suspension): void
    {
        if ($this->suspension !== null) {
            throw new \Error('Suspension is already defined');
        }

        $this->suspension           = $suspension;
    }

    /**
     * @param Suspension<mixed> $schedulerSuspension
     */
    public function defineSchedulerSuspension(Suspension $schedulerSuspension): void
    {
        if ($this->schedulerSuspension !== null) {
            throw new \Error('Scheduler is already defined');
        }

        $this->schedulerSuspension = \WeakReference::create($schedulerSuspension);
    }

    /**
     * @return Future<mixed>|null
     */
    public function getFuture(): Future|null
    {
        return $this->future?->getFuture();
    }

    public function suspend(): void
    {
        $this->schedulerSuspension?->get()?->resume();
        $this->suspension?->suspend();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getStartAt(): int
    {
        return $this->startAt;
    }

    public function isFinished(): bool
    {
        return $this->isFinished;
    }

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }

    public function getTimeLimit(): int
    {
        return $this->timeLimit;
    }
}
