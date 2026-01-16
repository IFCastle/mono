<?php

declare(strict_types=1);

namespace IfCastle\Services\UserManager;

use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;

final class Bootloader implements BootloaderInterface
{
    #[\Override]
    public function buildBootloader(BootloaderExecutorInterface $bootloaderExecutor): void
    {
        // TODO: Implement UserManager bootloader logic
    }
}
