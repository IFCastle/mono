<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CancellationInterface;
use IfCastle\Swoole\Exceptions\CancelledException;
use Swoole\Coroutine;

abstract class CancellationAbstract implements CancellationInterface
{
    /**
     * @var array<callable>
     */
    protected array $callbacks      = [];

    protected bool $isRequested     = false;

    public function __construct(
        protected string $message = 'The operation was cancelled'
    ) {
        $self                       = \WeakReference::create($this);

        Coroutine::create(static function () use ($self) {

            $self                   = $self->get();

            if ($self === null) {
                return;
            }

            try {
                $self->await();
            } finally {
                $self->isRequested  = true;
                $callbacks          = $self->callbacks;
                $self->callbacks    = [];

                foreach ($callbacks as $callback) {
                    Coroutine::create($callback);
                }
            }
        });
    }

    abstract protected function await(): void;

    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        $callbackId                 = (string) \spl_object_id($callback);
        $this->callbacks[$callbackId] = $callback;

        return $callbackId;
    }

    #[\Override]
    public function unsubscribe(string $id): void
    {
        if (\array_key_exists($id, $this->callbacks)) {
            unset($this->callbacks[$id]);
        }
    }

    #[\Override]
    public function isRequested(): bool
    {
        return $this->isRequested;
    }

    /**
     * @throws CancelledException
     */
    #[\Override]
    public function throwIfRequested(): void
    {
        if ($this->isRequested) {
            throw new CancelledException($this->message);
        }
    }
}
