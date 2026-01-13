<?php

declare(strict_types=1);

namespace IfCastle\Swoole\Internal;

use IfCastle\Swoole\Future;
use IfCastle\Swoole\Internal\Exceptions\UnhandledFutureError;
use Swoole\Coroutine;

/**
 * @internal
 *
 * @template T
 */
final class FutureState
{
    public static function completed(): self
    {
        $state                      = new self();
        $state->complete            = true;
        return $state;
    }

    private bool $complete = false;

    private bool $handled = false;

    /**
     * @var array<string, \Closure(self): void>
     */
    private array $callbacks = [];

    /**
     * @var T|null
     */
    private mixed $result = null;

    private ?\Throwable $throwable = null;

    private ?string $origin = null;

    public function __destruct()
    {
        if ($this->throwable instanceof \Throwable && !$this->handled) {
            throw new UnhandledFutureError($this->throwable, $this->origin);
        }
    }

    /**
     * Completes the operation with a result value.
     *
     * @param T $result Result of the operation.
     */
    public function complete(mixed $result): void
    {
        if ($this->complete) {
            return;
        }

        if ($result instanceof Future) {
            throw new \Error('Cannot complete with an instance of ' . Future::class);
        }

        $this->result               = $result;
        $this->invokeCallbacks();
    }

    /**
     * Marks the operation as failed.
     *
     * @param \Throwable $throwable Throwable to indicate the error.
     */
    public function error(\Throwable $throwable): void
    {
        if ($this->complete) {
            return;
        }

        $this->throwable            = $throwable;
        $this->invokeCallbacks();
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }

    /**
     * @return bool True if the operation has completed.
     */
    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * Suppress the exception thrown to the loop error handler if and operation error is not handled by a callback.
     */
    public function ignore(): void
    {
        $this->handled              = true;
    }

    public function subscribe(\Closure $callback): void
    {
        $this->callbacks[(string) \spl_object_id($callback)] = $callback;
    }

    public function unsubscribe(\Closure|string $callback): void
    {
        $id = \is_string($callback) ? $callback : (string) \spl_object_id($callback);

        if (\array_key_exists($id, $this->callbacks)) {
            unset($this->callbacks[$id]);
        }
    }

    private function invokeCallbacks(): void
    {
        $this->complete             = true;
        $callbacks                  = $this->callbacks;
        $this->callbacks            = [];

        foreach ($callbacks as $callback) {
            Coroutine::create($callback, $this);
        }
    }
}
