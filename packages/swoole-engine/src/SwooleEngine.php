<?php

declare(strict_types=1);

namespace IfCastle\Swoole;

use IfCastle\Application\EngineAbstract;

class SwooleEngine extends EngineAbstract
{
    #[\Override]
    public function start(): void {}

    #[\Override]
    public function getEngineName(): string
    {
        return 'swoole/' . PHP_VERSION;
    }

    #[\Override]
    public function isStateful(): bool
    {
        return true;
    }

    #[\Override]
    public function isAsynchronous(): bool
    {
        return true;
    }

    #[\Override]
    public function supportCoroutines(): bool
    {
        return true;
    }
}
