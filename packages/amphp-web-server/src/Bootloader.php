<?php

declare(strict_types=1);

namespace IfCastle\AmphpWebServer;

use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;
use IfCastle\Application\Console\ConsoleOutput;
use IfCastle\Application\Console\ConsoleOutputInterface;
use IfCastle\Application\EngineInterface;

final class Bootloader implements BootloaderInterface
{
    #[\Override]
    public function buildBootloader(BootloaderExecutorInterface $bootloaderExecutor): void
    {
        $builder                    = $bootloaderExecutor->getBootloaderContext()->getSystemEnvironmentBootBuilder();

        if ($builder->isBound(EngineInterface::class)) {
            return;
        }

        $builder->bindConstructible(EngineInterface::class, WebServerEngine::class, isThrow: false)
                ->bindConstructible(ConsoleOutputInterface::class, ConsoleOutput::class, isThrow: false);
    }
}
