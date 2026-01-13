<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use Swoole\Coroutine;

final class TimeoutCancellation extends CancellationAbstract
{
    public function __construct(public readonly float $timeout, string|null $message = null)
    {
        parent::__construct($message ?? 'The operation timed out ' . $timeout);
    }

    #[\Override]
    protected function await(): void
    {
        Coroutine::sleep($this->timeout);
    }
}
