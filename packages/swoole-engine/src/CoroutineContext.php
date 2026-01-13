<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Async\CoroutineContextInterface;
use Swoole\Coroutine;

class CoroutineContext implements CoroutineContextInterface
{
    #[\Override]
    public function isCoroutine(): bool
    {
        return Coroutine::getCid() !== -1;
    }

    #[\Override]
    public function getCoroutineId(): string|int
    {
        return Coroutine::getCid();
    }

    #[\Override]
    public function getCoroutineParentId(): string|int
    {
        return Coroutine::getPcid();
    }

    #[\Override]
    public function has(string $key): bool
    {
        // Get the current coroutine ID
        $cid                        = Coroutine::getCid();

        do {
            /*
             * Get the context object using the current coroutine
             * ID and check if our key exists, looping through the
             * coroutine tree if we are deep inside sub coroutines.
             */
            if (Coroutine::getContext($cid)?->offsetExists($key)) {
                return true;
            }

            // We may be inside a child coroutine, let's check the parent ID for a context
            $cid                    = Coroutine::getPcid($cid);

        } while ($cid !== -1 && $cid !== false);

        return false;
    }

    #[\Override]
    public function get(string $key): mixed
    {
        // Get the current coroutine ID
        $cid                        = Coroutine::getCid();

        do {
            /*
             * Get the context object using the current coroutine
             * ID and check if our key exists, looping through the
             * coroutine tree if we are deep inside sub coroutines.
             */
            if (Coroutine::getContext($cid)?->offsetExists($key)) {
                return Coroutine::getContext($cid)[$key];
            }

            // We may be inside a child coroutine, let's check the parent ID for a context
            $cid                    = Coroutine::getPcid($cid);

        } while ($cid !== -1 && $cid !== false);

        return null;
    }

    #[\Override]
    public function getLocal(string $key): mixed
    {
        $context                    = Coroutine::getContext();

        if ($context === null) {
            return null;
        }

        return $context[$key] ?? null;
    }

    #[\Override]
    public function hasLocal(string $key): bool
    {
        $context                    = Coroutine::getContext();

        if ($context === null) {
            return false;
        }

        return isset($context[$key]);
    }

    #[\Override]
    public function set(string $key, mixed $value): static
    {
        Coroutine::getContext()[$key] = $value;

        return $this;
    }

    #[\Override]
    public function defer(callable $callback): static
    {
        Coroutine::defer($callback);

        return $this;
    }
}
