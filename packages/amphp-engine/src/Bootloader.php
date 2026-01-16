<?php

declare(strict_types=1);

namespace IfCastle\Amphp;

use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;
use IfCastle\Application\EngineInterface;
use IfCastle\Async\CoroutineContextInterface;
use IfCastle\Async\CoroutineSchedulerInterface;

final class Bootloader implements BootloaderInterface
{
    #[\Override]
    public function buildBootloader(BootloaderExecutorInterface $bootloaderExecutor): void
    {
        $builder                    = $bootloaderExecutor->getBootloaderContext()->getSystemEnvironmentBootBuilder();

        if ($builder->isBound(EngineInterface::class)) {
            return;
        }

        $builder->bindConstructible(EngineInterface::class, AmphpEngine::class, isThrow: false)
                ->bindConstructible(CoroutineContextInterface::class, CoroutineContext::class)
                ->bindConstructible(CoroutineSchedulerInterface::class, CoroutineScheduler::class);
    }
}
