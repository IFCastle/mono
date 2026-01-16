<?php

declare(strict_types=1);

namespace IfCastle\Amphp;

use IfCastle\DI\DisposableInterface;

final class InternalContext extends \ArrayObject implements DisposableInterface
{
    /**
     * @var array<callable>
     */
    private array $callbacks        = [];

    public function defer(callable $callback): void
    {
        $this->callbacks[]          = $callback;
    }

    #[\Override]
    public function dispose(): void
    {
        $callbacks                  = $this->callbacks;
        $this->callbacks            = [];

        $errors                     = [];

        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $throwable) {
                $errors[]           = $throwable;
            }
        }

        $this->exchangeArray([]);

        if ($errors === []) {
            return;
        }

        if (\count($errors) === 1) {
            throw $errors[0];
        }

        if (\count($errors) > 1) {
            throw new \RuntimeException('Multiple errors occurred during coroutine disposal', 0, $errors[0]);
        }
    }

    public function __destruct()
    {
        $this->dispose();
    }
}
