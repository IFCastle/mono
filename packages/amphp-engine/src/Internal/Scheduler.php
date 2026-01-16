<?php

declare(strict_types=1);

namespace IfCastle\Amphp\Internal;

use Amp\Cancellation;
use Amp\DeferredFuture;
use IfCastle\Amphp\Internal\Exceptions\CoroutineTerminationException;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

final class Scheduler
{
    public static function default(): self
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * @var Coroutine[]
     */
    private array $coroutines       = [];

    /**
     * @var array<int, array<Coroutine>>
     */
    private array $coroutinesQueue  = [];

    private int         $highestPriority   = 0;

    /**
     * @var Suspension<mixed, mixed, mixed>|null
     */
    private ?Suspension $suspension = null;

    private string      $callbackId = '';

    private bool        $isRunning  = true;

    /**
     * @var DeferredFuture<mixed>|null
     */
    private ?DeferredFuture $future = null;

    private \Throwable|null $stopException = null;

    private bool $managerResumed    = false;

    private function init(): void
    {
        if ($this->callbackId !== '') {
            return;
        }

        $this->future               = new DeferredFuture();
        $this->stopException        = null;
        $this->isRunning            = true;
        $this->callbackId           = EventLoop::defer($this->scheduleCoroutines(...));
    }

    private function scheduleCoroutines(): void
    {
        $this->suspension           = EventLoop::getSuspension();

        while ($this->coroutines !== [] && $this->isRunning) {

            $this->managerResumed   = false;

            if ($this->coroutinesQueue === []) {

                $this->highestPriority = 0;

                foreach ($this->coroutines as $coroutine) {
                    if ($coroutine->getPriority() > $this->highestPriority) {
                        $this->highestPriority = $coroutine->getPriority();
                    }
                }

                foreach ($this->coroutines as $coroutine) {
                    if ($coroutine->getPriority() === $this->highestPriority) {
                        $this->coroutinesQueue[] = $coroutine;
                    }
                }
            }

            $coroutine              = \array_shift($this->coroutinesQueue);
            $coroutine->getSuspension()?->resume();
            $this->suspension->suspend();
        }

        try {

            if ($this->stopException !== null) {
                foreach ($this->coroutines as $callbackId => $coroutine) {

                    if ($coroutine->getSuspension() === null) {
                        EventLoop::cancel($callbackId);
                    } else {
                        $coroutine->getSuspension()->throw($this->stopException);
                    }
                }
            }

        } finally {

            $future                     = $this->future;
            $stopException              = $this->stopException;

            $this->future               = null;
            $this->coroutinesQueue      = [];
            $this->coroutines           = [];
            $this->stopException        = null;
            $this->suspension           = null;

            try {
                $future->complete($stopException);
            } finally {
                $this->callbackId       = '';
                $this->isRunning        = false;
            }
        }
    }

    public function run(Coroutine $coroutine): string
    {
        $this->init();

        $selfRef                    = \WeakReference::create($this);

        $callbackId                 = EventLoop::defer(static function (string $callbackId) use ($coroutine, $selfRef): void {

            $self                   = $selfRef->get();

            if ($self === null) {
                return;
            }

            if (false === \array_key_exists($callbackId, $self->coroutines)) {
                $coroutine->cancel();
                return;
            }

            $suspension             = EventLoop::getSuspension();

            $coroutine->defineSuspension($suspension);
            $coroutine->defineSchedulerSuspension($self->suspension);
            unset($self);

            try {
                $coroutine->execute();
            } catch (\Throwable $exception) {
                if ($exception !== $this->stopException) {
                    throw $exception;
                }
            } finally {

                $self               = $selfRef->get();

                if ($self !== null) {
                    unset($self->coroutines[$callbackId]);
                    $coroutine->cancel();
                }

                $self?->resume();
            }
        });

        $this->coroutines[$callbackId] = $coroutine;

        if ($coroutine->getPriority() >= $this->highestPriority) {
            $this->coroutinesQueue  = [];
        }

        return $callbackId;
    }

    public function awaitAll(?Cancellation $cancellation = null): void
    {
        if ($this->coroutines === [] || $this->future === null) {
            return;
        }

        $this->future->getFuture()->await($cancellation);
    }

    public function stopAll(?\Throwable $exception = null): void
    {
        $exception                  ??= new CoroutineTerminationException('Coroutine has been terminated');
        $this->isRunning            = false;
        $this->stopException        = $exception;
        $this->resume();
    }

    public function isCoroutineExists(string $callbackId): bool
    {
        return \array_key_exists($callbackId, $this->coroutines);
    }

    public function findCoroutine(string $callbackId): Coroutine|null
    {
        return $this->coroutines[$callbackId] ?? null;
    }

    public function stop(string $callbackId): bool
    {
        if (false === \array_key_exists($callbackId, $this->coroutines)) {
            return false;
        }

        $coroutine                  = $this->coroutines[$callbackId];
        unset($this->coroutines[$callbackId]);
        $coroutine->cancel();
        EventLoop::cancel($callbackId);

        return true;
    }

    public function getCoroutinesCount(): int
    {
        return \count($this->coroutines);
    }

    protected function resume(): void
    {
        if ($this->managerResumed) {
            return;
        }

        $this->managerResumed       = true;
        $this->suspension?->resume();
    }
}
